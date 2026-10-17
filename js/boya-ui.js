/* 全站前台交互（_footer.php 里原来内联的 4 段脚本）
 * --------------------------------------------------------------------------
 * 包含：明暗切换、外观（主题）切换、设置弹窗、右上角用户菜单、移动端汉堡菜单、
 * 顶部胶囊提示、复制邮箱。
 *
 * 以前这些全部内联在 _footer.php 里（331 行 PHP 里混着 4 段脚本），
 * 现在拆出来成为可缓存的静态文件；_footer.php 只剩页脚与弹窗的结构。
 *
 * 依赖 _head.php 输出的 window.BOYA = { base: '/', api: '/api/' }（缺失时按站点根兜底）。
 */
(function () {
    'use strict';

    var BOYA = window.BOYA || {};
    var SITE_BASE = (function () {
        var base = String(BOYA.base || '/');
        return base.replace(/\/+$/, '') + '/';
    })();
    var API_BASE = String(BOYA.api || (SITE_BASE + 'api/')).replace(/\/+$/, '') + '/';

    function byId(id) {
        return document.getElementById(id);
    }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---------------------------------------------------------------- 顶部提示
    function showToast(msg) {
        var existing = document.querySelector('.toast-capsule');
        if (existing) { existing.remove(); }

        var toast = document.createElement('div');
        toast.className = 'toast-capsule';
        toast.textContent = msg;
        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('visible');
        });

        setTimeout(function () {
            toast.classList.remove('visible');
            setTimeout(function () { toast.remove(); }, 300);
        }, 1800);
    }

    // ---------------------------------------------------------------- 复制邮箱
    function fallbackCopy(email) {
        var textArea = document.createElement('textarea');
        textArea.value = email;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        textArea.style.left = '-9999px';
        textArea.style.top = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            if (document.execCommand('copy')) {
                showToast('邮箱已复制');
            }
        } catch (err) {}
        document.body.removeChild(textArea);
    }

    function copyEmail(email) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(email)
                .then(function () { showToast('邮箱已复制'); })
                .catch(function () {});   // 失败静默
        } else {
            fallbackCopy(email);
        }
    }

    // ---------------------------------------------------------------- 明暗主题
    (function () {
        var htmlEl = document.documentElement;
        var toggle = byId('settingsThemeToggle');
        var text = byId('settingsThemeText');
        var icon = byId('settingsThemeIcon');

        function setTheme(theme) {
            htmlEl.setAttribute('data-theme', theme);
            if (!toggle) { return; }
            var isDark = theme === 'dark';
            toggle.setAttribute('aria-label', isDark ? '切换到浅色模式' : '切换到深色模式');
            toggle.setAttribute('title', isDark ? '切换到浅色模式' : '切换到深色模式');
            toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            if (icon) { icon.className = 'fa-solid ' + (isDark ? 'fa-sun' : 'fa-moon'); }
            if (text) { text.textContent = isDark ? '深色' : '浅色'; }
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                var current = htmlEl.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                setTheme(next);
                try { localStorage.setItem('theme', next); } catch (err) {}
            });
        }

        // 加载时按当前状态同步一次按钮文案（_head.php 已设好 data-theme）
        setTheme(htmlEl.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
    })();

    // ---------------------------------------------------------------- 外观（主题）切换
    (function () {
        var htmlEl = document.documentElement;
        var options = Array.prototype.slice.call(document.querySelectorAll('.style-option[data-style]'));

        function currentStyle() {
            var value = htmlEl.getAttribute('data-style');
            var valid = ['gothic', 'tech', 'classical', 'editorial', 'western', 'default'];
            return valid.indexOf(value) >= 0 ? value : 'default';
        }

        function syncOptions() {
            var current = currentStyle();
            options.forEach(function (opt) {
                var active = opt.getAttribute('data-style') === current;
                opt.classList.toggle('active', active);
                if (opt.getAttribute('role') === 'menuitemradio') {
                    opt.setAttribute('aria-checked', active ? 'true' : 'false');
                }
            });
        }

        function setStyle(style) {
            htmlEl.setAttribute('data-style', style);
            try { localStorage.setItem('boya-style', style); } catch (err) {}
            syncOptions();
        }

        // 设置弹窗里的主题选项
        Array.prototype.forEach.call(
            document.querySelectorAll('#settingsStyleOptions .style-option[data-style]'),
            function (opt) {
                opt.addEventListener('click', function () {
                    var style = opt.getAttribute('data-style');
                    if (style) { setStyle(style); }
                });
            }
        );

        // 移动端汉堡菜单里保留的外观按钮
        document.addEventListener('click', function (event) {
            var opt = event.target.closest('.nav-mobile-style .style-option');
            if (!opt) { return; }
            var style = opt.getAttribute('data-style');
            if (style) { setStyle(style); }
        });

        syncOptions();
    })();

    // ---------------------------------------------------------------- 设置弹窗
    (function () {
        var modal = byId('settingsModal');
        if (!modal) { return; }
        var backdrop = byId('settingsModalBackdrop');
        var closeBtn = byId('settingsModalClose');
        var doneBtn = byId('settingsDone');

        function openSettings() {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeSettings() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        window.openSettings = openSettings;
        window.closeSettings = closeSettings;

        if (backdrop) { backdrop.addEventListener('click', closeSettings); }
        if (closeBtn) { closeBtn.addEventListener('click', closeSettings); }
        if (doneBtn) { doneBtn.addEventListener('click', closeSettings); }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('open')) { closeSettings(); }
        });
    })();

    // ---------------------------------------------------------------- 右上角用户菜单
    (function () {
        var menu = byId('userMenu');
        var btn = byId('userMenuBtn');
        var dropdown = byId('userMenuDropdown');
        var avatarBox = byId('navUserAvatar');
        var profileLink = byId('userMenuProfile');
        var settingsItem = byId('userMenuSettings');
        if (!menu || !btn || !dropdown || !avatarBox || !profileLink || !settingsItem) { return; }

        function avatarInitial(session) {
            var name = (session && session.name) || (session && session.username) || '';
            return name ? name.charAt(0) : '';
        }

        function renderAvatar(session) {
            var avatar = String((session && session.avatar) || '').trim();
            var first = avatarInitial(session) || '客';
            if (session && session.loggedIn && avatar) {
                // 头像加载失败时显示姓氏占位
                avatarBox.innerHTML =
                    '<img class="nav-user-avatar-img" src="' + esc(avatar) + '" alt="头像" ' +
                    'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">' +
                    '<span class="nav-user-avatar-fallback" style="display:none;">' + esc(first) + '</span>';
            } else if (session && session.loggedIn) {
                avatarBox.innerHTML = '<span class="nav-user-avatar-fallback">' + esc(first) + '</span>';
            } else {
                avatarBox.innerHTML = '<i class="fa-solid fa-user" aria-hidden="true"></i>';
            }
        }

        function renderMenu(session) {
            var icon = profileLink.querySelector('.user-menu-icon');
            var text = profileLink.querySelector('.user-menu-text');
            if (session && session.loggedIn && session.uid > 0) {
                profileLink.href = SITE_BASE + 'user/' + session.uid;
                if (icon) { icon.className = 'fa-solid fa-id-card user-menu-icon'; }
                if (text) { text.textContent = '个人主页'; }
            } else {
                // 游客没有个人主页，第一项变成登录入口
                profileLink.href = SITE_BASE + 'admin-files/edit-profile.php';
                if (icon) { icon.className = 'fa-solid fa-right-to-bracket user-menu-icon'; }
                if (text) { text.textContent = '登录'; }
            }
        }

        function setOpen(open) {
            dropdown.classList.toggle('open', !!open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            setOpen(!dropdown.classList.contains('open'));
        });
        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) { setOpen(false); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { setOpen(false); }
        });

        settingsItem.addEventListener('click', function () {
            setOpen(false);
            if (typeof window.openSettings === 'function') { window.openSettings(); }
        });

        fetch(API_BASE + 'session.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (session) {
                renderAvatar(session || {});
                renderMenu(session || {});
            })
            .catch(function () {
                renderAvatar({});
                renderMenu({});
            });
    })();

    // ---------------------------------------------------------------- 移动端汉堡菜单
    (function () {
        var btn = byId('hamburgerBtn');
        var dropdown = byId('mobileDropdown');
        if (!btn || !dropdown) { return; }

        function close() {
            dropdown.classList.remove('visible');
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = dropdown.classList.contains('visible');
            if (isOpen) {
                close();
            } else {
                dropdown.classList.add('visible');
                btn.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });

        Array.prototype.forEach.call(dropdown.querySelectorAll('a'), function (link) {
            link.addEventListener('click', close);
        });

        document.addEventListener('click', function (event) {
            if (!dropdown.contains(event.target) && event.target !== btn && !btn.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && dropdown.classList.contains('visible')) {
                close();
                btn.focus();
            }
        });
    })();

    // 供其它脚本使用（forum.js 的 toast 会优先复用 window.showToast；
    // 页面脚本统一用 window.Boya.escapeHtml 转义，别再各写一份）
    window.Boya = { base: SITE_BASE, api: API_BASE, escapeHtml: esc };
    window.showToast = showToast;
    window.copyEmail = copyEmail;
})();
