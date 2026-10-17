<?php
// 同学登录弹窗（messages.php 与 post.php 共用）。
// 交互在 js/boya-auth.js：打开/关闭、提交登录、退出、登录态变化回调。
?>
<div class="forum-modal" id="loginModal">
    <div class="forum-modal-card">
        <h3>同学登录</h3>
        <p class="muted">用真实姓名或论坛用户名 + 密码登录，和「编辑我的信息」是同一个账号。</p>
        <label for="loginName">姓名 / 用户名</label>
        <input type="text" id="loginName" autocomplete="username">
        <label for="loginPassword">密码</label>
        <input type="password" id="loginPassword" autocomplete="current-password">
        <p class="form-feedback error" id="loginError"></p>
        <div class="forum-modal-actions">
            <button class="forum-cancel-btn" id="loginCancel" type="button">取消</button>
            <button class="submit-btn" id="loginSubmit" type="button">登录</button>
        </div>
    </div>
</div>
