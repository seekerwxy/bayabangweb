<link rel="stylesheet" href="css/beauty.css">
<footer class="footer">
    <div class="footer-container">
        <div class="footer-links">
            <!-- GitHub -->
            <a href="https://github.com/seekerwxy/bayabangweb" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
                <i class="fa-brands fa-github"></i>
            </a>
            <!-- 邮箱 -->
            <a href="javascript:void(0)" onclick="copyEmail('1708043179@qq.com')" aria-label="复制邮箱">
                <i class="fa-regular fa-envelope"></i>
            </a>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> 25级博雅班. All rights reserved.
        </div>
    </div>
</footer>

<script>

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
