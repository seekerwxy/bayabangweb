<?php
// 公共头部：主题与外观样式初始化。$baseUrl 只用于「在磁盘上找文件」（admin/ 下是 '../'）。
$baseUrl = $baseUrl ?? '';
$rootDir = __DIR__;

if (!function_exists('boya_asset_path')) {
    function boya_asset_path($rootDir, $baseUrl, $relative)
    {
        $relative = ltrim(str_replace('\\', '/', (string)$relative), '/');
        $candidates = [
            $rootDir . '/' . $relative,
            $rootDir . '/' . $baseUrl . $relative,
            $rootDir . '/../' . $relative,
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return $candidates[0];
    }
}

if (!function_exists('boya_asset_version')) {
    function boya_asset_version($path)
    {
        $time = @filemtime($path);
        return $time === false ? '0' : (string)$time;
    }
}

$styleUrl = isset($styleUrl) && $styleUrl !== '' ? $styleUrl : 'css/gothic.css';
$styleRelative = ltrim(preg_replace('#^[./]+#', '', explode('?', $styleUrl, 2)[0]), '/');
if ($styleRelative === '') {
    $styleRelative = 'css/gothic.css';
}

// 资源链接必须是「站点根绝对路径」：个人主页有 /user/2 这种重写网址，
// 用相对路径 css/beauty.css 会变成 /user/css/beauty.css（线上实测 404，页面完全没样式）。
// $baseUrl 不能拿来当 URL 前缀（后台页面它是 '../'），所以统一用 boya_site_prefix()。
require_once __DIR__ . '/_site.php';
$assetPrefix = boya_site_prefix();
$styleHref = $assetPrefix . $styleRelative;

$beautyCss = boya_asset_path($rootDir, $baseUrl, 'css/beauty.css');
$gothicCss = boya_asset_path($rootDir, $baseUrl, 'css/gothic.css');
$techCss = boya_asset_path($rootDir, $baseUrl, 'css/tech.css');
$classicalCss = boya_asset_path($rootDir, $baseUrl, 'css/classical.css');
$editorialCss = boya_asset_path($rootDir, $baseUrl, 'css/editorial.css');
$westernCss = boya_asset_path($rootDir, $baseUrl, 'css/western.css');
$styleCss = boya_asset_path($rootDir, $baseUrl, $styleRelative);
$techBgJs = boya_asset_path($rootDir, $baseUrl, 'js/tech-bg.js');
?>
<script>
(function () {
    try {
        var savedTheme = localStorage.getItem('theme');
        var theme = (savedTheme === 'dark' || savedTheme === 'light')
            ? savedTheme
            : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);

        var params = new URLSearchParams(window.location.search);
        var paramStyle = params.get('style');
        var savedStyle = localStorage.getItem('boya-style');
        var style = 'default';
        if (paramStyle === 'gothic' || paramStyle === 'tech' || paramStyle === 'classical' || paramStyle === 'editorial' || paramStyle === 'western' || paramStyle === 'default') {
            style = paramStyle;
            try { localStorage.setItem('boya-style', style); } catch (e) {}
        } else if (savedStyle === 'gothic' || savedStyle === 'tech' || savedStyle === 'classical' || savedStyle === 'editorial' || savedStyle === 'western' || savedStyle === 'default') {
            style = savedStyle;
        }
        document.documentElement.setAttribute('data-style', style);
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-style', 'default');
    }
})();
</script>
<link rel="stylesheet" href="<?= $assetPrefix ?>css/beauty.css?v=<?= boya_asset_version($beautyCss) ?>">
<link rel="stylesheet" href="<?= $styleHref ?>?v=<?= boya_asset_version($styleCss) ?>">
<link rel="stylesheet" href="<?= $assetPrefix ?>css/tech.css?v=<?= boya_asset_version($techCss) ?>">
<link rel="stylesheet" href="<?= $assetPrefix ?>css/classical.css?v=<?= boya_asset_version($classicalCss) ?>">
<link rel="stylesheet" href="<?= $assetPrefix ?>css/editorial.css?v=<?= boya_asset_version($editorialCss) ?>">
<link rel="stylesheet" href="<?= $assetPrefix ?>css/western.css?v=<?= boya_asset_version($westernCss) ?>">
<script src="<?= $assetPrefix ?>js/tech-bg.js?v=<?= boya_asset_version($techBgJs) ?>" defer></script>
