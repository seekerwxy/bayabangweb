<?php
// 公共头部：外观（明暗 + 主题）初始化 + 全站样式。
//
// 资源地址统一用 inc/assets.php 的 boya_asset()：站点根绝对路径 + 内容哈希版本号。
// 以前这里靠「在磁盘上试几个候选路径、再用脚本目录反推 URL」来兼容后台子目录，
// 逻辑复杂且容易出错；实际上站点永远部署在域名根目录，直接用 /css/... 就是对的，
// 个人主页的 /user/2 这类重写网址下也一样。
//
// 同时输出 window.BOYA（站点根 + 接口前缀），页面脚本不用再各自硬编码 '/api/'。

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/theme.php';

// 兼容旧的 $styleUrl 变量（指定默认外观样式文件），默认哥特。
$styleRelative = 'css/gothic.css';
if (isset($styleUrl) && is_string($styleUrl) && $styleUrl !== '') {
    $candidate = ltrim(preg_replace('#^[./]+#', '', explode('?', $styleUrl, 2)[0]), '/');
    if ($candidate !== '') {
        $styleRelative = $candidate;
    }
}
?>
<?= boya_theme_init_script() ?>

<script>window.BOYA = <?= json_encode([
    'base' => boya_site_prefix(),
    'api' => boya_url('api/'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<!-- 图标库：以前 9 个页面各写一遍（含 SRI），升版本要改 9 处，现在只有这里一份 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= boya_asset('css/beauty.css') ?>">
<link rel="stylesheet" href="<?= boya_asset($styleRelative) ?>">
<link rel="stylesheet" href="<?= boya_asset('css/tech.css') ?>">
<link rel="stylesheet" href="<?= boya_asset('css/classical.css') ?>">
<link rel="stylesheet" href="<?= boya_asset('css/editorial.css') ?>">
<link rel="stylesheet" href="<?= boya_asset('css/western.css') ?>">
<script src="<?= boya_asset('js/tech-bg.js') ?>" defer></script>
