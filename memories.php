<?php $currentPage = 'memories'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博雅班时光回忆馆</title>
    <meta name="description" content="博雅班时光回忆馆，记录班级的成长历程和美好回忆。">
    <link rel="stylesheet" href="css/beauty.css">
</head>
<body>

    <main class="main">
        <section class="page-header">
            <div class="badge">Memory Lane</div>
            <h2>时光回忆馆</h2>
        </section>

        <!-- 时光轴 -->
        <div class="timeline-section">
            <h3 class="section-title">班级时光轴</h3>
            <p class="section-sub">从初遇到现在，我们一起走过的每一步</p>
            <div class="timeline" id="timelineContainer">
                <!-- 数据将由 JavaScript 动态生成 -->
                <div class="empty-message">加载中...</div>
            </div>
        </div>

        <!-- 相册（保留，暂不启用） -->
        <div class="gallery-section">
            <!-- 注释掉内容，保留占位 -->
        </div>

        <!-- 拔牙帮天数 -->
        <div class="days-counter">
            <p class="label">拔牙帮至今已</p>
            <p id="daysCounter" class="number">0</p>
            <p class="unit">天</p>
        </div>
    </main>

    <script>
        // ---------- 图片加载处理（保留原有） ----------
        function initImages() {
            const images = document.querySelectorAll('.timeline-photo img, .gallery-item img');
            images.forEach(img => {
                const container = img.closest('.timeline-photo') || img.closest('.gallery-item');
                if (!img.getAttribute('src') || img.getAttribute('src').trim() === '') {
                    if (container) container.style.display = 'none';
                    return;
                }
                img.addEventListener('load', () => {
                    img.classList.add('loaded');
                    if (container) container.style.display = '';
                });
                img.addEventListener('error', () => {
                    if (container) container.style.display = 'none';
                });
                if (img.complete) {
                    if (img.naturalWidth > 0) {
                        img.classList.add('loaded');
                        if (container) container.style.display = '';
                    } else {
                        if (container) container.style.display = 'none';
                    }
                }
            });
        }

        // ---------- 计算天数（保留） ----------
        function calcDays() {
            const startDate = new Date('2025-10-20');
            const today = new Date();
            const diffTime = today - startDate;
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            const daysElement = document.getElementById('daysCounter');
            if (daysElement) {
                daysElement.textContent = diffDays >= 0 ? diffDays : 0;
            }
        }

        // ---------- 从 API 加载时光轴数据 ----------
        async function loadTimeline() {
            const container = document.getElementById('timelineContainer');
            try {
                const resp = await fetch('api/memories.php');
                if (!resp.ok) throw new Error('网络错误');
                const events = await resp.json();

                if (!Array.isArray(events) || events.length === 0) {
                    container.innerHTML = '<div class="empty-message">还没有时光事件，管理员快去添加吧~</div>';
                    return;
                }

                let html = '';
                events.forEach(ev => {
                    // 安全转义
                    const date = escapeHtml(ev.event_date);
                    const title = escapeHtml(ev.title);
                    const desc = escapeHtml(ev.description);
                    // 图片处理
                    let photoHtml = '';
                    if (ev.photo && ev.photo.trim() !== '') {
                        photoHtml = `<img src="${escapeHtml(ev.photo)}" alt="${title}" loading="lazy">`;
                    } else {
                        photoHtml = `<span class="timeline-photo-placeholder">📷 暂无照片</span>`;
                    }

                    html += `
                        <div class="timeline-item">
                            <div class="timeline-date">${date}</div>
                            <div class="timeline-title">${title}</div>
                            <div class="timeline-desc">${desc}</div>
                            <div class="timeline-photo">
                                ${photoHtml}
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;

                // 重新初始化图片加载逻辑
                initImages();
            } catch (err) {
                container.innerHTML = '<div class="empty-message">加载失败，请刷新重试</div>';
                console.error(err);
            }
        }

        // 辅助：转义 HTML 防止 XSS
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 页面加载完成后执行
        window.addEventListener('DOMContentLoaded', () => {
            loadTimeline();
            calcDays();
        });
    </script>

    <?php include '_footer.php'; ?>
</body>
</html>