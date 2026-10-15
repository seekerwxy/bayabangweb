<?php
require_once __DIR__ . '/_site.php';   // boya_site_prefix()

// 同 _header.php：页脚里的站内链接要用站点根绝对路径，/user/2 这类重写网址下才不会 404。
$sitePrefix = boya_site_prefix();
?>
<footer class="footer">
    <div class="footer-container">
        <div class="footer-links">
            <!-- GitHub -->
            <a href="https://github.com/seekerwxy/bayabangweb" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="跳转至Github仓库">
                <i class="fa-brands fa-github fa-lg"></i>
            </a>
            <!-- 邮箱 -->
            <a href="javascript:void(0)" onclick="copyEmail('1708043179@qq.com')" aria-label="复制邮箱" title="复制邮箱">
                <i class="fa-solid fa-envelope fa-lg"></i>
            </a>
            <!-- Cat^_^ -->
            <a title="跳转至管理员个人主页" href="https://wangxuanyi.pages.dev" target="_blank" rel="noopener noreferrer" aria-label="管理员个人主页">
                <i class="fa-solid fa-cat fa-lg"></i>
            </a>
            <!-- 管理员 -->
            <a href="<?= $sitePrefix ?>admin-files/admin-list.php" aria-label="管理员入口" title="管理员入口">
                <i class="fa-solid fa-user-shield fa-lg"></i>
            </a>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> 25级博雅班. All rights reserved.
        </div>
    </div>
</footer>

<!-- 外观设置弹窗：右上角头像菜单 → 设置 打开 -->
<div class="settings-modal" id="settingsModal" aria-hidden="true">
    <div class="settings-modal-backdrop" id="settingsModalBackdrop"></div>
    <div class="settings-modal-card" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
        <div class="settings-modal-head">
            <h3 id="settingsTitle">外观设置</h3>
            <button class="settings-modal-close" id="settingsModalClose" type="button" aria-label="关闭设置">×</button>
        </div>
        <div class="settings-group">
            <span class="settings-label">明暗</span>
            <button class="settings-theme-btn" id="settingsThemeToggle" type="button" aria-pressed="false" title="切换深浅色">
                <i class="fa-solid fa-moon" id="settingsThemeIcon" aria-hidden="true"></i>
                <span id="settingsThemeText">浅色</span>
            </button>
        </div>
        <div class="settings-group">
            <span class="settings-label">主题</span>
            <div class="settings-style-options" id="settingsStyleOptions">
                <button type="button" class="style-option" data-style="default" role="menuitemradio" aria-checked="true">
                    <span class="style-dot style-dot-default" aria-hidden="true"></span>原版
                </button>
                <button type="button" class="style-option" data-style="gothic" role="menuitemradio" aria-checked="false">
                    <span class="style-dot style-dot-gothic" aria-hidden="true"></span>哥特
                </button>
                <button type="button" class="style-option" data-style="tech" role="menuitemradio" aria-checked="false">
                    <span class="style-dot style-dot-tech" aria-hidden="true"></span>科技
                </button>
                <button type="button" class="style-option" data-style="classical" role="menuitemradio" aria-checked="false">
                    <span class="style-dot style-dot-classical" aria-hidden="true"></span>古风
                </button>
                <button type="button" class="style-option" data-style="editorial" role="menuitemradio" aria-checked="false">
                    <span class="style-dot style-dot-editorial" aria-hidden="true"></span>编辑社
                </button>
                <button type="button" class="style-option" data-style="western" role="menuitemradio" aria-checked="false">
                    <span class="style-dot style-dot-western" aria-hidden="true"></span>西方风
                </button>
            </div>
        </div>
        <button class="settings-done" id="settingsDone" type="button">完成</button>
    </div>
</div>

