<?php
// 公共头部：主题与外观样式初始化。$baseUrl 用于子目录页面（如 admin/）。
$baseUrl = $baseUrl ?? '';
$rootDir = __DIR__;

// 解析静态资源真实路径：优先按 $baseUrl 找（admin-files 下 $baseUrl 为 '../'），
// 并对 filemtime() 做存在性保护，避免文件缺失时产生 PHP 警告、版本号变成空值。
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

$styleUrl = isset($styleUrl) && $styleUrl !== '' ? $styleUrl : $baseUrl . 'css/gothic.css';
$styleRelative = ltrim(preg_replace('#^[./]+#', '', explode('?', $styleUrl, 2)[0]), '/');
if ($styleRelative === '') {
    $styleRelative = 'css/gothic.css';
}

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
<link rel="stylesheet" href="<?= $baseUrl ?>css/beauty.css?v=<?= boya_asset_version($beautyCss) ?>">
<link rel="stylesheet" href="<?= $styleUrl ?>?v=<?= boya_asset_version($styleCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/tech.css?v=<?= boya_asset_version($techCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/classical.css?v=<?= boya_asset_version($classicalCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/editorial.css?v=<?= boya_asset_version($editorialCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/western.css?v=<?= boya_asset_version($westernCss) ?>">
<script src="<?= $baseUrl ?>js/tech-bg.js?v=<?= boya_asset_version($techBgJs) ?>" defer></script>
