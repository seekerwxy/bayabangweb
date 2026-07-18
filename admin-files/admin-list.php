<?php $currentPage = 'home'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博雅班管理页面列表</title>
    <meta name="description" content="博雅班管理页面列表，包含班级时光轴、留言板等功能。">
    <link rel="stylesheet" href="/css/beauty.css">
</head>
<body>

    <main class="main">
        <section class="page-header">
            <div class="badge">Admin</div>
            <h2>管理面板</h2>
        </section>
        <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-bottom: 40px;">
            <a href="admin-memories.php" class="edit-profile-btn">时光轴管理</a>
            <a href="admin-messages.php" class="edit-profile-btn">留言管理</a>
        </div>
        </main>

    <?php include '_footer.php'; ?>

</body>
</html>