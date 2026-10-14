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
 * 判断路径在本站是否真实存在。外链与空值一律视为可用（不做检查）。
 */
function boya_photo_exists($path, $rootDir = null)
{
    $value = boya_normalize_photo($path);
    if ($value === '' || preg_match('#^https?://#i', $value)) {
        return true;
    }
    $rootDir = $rootDir === null ? __DIR__ : rtrim($rootDir, '/');
    return is_file($rootDir . $value);
}

/**
 * photos/ 目录下现有的图片，用于填错路径时给出候选提示。
 */
function boya_photo_suggestions($rootDir = null, $limit = 20)
{
    $rootDir = $rootDir === null ? __DIR__ : rtrim($rootDir, '/');
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
