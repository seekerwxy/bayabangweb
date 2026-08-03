<?php
$currentPage = 'home';

function boyaFormatBytes($bytes, $precision = 1)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max(0, (int)$bytes);
    $pow = floor(($bytes ? log($bytes, 1024) : 0));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>下载博雅 APP | 温二外25级博雅班</title>
    <meta name="description" content="下载博雅 APP，选择适合手机架构的 Android 安装包。">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <section class="page-header">
            <div class="badge">Boya App</div>
            <h2>下载博雅 APP</h2>
            <p class="section-sub">请根据手机架构选择对应的 Android 安装包</p>
        </section>

        <p class="section-label">Android APK</p>
        <div class="cards-grid">
            <?php
            $apkDir = __DIR__ . '/text/bayaAPP';
            $apkFiles = glob($apkDir . '/*.apk');

            if (empty($apkFiles)) {
                echo '<div class="empty-message">暂未找到 APK 安装包</div>';
            } else {
                sort($apkFiles);
                foreach ($apkFiles as $apkFile) {
                    $fileName = basename($apkFile);
                    $fileSize = @filesize($apkFile);
                    $sizeText = $fileSize === false ? '未知大小' : boyaFormatBytes($fileSize);

                    $label = '博雅 APP';
                    $archDesc = 'Android APK 安装包。';
                    if (strpos($fileName, 'arm64-v8a') !== false) {
                        $label = '64 位版本';
                        $archDesc = '适合大多数较新的 Android 手机，优先推荐。';
                    } elseif (strpos($fileName, 'armeabi-v7a') !== false) {
                        $label = '32 位兼容版本';
                        $archDesc = '适合较老或 32 位架构的 Android 设备。';
                    }

                    preg_match('/(\d+\.\d+\.\d+)/', $fileName, $versionMatch);
                    $versionText = isset($versionMatch[1]) ? 'v' . $versionMatch[1] : 'APK';

                    $cleanName = preg_replace('/^_+/', '', $fileName);
                    $downloadName = preg_replace('/^(\d+\.\d+\.\d+)_/', 'boya-app-$1-', $cleanName);
            ?>
            <a class="card download-card" href="text/bayaAPP/<?= rawurlencode($fileName) ?>"
               type="application/vnd.android.package-archive"
               download="<?= htmlspecialchars($downloadName, ENT_QUOTES, 'UTF-8') ?>">
                <span class="card-number">APK · <?= htmlspecialchars($versionText, ENT_QUOTES, 'UTF-8') ?></span>
                <h3 class="card-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="card-desc"><?= htmlspecialchars($archDesc, ENT_QUOTES, 'UTF-8') ?></p>
                <span class="card-arrow"><i class="fa-solid fa-download" aria-hidden="true"></i> 下载 · <?= htmlspecialchars($sizeText, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <?php
                }
            }
            ?>
        </div>
    </main>

    <?php include '_footer.php'; ?>

</body>
</html>
