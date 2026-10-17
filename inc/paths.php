<?php
// 运行期数据目录（data/）的路径工具。
//
// data/ 里都是「只应该存在服务器上」的东西：登录限速、发帖限速、审计日志、浏览量去重、
// 结构版本标记。.gitignore 与部署 exclude 都已排除，.htaccess 禁止外部直接访问。
// 以前每个模块各自写一遍「拼 data 目录 + mkdir」，这里收成一处。

if (!function_exists('boya_data_dir')) {
    function boya_data_dir()
    {
        $dir = defined('BOYA_ROOT') ? BOYA_ROOT . '/data' : dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('boya_data_file')) {
    function boya_data_file($name)
    {
        return boya_data_dir() . '/' . ltrim((string)$name, '/');
    }
}
