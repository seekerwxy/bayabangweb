<?php

// InfinityFree 跑的是 PHP 8.x：mysqli 自 PHP 8.1 起默认把错误当异常抛出，
// 会让各接口里 `if ($conn->connect_error)` / `if (!$stmt)` 这类判断失效（表现为空白 500）。
// 这里显式关掉异常模式，恢复各接口里已有的 JSON 错误处理。
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// 站点部署位置：本站固定部署在域名根目录（InfinityFree 的站点根就是 htdocs/）。
// 站内链接与静态资源地址都以它为前缀，是「URL 前缀」的单一事实来源。
// 万一哪天要挪到子目录（例如 /boya/），只改这一行即可。
if (!defined('BOYA_BASE_PATH')) {
    define('BOYA_BASE_PATH', '/');
}

$config = [];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    require $localConfigFile;
}

$defaults = [
    'messages_db' => [
        'host' => getenv('MESSAGES_DB_HOST') ?: '',
        'username' => getenv('MESSAGES_DB_USER') ?: '',
        'password' => getenv('MESSAGES_DB_PASSWORD') ?: '',
        'database' => getenv('MESSAGES_DB_NAME') ?: ''
    ],
    'classmates_db' => [
        'host' => getenv('CLASSMATES_DB_HOST') ?: '',
        'username' => getenv('CLASSMATES_DB_USER') ?: '',
        'password' => getenv('CLASSMATES_DB_PASSWORD') ?: '',
        'database' => getenv('CLASSMATES_DB_NAME') ?: ''
    ],
    // 说明：原来的 memories_db 一段从未被任何代码使用（时光轴和留言都在 messages_db 里），
    // 已经删掉以减少需要维护的口令数量。config.local.php 里若还留着这段，删不删都不影响运行。
    'admin_db' => [
        'username' => getenv('ADMIN_USER') ?: '',
        'password' => getenv('ADMIN_PASSWORD') ?: ''
    ]
];

foreach ($defaults as $section => $values) {
    $config[$section] = array_merge($values, $config[$section] ?? []);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