<script>

    // ---------- 明暗主题切换（放进「设置」弹窗，全站共享） ----------
    (function() {
        const htmlEl = document.documentElement;
        const themeToggle = document.getElementById('settingsThemeToggle');
        const themeText = document.getElementById('settingsThemeText');
        const themeIcon = document.getElementById('settingsThemeIcon');

        function setTheme(theme) {
            htmlEl.setAttribute('data-theme', theme);
            if (themeToggle) {
                const isDark = theme === 'dark';
                themeToggle.setAttribute('aria-label', isDark ? '切换到浅色模式' : '切换到深色模式');
                themeToggle.setAttribute('title', isDark ? '切换到浅色模式' : '切换到深色模式');
                themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                if (themeIcon) {
                    themeIcon.className = 'fa-solid ' + (isDark ? 'fa-sun' : 'fa-moon');
                }
                if (themeText) {
                    themeText.textContent = isDark ? '深色' : '浅色';
                }
            }
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const current = htmlEl.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                setTheme(next);
                try {
                    localStorage.setItem('theme', next);
                } catch (err) {}
            });
        }

        // 加载时按当前状态同步一次按钮文案（_head.php 已设好 data-theme）
        setTheme(htmlEl.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
    })();

    // ---------- 网站外观切换（设置弹窗里选 + 移动端菜单保留） ----------
    (function() {
        const htmlEl = document.documentElement;
        const options = Array.prototype.slice.call(document.querySelectorAll('.style-option[data-style]'));

        function currentStyle() {
            const s = htmlEl.getAttribute('data-style');
            return ['gothic', 'tech', 'classical', 'editorial', 'western', 'default'].indexOf(s) >= 0
                ? s
                : 'default';
        }

        function syncOptions() {
            const current = currentStyle();
            options.forEach(function(opt) {
                const active = opt.getAttribute('data-style') === current;
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
        document.querySelectorAll('#settingsStyleOptions .style-option[data-style]').forEach(function(opt) {
            opt.addEventListener('click', function() {
                const style = opt.getAttribute('data-style');
                if (style) setStyle(style);
            });
        });

        // 移动端汉堡菜单里保留的外观按钮
        document.addEventListener('click', function(e) {
            const opt = e.target.closest('.nav-mobile-style .style-option');
            if (!opt) return;
            const style = opt.getAttribute('data-style');
            if (style) setStyle(style);
        });

        syncOptions();
    })();

    // ---------- 设置弹窗开/关 ----------
    (function() {
        const modal = document.getElementById('settingsModal');
        if (!modal) return;
        const backdrop = document.getElementById('settingsModalBackdrop');
        const closeBtn = document.getElementById('settingsModalClose');
        const doneBtn = document.getElementById('settingsDone');

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

        if (backdrop) backdrop.addEventListener('click', closeSettings);
        if (closeBtn) closeBtn.addEventListener('click', closeSettings);
        if (doneBtn) doneBtn.addEventListener('click', closeSettings);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) closeSettings();
        });
    })();

    // ---------- 右上角用户头像菜单 ----------
    (function() {
        const SITE_ROOT = <?= json_encode($sitePrefix, JSON_UNESCAPED_SLASHES) ?>;
        const menu = document.getElementById('userMenu');
        const btn = document.getElementById('userMenuBtn');
        const dropdown = document.getElementById('userMenuDropdown');
        const avatarBox = document.getElementById('navUserAvatar');
        const profileLink = document.getElementById('userMenuProfile');
        const settingsItem = document.getElementById('userMenuSettings');
        if (!menu || !btn || !dropdown || !avatarBox || !profileLink || !settingsItem) return;

        function esc(text) {
            return String(text === null || text === undefined ? '' : text)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function avatarInitial(session) {
            const name = (session && session.name) || (session && session.username) || '';
            return name ? name.charAt(0) : '';
        }

        function renderAvatar(session) {
            const avatar = ((session && session.avatar) || '').trim();
            const first = avatarInitial(session) || '客';
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
            const icon = profileLink.querySelector('.user-menu-icon');
            const text = profileLink.querySelector('.user-menu-text');
            if (session && session.loggedIn && session.uid > 0) {
                profileLink.href = SITE_ROOT + 'user/' + session.uid;
                if (icon) icon.className = 'fa-solid fa-id-card user-menu-icon';
                if (text) text.textContent = '个人主页';
            } else {
                // 游客没有个人主页，第一项变成登录入口
                profileLink.href = SITE_ROOT + 'admin-files/edit-profile.php';
                if (icon) icon.className = 'fa-solid fa-right-to-bracket user-menu-icon';
                if (text) text.textContent = '登录';
            }
        }

        function setOpen(open) {
            dropdown.classList.toggle('open', !!open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            setOpen(!dropdown.classList.contains('open'));
        });
        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target)) setOpen(false);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') setOpen(false);
        });

        settingsItem.addEventListener('click', function() {
            setOpen(false);
            if (typeof window.openSettings === 'function') window.openSettings();
        });

        fetch(SITE_ROOT + 'api/session.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function(res) { return res.json(); })
            .then(function(session) {
                renderAvatar(session || {});
                renderMenu(session || {});
            })
            .catch(function() {
                renderAvatar({});
                renderMenu({});
            });
    })();

// ---------- 移动端汉堡菜单（所有页面共用） ----------
    (function() {
        const btn = document.getElementById('hamburgerBtn');
        const dropdown = document.getElementById('mobileDropdown');
        if (!btn || !dropdown) return;
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('visible');
            if (isOpen) {
                dropdown.classList.remove('visible');
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            } else {
                dropdown.classList.add('visible');
                btn.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
        dropdown.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                dropdown.classList.remove('visible');
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            });
        });
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target) && event.target !== btn && !btn.contains(event.target)) {
                dropdown.classList.remove('visible');
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && dropdown.classList.contains('visible')) {
                dropdown.classList.remove('visible');
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                btn.focus();
            }
        });
    })();

    //other
    function showToast(msg) {
        const existing = document.querySelector('.toast-capsule');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'toast-capsule';
        toast.textContent = msg;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('visible');
        });

        setTimeout(() => {
            toast.classList.remove('visible');
            setTimeout(() => toast.remove(), 300);
        }, 1800);
    }

    function copyEmail(email) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(email)
                .then(() => showToast('邮箱已复制'))
                .catch(() => {}); // 失败静默
        } else {
            fallbackCopy(email);
        }
    }

    function fallbackCopy(email) {
        const textArea = document.createElement('textarea');
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

</script>
