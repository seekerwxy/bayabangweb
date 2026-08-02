<?php
// 公共头部：主题与外观样式初始化。$baseUrl 用于子目录页面（如 admin/）。
$baseUrl = $baseUrl ?? '';
$styleUrl = isset($styleUrl) && $styleUrl !== '' ? $styleUrl : $baseUrl . 'css/gothic.css';
$rootDir = __DIR__;
$beautyCss = file_exists($rootDir . '/css/beauty.css')
    ? $rootDir . '/css/beauty.css'
    : $rootDir . '/../css/beauty.css';
$gothicCss = file_exists($rootDir . '/css/gothic.css')
    ? $rootDir . '/css/gothic.css'
    : $rootDir . '/../css/gothic.css';
$techCss = file_exists($rootDir . '/css/tech.css')
    ? $rootDir . '/css/tech.css'
    : $rootDir . '/../css/tech.css';
$classicalCss = file_exists($rootDir . '/css/classical.css')
    ? $rootDir . '/css/classical.css'
    : $rootDir . '/../css/classical.css';
$editorialCss = file_exists($rootDir . '/css/editorial.css')
    ? $rootDir . '/css/editorial.css'
    : $rootDir . '/../css/editorial.css';
$westernCss = file_exists($rootDir . '/css/western.css')
    ? $rootDir . '/css/western.css'
    : $rootDir . '/../css/western.css';
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
<link rel="stylesheet" href="<?= $baseUrl ?>css/beauty.css?v=<?= filemtime($beautyCss) ?>">
<link rel="stylesheet" href="<?= $styleUrl ?>?v=<?= filemtime($gothicCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/tech.css?v=<?= filemtime($techCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/classical.css?v=<?= filemtime($classicalCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/editorial.css?v=<?= filemtime($editorialCss) ?>">
<link rel="stylesheet" href="<?= $baseUrl ?>css/western.css?v=<?= filemtime($westernCss) ?>">
<script src="<?= $baseUrl ?>js/tech-bg.js?v=<?= filemtime($rootDir . '/js/tech-bg.js') ?>" defer></script>
