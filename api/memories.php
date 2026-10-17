<?php
// 时光轴（班级回忆事件）读取接口：前台 memories.php 用，游客可读。
//
// 以前这里自己 new mysqli、自己设 header、还漏了 Cache-Control（Cloudflare 可能把
// JSON 缓存住，后台改完前台不更新）。现在统一走 inc/ 的引导与响应助手。

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/photo.php';

boya_require_method('GET');

$conn = boya_db('messages');
if (!$conn) {
    error_log('memories DB connect failed');
    boya_json_error('数据库连接失败', 500);
}

$result = $conn->query('SELECT * FROM timeline_events ORDER BY display_order ASC, id ASC');
if (!$result) {
    error_log('timeline query failed: ' . $conn->error);
    boya_json_error('查询失败', 500);
}

$events = [];
while ($row = $result->fetch_assoc()) {
    // 统一成站点根绝对路径，后台填成 htdocs/… 、../photos/… 时前台也不会 404
    $row['photo'] = boya_normalize_photo($row['photo'] ?? '');
    $events[] = $row;
}

// 注意：这里返回的是「裸数组」（前端 memories.php 直接当数组用），
// boya_json() 只对关联数组补 ok 字段，所以形状与原来一致。
boya_json($events);
