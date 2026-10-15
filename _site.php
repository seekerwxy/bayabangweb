<?php
// 站点根前缀工具。这是「零依赖」的公共片段：任何页面（包括后台子目录的页面）
// 都可以在任何时候 require 它，不需要先加载 config.php。
//
// 用它来拼站内链接，保证得到的是「以单个 / 开头、以单个 / 结尾」的站点根绝对路径。

if (!function_exists('boya_site_prefix')) {
    /**
     * 站点根前缀，站点根就是 "/"。
     *
     * 为什么不用 `htmlspecialchars(dirname($_SERVER['SCRIPT_NAME']))`：线上
     * （InfinityFree，PHP 8.4.25）实测这个写法返回空串，前缀因此变成 "//"；
     * 而 '//' + 'post.php?id=1' 是协议相对地址，浏览器会把 post.php 当成主机名，
     * 直接跳到 https://post.php/?id=1。同样的 dirname() 结果先存进变量再拼
     * （不走 htmlspecialchars）却是正常的 "/"。这里索性不经过 htmlspecialchars，
     * 直接按字符白名单过滤，结果只可能是安全字符。
     */
    function boya_site_prefix()
    {
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
        $dir = str_replace('\\', '/', dirname($scriptName));
        // 只保留路径安全字符；'..' 一并去掉，避免拼出奇怪的路径
        $dir = preg_replace('#[^A-Za-z0-9/_\-.]#', '', $dir);
        $dir = str_replace('..', '', (string)$dir);
        $dir = '/' . ltrim((string)$dir, '/');
        return rtrim($dir, '/') . '/';   // '//' → '/'，'/user/' → '/user/'
    }
}
