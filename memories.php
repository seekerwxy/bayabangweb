<?php $currentPage = 'memories'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>时光回忆馆 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班时光回忆馆，记录班级的成长历程和美好回忆。">
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <section class="page-header">
            <div class="badge">Memory Lane</div>
            <h2>时光回忆馆</h2>
        </section>

        <!-- 时光轴 -->
        <div class="timeline-section">
            <h3 class="section-title">班级时光轴</h3>
            <p class="section-sub">从初遇到现在，我们一起走过的每一步</p>
            <div class="timeline" id="timelineContainer">
                <!-- 数据将由 JavaScript 动态生成 -->
                <div class="empty-message">加载中...</div>
            </div>
        </div>

        <!-- 相册（保留，暂不启用） -->
        <div class="gallery-section">
            <!-- 注释掉内容，保留占位 -->
        </div>

        <!-- 拔牙帮天数 -->
        <div class="days-counter">
            <p class="label">拔牙帮至今已</p>
            <p id="daysCounter" class="number">0</p>
            <p class="unit">天</p>
        </div>
    </main>

    <?php include '_footer.php'; ?>

    <script src="<?= boya_asset('js/pages/memories.js') ?>"></script>
</body>
</html>
