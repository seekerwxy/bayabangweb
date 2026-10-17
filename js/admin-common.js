/* 后台页面公共方法（admin-files/*.php 共用）
 * --------------------------------------------------------------------------
 * 以前每个后台页面都自带一份 escapeHtml / showResult / 分页按钮拼接，
 * 7 个文件里重复了 4~5 遍。现在集中到这里，页面只留自己的业务逻辑。
 *
 * 依赖页面里由 admin-files/_layout.php 输出的 window.BOYA_ADMIN：
 *   { csrf: '<token>', root: '/' }
 */
(function () {
    'use strict';

    var cfg = window.BOYA_ADMIN || { csrf: '', root: '/' };
    var Admin = {};

    Admin.csrf = function () {
        return cfg.csrf || '';
    };

    /** 站点根前缀，永远是「单个 / 开头、单个 / 结尾」。 */
    Admin.root = function () {
        var root = String(cfg.root || '/');
        return root.replace(/\/+$/, '') + '/';
    };

    /** 接口地址：Admin.url('api/admin-forum.php') → '/api/admin-forum.php' */
    Admin.url = function (path) {
        return Admin.root() + String(path || '').replace(/^\/+/, '');
    };

    Admin.$ = function (id) {
        return document.getElementById(id);
    };

    Admin.esc = function (text) {
        if (text === null || text === undefined) {
            return '';
        }
        return String(text).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    };

    /** 只保留「YYYY-MM-DD HH:MM」这种长度，表格里更紧凑。 */
    Admin.shortTime = function (value) {
        var text = String(value === null || value === undefined ? '' : value);
        return text.length >= 16 ? text.slice(0, 16) : text;
    };

    /** fetch + JSON 解析，统一返回 { ok, status, data }，不再让调用处各写一遍。 */
    Admin.fetchJson = function (url, options) {
        return fetch(url, options || { cache: 'no-store' }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data || {} };
            }).catch(function () {
                return { ok: false, status: res.status, data: { error: '服务器返回异常（' + res.status + '）' } };
            });
        }).catch(function () {
            return { ok: false, status: 0, data: { error: '网络错误' } };
        });
    };

    /** 表单式 POST（后台接口读 $_POST），自动带上 CSRF token。 */
    Admin.postForm = function (url, data) {
        var body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key] === null || data[key] === undefined ? '' : data[key]);
        });
        body.append('csrf_token', Admin.csrf());
        return Admin.fetchJson(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        });
    };

    /** 页面内的结果提示条：kind = 'ok' | 'error' | ''（黄色警告）。 */
    Admin.showResult = function (id, text, kind) {
        var box = Admin.$(id);
        if (!box) {
            return;
        }
        box.textContent = text;
        box.className = 'alert' + (kind ? ' alert-' + kind : '');
        box.style.display = 'block';
    };

    /** 底部浮层提示（#toastMsg 不存在时自动造一个）。 */
    Admin.toast = function (message) {
        var box = Admin.$('toastMsg');
        if (!box) {
            box = document.createElement('div');
            box.id = 'toastMsg';
            box.className = 'toast';
            document.body.appendChild(box);
        }
        box.textContent = message;
        box.style.opacity = '1';
        clearTimeout(box._timer);
        box._timer = setTimeout(function () {
            box.style.opacity = '0';
        }, 2000);
    };

    Admin.openModal = function (id) {
        var modal = Admin.$(id);
        if (modal) {
            modal.style.display = 'flex';
        }
    };

    Admin.closeModal = function (id) {
        var modal = Admin.$(id);
        if (modal) {
            modal.style.display = 'none';
        }
    };

    /** 列表分页按钮（上一页 / 第 x / y 页 / 下一页）。 */
    Admin.pager = function (containerId, page, totalPages, onGo) {
        var box = Admin.$(containerId);
        if (!box) {
            return;
        }
        if (!totalPages || totalPages <= 1) {
            box.innerHTML = '';
            return;
        }
        box.innerHTML = ''
            + '<button class="btn ghost" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>'
            + '<span class="muted">第 ' + page + ' / ' + totalPages + ' 页</span>'
            + '<button class="btn ghost" data-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + '>下一页</button>';
        Array.prototype.forEach.call(box.querySelectorAll('button[data-page]'), function (btn) {
            btn.addEventListener('click', function () {
                onGo(Number(btn.dataset.page));
            });
        });
    };

    window.BoyaAdmin = Admin;
})();
