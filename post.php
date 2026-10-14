<?php $currentPage = 'messages'; ?>
<!DOCTYPE html>
<html lang="zh-CN" data-user-link-base="<?= htmlspecialchars(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), ENT_QUOTES, 'UTF-8') ?>/">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>帖子 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班留言板帖子详情与回复。">
    <meta name="robots" content="noindex, follow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php include __DIR__ . '/_head.php'; ?>
    <link rel="stylesheet" href="css/forum.css?v=<?= filemtime(__DIR__ . '/css/forum.css') ?>">
    <script src="js/vendor/marked.min.js?v=<?= filemtime(__DIR__ . '/js/vendor/marked.min.js') ?>"></script>
    <script src="js/vendor/purify.min.js?v=<?= filemtime(__DIR__ . '/js/vendor/purify.min.js') ?>"></script>
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <div class="forum-bar">
            <div class="forum-who" id="forumWho">正在读取登录状态…</div>
            <div class="forum-bar-actions">
                <a class="forum-login-btn" href="/messages.php">← 返回列表</a>
                <button class="forum-login-btn" id="loginBtn" type="button" style="display:none;">登录后回复</button>
                <button class="forum-cancel-btn" id="logoutBtn" type="button" style="display:none;">退出</button>
            </div>
        </div>

        <div class="forum-notice" id="needUsername" style="display:none;">
            你还没有设置<b>论坛用户名</b>（论坛上只显示用户名，不显示真实姓名）。
            <a href="admin-files/edit-profile.php">去设置 →</a>
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
        var state = { id: Number(Forum.qs('id') || 0), page: Number(Forum.qs('page') || 1), session: null, myId: 0, locked: false };
        var byId = function (id) { return document.getElementById(id); };

        if (!state.id) {
            byId('postWrap').innerHTML = '<div class="empty-message">缺少帖子 id，<a href="/messages.php">回到列表</a></div>';
            return;
        }

        // ---------------------------------------------------- 登录状态
        function renderBar() {
            var s = state.session || {};
            var who = byId('forumWho');
            if (!s.loggedIn) {
                who.innerHTML = '游客浏览中，登录后即可回复';
                byId('loginBtn').style.display = '';
                byId('logoutBtn').style.display = 'none';
                byId('needUsername').style.display = 'none';
                byId('replyBox').style.display = 'none';
                return;
            }
            who.innerHTML = '已登录：<b>' + Forum.escapeHtml(s.username || '（还没设置用户名）') + '</b>';
            byId('loginBtn').style.display = 'none';
            byId('logoutBtn').style.display = '';
            byId('needUsername').style.display = s.hasUsername ? 'none' : '';
            byId('replyBox').style.display = (s.hasUsername && !state.locked) ? '' : 'none';
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

        // ---------------------------------------------------- 帖子与回复
        function render(data) {
            state.myId = Number(data.myId || 0);
            state.locked = Number(data.post.is_locked) === 1;

            var badges = [];
            if (data.post.is_pinned) { badges.push('<span class="badge-forum pin">置顶</span>'); }
            if (data.post.is_locked) { badges.push('<span class="badge-forum lock">已锁定</span>'); }
            if (data.post.is_history) { badges.push('<span class="badge-forum history">历史留言</span>'); }

            var canDeletePost = state.myId && state.myId === Number(data.post.author_id);

            var html = '<article class="forum-post">'
                + '<h1>' + Forum.escapeHtml(data.post.title) + '</h1>'
                + '<div class="forum-post-meta">'
                + badges.join(' ')
                + Forum.authorLine(data.post)
                + '<span>' + Forum.formatTime(data.post.created_at) + '</span>'
                + '<span>回复 ' + data.post.reply_count + '</span>'
                + '<span>浏览 ' + data.post.view_count + '</span>'
                + (canDeletePost ? '<button class="forum-del-btn" data-delete-post="' + data.post.id + '" type="button">删除我的帖子</button>' : '')
                + (data.post.can_moderate
                    ? '<button class="mod-btn" data-mod="' + (data.post.is_pinned ? 'unpin' : 'pin') + '" data-id="' + data.post.id + '" type="button">' + (data.post.is_pinned ? '取消置顶' : '置顶') + '</button>'
                      + '<button class="mod-btn" data-mod="' + (data.post.is_locked ? 'unlock' : 'lock') + '" data-id="' + data.post.id + '" type="button">' + (data.post.is_locked ? '解锁' : '锁定') + '</button>'
                      + '<button class="mod-btn danger" data-mod="delete" data-id="' + data.post.id + '" type="button">删除帖子</button>'
                    : '')
                + '</div>'
                + '<div class="forum-body">' + Forum.richText(data.post.content) + '</div>'
                + '</article>';

            html += '<div class="forum-replies" id="replyList">';
            if (!data.replies.length) {
                html += '<div class="empty-message">还没有回复，来抢沙发吧</div>';
            } else {
                html += data.replies.map(function (reply) {
                    var canDelete = state.myId && state.myId === Number(reply.author_id);
                    var actions = '';
                    if (canDelete) {
                        actions += '<button class="forum-del-btn" data-delete-reply="' + reply.id + '" type="button">删除我的回复</button>';
                    }
                    if (data.post.can_moderate) {
                        actions += '<button class="mod-btn danger" data-mod="delete" data-type="reply" data-id="' + reply.id + '" type="button">管理删除</button>';
                    }
                    return '<div class="forum-reply">'
                        + '<div class="forum-reply-meta">'
                        + '<span class="forum-floor">' + reply.floor + ' 楼</span>'
                        + Forum.authorLine(reply)
                        + '<span>' + Forum.formatTime(reply.created_at) + '</span>'
                        + '</div>'
                        + '<div class="forum-body">' + Forum.richText(reply.content) + '</div>'
                        + (actions ? '<div class="forum-reply-actions">' + actions + '</div>' : '')
                        + '</div>';
                }).join('');
            }
            html += '</div>';

            byId('postWrap').innerHTML = html;

            // 分页
            var pager = byId('replyPager');
            if (data.totalPages <= 1) {
                pager.innerHTML = '';
            } else {
                pager.innerHTML = ''
                    + '<button type="button" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>上一页</button>'
                    + '<span>第 ' + data.page + ' / ' + data.totalPages + ' 页</span>'
                    + '<button type="button" data-page="' + (data.page + 1) + '"' + (data.page >= data.totalPages ? ' disabled' : '') + '>下一页</button>';
                pager.querySelectorAll('button[data-page]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        state.page = Number(btn.dataset.page);
                        loadPost(true);
                    });
                });
            }

            // 删除按钮
            var delPost = byId('postWrap').querySelector('button[data-delete-post]');
            if (delPost) {
                delPost.addEventListener('click', function () {
                    if (!confirm('确定删除这个帖子吗？')) { return; }
                    deleteOwn('post', Number(delPost.dataset.deletePost), function () {
                        Forum.toast('帖子已删除');
                        window.location.href = '/messages.php';
                    });
                });
            }
            byId('postWrap').querySelectorAll('button[data-delete-reply]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm('确定删除这条回复吗？')) { return; }
                    deleteOwn('reply', Number(btn.dataset.deleteReply), function () {
                        Forum.toast('回复已删除');
                        loadPost();
                    });
                });
            });

            // 管理按钮（置顶 / 锁定 / 删除，需要管理权限）
            byId('postWrap').querySelectorAll('button[data-mod]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var action = btn.dataset.mod;
                    var type = btn.dataset.type || 'post';
                    var targetId = Number(btn.dataset.id);
                    if (action === 'delete' && !confirm('确定删除？管理员删除后可在后台恢复。')) { return; }
                    moderate(action, type, targetId);
                });
            });

            renderBar();
        }

        function moderate(action, type, id) {
            var s = state.session || {};
            Forum.moderate(action, type, id, s.admin_csrf_token || s.csrf_token || '').then(function (res) {
                Forum.toast(res.data.message || (res.ok ? '操作完成' : '操作失败'));
                if (res.ok) {
                    loadPost(true);
                }
            }).catch(function () {
                Forum.toast('网络错误，操作失败');
            });
        }

        function loadPost(keepScroll) {
            var scrollTop = window.scrollY;
            var wrap = byId('postWrap');
            if (!keepScroll) {
                wrap.innerHTML = '<div class="empty-message">正在加载帖子…</div>';
            }
            Forum.fetchJson('/api/forum.php?action=post&id=' + state.id + '&page=' + state.page).then(function (res) {
                if (!res.ok) {
                    wrap.innerHTML = '<div class="empty-message">'
                        + Forum.escapeHtml(res.data.message || '加载失败')
                        + ' <a href="/messages.php">回到列表</a></div>';
                    byId('replyPager').innerHTML = '';
                    byId('replyBox').style.display = 'none';
                    return;
                }
                render(res.data);
                if (keepScroll) {
                    window.scrollTo(0, scrollTop);
                }
            });
        }

        function deleteOwn(type, id, onDone) {
            var s = state.session || {};
            Forum.send({
                action: 'delete-own',
                type: type,
                id: id,
                csrf_token: s.csrf_token || ''
            }).then(function (res) {
                if (res.ok) {
                    onDone();
                    return;
                }
                if (res.data.needLogin) { openLogin(); return; }
                Forum.toast(res.data.message || '删除失败');
            }).catch(function () {
                Forum.toast('网络错误，删除失败');
            });
        }

        // ---------------------------------------------------- 回复
        byId('replyBtn').addEventListener('click', function () {
            var s = state.session || {};
            var feedback = byId('replyFeedback');
            var content = byId('replyContent').value.trim();

            if (!s.loggedIn) { openLogin(); return; }
            if (!content) {
                feedback.textContent = '回复内容不能为空';
                feedback.className = 'form-feedback error';
                return;
            }

            var btn = this;
            btn.disabled = true;
            btn.textContent = '发送中…';
            feedback.textContent = '';

            Forum.send({
                action: 'create-reply',
                post_id: state.id,
                content: content,
                csrf_token: s.csrf_token || ''
            }).then(function (res) {
                if (res.ok) {
                    byId('replyContent').value = '';
                    Forum.toast('回复成功');
                    loadPost();
                    return;
                }
                if (res.data.needUsername) { byId('needUsername').style.display = ''; }
                if (res.data.needLogin) { openLogin(); return; }
                feedback.textContent = res.data.message || '回复失败，请稍后重试';
                feedback.className = 'form-feedback error';
            }).catch(function () {
                feedback.textContent = '网络错误，回复失败';
                feedback.className = 'form-feedback error';
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = '发表回复';
            });
        });

        // ---------------------------------------------------- 登录
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
            if (event.target === byId('loginModal')) { closeLogin(); }
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
                try { localStorage.setItem('profile_name', name); } catch (e) {}
                closeLogin();
                Forum.toast('登录成功');
                loadSession();
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
                loadSession();
            });
        });

        // ---------------------------------------------------- 初始化
        loadSession().then(loadPost);
    })();
    </script>

</body>
</html>
