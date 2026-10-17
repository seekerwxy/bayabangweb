/* 同学登录（留言板 / 帖子页共用）
 * --------------------------------------------------------------------------
 * 以前 messages.php 和 post.php 各自内联一份「登录弹窗 + 登录提交 + 退出 +
 * 登录状态条」的脚本（两份各约 80 行，几乎一模一样）。现在收成这一个文件，
 * 页面只需要提供按钮 id，然后监听 onChange 重新渲染。
 *
 * 依赖：
 *   - js/forum.js（window.BoyaForum：登录接口、提示、转义）
 *   - _login_modal.php（弹窗结构：loginModal / loginName / loginPassword /
 *     loginError / loginCancel / loginSubmit）
 *
 * 用法：
 *   BoyaAuth.init({ onChange: function (session) { renderBar(); } });
 */
(function () {
    'use strict';

    var Forum = window.BoyaForum;
    var BoyaAuth = {};

    BoyaAuth.session = null;
    BoyaAuth.onChange = null;

    function byId(id) {
        return document.getElementById(id);
    }

    function notify() {
        if (typeof BoyaAuth.onChange === 'function') {
            BoyaAuth.onChange(BoyaAuth.session || {});
        }
    }

    BoyaAuth.open = function () {
        var error = byId('loginError');
        if (error) { error.textContent = ''; }
        var modal = byId('loginModal');
        if (modal) { modal.classList.add('open'); }
        var name = byId('loginName');
        if (name) { name.focus(); }
    };

    BoyaAuth.close = function () {
        var modal = byId('loginModal');
        if (modal) { modal.classList.remove('open'); }
        var password = byId('loginPassword');
        if (password) { password.value = ''; }
    };

    /** 重新读取登录状态，成功后触发 onChange。 */
    BoyaAuth.load = function () {
        return Forum.loadSession().then(function (session) {
            BoyaAuth.session = session || {};
            notify();
            return BoyaAuth.session;
        }).catch(function () {
            BoyaAuth.session = {};
            notify();
            return BoyaAuth.session;
        });
    };

    BoyaAuth.login = function (name, password) {
        return Forum.login(name, password).then(function (res) {
            if (!res.ok || res.data.message !== 'success') {
                return res;
            }
            try { localStorage.setItem('profile_name', name); } catch (e) {}
            BoyaAuth.close();
            Forum.toast('登录成功');
            return BoyaAuth.load().then(function () { return res; });
        });
    };

    BoyaAuth.logout = function () {
        var session = BoyaAuth.session || {};
        return Forum.logout(session.csrf_token || '').then(function () {
            Forum.toast('已退出');
            return BoyaAuth.load();
        });
    };

    /** 绑定弹窗与「登录 / 退出」按钮，并读一次登录状态。 */
    BoyaAuth.init = function (options) {
        var o = options || {};
        BoyaAuth.onChange = o.onChange || null;

        var loginBtn = byId('loginBtn');
        if (loginBtn) { loginBtn.addEventListener('click', BoyaAuth.open); }

        var logoutBtn = byId('logoutBtn');
        if (logoutBtn) { logoutBtn.addEventListener('click', function () { BoyaAuth.logout(); }); }

        var cancel = byId('loginCancel');
        if (cancel) { cancel.addEventListener('click', BoyaAuth.close); }

        var modal = byId('loginModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) { BoyaAuth.close(); }
            });
        }

        var submit = byId('loginSubmit');
        if (submit) {
            submit.addEventListener('click', function () {
                var nameInput = byId('loginName');
                var passwordInput = byId('loginPassword');
                var errorBox = byId('loginError');
                var name = nameInput ? nameInput.value.trim() : '';
                var password = passwordInput ? passwordInput.value : '';

                if (!name || !password) {
                    if (errorBox) { errorBox.textContent = '请填写姓名和密码'; }
                    return;
                }

                var btn = this;
                btn.disabled = true;
                btn.textContent = '登录中…';
                if (errorBox) { errorBox.textContent = ''; }

                BoyaAuth.login(name, password).then(function (res) {
                    if (!res.ok || res.data.message !== 'success') {
                        if (errorBox) { errorBox.textContent = res.data.message || '登录失败'; }
                    }
                }).catch(function () {
                    if (errorBox) { errorBox.textContent = '网络错误，登录失败'; }
                }).finally(function () {
                    btn.disabled = false;
                    btn.textContent = '登录';
                });
            });
        }

        return BoyaAuth.load();
    };

    window.BoyaAuth = BoyaAuth;
})();
