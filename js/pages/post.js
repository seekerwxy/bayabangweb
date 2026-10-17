/* 帖子详情页（post.php）
 * --------------------------------------------------------------------------
 * 原来内联在 post.php 里。搬出来后页面只留结构，登录/退出交给 js/boya-auth.js，
 * 接口前缀由 window.BOYA.api 提供。
 */
(function () {
    'use strict';

    var Forum = window.BoyaForum;
    var BoyaAuth = window.BoyaAuth;
    var state = {
        id: Number(Forum.qs('id') || 0),
        page: Number(Forum.qs('page') || 1),
        myId: 0,
        locked: false
    };

    function byId(id) {
        return document.getElementById(id);
    }

    if (!state.id) {
        byId('postWrap').innerHTML = '<div class="empty-message">缺少帖子 id，<a href="'
            + Forum.siteBase() + 'messages.php">回到列表</a></div>';
        return;
    }

    // ---------------------------------------------------- 登录状态
    function renderBar() {
        var session = BoyaAuth.session || {};
        var who = byId('forumWho');
        if (!who) { return; }

        if (!session.loggedIn) {
            who.innerHTML = '游客浏览中，登录后即可回复';
            byId('loginBtn').style.display = '';
            byId('logoutBtn').style.display = 'none';
            byId('needUsername').style.display = 'none';
            byId('replyBox').style.display = 'none';
            return;
        }
        who.innerHTML = '已登录：<b>' + Forum.escapeHtml(session.username || '（还没设置用户名）') + '</b>';
        byId('loginBtn').style.display = 'none';
        byId('logoutBtn').style.display = '';
        byId('needUsername').style.display = session.hasUsername ? 'none' : '';
        byId('replyBox').style.display = (session.hasUsername && !state.locked) ? '' : 'none';
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
        Forum.renderPager('replyPager', data.page, data.totalPages, function (page) {
            state.page = page;
            loadPost(true);
        });

        // 删除按钮
        var delPost = byId('postWrap').querySelector('button[data-delete-post]');
        if (delPost) {
            delPost.addEventListener('click', function () {
                if (!confirm('确定删除这个帖子吗？')) { return; }
                deleteOwn('post', Number(delPost.dataset.deletePost), function () {
                    Forum.toast('帖子已删除');
                    window.location.href = Forum.siteBase() + 'messages.php';
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
        var session = BoyaAuth.session || {};
        Forum.moderate(action, type, id, session.admin_csrf_token || session.csrf_token || '').then(function (res) {
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
        Forum.fetchJson(Forum.apiUrl('forum.php') + '?action=post&id=' + state.id + '&page=' + state.page).then(function (res) {
            if (!res.ok) {
                wrap.innerHTML = '<div class="empty-message">'
                    + Forum.escapeHtml(res.data.message || '加载失败')
                    + ' <a href="' + Forum.siteBase() + 'messages.php">回到列表</a></div>';
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
        var session = BoyaAuth.session || {};
        Forum.send({
            action: 'delete-own',
            type: type,
            id: id,
            csrf_token: session.csrf_token || ''
        }).then(function (res) {
            if (res.ok) {
                onDone();
                return;
            }
            if (res.data.needLogin) { BoyaAuth.open(); return; }
            Forum.toast(res.data.message || '删除失败');
        }).catch(function () {
            Forum.toast('网络错误，删除失败');
        });
    }

    // ---------------------------------------------------- 回复
    byId('replyBtn').addEventListener('click', function () {
        var session = BoyaAuth.session || {};
        var feedback = byId('replyFeedback');
        var content = byId('replyContent').value.trim();

        if (!session.loggedIn) { BoyaAuth.open(); return; }
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
            csrf_token: session.csrf_token || ''
        }).then(function (res) {
            if (res.ok) {
                byId('replyContent').value = '';
                Forum.toast('回复成功');
                loadPost();
                return;
            }
            if (res.data.needUsername) { byId('needUsername').style.display = ''; }
            if (res.data.needLogin) { BoyaAuth.open(); return; }
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

    // ---------------------------------------------------- 初始化
    BoyaAuth.init({
        onChange: function () {
            renderBar();
        }
    }).then(function () {
        loadPost();
    });
})();
