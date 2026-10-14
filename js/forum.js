(function () {
    "use strict";

    // 留言板前端公共方法。站内链接一律用「站点根绝对路径」：
    // /user/2 这类重写网址会让相对路径（api/…、user/2）解析到错误的目录下。
    var Forum = {};

    Forum.escapeHtml = function (text) {
        if (text === null || text === undefined) {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };

    /**
     * 把正文里残留的彩字标记 [color=#rrggbb]…[/color] 转成受控的 <span style="color:…">。
     *
     * 留言板第一期的发帖框用的就是这套 [color=] 语法，颜色只可能来自 12 个固定色块；
     * 旧留言导入时也把原始标记一起带了进来。以前前台只跑 marked，这些标记就原样显示，
     * 所以这里统一转成 HTML（只接受 6 位十六进制，其它写法原样保留，不会误吃正文）。
     */
    Forum.colorTagsToHtml = function (text) {
        var source = String(text === null || text === undefined ? '' : text);
        return source.replace(/\[color=(#[0-9a-fA-F]{6})\]([\s\S]*?)\[\/color\]/gi, function (match, color, body) {
            return '<span style="color:' + color.toLowerCase() + '">' + body + '</span>';
        });
    };

    /**
     * 富文本渲染：先转彩字标记，再走 Markdown，最后用 DOMPurify 过滤（第二步过滤同样能拦住
     * 用户自己写进正文的 <script>／onclick= 之类片段）。
     */
    Forum.richText = function (text) {
        return Forum.markdown(Forum.colorTagsToHtml(text));
    };

    /** 拿一个可用的 DOMPurify 实例：2.x 直接挂在 window 上，3.x 是工厂函数，必须先调用。 */
    Forum.purifier = function () {
        var dompurify = window.DOMPurify;
        if (!dompurify) {
            return null;
        }
        // js/vendor/purify.min.js 是 DOMPurify 3.x（工厂式）：DOMPurify 本身没有 sanitize，
        // 必须先 DOMPurify(window) 拿到实例；2.x 的独立构建则可以直接用。两种都兼容。
        var instance = typeof dompurify.sanitize === 'function'
            ? dompurify
            : (typeof dompurify === 'function' ? dompurify(window) : null);
        if (!instance || typeof instance.sanitize !== 'function') {
            return null;
        }
        return instance;
    };

    /** Markdown 渲染 + XSS 过滤（marked / DOMPurify 是自托管的，见 js/vendor/） */
    Forum.markdown = function (text) {
        var source = String(text === null || text === undefined ? '' : text);
        try {
            var html = window.marked
                ? window.marked.parse(source)
                : Forum.escapeHtml(source);        // 没有 marked 时只显示纯文本
            // 过滤库不可用（或版本不兼容）时按纯文本显示，绝不直接输出未过滤的 HTML
            var purifier = Forum.purifier();
            if (!purifier) {
                return Forum.escapeHtml(source);
            }
            return purifier.sanitize(html, { USE_PROFILES: { html: true } });
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

    /**
     * 站点根路径，用来拼「个人主页」链接。
     *
     * 个人主页同时有两个网址：/personal.php?id=2 和 /user/2（.htaccess 重写）。
     * 后者在浏览器里位于 /user/ 这一层，之前用相对链接 "user/2" 会变成 /post/user/2（404）。
     * 这里由服务端在 <html data-user-link-base="..."> 上给出前缀，缺失时按「当前目录」兜底。
     */
    Forum.siteBase = function () {
        var el = document.documentElement;
        if (el) {
            var explicit = el.getAttribute('data-user-link-base');
            if (explicit) {
                return explicit.charAt(explicit.length - 1) === '/'
                    ? explicit
                    : explicit + '/';
            }
        }
        var path = window.location.pathname || '/';
        var base = path.replace(/[^/]*$/, '');
        return base.charAt(base.length - 1) === '/' ? base : base + '/';
    };

    /** 当前登录状态：{loggedIn, username, hasUsername, avatar, csrf_token} */
    Forum.loadSession = function () {
        return fetch('/api/session.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); });
    };

    /** 同学登录（复用资料编辑页那套接口） */
    Forum.login = function (name, password) {
        return fetch('/api/classmates.php', {
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
        return fetch('/api/classmates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout', csrf_token: csrfToken })
        }).then(function (res) { return res.json(); });
    };

    /** 发帖 / 回复 / 删除自己的内容 */
    Forum.send = function (payload) {
        return fetch('/api/forum-write.php', {
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

    /** 发帖/回复列表里的作者行：可点击进个人主页，显示 UID / 昵称 / 称号 / 管理徽章 */
    Forum.authorLine = function (item) {
        var uid = Number(item.author_id || 0);
        var name = Forum.escapeHtml(item.author_username || '');
        var html = '<span class="author-line">';
        if (uid > 0) {
            html += '<a href="' + Forum.siteBase() + 'user/' + uid + '" title="前往个人主页">' + name + '</a>';
        } else {
            html += '<span>' + name + '</span>';
        }
        html += '<span class="author-uid">UID ' + uid + '</span>';
        if (item.author_nickname) {
            html += '<span class="author-nick">“' + Forum.escapeHtml(item.author_nickname) + '”</span>';
        }
        if (item.author_title) {
            html += '<span class="uspace-badge">' + Forum.escapeHtml(item.author_title) + '</span>';
        }
        if (Number(item.author_is_admin) === 1) {
            html += '<span class="uspace-badge admin">管理</span>';
        }
        if (item.is_history) {
            html += '<span class="badge-forum history">历史留言</span>';
        }
        html += '</span>';
        return html;
    };

    /** 论坛管理动作：置顶 / 锁定 / 删除 / 恢复（需要管理权限） */
    Forum.moderate = function (action, type, id, csrfToken) {
        return fetch('/api/admin-forum.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, type: type, id: id, csrf_token: csrfToken || '' })
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data || {} };
            }).catch(function () {
                return { ok: false, status: res.status, data: { message: '服务器返回异常（' + res.status + '）' } };
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
