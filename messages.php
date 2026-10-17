<?php
require_once __DIR__ . '/_site.php';   // boya_site_prefix()：<html> 那一行就要用
$currentPage = 'messages';
?>
<!DOCTYPE html>
<html lang="zh-CN" data-user-link-base="<?= boya_site_prefix() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>留言板 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班留言板：登录后可以发帖和回复，游客也能浏览。">
    <?php include __DIR__ . '/_head.php'; ?>
    <link rel="stylesheet" href="<?= boya_asset('css/forum.css') ?>">
    <script src="<?= boya_asset('js/vendor/marked.min.js') ?>"></script>
    <script src="<?= boya_asset('js/vendor/purify.min.js') ?>"></script>
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <section class="page-header">
            <div class="badge">Message Board</div>
            <h2>留言板</h2>
            <p class="section-sub">登录后可以发帖、回复；论坛只显示你的论坛用户名，不显示真实姓名</p>
        </section>

        <div class="forum-bar">
            <div class="forum-who" id="forumWho">正在读取登录状态…</div>
            <div class="forum-bar-actions">
                <button class="submit-btn" id="newPostBtn" type="button" style="display:none;">发新帖</button>
                <button class="forum-login-btn" id="loginBtn" type="button" style="display:none;">登录后发言</button>
                <button class="forum-cancel-btn" id="logoutBtn" type="button" style="display:none;">退出</button>
            </div>
        </div>

        <div class="forum-notice" id="needUsername" style="display:none;">
            你还没有设置<b>论坛用户名</b>（论坛上只显示用户名，不显示真实姓名）。
            <a href="<?= boya_url('admin-files/edit-profile.php') ?>">去设置 →</a>
        </div>

        <section class="forum-editor" id="postEditor" style="display:none;">
            <h3>发新帖</h3>
            <input type="text" id="postTitle" maxlength="60" placeholder="标题（最多 60 字）">
            <textarea id="postContent" rows="6" maxlength="5000" placeholder="正文支持 Markdown：**粗体**、*斜体*、[链接](url)、- 列表、&gt; 引用"></textarea>
            <div class="forum-editor-foot">
                <div class="color-picker" id="postColors">
                    <span class="color-swatch" style="background:#000000" data-color="#000000"></span>
                    <span class="color-swatch" style="background:#e60000" data-color="#e60000"></span>
                    <span class="color-swatch" style="background:#ff9900" data-color="#ff9900"></span>
                    <span class="color-swatch" style="background:#008000" data-color="#008000"></span>
                    <span class="color-swatch" style="background:#0000ff" data-color="#0000ff"></span>
                    <span class="color-swatch" style="background:#800080" data-color="#800080"></span>
                    <span class="color-swatch" style="background:#ff1493" data-color="#ff1493"></span>
                    <span class="color-swatch" style="background:#00ced1" data-color="#00ced1"></span>
                    <span class="color-swatch" style="background:#32cd32" data-color="#32cd32"></span>
                    <span class="color-swatch" style="background:#ffd700" data-color="#ffd700"></span>
                    <span class="color-swatch" style="background:#ff4500" data-color="#ff4500"></span>
                    <span class="color-swatch" style="background:#8a2be2" data-color="#8a2be2"></span>
                    <span class="forum-hint">点色块给选中的文字上色</span>
                </div>
                <div>
                    <button class="forum-cancel-btn" id="cancelPostBtn" type="button">取消</button>
                    <button class="submit-btn" id="publishBtn" type="button">发布</button>
                </div>
            </div>
            <p class="form-feedback" id="postFeedback"></p>
        </section>

        <div class="forum-list" id="postList">
            <div class="empty-message">正在加载帖子…</div>
        </div>
        <div class="forum-pager" id="postPager"></div>
    </main>

    <?php include '_login_modal.php'; ?>

    <?php include '_footer.php'; ?>

    <script src="<?= boya_asset('js/forum.js') ?>"></script>
    <script src="<?= boya_asset('js/boya-auth.js') ?>"></script>
    <script src="<?= boya_asset('js/pages/messages.js') ?>"></script>

</body>
</html>
