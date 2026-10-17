/* 时光回忆馆（memories.php）
 * --------------------------------------------------------------------------
 * 原来内联在页面里。搬出来后接口前缀取自 window.BOYA.api，
 * 图片「加载完成」的显隐沿用页面加载时的一次性处理。
 */
(function () {
    'use strict';

    var BOYA = window.BOYA || {};
    var API = (BOYA.api || '/api/') + 'memories.php';

    // 统一的转义实现（js/boya-ui.js 里定义，避免每个页面各写一份）
    var esc = window.Boya.escapeHtml;

    /** 图片加载成功才显示容器，失败/空路径就把整块隐藏。 */
    function initImages() {
        var images = document.querySelectorAll('.timeline-photo img, .gallery-item img');
        Array.prototype.forEach.call(images, function (img) {
            var container = img.closest('.timeline-photo') || img.closest('.gallery-item');
            var src = img.getAttribute('src');
            if (!src || src.trim() === '') {
                if (container) { container.style.display = 'none'; }
                return;
            }
            img.addEventListener('load', function () {
                img.classList.add('loaded');
                if (container) { container.style.display = ''; }
            });
            img.addEventListener('error', function () {
                if (container) { container.style.display = 'none'; }
            });
            if (img.complete) {
                if (img.naturalWidth > 0) {
                    img.classList.add('loaded');
                    if (container) { container.style.display = ''; }
                } else if (container) {
                    container.style.display = 'none';
                }
            }
        });
    }

    /** 拔牙帮至今多少天。 */
    function calcDays() {
        var startDate = new Date('2025-10-20');
        var diffDays = Math.floor((new Date() - startDate) / (1000 * 60 * 60 * 24));
        var el = document.getElementById('daysCounter');
        if (el) {
            el.textContent = diffDays >= 0 ? diffDays : 0;
        }
    }

    function itemHtml(ev) {
        var title = esc(ev.title);
        var photo = String(ev.photo || '').trim();
        var photoHtml = photo !== ''
            ? '<img src="' + esc(photo) + '" alt="' + title + '" loading="lazy">'
            : '<span class="timeline-photo-placeholder">📷 暂无照片</span>';

        return '<div class="timeline-item">'
            + '<div class="timeline-date">' + esc(ev.event_date) + '</div>'
            + '<div class="timeline-title">' + title + '</div>'
            + '<div class="timeline-desc">' + esc(ev.description) + '</div>'
            + '<div class="timeline-photo">' + photoHtml + '</div>'
            + '</div>';
    }

    function loadTimeline() {
        var container = document.getElementById('timelineContainer');
        fetch(API, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) { throw new Error('网络错误'); }
                return res.json();
            })
            .then(function (events) {
                if (!Array.isArray(events) || events.length === 0) {
                    container.innerHTML = '<div class="empty-message">还没有时光事件，管理员快去添加吧~</div>';
                    return;
                }
                container.innerHTML = events.map(itemHtml).join('');
                initImages();
            })
            .catch(function () {
                container.innerHTML = '<div class="empty-message">加载失败，请刷新重试</div>';
            });
    }

    loadTimeline();
    calcDays();
})();
