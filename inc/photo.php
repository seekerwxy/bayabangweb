<?php
// 公共图片路径工具：时光轴配图、同学头像等都走这里，避免各处各写一套。
//
// 支持的写法：
//   photos/shijian1.jpg          站点根相对
//   /photos/shijian1.jpg         站点根绝对
//   ./photos/shijian1.jpg        ./ 前缀
//   ../photos/shijian1.jpg       ../ 前缀
//   htdocs/photos/shijian1.jpg   误填的 FTP 路径也会被修掉
//   https://example.com/x.jpg    外链，原样保留
//
// 统一归一化成「站点根绝对路径」，这样前台、后台预览、后台编辑框里看到的都一致。
// （原文件位置：根目录 _photo.php，重构后搬进 inc/，逻辑未改。）

/**
 * 归一化图片路径。外链原样返回，空值返回空串。
 */
function boya_normalize_photo($path)
{
    $value = trim(str_replace('\\', '/', (string)$path));
    if ($value === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    // 去掉误填的 FTP / 文件系统前缀
    $value = preg_replace('#^/?(?:bayabang\.top/)?htdocs/#i', '', $value);
    // 去掉开头的 ./ 和 ../
    $value = preg_replace('#^(?:\.{1,2}/)+#', '', $value);
    return '/' . ltrim($value, '/');
}

/**
 * 判断路径在本站是否真实存在、且允许被当作图片使用。
 *
 * 规则（外链与空值一律放行，不做检查）：
 *   1. 拒绝含 `..` 的路径穿越写法；
 *   2. 必须以图片扩展名结尾（jpg/jpeg/png/webp/gif）；
 *   3. 只允许这几个目录下的文件：/photos/、/uploads/、/text/、/api/photos/（旧头像目录），
 *      以及站点根目录下的单个图片文件。
 * 这样 `/../config.local.php`、`/api/classmates.php` 这类值不会“看起来是存在的文件”而被写入数据库。
 */
function boya_photo_exists($path, $rootDir = null)
{
    $value = boya_normalize_photo($path);
    if ($value === '' || preg_match('#^https?://#i', $value)) {
        return true;
    }

    // 1. 路径穿越
    if (strpos($value, '..') !== false) {
        return false;
    }

    // 2. 必须是图片扩展名
    if (!preg_match('#\.(jpe?g|png|webp|gif)$#i', $value)) {
        return false;
    }

    // 3. 目录白名单（或站点根目录下的图片）
    $allowedPrefix = preg_match('#^/(photos|uploads|text|api/photos)/#', $value) === 1;
    $rootLevelImage = preg_match('#^/[^/]+\.(jpe?g|png|webp|gif)$#i', $value) === 1;
    if (!$allowedPrefix && !$rootLevelImage) {
        return false;
    }

    $rootDir = $rootDir === null ? (defined('BOYA_ROOT') ? BOYA_ROOT : dirname(__DIR__)) : rtrim($rootDir, '/');
    return is_file($rootDir . $value);
}

/**
 * photos/ 目录下现有的图片，用于填错路径时给出候选提示。
 */
function boya_photo_suggestions($rootDir = null, $limit = 20)
{
    $rootDir = $rootDir === null ? (defined('BOYA_ROOT') ? BOYA_ROOT : dirname(__DIR__)) : rtrim($rootDir, '/');
    $files = glob($rootDir . '/photos/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
    if (!$files) {
        return [];
    }
    sort($files, SORT_STRING);
    $names = [];
    foreach ($files as $file) {
        $names[] = 'photos/' . basename($file);
        if (count($names) >= $limit) {
            break;
        }
    }
    return $names;
}
