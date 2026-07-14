<footer class="footer">
    <span>献给25级博雅班&qkj</span>
    <span class="footer-line">— 我已燃尽 —</span>
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
</script>