<?php
// 头像上传接口：前端把裁剪后的图片转成 dataURL 提交，这里落盘成一个随机文件名，
// 返回「站点根绝对路径」，由前端再带进 update 接口写库。
//
// 返回字段保持 success / path 不变（admin-files/edit-profile.php 直接读这两个键）。

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/member.php';    // 会话 / CSRF
require_once __DIR__ . '/../inc/avatars.php';   // 头像目录、文件名、配额
require_once __DIR__ . '/../inc/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

boya_require_method('POST');

$input = boya_json_input();

if (member_id() <= 0) {
    boya_json_error('请先登录后再上传', 401);
}

if (!member_verify_csrf($input['csrf_token'] ?? '')) {
    boya_json_error('CSRF验证失败', 403);
}

$imageData = $input['image'] ?? '';
if (!is_string($imageData) || $imageData === '') {
    boya_json_error('缺少图片数据', 400);
}

$allowedMimeMap = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $imageData, $matches)) {
    boya_json_error('无效的图片数据格式', 400);
}

$declaredMime = strtolower($matches[1]);
if ($declaredMime === 'image/jpg') {
    $declaredMime = 'image/jpeg';
}
if (!isset($allowedMimeMap[$declaredMime])) {
    boya_json_error('仅支持 JPEG、PNG、WebP 图片', 400);
}

$imageData = substr($imageData, strpos($imageData, ',') + 1);
$imageData = base64_decode($imageData, true);
if ($imageData === false || $imageData === '') {
    boya_json_error('base64解码失败', 400);
}

if (strlen($imageData) > 500 * 1024) {
    boya_json_error('图片太大，最大500KB', 400);
}

$imageInfo = @getimagesizefromstring($imageData);
$allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if ($imageInfo === false || !in_array($imageInfo[2] ?? 0, $allowedImageTypes, true)) {
    boya_json_error('文件不是有效图片', 400);
}

if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->buffer($imageData);
    if (!isset($allowedMimeMap[$detectedMime]) || $detectedMime !== $declaredMime) {
        boya_json_error('图片内容与声明格式不一致', 400);
    }
}

// 用户上传的图片放在站点根的 uploads/classmates/ 下，api/ 只留接口脚本
$absoluteDir = boya_avatar_abs_dir();
if (!is_dir($absoluteDir)) {
    error_log('upload directory create failed: ' . $absoluteDir);
    boya_json_error('无法创建上传目录', 500);
}

// 空间保护：目录文件数上限。免费主机只有 30000 inode / 5GB，
// 没有上限的话任何一个有账号的人都能把整站配额塞满。
$existing = boya_avatar_count($absoluteDir);
if ($existing >= boya_avatar_max_files()) {
    error_log('upload rejected: directory full (' . $existing . ' files)');
    boya_json_error('头像空间已满，请联系管理员清理', 507);
}

$filename = boya_avatar_new_name($allowedMimeMap[$declaredMime]);
$absolutePath = $absoluteDir . $filename;
$webPath = boya_avatar_dir() . $filename;

if (file_put_contents($absolutePath, $imageData)) {
    boya_audit('avatar-upload', 'path=' . $webPath . ' bytes=' . strlen($imageData));
    boya_json(['success' => true, 'path' => $webPath]);
}

error_log('upload file save failed: ' . $absolutePath);
boya_json_error('文件保存失败', 500);
