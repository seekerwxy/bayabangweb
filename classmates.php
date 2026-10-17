<?php $currentPage = 'classmates'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>同学录 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班同学录，记录同学们的青春时光和美好回忆。">
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <section class="page-header">
            <div class="badge">Classmates</div>
            <h2>同学录</h2>
        </section>

        <div class="members-grid" id="membersGrid">
            <div class="empty-message">正在加载同学信息…</div>
        </div>

        <!-- 编辑我的信息入口 -->
        <div class="edit-profile-section">
            <div class="edit-profile-card">
                <h3>编辑我的信息</h3>
                <p>更新你的头像、生日、座右铭，让同学们更了解你！</p>
                <a href="<?= boya_url('admin-files/edit-profile.php') ?>" class="edit-profile-btn">去编辑 →</a>
            </div>
        </div>
    </main>

    <?php include '_footer.php'; ?>

    <script src="<?= boya_asset('js/pages/classmates.js') ?>"></script>

</body>
</html>
