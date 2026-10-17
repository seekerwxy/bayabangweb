<?php
// 站点根前缀工具。这是「零依赖」的公共片段：任何页面（包括后台子目录的页面）
// 都可以在任何时候 require 它，不需要先加载 config.php。
//
// 前缀是拼站内链接用的，保证得到的是「以单个 / 开头、以单个 / 结尾」的站点根绝对路径。
//
// 现在的实现：以 config.php 里的 BOYA_BASE_PATH 为准（单一事实来源，默认 '/'）。
// 它有值就直接用，这样同一个函数在根目录页面和 admin-files/ 子目录页面里
// 得到的结果是一致的（都是站点根），不会再出现「后台页面拼出 /admin-files/xxx」的偏差。
//
// 为什么不再用 `htmlspecialchars(dirname($_SERVER['SCRIPT_NAME']))` 反推：
// 线上（InfinityFree，PHP 8.4）实测这个写法返回空串，前缀因此变成 "//"；
// 而 '//' + 'post.php?id=1' 是协议相对地址，浏览器会把 post.php 当成主机名，
// 直接跳到 https://post.php/?id=1。没有 BOYA_BASE_PATH 时（例如极早的 include）
// 才退回旧的字符白名单反推逻辑，保证独立可用。

if (!function_exists('boya_site_prefix')) {
    function boya_site_prefix()
    {
        if (defined('BOYA_BASE_PATH') && (string)BOYA_BASE_PATH !== '') {
            // '/' → '/'；'/boya/' → '/boya/'；'/boya' → '/boya/'
            return rtrim('/' . trim((string)BOYA_BASE_PATH, '/'), '/') . '/';
        }

        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
        $dir = str_replace('\\', '/', dirname($scriptName));
        // 只保留路径安全字符；'..' 一并去掉，避免拼出奇怪的路径
        $dir = preg_replace('#[^A-Za-z0-9/_\-.]#', '', $dir);
        $dir = str_replace('..', '', (string)$dir);
        $dir = '/' . ltrim((string)$dir, '/');
        return rtrim($dir, '/') . '/';   // '//' → '/'，'/user/' → '/user/'
    }
}
