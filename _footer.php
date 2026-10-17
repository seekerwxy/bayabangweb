<?php
// 页脚 + 外观设置弹窗（结构部分）。交互逻辑在 js/boya-ui.js 里。
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/theme.php';

// 同 _header.php：页脚里的站内链接要用站点根绝对路径，/user/2 这类重写网址下才不会 404。
$sitePrefix = boya_site_prefix();
?>
<footer class="footer">
    <div class="footer-container">
        <div class="footer-links">
            <!-- GitHub -->
            <a href="https://github.com/seekerwxy/bayabangweb" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="跳转至Github仓库">
                <i class="fa-brands fa-github fa-lg"></i>
            </a>
            <!-- 邮箱 -->
            <a href="javascript:void(0)" onclick="copyEmail('1708043179@qq.com')" aria-label="复制邮箱" title="复制邮箱">
                <i class="fa-solid fa-envelope fa-lg"></i>
            </a>
            <!-- Cat^_^ -->
            <a title="跳转至管理员个人主页" href="https://wangxuanyi.pages.dev" target="_blank" rel="noopener noreferrer" aria-label="管理员个人主页">
                <i class="fa-solid fa-cat fa-lg"></i>
            </a>
            <!-- 管理员 -->
            <a href="<?= $sitePrefix ?>admin-files/admin-list.php" aria-label="管理员入口" title="管理员入口">
                <i class="fa-solid fa-user-shield fa-lg"></i>
            </a>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> 25级博雅班. All rights reserved.
        </div>
    </div>
</footer>

<!-- 外观设置弹窗：右上角头像菜单 → 设置 打开 -->
<div class="settings-modal" id="settingsModal" aria-hidden="true">
    <div class="settings-modal-backdrop" id="settingsModalBackdrop"></div>
    <div class="settings-modal-card" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
        <div class="settings-modal-head">
            <h3 id="settingsTitle">外观设置</h3>
            <button class="settings-modal-close" id="settingsModalClose" type="button" aria-label="关闭设置">×</button>
        </div>
        <div class="settings-group">
            <span class="settings-label">明暗</span>
            <button class="settings-theme-btn" id="settingsThemeToggle" type="button" aria-pressed="false" title="切换深浅色">
                <i class="fa-solid fa-moon" id="settingsThemeIcon" aria-hidden="true"></i>
                <span id="settingsThemeText">浅色</span>
            </button>
        </div>
        <div class="settings-group">
            <span class="settings-label">主题</span>
            <div class="settings-style-options" id="settingsStyleOptions">
                <?= boya_style_option_buttons('style-option', true) ?>

            </div>
        </div>
        <button class="settings-done" id="settingsDone" type="button">完成</button>
    </div>
</div>

<script src="<?= boya_asset('js/boya-ui.js') ?>"></script>
