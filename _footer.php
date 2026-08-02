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
            <a href="admin-files/admin-list.php" aria-label="管理员入口" title="管理员入口">
                <i class="fa-solid fa-user-shield fa-lg"></i>
            </a>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> 25级博雅班. All rights reserved.
        </div>
    </div>
</footer>

<script>

    // ---------- 明暗主题切换（全站共享） ----------
    (function() {
        const htmlEl = document.documentElement;
        const themeToggle = document.getElementById('themeToggle');

        function setTheme(theme) {
            htmlEl.setAttribute('data-theme', theme);
            if (themeToggle) {
                themeToggle.setAttribute('aria-label', theme === 'dark' ? '切换到浅色模式' : '切换到深色模式');
                themeToggle.setAttribute('title', theme === 'dark' ? '切换到浅色模式' : '切换到深色模式');
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
    })();

    // ---------- 网站外观切换（原版 / 哥特 / 科技 / 古风 / 编辑社 / 西方风） ----------
    (function() {
        const htmlEl = document.documentElement;
        const switchRoot = document.getElementById('styleSwitch');
        const switchBtn = document.getElementById('styleSwitchBtn');
        const switchMenu = document.getElementById('styleSwitchMenu');
        const options = Array.prototype.slice.call(document.querySelectorAll('.style-option[data-style]'));

        function syncOptions() {
            const current = htmlEl.getAttribute('data-style') === 'western'
                ? 'western'
                : (htmlEl.getAttribute('data-style') === 'editorial'
                    ? 'editorial'
                    : (htmlEl.getAttribute('data-style') === 'classical'
                        ? 'classical'
                        : (htmlEl.getAttribute('data-style') === 'tech'
                            ? 'tech'
                            : (htmlEl.getAttribute('data-style') === 'gothic' ? 'gothic' : 'default'))));
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

        function closeMenu() {
            if (switchMenu) switchMenu.classList.remove('open');
            if (switchBtn) switchBtn.setAttribute('aria-expanded', 'false');
        }

        if (switchBtn && switchMenu) {
            switchBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = switchMenu.classList.toggle('open');
                switchBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
            switchMenu.addEventListener('click', function(e) {
                const opt = e.target.closest('.style-option');
                if (!opt) return;
                const style = opt.getAttribute('data-style');
                if (style) setStyle(style);
                closeMenu();
            });
            document.addEventListener('click', function(e) {
                if (switchRoot && !switchRoot.contains(e.target)) closeMenu();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && switchMenu.classList.contains('open')) {
                    closeMenu();
                    switchBtn.focus();
                }
            });
        }

        document.addEventListener('click', function(e) {
            const opt = e.target.closest('.nav-mobile-style .style-option');
            if (!opt) return;
            const style = opt.getAttribute('data-style');
            if (style) setStyle(style);
        });

        syncOptions();
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
