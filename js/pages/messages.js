/* 留言板列表页（messages.php）
 * --------------------------------------------------------------------------
 * 原来这些逻辑内联在 messages.php 里（约 250 行 PHP 里夹着脚本）。搬出来后：
 *   - 页面 HTML 不含业务脚本，可被浏览器缓存；
 *   - 登录/退出/登录态条统一交给 js/boya-auth.js；
 *   - 接口前缀由 window.BOYA.api 提供，不再硬编码 '/api/'。
 */
(function () {
    'use strict';

    var Forum = window.BoyaForum;
    var BoyaAuth = window.BoyaAuth;
    var state = { page: 1 };

    function byId(id) {
        return document.getElementById(id);
    }

    // ---------------------------------------------------- 登录状态条
    function renderBar() {
        var session = BoyaAuth.session || {};
        var who = byId('forumWho');
        if (!who) { return; }

        if (!session.loggedIn) {
            who.innerHTML = '游客浏览中，登录后即可发帖与回复';
            byId('loginBtn').style.display = '';
            byId('logoutBtn').style.display = 'none';
            byId('newPostBtn').style.display = 'none';
            byId('needUsername').style.display = 'none';
            byId('postEditor').style.display = 'none';
            return;
        }

        who.innerHTML = '已登录：<b>' + Forum.escapeHtml(session.username || '（还没设置用户名）') + '</b>';
        byId('loginBtn').style.display = 'none';
        byId('logoutBtn').style.display = '';
        byId('newPostBtn').style.display = session.hasUsername ? '' : 'none';
        byId('needUsername').style.display = session.hasUsername ? 'none' : '';
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
        Forum.renderPager('postPager', data.page, data.totalPages, function (page) {
            state.page = page;
            loadPosts();
        });
    }

    function loadPosts() {
        byId('postList').innerHTML = '<div class="empty-message">正在加载帖子…</div>';
        Forum.fetchJson(Forum.apiUrl('forum.php') + '?action=list&page=' + state.page).then(function (res) {
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
        var session = BoyaAuth.session || {};
        var feedback = byId('postFeedback');
        var title = byId('postTitle').value.trim();
        var content = byId('postContent').value.trim();

        if (!session.loggedIn) {
            BoyaAuth.open();
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
            csrf_token: session.csrf_token || ''
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
                BoyaAuth.open();
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

    BoyaAuth.init({
        onChange: function () {
            renderBar();
        }
    }).then(loadPosts);
})();
