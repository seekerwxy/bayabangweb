<?php
// 临时诊断文件：只输出服务器告诉 PHP 的请求信息，排查 data-user-link-base 变成 "//" 的原因。
// 排查完立刻删除。
header('Content-Type: application/json; charset=utf-8');
$out = [
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
    'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'PHP_VERSION' => PHP_VERSION,
    'dirname_SCRIPT_NAME' => dirname($_SERVER['SCRIPT_NAME'] ?? '/'),
    'dirname_php_self' => dirname($_SERVER['PHP_SELF'] ?? '/'),
    'disable_functions' => ini_get('disable_functions'),
    'dirname_exists' => function_exists('dirname'),
];
$out['expr_inline'] = htmlspecialchars(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), ENT_QUOTES, 'UTF-8') . '/';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$out['expr_variable'] = ($base === '' ? '' : $base) . '/';
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
