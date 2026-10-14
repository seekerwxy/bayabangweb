(function () {
    "use strict";

    // 留言板前端公共方法（只在站点根目录的页面使用，路径都是站点根相对）
    var Forum = {};

    Forum.escapeHtml = function (text) {
        if (text === null || text === undefined) {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };

    /** Markdown 渲染 + XSS 过滤（marked / DOMPurify 是自托管的，见 js/vendor/） */
    Forum.markdown = function (text) {
        var source = String(text === null || text === undefined ? '' : text);
        try {
            var html = window.marked ? window.marked.parse(source) : Forum.escapeHtml(source);
            return window.DOMPurify
                ? window.DOMPurify.sanitize(html, { USE_PROFILES: { html: true } })
                : Forum.escapeHtml(source);
        } catch (err) {
            return Forum.escapeHtml(source);
        }
    };

    /** 把 "2026-01-02 03:04:05" 显示成 "2026-01-02 03:04" */
    Forum.formatTime = function (value) {
        var text = String(value === null || value === undefined ? '' : value);
        if (text.length >= 16) {
            return text.slice(0, 16);
        }
        return text;
    };

    Forum.qs = function (name) {
        return new URLSearchParams(window.location.search).get(name);
    };

    /** 当前登录状态：{loggedIn, username, hasUsername, avatar, csrf_token} */
    Forum.loadSession = function () {
        return fetch('api/session.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); });
    };

    /** 同学登录（复用资料编辑页那套接口） */
    Forum.login = function (name, password) {
        return fetch('api/classmates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', name: name, password: password })
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data || {} };
            }).catch(function () {
                return { ok: false, status: res.status, data: { message: '服务器返回异常（' + res.status + '）' } };
            });
        });
    };

    Forum.logout = function (csrfToken) {
        return fetch('api/classmates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout', csrf_token: csrfToken })
        }).then(function (res) { return res.json(); });
    };

    /** 发帖 / 回复 / 删除自己的内容 */
    Forum.send = function (payload) {
        return fetch('api/forum-write.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data || {} };
            }).catch(function () {
                return { ok: false, status: res.status, data: { message: '服务器返回异常（' + res.status + '）' } };
            });
        });
    };

    /** 读取帖子列表 / 详情 */
    Forum.fetchJson = function (url) {
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data || {} };
                }).catch(function () {
                    return { ok: false, status: res.status, data: { message: '服务器返回异常（' + res.status + '）' } };
                });
            });
    };

    /** 颜色快捷标记：往 textarea 插入 [color=#xxxxxx]...[/color]，并同步隐藏字段 */
    Forum.setupSwatches = function (swatchSelector, textareaId, hiddenId) {
        var swatches = document.querySelectorAll(swatchSelector);
        if (!swatches.length) {
            return;
        }
        swatches.forEach(function (dot) {
            dot.addEventListener('click', function () {
                var color = dot.dataset.color || '';
                var textarea = document.getElementById(textareaId);
                if (!textarea || !color) {
                    return;
                }
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var selected = textarea.value.substring(start, end) || '文字';
                textarea.setRangeText('[color=' + color + ']' + selected + '[/color]', start, end, 'end');
                textarea.focus();
                var hidden = document.getElementById(hiddenId);
                if (hidden) {
                    hidden.value = color;
                }
            });
        });
    };

    /** 顶部胶囊提示（复用 _footer.php 里的 showToast，没有就自己造一个） */
    Forum.toast = function (message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message);
            return;
        }
        var old = document.querySelector('.forum-toast');
        if (old) {
            old.remove();
        }
        var box = document.createElement('div');
        box.className = 'forum-toast';
        box.textContent = message;
        document.body.appendChild(box);
        requestAnimationFrame(function () { box.classList.add('visible'); });
        setTimeout(function () {
            box.classList.remove('visible');
            setTimeout(function () { box.remove(); }, 300);
        }, 2200);
    };

    window.BoyaForum = Forum;
})();
