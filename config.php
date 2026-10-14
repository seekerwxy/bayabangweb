<?php

// InfinityFree 跑的是 PHP 8.x：mysqli 自 PHP 8.1 起默认把错误当异常抛出，
// 会让各接口里 `if ($conn->connect_error)` / `if (!$stmt)` 这类判断失效（表现为空白 500）。
// 这里显式关掉异常模式，恢复各接口里已有的 JSON 错误处理。
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
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
    'memories_db' => [
        'host' => getenv('MEMORIES_DB_HOST') ?: '',
        'username' => getenv('MEMORIES_DB_USER') ?: '',
        'password' => getenv('MEMORIES_DB_PASSWORD') ?: '',
        'database' => getenv('MEMORIES_DB_NAME') ?: ''
    ],
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
