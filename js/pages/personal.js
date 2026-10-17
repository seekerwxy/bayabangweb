/* 个人主页（personal.php，也就是 /user/{id}）
 * --------------------------------------------------------------------------
 * 原来内联在页面里。搬出来后站点前缀取自 window.BOYA.base（不再由 PHP 注入），
 * 接口前缀取自 window.BOYA.api。
 */
(function () {
    'use strict';

    var Forum = window.BoyaForum;
    var BOYA = window.BOYA || {};
    var PROFILE_BASE = Forum.siteBase();
    var byId = function (id) { return document.getElementById(id); };
    var esc = Forum.escapeHtml;

    // id 优先读查询串（/personal.php?id=2）；/user/2 这类重写网址没有查询串，
    // 只能从路径末段取（以前只读查询串，所以 /user/2 永远显示「缺少用户 id」）。
    function resolveUserId() {
        var fromQuery = Number(Forum.qs('id') || 0);
        if (fromQuery > 0) {
            return fromQuery;
        }
        var match = (window.location.pathname || '').match(/\/user\/(\d+)\/?$/);
        return match ? Number(match[1]) : 0;
    }

    var state = { id: resolveUserId(), tab: 'timeline', page: 1, user: null, stats: null };

    if (!state.id) {
        byId('userWrap').innerHTML = '<div class="empty-message">缺少用户 id，<a href="'
            + esc(PROFILE_BASE) + 'classmates.php">回到同学录</a></div>';
        return;
    }

    var TABS = [
        { key: 'timeline', label: '动态' },
        { key: 'profile', label: '资料' },
        { key: 'replies', label: '回复' }
    ];

    function displayName(user) {
        var name = (user.username || '').trim();
        return name !== '' ? name : ('同学#' + user.id);
    }

    function badgeHtml(user) {
        var html = '';
        if (user.title) {
            html += '<span class="uspace-badge">' + esc(user.title) + '</span>';
        }
        if (Number(user.is_admin) === 1) {
            html += '<span class="uspace-badge admin">管理</span>';
        }
        return html;
    }

    function headHtml(user, stats) {
        var avatar = (user.avatar || '').trim();
        var initial = esc((user.name || user.username || '?').charAt(0));
        var avatarHtml = avatar !== ''
            ? '<img src="' + esc(avatar) + '" alt="头像" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="uspace-avatar-fallback" style="display:none;">' + initial + '</span>'
            : '<span class="uspace-avatar-fallback">' + initial + '</span>';

        // 不显示「加入时间」，只留 UID 与最后活跃（加入时间对同学没意义，
        // 而且老数据是补写进去的，看着容易误会）
        var sub = 'UID ' + user.id;
        if (user.last_active_at) { sub += ' · 最后活跃 ' + Forum.formatTime(user.last_active_at); }

        return '<div class="uspace-head">'
            + '<div class="uspace-avatar">' + avatarHtml + '</div>'
            + '<div class="uspace-main">'
            + '<h1 class="uspace-name">' + esc(displayName(user))
            + (user.nickname ? ' <span class="uspace-nick">“' + esc(user.nickname) + '”</span>' : '')
            + ' ' + badgeHtml(user) + '</h1>'
            + '<p class="uspace-sub">' + esc(sub) + '</p>'
            + '<p class="uspace-stats">帖子 <b>' + (stats.post_count || 0) + '</b> · 回复 <b>' + (stats.reply_count || 0) + '</b></p>'
            + (user.quote ? '<p class="uspace-sign">' + esc(user.quote) + '</p>' : '')
            + '<div class="uspace-actions">'
            + (user.is_self ? '<a class="forum-login-btn" href="' + esc(PROFILE_BASE) + 'admin-files/edit-profile.php">编辑我的资料</a>' : '')
            + '<a class="forum-login-btn" href="' + esc(PROFILE_BASE) + 'classmates.php">同学录</a>'
            + '</div>'
            + '</div></div>';
    }

    function tabsHtml() {
        return '<div class="uspace-tabs">' + TABS.map(function (tab) {
            return '<button type="button" class="uspace-tab' + (state.tab === tab.key ? ' active' : '') + '" data-tab="' + tab.key + '">' + tab.label + '</button>';
        }).join('') + '</div>';
    }

    function profileHtml(user) {
        var rows = [
            ['真实姓名', user.name],
            ['昵称/外号', user.nickname],
            ['性别', user.gender],
            ['生日', user.birthday],
            ['家乡/出生地', user.hometown],
            ['兴趣爱好', user.hobbies],
            ['特长/技能', user.skills],
            // 座右铭就是资料上的「个性签名」，以前两个字段各存一份、主页上重复显示，
            // 现在统一只保留 quote 这一个（signature 字段已弃用，见 DEPLOY.md）。
            ['座右铭 / 个性签名', user.quote],
            ['论坛用户名', user.username],
            ['称号', user.title],
            ['联系方式', user.contact_info],
            // 同样不显示「加入时间」（见 headHtml 的说明）
            ['最后活跃', user.last_active_at]
        ];

        var html = '<div class="personal-profile-details">';
        rows.forEach(function (row) {
            var value = row[1];
            // 联系方式现在对游客也可见（需求变更），不再跳过
            var text = (value === null || value === undefined || String(value).trim() === '')
                ? '<span class="personal-empty">未填写</span>'
                : esc(String(value));
            html += '<div class="personal-detail-item">'
                + '<span class="personal-detail-label">' + row[0] + '</span>'
                + '<span class="personal-detail-value">' + text + '</span>'
                + '</div>';
        });
        html += '</div>';
        return html;
    }

    function itemHtml(item) {
        var isPost = item.kind === 'post';
        var kind = isPost
            ? '<div class="feed-kind">发布了帖子</div>'
            : '<div class="feed-kind">回复了 <b>' + esc(item.post_title || '') + '</b></div>';
        var extra = isPost
            ? '<span>回复 ' + (item.reply_count || 0) + '</span><span>浏览 ' + (item.view_count || 0) + '</span>'
            : '<span>来自《' + esc(item.post_title || '') + '》</span>';

        return '<a class="feed-item" href="' + esc(PROFILE_BASE) + 'post.php?id=' + item.post_id + '">'
            + kind
            + (isPost ? '<div class="feed-title">' + esc(item.post_title || '') + '</div>' : '')
            + '<div class="feed-body">' + esc(item.excerpt || '') + '</div>'
            + '<div class="feed-meta"><span>' + Forum.formatTime(item.created_at) + '</span>' + extra + '</div>'
            + '</a>';
    }

    function renderShell() {
        var user = state.user;
        var stats = state.stats || { post_count: 0, reply_count: 0 };
        byId('userWrap').innerHTML = '<div class="uspace">'
            + headHtml(user, stats)
            + tabsHtml()
            + '<div class="uspace-panel" id="uspacePanel"></div>'
            + '</div>';

        byId('userWrap').querySelectorAll('.uspace-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.tab = btn.dataset.tab;
                state.page = 1;
                renderShell();
                loadTab();
            });
        });
    }

    function loadTab() {
        var panel = byId('uspacePanel');
        var pager = byId('feedPager');

        if (state.tab === 'profile') {
            panel.innerHTML = profileHtml(state.user);
            pager.innerHTML = '';
            return;
        }

        panel.innerHTML = '<div class="empty-message">正在加载…</div>';
        Forum.fetchJson(Forum.apiUrl('user.php') + '?id=' + state.id + '&tab=' + state.tab + '&page=' + state.page).then(function (res) {
            if (!res.ok) {
                panel.innerHTML = '<div class="empty-message">' + esc(res.data.message || '加载失败') + '</div>';
                pager.innerHTML = '';
                return;
            }
            var items = res.data.items || [];
            if (!items.length) {
                panel.innerHTML = '<div class="empty-message">'
                    + (state.tab === 'replies' ? '还没有回复过帖子' : '还没有发过帖子')
                    + '</div>';
                pager.innerHTML = '';
                return;
            }
            panel.innerHTML = items.map(itemHtml).join('');

            Forum.renderPager('feedPager', res.data.page, res.data.totalPages, function (page) {
                state.page = page;
                loadTab();
            });
        });
    }

    Forum.fetchJson(Forum.apiUrl('user.php') + '?id=' + state.id).then(function (res) {
        if (!res.ok) {
            byId('userWrap').innerHTML = '<div class="empty-message">' + esc(res.data.message || '加载失败') + '</div>';
            return;
        }
        state.user = res.data.user;
        state.stats = res.data.stats;
        document.title = displayName(state.user) + ' 的个人主页 | 温二外25级博雅班';
        renderShell();
        loadTab();
    });
})();
