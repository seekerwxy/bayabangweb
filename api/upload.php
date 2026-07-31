<?php
// api/upload.php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

function upload_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_response(405, ['message' => '仅支持POST']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

if (empty($_SESSION['classmate_id'])) {
    upload_response(401, ['message' => '请先登录后再上传']);
}

$csrfToken = $input['csrf_token'] ?? '';
if (!is_string($csrfToken)
    || empty($_SESSION['classmate_csrf_token'])
    || !hash_equals($_SESSION['classmate_csrf_token'], $csrfToken)) {
    upload_response(403, ['message' => 'CSRF验证失败']);
}

$imageData = $input['image'] ?? '';
if (!is_string($imageData) || $imageData === '') {
    upload_response(400, ['message' => '缺少图片数据']);
}

$allowedMimeMap = [
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $imageData, $matches)) {
    upload_response(400, ['message' => '无效的图片数据格式']);
}

$declaredMime = strtolower($matches[1]);
if ($declaredMime === 'image/jpg') {
    $declaredMime = 'image/jpeg';
}
if (!isset($allowedMimeMap[$declaredMime])) {
    upload_response(400, ['message' => '仅支持 JPEG、PNG、WebP 图片']);
}

$imageData = substr($imageData, strpos($imageData, ',') + 1);
$imageData = base64_decode($imageData, true);
if ($imageData === false || $imageData === '') {
    upload_response(400, ['message' => 'base64解码失败']);
}

if (strlen($imageData) > 500 * 1024) {
    upload_response(400, ['message' => '图片太大，最大500KB']);
}

$imageInfo = @getimagesizefromstring($imageData);
$allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if ($imageInfo === false || !in_array($imageInfo[2] ?? 0, $allowedImageTypes, true)) {
    upload_response(400, ['message' => '文件不是有效图片']);
}

if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->buffer($imageData);
    if (!isset($allowedMimeMap[$detectedMime]) || $detectedMime !== $declaredMime) {
        upload_response(400, ['message' => '图片内容与声明格式不一致']);
    }
}

$relativeDir = 'photos/classmates/';
$absoluteDir = __DIR__ . '/' . $relativeDir;

if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true)) {
    error_log('upload directory create failed: ' . $absoluteDir);
    upload_response(500, ['message' => '无法创建上传目录']);
}

$extension = $allowedMimeMap[$declaredMime];
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$absolutePath = $absoluteDir . $filename;
$webPath = '/api/' . $relativeDir . $filename;

if (file_put_contents($absolutePath, $imageData)) {
    upload_response(200, ['success' => true, 'path' => $webPath]);
}

error_log('upload file save failed: ' . $absolutePath);
upload_response(500, ['message' => '文件保存失败']);
