/* 图片「加载完成」状态（首页合照、班主任照片等共用）
 * --------------------------------------------------------------------------
 * index.php 与 teacher.php 原来各写一份几乎一样的 onload/onerror + window.load
 * 双保险逻辑（约 25 行 × 2）。现在统一成：给 <img> 加 data-photo-state 属性即可。
 *
 * 为什么需要它：图片从缓存里恢复时不会触发 load 事件，只用 onload 会让
 * 「加载中」的占位闪烁或一直不消失，所以页面加载完再主动查一次 complete。
 */
(function () {
    'use strict';

    function mark(img) {
        if (!img.complete) {
            return;
        }
        if (img.naturalWidth > 0) {
            img.classList.add('loaded');
        } else {
            img.classList.remove('loaded');
        }
    }

    function bind(img) {
        img.addEventListener('load', function () { img.classList.add('loaded'); });
        img.addEventListener('error', function () { img.classList.remove('loaded'); });
        mark(img);
    }

    var images = document.querySelectorAll('img[data-photo-state]');
    Array.prototype.forEach.call(images, bind);

    // 双保险：整页资源加载完再核对一次（缓存命中的图片不会再发 load 事件）
    window.addEventListener('load', function () {
        Array.prototype.forEach.call(images, mark);
    });
})();
