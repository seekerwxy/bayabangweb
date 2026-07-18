<?php
// api/upload.php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Shanghai');

// 调试时可开启错误显示，正式运行建议注释掉
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "仅支持POST"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imageData = $input['image'] ?? '';

if (empty($imageData)) {
    http_response_code(400);
    echo json_encode(["message" => "缺少图片数据"]);
    exit;
}

if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
    $extension = $matches[1];
    if ($extension === 'jpeg' || $extension === 'jpg') {
        $extension = 'jpeg';
    }
    $imageData = substr($imageData, strpos($imageData, ',') + 1);
    $imageData = base64_decode($imageData);
    if ($imageData === false) {
        http_response_code(400);
        echo json_encode(["message" => "base64解码失败"]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "无效的图片数据格式"]);
    exit;
}

if (strlen($imageData) > 500 * 1024) {
    http_response_code(400);
    echo json_encode(["message" => "图片太大，最大500KB"]);
    exit;
}

// ----- 保存到 api/photos/classmates/ 目录 -----
$relativeDir = 'photos/classmates/';
$absoluteDir = __DIR__ . '/' . $relativeDir;   // __DIR__ 是 api 目录

if (!file_exists($absoluteDir)) {
    if (!mkdir($absoluteDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(["message" => "无法创建上传目录"]);
        exit;
    }
}

$filename = uniqid('avatar_') . '.' . $extension;
$absolutePath = $absoluteDir . $filename;
// 返回从网站根开始的路径，例如 /api/photos/classmates/avatar_xxx.jpeg
$webPath = '/api/' . $relativeDir . $filename;

if (file_put_contents($absolutePath, $imageData)) {
    echo json_encode(["success" => true, "path" => $webPath]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "文件保存失败"]);
}