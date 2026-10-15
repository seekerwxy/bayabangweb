<?php
require_once __DIR__ . '/_site.php';   // boya_site_prefix()：本文件在 include _head.php 之前就要用
$currentPage = 'classmates';
// 个人主页有两个网址：/personal.php?id=2 和 /user/2（.htaccess 重写）。
// 前缀统一由 boya_site_prefix() 计算：它保证以单个 / 开头、单个 / 结尾
// （直接用 htmlspecialchars(dirname(...)) 在线上会得到空串，前缀变成 "//"，
//  而 '//' + 'post.php?id=1' 是协议相对地址，浏览器会跳到 https://post.php/?id=1）。
$sitePrefix = boya_site_prefix();
?>
<!DOCTYPE html>
<html lang="zh-CN" data-user-link-base="<?= $sitePrefix ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人主页 | 温二外25级博雅班</title>
    <meta name="description" content="博雅班同学的个人主页：资料、发帖与回复动态。">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php include __DIR__ . '/_head.php'; ?>
    <link rel="stylesheet" href="<?= $sitePrefix ?>css/forum.css?v=<?= filemtime(__DIR__ . '/css/forum.css') ?>">
</head>
<body>

    <?php include '_header.php'; ?>

    <main class="main">
        <div id="userWrap">
            <div class="empty-message">正在加载…</div>
        </div>
        <div class="forum-pager" id="feedPager"></div>

        <div class="uspace-tip">
            <a href="<?= $sitePrefix ?>classmates.php">← 返回同学录</a>
            <span>·</span>
            <a href="<?= $sitePrefix ?>messages.php">去留言板 →</a>
        </div>
    </main>

    <?php include '_footer.php'; ?>

    <script src="<?= $sitePrefix ?>js/forum.js?v=<?= filemtime(__DIR__ . '/js/forum.js') ?>"></script>
    <script>
    (function () {
        "use strict";

        var Forum = window.BoyaForum;
        var profileBase = <?= json_encode($sitePrefix, JSON_UNESCAPED_SLASHES) ?>;
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
            byId('userWrap').innerHTML = '<div class="empty-message">缺少用户 id，<a href="' + esc(profileBase) + 'classmates.php">回到同学录</a></div>';
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

            var sub = 'UID ' + user.id;
            if (user.created_at) { sub += ' · 加入于 ' + Forum.formatTime(user.created_at); }
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
                + (user.is_self ? '<a class="forum-login-btn" href="' + esc(profileBase) + 'admin-files/edit-profile.php">编辑我的资料</a>' : '')
                + '<a class="forum-login-btn" href="' + esc(profileBase) + 'classmates.php">同学录</a>'
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
                ['加入时间', user.created_at],
                ['最后活跃', user.last_active_at]
            ];

            var html = '<div class="personal-profile-details">';
            rows.forEach(function (row) {
                var label = row[0];
                var value = row[1];
                if (label === '联系方式' && !user.is_self) {
                    return;   // 本人之外不显示联系方式
                }
                var text = (value === null || value === undefined || String(value).trim() === '')
                    ? '<span class="personal-empty">未填写</span>'
                    : esc(String(value));
                html += '<div class="personal-detail-item">'
                    + '<span class="personal-detail-label">' + label + '</span>'
                    + '<span class="personal-detail-value">' + text + '</span>'
                    + '</div>';
            });
            html += '</div>';
            if (!user.is_self) {
                html += '<p class="forum-hint" style="margin-top:12px;">联系方式只有本人登录后可见。</p>';
            }
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

            return '<a class="feed-item" href="' + esc(profileBase) + 'post.php?id=' + item.post_id + '">'
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
            Forum.fetchJson('/api/user.php?id=' + state.id + '&tab=' + state.tab + '&page=' + state.page).then(function (res) {
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

                if (res.data.totalPages > 1) {
                    pager.innerHTML = ''
                        + '<button type="button" data-page="' + (res.data.page - 1) + '"' + (res.data.page <= 1 ? ' disabled' : '') + '>上一页</button>'
                        + '<span>第 ' + res.data.page + ' / ' + res.data.totalPages + ' 页</span>'
                        + '<button type="button" data-page="' + (res.data.page + 1) + '"' + (res.data.page >= res.data.totalPages ? ' disabled' : '') + '>下一页</button>';
                    pager.querySelectorAll('button[data-page]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            state.page = Number(btn.dataset.page);
                            loadTab();
                        });
                    });
                } else {
                    pager.innerHTML = '';
                }
            });
        }

        Forum.fetchJson('/api/user.php?id=' + state.id).then(function (res) {
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
    </script>

</body>
</html>
