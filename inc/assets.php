<?php
// 静态资源的 URL 与版本号。
//
// 历史包袱：以前 _head.php 用「在磁盘上试着找文件 + 用脚本目录反推 URL」的方式拼资源地址
// （boya_asset_path / boya_asset_url），因为后台页面在子目录、个人主页又有 /user/2 这种
// 重写网址，相对路径会解析到错误目录。那套逻辑能用，但很难读。
//
// 事实是：站点永远部署在域名根目录（InfinityFree 的站点根就是 htdocs/），
// 所以资源地址直接用「站点根绝对路径」就是正确答案 —— /css/beauty.css 在任何
// 层级（含 /user/2）都指向同一个文件。于是这里只剩三件事：
//   1. boya_asset('css/x.css')  → '/css/x.css?v=<内容哈希>'
//   2. boya_url('post.php')     → '/post.php'
//   3. boya_asset_version()     → 内容哈希（保留旧函数名与旧签名）

require_once dirname(__DIR__) . '/config.php';   // BOYA_BASE_PATH：站点根的单一事实来源
require_once dirname(__DIR__) . '/_site.php';

if (!function_exists('boya_asset_version')) {
    /**
     * 资源版本号：用内容哈希而不是 filemtime。
     *
     * FTP 上传可能保留旧 mtime，改了内容但 mtime 没变时浏览器会一直用缓存里的旧 CSS
     * （线上确实踩过「样式没跟上」）。内容一变哈希就变，缓存永不失效。
     * 同一个请求里同一文件只算一次（一页要引 6 个 CSS，别重复算）。
     */
    function boya_asset_version($path)
    {
        static $memo = [];
        $key = (string)$path;
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $hash = is_file($key) && function_exists('md5_file') ? @md5_file($key) : false;
        if ($hash !== false && $hash !== '') {
            return $memo[$key] = substr($hash, 0, 10);
        }
        $time = @filemtime($key);
        return $memo[$key] = ($time === false ? '0' : (string)$time);
    }
}

if (!function_exists('boya_asset')) {
    /**
     * 把「相对站点根的静态资源路径」变成带版本号的站点根绝对地址。
     * 例：boya_asset('css/forum.css') → '/css/forum.css?v=1a2b3c4d5e'
     */
    function boya_asset($relative)
    {
        $relative = ltrim(str_replace('\\', '/', (string)$relative), '/');
        if ($relative === '') {
            return boya_site_prefix();
        }
        $file = (defined('BOYA_ROOT') ? BOYA_ROOT : dirname(__DIR__)) . '/' . $relative;
        return boya_site_prefix() . $relative . '?v=' . boya_asset_version($file);
    }
}

if (!function_exists('boya_url')) {
    /** 站内链接：boya_url('post.php?id=1') → '/post.php?id=1'。 */
    function boya_url($path = '')
    {
        $path = (string)$path;
        if ($path === '') {
            return boya_site_prefix();
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return boya_site_prefix() . ltrim($path, '/');
    }
}
