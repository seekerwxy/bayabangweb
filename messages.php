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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php include __DIR__ . '/_head.php'; ?>
    <link rel="stylesheet" href="css/forum.css?v=<?= filemtime(__DIR__ . '/css/forum.css') ?>">
    <script src="js/vendor/marked.min.js?v=<?= filemtime(__DIR__ . '/js/vendor/marked.min.js') ?>"></script>
    <script src="js/vendor/purify.min.js?v=<?= filemtime(__DIR__ . '/js/vendor/purify.min.js') ?>"></script>
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
            <a href="admin-files/edit-profile.php">去设置 →</a>
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

    <!-- 登录弹窗 -->
    <div class="forum-modal" id="loginModal">
        <div class="forum-modal-card">
            <h3>同学登录</h3>
            <p class="muted">用真实姓名或论坛用户名 + 密码登录，和「编辑我的信息」是同一个账号。</p>
            <label for="loginName">姓名 / 用户名</label>
            <input type="text" id="loginName" autocomplete="username">
            <label for="loginPassword">密码</label>
            <input type="password" id="loginPassword" autocomplete="current-password">
            <p class="form-feedback error" id="loginError"></p>
            <div class="forum-modal-actions">
                <button class="forum-cancel-btn" id="loginCancel" type="button">取消</button>
                <button class="submit-btn" id="loginSubmit" type="button">登录</button>
            </div>
        </div>
    </div>

    <?php include '_footer.php'; ?>

    <script src="js/forum.js?v=<?= filemtime(__DIR__ . '/js/forum.js') ?>"></script>
    <script>
    (function () {
        "use strict";

        var Forum = window.BoyaForum;
        var state = { session: null, page: 1 };
        var byId = function (id) { return document.getElementById(id); };

        // ---------------------------------------------------- 登录状态条
        function renderBar() {
            var s = state.session || {};
            var who = byId('forumWho');

            if (!s.loggedIn) {
                who.innerHTML = '游客浏览中，登录后即可发帖与回复';
                byId('loginBtn').style.display = '';
                byId('logoutBtn').style.display = 'none';
                byId('newPostBtn').style.display = 'none';
                byId('needUsername').style.display = 'none';
                byId('postEditor').style.display = 'none';
                return;
            }

            who.innerHTML = '已登录：<b>' + Forum.escapeHtml(s.username || '（还没设置用户名）') + '</b>';
            byId('loginBtn').style.display = 'none';
            byId('logoutBtn').style.display = '';
            byId('newPostBtn').style.display = s.hasUsername ? '' : 'none';
            byId('needUsername').style.display = s.hasUsername ? 'none' : '';
        }

        function loadSession() {
            return Forum.loadSession().then(function (data) {
                state.session = data;
                renderBar();
                return data;
            }).catch(function () {
                byId('forumWho').textContent = '登录状态读取失败（刷新试试）';
            });
        }

        // ---------------------------------------------------- 帖子列表
        function renderPosts(data) {
            var list = byId('postList');
            var pager = byId('postPager');

            if (!data.posts || !data.posts.length) {
                list.innerHTML = '<div class="empty-message">还没有帖子，登录后点「发新帖」写第一条吧</div>';
                pager.innerHTML = '';
                return;
            }

            list.innerHTML = data.posts.map(function (post) {
                var badges = [];
                if (post.is_pinned) { badges.push('<span class="badge-forum pin">置顶</span>'); }
                if (post.is_locked) { badges.push('<span class="badge-forum lock">已锁定</span>'); }
                var time = post.last_reply_at
                    ? '最后回复 ' + Forum.formatTime(post.last_reply_at)
                    : '发表于 ' + Forum.formatTime(post.created_at);

                // 注意：整张卡片不能再是 <a>！作者行里也有一个 <a>（进个人主页），
                // <a> 不能嵌套 <a>，浏览器会在内层链接处强行闭合外层，
                // 结果卡片样式只套住一个空 <a>，标题和摘要全跑到卡片外面去。
                // 所以卡片用 div + data-post，标题单独做链接，点击卡片时由下面的委托脚本跳转。
                return '<div class="forum-item" data-post="' + post.id + '">'
                    + '<div class="forum-item-top">'
                    + '<a class="forum-item-title" href="' + Forum.siteBase() + 'post.php?id=' + post.id + '">'
                    + Forum.escapeHtml(post.title) + '</a>'
                    + badges.join(' ')
                    + '</div>'
                    + '<div class="forum-item-excerpt">' + Forum.escapeHtml(post.excerpt || '') + '</div>'
                    + '<div class="forum-item-meta">'
                    + Forum.authorLine(post)
                    + '<span>回复 ' + post.reply_count + '</span>'
                    + '<span>浏览 ' + post.view_count + '</span>'
                    + '<span>' + time + '</span>'
                    + '</div></div>';
            }).join('');

            if (data.totalPages <= 1) {
                pager.innerHTML = '';
                return;
            }
            pager.innerHTML = ''
                + '<button type="button" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>上一页</button>'
                + '<span>第 ' + data.page + ' / ' + data.totalPages + ' 页</span>'
                + '<button type="button" data-page="' + (data.page + 1) + '"' + (data.page >= data.totalPages ? ' disabled' : '') + '>下一页</button>';
            pager.querySelectorAll('button[data-page]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    state.page = Number(btn.dataset.page);
                    loadPosts();
                });
            });
        }

        function loadPosts() {
            byId('postList').innerHTML = '<div class="empty-message">正在加载帖子…</div>';
            Forum.fetchJson('/api/forum.php?action=list&page=' + state.page).then(function (res) {
                if (!res.ok) {
                    byId('postList').innerHTML = '<div class="empty-message">'
                        + Forum.escapeHtml(res.data.message || '加载失败，请刷新重试') + '</div>';
                    return;
                }
                renderPosts(res.data);
            });
        }

        // ---------------------------------------------------- 发帖
        byId('newPostBtn').addEventListener('click', function () {
            var editor = byId('postEditor');
            editor.style.display = editor.style.display === 'none' ? '' : 'none';
            if (editor.style.display !== 'none') {
                byId('postTitle').focus();
            }
        });

        byId('cancelPostBtn').addEventListener('click', function () {
            byId('postEditor').style.display = 'none';
            byId('postFeedback').textContent = '';
        });

        byId('publishBtn').addEventListener('click', function () {
            var s = state.session || {};
            var feedback = byId('postFeedback');
            var title = byId('postTitle').value.trim();
            var content = byId('postContent').value.trim();

            if (!s.loggedIn) {
                openLogin();
                return;
            }
            if (!title || !content) {
                feedback.textContent = '标题和正文都要填';
                feedback.className = 'form-feedback error';
                return;
            }

            var btn = this;
            btn.disabled = true;
            btn.textContent = '发布中…';
            feedback.textContent = '';

            Forum.send({
                action: 'create-post',
                title: title,
                content: content,
                csrf_token: s.csrf_token || ''
            }).then(function (res) {
                if (res.ok) {
                    byId('postTitle').value = '';
                    byId('postContent').value = '';
                    byId('postEditor').style.display = 'none';
                    feedback.textContent = '';
                    Forum.toast('发布成功');
                    state.page = 1;
                    loadPosts();
                    return;
                }
                if (res.data.needUsername) {
                    byId('needUsername').style.display = '';
                }
                if (res.data.needLogin) {
                    openLogin();
                    return;
                }
                feedback.textContent = res.data.message || '发布失败，请稍后重试';
                feedback.className = 'form-feedback error';
            }).catch(function () {
                feedback.textContent = '网络错误，发布失败';
                feedback.className = 'form-feedback error';
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = '发布';
            });
        });

        // ---------------------------------------------------- 登录 / 退出
        function openLogin() {
            byId('loginError').textContent = '';
            byId('loginModal').classList.add('open');
            byId('loginName').focus();
        }

        function closeLogin() {
            byId('loginModal').classList.remove('open');
            byId('loginPassword').value = '';
        }

        byId('loginBtn').addEventListener('click', openLogin);
        byId('loginCancel').addEventListener('click', closeLogin);
        byId('loginModal').addEventListener('click', function (event) {
            if (event.target === byId('loginModal')) {
                closeLogin();
            }
        });

        byId('loginSubmit').addEventListener('click', function () {
            var name = byId('loginName').value.trim();
            var password = byId('loginPassword').value;
            var error = byId('loginError');
            if (!name || !password) {
                error.textContent = '请填写姓名和密码';
                return;
            }
            var btn = this;
            btn.disabled = true;
            btn.textContent = '登录中…';
            error.textContent = '';

            Forum.login(name, password).then(function (res) {
                if (!res.ok || res.data.message !== 'success') {
                    error.textContent = res.data.message || '登录失败';
                    return;
                }
                try {
                    localStorage.setItem('profile_name', name);
                } catch (e) {}
                closeLogin();
                Forum.toast('登录成功');
                loadSession().then(loadPosts);
            }).catch(function () {
                error.textContent = '网络错误，登录失败';
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = '登录';
            });
        });

        byId('logoutBtn').addEventListener('click', function () {
            var s = state.session || {};
            Forum.logout(s.csrf_token || '').then(function () {
                Forum.toast('已退出');
                loadSession().then(loadPosts);
            });
        });

        // ---------------------------------------------------- 初始化
        // 卡片是 div（见 renderPosts 的注释），点卡片空白处跳帖子详情；
        // 点在卡片内部的链接/按钮上时交回给它们自己处理。
        byId('postList').addEventListener('click', function (event) {
            if (event.target.closest('a, button')) {
                return;
            }
            var card = event.target.closest('.forum-item[data-post]');
            if (card) {
                window.location.href = Forum.siteBase() + 'post.php?id=' + card.dataset.post;
            }
        });

        Forum.setupSwatches('#postColors .color-swatch', 'postContent', '');
        loadSession().then(loadPosts);
    })();
    </script>

</body>
</html>
