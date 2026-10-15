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
        // 用内容哈希而不是 filemtime：FTP 上传可能保留旧 mtime，改了内容但 mtime 没变时
        // 浏览器会一直用缓存的旧 CSS（之前就因此出现过「样式没跟上主题」的现象）。
        // 内容一变哈希就变，链接里的 ?v= 必然变化，缓存永不失效。
        if (is_file($path) && function_exists('md5_file')) {
            $hash = @md5_file($path);
            if ($hash !== false && $hash !== '') {
                return substr($hash, 0, 10);
            }
        }
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
require_once __DIR__ . '/_site.php';

if (!function_exists('boya_asset_url')) {
    /**
     * 由「磁盘上的真实路径」反推这个资源该怎么用 URL 引用。
     *
     * 为什么不直接拼 $baseUrl：后台页面 $baseUrl 是 '../'，拼出来是相对的，
     * 而个人主页那种重写网址下相对路径会解析到错误目录。
     * 这里用脚本目录与目标文件目录的公共前缀来还原 URL：两者都在站点根之下时，
     * 多出来的那一段就是 URL 前缀；后台页面（/admin-files/ 对 /）公共前缀为空 → 站点根。
     */
    function boya_asset_url($rootDir, $relative, $filePath)
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        // '..' 去掉、目录结尾补斜杠，保证 $rootDir 与 $targetDir 都有站点根这一层
        $rootDir = rtrim(str_replace('\\', '/', str_replace('..', '', (string)$rootDir)), '/');
        $targetDir = rtrim(str_replace('\\', '/', str_replace('..', '', dirname((string)$filePath))), '/');
        $rootDir = preg_replace('#[^A-Za-z0-9/_\-.]#', '', $rootDir);
        $targetDir = preg_replace('#[^A-Za-z0-9/_\-.]#', '', $targetDir);

        $shared = 0;
        if ($rootDir !== '' && strpos($targetDir, $rootDir) === 0) {
            $shared = substr_count($rootDir, '/');     // /xxx/htdocs → 2 段
        }
        $segments = $scriptDir === '' ? [] : explode('/', ltrim($scriptDir, '/'));
        if ($shared > 0) {
            $segments = array_slice($segments, $shared);
        }
        $prefix = $segments ? '/' . implode('/', $segments) . '/' : '/';

        return $prefix . ltrim(str_replace('\\', '/', (string)$relative), '/');
    }
}

$styleHref = boya_asset_url($rootDir, $styleRelative, boya_asset_path($rootDir, $baseUrl, $styleRelative));

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
<link rel="stylesheet" href="<?= boya_asset_url($rootDir, 'css/beauty.css', $beautyCss) ?>?v=<?= boya_asset_version($beautyCss) ?>">
<link rel="stylesheet" href="<?= $styleHref ?>?v=<?= boya_asset_version($styleCss) ?>">
<link rel="stylesheet" href="<?= boya_asset_url($rootDir, 'css/tech.css', $techCss) ?>?v=<?= boya_asset_version($techCss) ?>">
<link rel="stylesheet" href="<?= boya_asset_url($rootDir, 'css/classical.css', $classicalCss) ?>?v=<?= boya_asset_version($classicalCss) ?>">
<link rel="stylesheet" href="<?= boya_asset_url($rootDir, 'css/editorial.css', $editorialCss) ?>?v=<?= boya_asset_version($editorialCss) ?>">
<link rel="stylesheet" href="<?= boya_asset_url($rootDir, 'css/western.css', $westernCss) ?>?v=<?= boya_asset_version($westernCss) ?>">
<script src="<?= boya_asset_url($rootDir, 'js/tech-bg.js', $techBgJs) ?>?v=<?= boya_asset_version($techBgJs) ?>" defer></script>
