<?php
// 站点唯一的 PHP 引导文件（被 include 的库里用，不直接被浏览器访问）。
//
// 任何接口 / 后台页面开头写一行 `require_once __DIR__ . '/../inc/bootstrap.php';`
// 就够了，不用再各自写 ini_set / date_default_timezone_set / mysqli_report。
//
// 幂等：重复 require 只会执行一次（define 守卫），所以库之间可以放心互相 require。

if (defined('BOYA_BOOTSTRAP')) {
    return;
}
define('BOYA_BOOTSTRAP', '1');

define('BOYA_ROOT', dirname(__DIR__));   // 站点根目录的磁盘绝对路径
define('BOYA_INC', __DIR__);

// 结构同步的版本号：改了「自动补列 / 建表」的逻辑就把它改一下，
// 下次请求会重新跑一次同步（见 inc/db.php 的 boya_schema_once）。
define('BOYA_SCHEMA_CLASSMATES', '2026-10-17-1');
define('BOYA_SCHEMA_FORUM', '2026-10-17-1');

// 全站统一东八区：数据库里存的是本地时间字符串，混用时区会差 8 小时。
date_default_timezone_set('Asia/Shanghai');

// config.php 是必需的引导文件（配置数组 + 关掉 mysqli 异常 + 错误显示设置）。
require_once BOYA_ROOT . '/config.php';

require_once __DIR__ . '/paths.php';     // data/ 路径 + mkdir
require_once __DIR__ . '/http.php';      // JSON 输入输出、客户端 IP
require_once __DIR__ . '/session.php';   // session + CSRF
require_once __DIR__ . '/db.php';        // 连接池 + 结构同步闸门
require_once __DIR__ . '/assets.php';    // 静态资源 URL + 内容哈希版本号
