<?php
require_once __DIR__ . '/_site.php';   // boya_site_prefix()：<html> 那一行就要用
$currentPage = 'messages';
?>
<!DOCTYPE html>
<html lang="zh-CN" data-user-link-base="<?= boya_site_prefix() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>帖子 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班留言板帖子详情与回复。">
    <meta name="robots" content="noindex, follow">
    <?php include __DIR__ . '/_head.php'; ?>
    <link rel="stylesheet" href="<?= boya_asset('css/forum.css') ?>">
    <script src="<?= boya_asset('js/vendor/marked.min.js') ?>"></script>
    <script src="<?= boya_asset('js/vendor/purify.min.js') ?>"></script>
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <div class="forum-bar">
            <div class="forum-who" id="forumWho">正在读取登录状态…</div>
            <div class="forum-bar-actions">
                <a class="forum-login-btn" href="<?= boya_url('messages.php') ?>">← 返回列表</a>
                <button class="forum-login-btn" id="loginBtn" type="button" style="display:none;">登录后回复</button>
                <button class="forum-cancel-btn" id="logoutBtn" type="button" style="display:none;">退出</button>
            </div>
        </div>

        <div class="forum-notice" id="needUsername" style="display:none;">
            你还没有设置<b>论坛用户名</b>（论坛上只显示用户名，不显示真实姓名）。
            <a href="<?= boya_url('admin-files/edit-profile.php') ?>">去设置 →</a>
        </div>

        <div id="postWrap">
            <div class="empty-message">正在加载帖子…</div>
        </div>

        <div class="forum-pager" id="replyPager"></div>

        <section class="forum-reply-box" id="replyBox" style="display:none;">
            <h3>发表回复</h3>
            <textarea id="replyContent" rows="4" maxlength="2000" placeholder="支持 Markdown：**粗体**、*斜体*、[链接](url)、- 列表"></textarea>
            <div class="forum-editor-foot">
                <span class="forum-hint">每人 15 秒最多回复一次，每天最多 100 条</span>
                <button class="submit-btn" id="replyBtn" type="button">发表回复</button>
            </div>
            <p class="form-feedback" id="replyFeedback"></p>
        </section>
    </main>

    <?php include '_login_modal.php'; ?>

    <?php include '_footer.php'; ?>

    <script src="<?= boya_asset('js/forum.js') ?>"></script>
    <script src="<?= boya_asset('js/boya-auth.js') ?>"></script>
    <script src="<?= boya_asset('js/pages/post.js') ?>"></script>

</body>
</html>
