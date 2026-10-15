<?php
require_once __DIR__ . '/_site.php';   // boya_site_prefix()：先加载，后面拼链接要用

$currentPage = $currentPage ?? '';

// 导航里的链接必须是「站点根绝对路径」：个人主页有 /user/2 这种重写网址，
// 用相对路径会变成 /user/messages.php（404）。前缀统一由 boya_site_prefix() 计算，
// 它保证结果是以单个 / 开头、单个 / 结尾（绝不会出现 "//"）。
$sitePrefix = boya_site_prefix();
?>
<div class="tech-statusbar" aria-hidden="true">
    <span class="sys-name">BOYA.CLASS / MEMORY SYSTEM</span>
    <span class="sys-status"><i class="status-dot"></i> SYSTEM ONLINE</span>
    <span class="sys-clock">EDITION 01 · 2026</span>
</div>
<nav class="nav" id="navbar">
    <div class="nav-inner">
        <a href="<?= $sitePrefix ?>index.php" class="nav-badge" title="班级首页" aria-label="班级首页">
            <img src="<?= $sitePrefix ?>photos/banhui.jpg" alt="班徽" width="38" height="38"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span class="nav-badge-placeholder" style="display:none;">班徽</span>
        </a>

        <ul class="nav-links" id="navLinksDesktop">
            <li><a href="<?= $sitePrefix ?>index.php" <?= $currentPage === 'home' ? 'class="active"' : '' ?>>首页</a></li>
            <li><a href="<?= $sitePrefix ?>teacher.php" <?= $currentPage === 'teacher' ? 'class="active"' : '' ?>>班主任</a></li>
            <li><a href="<?= $sitePrefix ?>classmates.php" <?= $currentPage === 'classmates' ? 'class="active"' : '' ?>>同学录</a></li>
            <li><a href="<?= $sitePrefix ?>memories.php" <?= $currentPage === 'memories' ? 'class="active"' : '' ?>>时光回忆馆</a></li>
            <li><a href="<?= $sitePrefix ?>messages.php" <?= $currentPage === 'messages' ? 'class="active"' : '' ?>>留言板</a></li>
        </ul>

        <div class="nav-actions">
            <!-- 右上角用户头像菜单：替换掉原来的「风格」和「明暗」两个按钮。
                 头像/姓氏占位由 _footer.php 里的脚本按登录状态渲染；
                 点开是「个人主页（游客为登录）」和「设置」（设置里放主题 + 明暗）。 -->
            <div class="user-menu" id="userMenu">
                <button class="user-menu-btn" id="userMenuBtn" type="button"
                        aria-label="用户菜单" aria-haspopup="true" aria-expanded="false" title="用户菜单">
                    <span class="nav-user-avatar" id="navUserAvatar" aria-hidden="true">
                        <i class="fa-solid fa-user"></i>
                    </span>
                </button>
                <div class="user-menu-dropdown" id="userMenuDropdown" role="menu" aria-label="用户菜单">
                    <a class="user-menu-item" id="userMenuProfile" href="<?= $sitePrefix ?>personal.php" role="menuitem">
                        <i class="fa-solid fa-id-card user-menu-icon" aria-hidden="true"></i>
                        <span class="user-menu-text">个人主页</span>
                    </a>
                    <button class="user-menu-item" id="userMenuSettings" type="button" role="menuitem">
                        <i class="fa-solid fa-gear user-menu-icon" aria-hidden="true"></i>
                        <span class="user-menu-text">设置</span>
                    </button>
                </div>
            </div>
            <button class="hamburger" id="hamburgerBtn" aria-label="菜单" aria-expanded="false">
                <svg class="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="4" y1="6" x2="20" y2="6"/>
                    <line x1="4" y1="12" x2="20" y2="12"/>
                    <line x1="4" y1="18" x2="20" y2="18"/>
                </svg>
                <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="6" y1="6" x2="18" y2="18"/>
                    <line x1="18" y1="6" x2="6" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="nav-mobile-dropdown" id="mobileDropdown">
        <a href="<?= $sitePrefix ?>index.php" <?= $currentPage === 'home' ? 'class="active"' : '' ?>>首页</a>
        <a href="<?= $sitePrefix ?>teacher.php" <?= $currentPage === 'teacher' ? 'class="active"' : '' ?>>班主任</a>
        <a href="<?= $sitePrefix ?>classmates.php" <?= $currentPage === 'classmates' ? 'class="active"' : '' ?>>同学录</a>
        <a href="<?= $sitePrefix ?>memories.php" <?= $currentPage === 'memories' ? 'class="active"' : '' ?>>时光回忆馆</a>
        <a href="<?= $sitePrefix ?>messages.php" <?= $currentPage === 'messages' ? 'class="active"' : '' ?>>留言板</a>
        <div class="nav-mobile-style">
            <span>外观</span>
            <button type="button" class="style-option style-option-mobile" data-style="default">
                <span class="style-dot style-dot-default" aria-hidden="true"></span>
                原版
            </button>
            <button type="button" class="style-option style-option-mobile" data-style="gothic">
                <span class="style-dot style-dot-gothic" aria-hidden="true"></span>
                哥特
            </button>
            <button type="button" class="style-option style-option-mobile" data-style="tech">
                <span class="style-dot style-dot-tech" aria-hidden="true"></span>
                科技
            </button>
            <button type="button" class="style-option style-option-mobile" data-style="classical">
                <span class="style-dot style-dot-classical" aria-hidden="true"></span>
                古风
            </button>
            <button type="button" class="style-option style-option-mobile" data-style="editorial">
                <span class="style-dot style-dot-editorial" aria-hidden="true"></span>
                编辑社
            </button>
            <button type="button" class="style-option style-option-mobile" data-style="western">
                <span class="style-dot style-dot-western" aria-hidden="true"></span>
                西方风
            </button>
        </div>
    </div>
</nav>

<a class="download-notice" href="<?= $sitePrefix ?>download.php" aria-label="前往下载博雅 APP">
    <span class="download-notice-icon" aria-hidden="true">
        <i class="fa-solid fa-mobile-screen-button"></i>
    </span>
    <span class="download-notice-copy">
        <strong>博雅 APP 已上线</strong>
        <span>点击下载安装包，把班级纪念装进口袋</span>
    </span>
    <span class="download-notice-arrow" aria-hidden="true">
        <i class="fa-solid fa-arrow-right"></i>
    </span>
</a>
