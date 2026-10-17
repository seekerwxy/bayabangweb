<?php
require_once __DIR__ . '/_site.php';   // boya_site_prefix()：本文件在 include _head.php 之前就要用
$currentPage = 'classmates';
// 个人主页有两个网址：/personal.php?id=2 和 /user/2（.htaccess 重写）。
// 前缀统一由 boya_site_prefix() 计算：它保证以单个 / 开头、以单个 / 结尾。
$sitePrefix = boya_site_prefix();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-user-link-base="<?= $sitePrefix ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人主页 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班同学的个人主页：资料、发帖与回复动态。">
    <?php include __DIR__ . '/_head.php'; ?>
    <link rel="stylesheet" href="<?= boya_asset('css/forum.css') ?>">
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <div id="userWrap">
            <div class="empty-message">正在加载…</div>
        </div>
        <div class="forum-pager" id="feedPager"></div>

        <div class="uspace-tip">
            <a href="<?= boya_url('classmates.php') ?>">← 返回同学录</a>
            <span>·</span>
            <a href="<?= boya_url('messages.php') ?>">去留言板 →</a>
        </div>
    </main>

    <?php include '_footer.php'; ?>

    <script src="<?= boya_asset('js/forum.js') ?>"></script>
    <script src="<?= boya_asset('js/pages/personal.js') ?>"></script>

</body>
</html>
