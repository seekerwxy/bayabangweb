/* 同学录（classmates.php）
 * --------------------------------------------------------------------------
 * 原来内联在页面里。搬出来后用统一的 escapeHtml（跟留言板同一套写法，
 * 不再用 document.createElement 那种更慢的做法），接口前缀取自 window.BOYA.api。
 */
(function () {
    'use strict';

    var BOYA = window.BOYA || {};
    var API = (BOYA.api || '/api/') + 'classmates.php';
    var PROFILE_BASE = BOYA.base || '/';

    // 统一的转义实现（js/boya-ui.js 里定义，避免每个页面各写一份）
    var esc = window.Boya.escapeHtml;

    function formatBirthday(dateStr) {
        if (!dateStr) { return ''; }
        var date = new Date(dateStr);
        if (isNaN(date.getTime())) { return ''; }
        return (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    function avatarHtml(member) {
        var avatar = String(member.avatar || '').trim();
        var placeholder = esc(String(member.name || '').charAt(0));
        if (avatar !== '' && avatar !== 'null') {
            return '<div class="member-avatar">'
                + '<img src="' + esc(avatar) + '" alt="' + esc(member.name) + '" loading="lazy"'
                + ' onload="this.classList.add(\'loaded\')"'
                + ' onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">'
                + '<span class="member-avatar-placeholder" style="display:none;">' + placeholder + '</span>'
                + '</div>';
        }
        return '<div class="member-avatar"><span class="member-avatar-placeholder">' + placeholder + '</span></div>';
    }

    function renderClassmates(classmates) {
        var grid = document.getElementById('membersGrid');
        if (!classmates.length) {
            grid.innerHTML = '<div class="empty-message">还没有同学信息，快让大家都来完善吧~</div>';
            return;
        }
        grid.innerHTML = classmates.map(function (member) {
            var nicknameHtml = member.nickname ? '<div class="member-nickname">' + esc(member.nickname) + '</div>' : '';
            return '<a href="' + esc(PROFILE_BASE) + 'personal.php?id=' + member.id + '" class="member-link">'
                + '<div class="member-card">'
                + avatarHtml(member)
                + '<div class="member-name">' + esc(member.name) + '</div>'
                + nicknameHtml
                + '<div class="member-birthday">' + (formatBirthday(member.birthday) || '🎂') + '</div>'
                + '<div class="member-quote">' + esc(member.quote || '') + '</div>'
                + '</div></a>';
        }).join('');
    }

    fetch(API, { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(renderClassmates)
        .catch(function () {
            document.getElementById('membersGrid').innerHTML = '<div class="empty-message">加载失败，请刷新重试</div>';
        });
})();
