<?php
$currentPage = $currentPage ?? '';
?>
<div class="tech-statusbar" aria-hidden="true">
    <span class="sys-name">BOYA.CLASS / MEMORY SYSTEM</span>
    <span class="sys-status"><i class="status-dot"></i> SYSTEM ONLINE</span>
    <span class="sys-clock">EDITION 01 · 2026</span>
</div>
<nav class="nav" id="navbar">
    <div class="nav-inner">
        <a href="index.php" class="nav-badge" title="班级首页" aria-label="班级首页">
            <img src="photos/banhui.jpg" alt="班徽" width="38" height="38"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span class="nav-badge-placeholder" style="display:none;">班徽</span>
        </a>

        <ul class="nav-links" id="navLinksDesktop">
            <li><a href="index.php" <?= $currentPage === 'home' ? 'class="active"' : '' ?>>首页</a></li>
            <li><a href="teacher.php" <?= $currentPage === 'teacher' ? 'class="active"' : '' ?>>班主任</a></li>
            <li><a href="classmates.php" <?= $currentPage === 'classmates' ? 'class="active"' : '' ?>>同学录</a></li>
            <li><a href="memories.php" <?= $currentPage === 'memories' ? 'class="active"' : '' ?>>时光回忆馆</a></li>
            <li><a href="messages.php" <?= $currentPage === 'messages' ? 'class="active"' : '' ?>>留言板</a></li>
        </ul>

        <div class="nav-actions">
            <div class="style-switch" id="styleSwitch">
                <button class="style-switch-toggle" id="styleSwitchBtn" type="button" aria-label="切换网站外观" aria-haspopup="true" aria-expanded="false" title="切换网站外观">
                    <i class="fa-solid fa-palette" aria-hidden="true"></i>
                </button>
                <div class="style-switch-menu" id="styleSwitchMenu" role="menu" aria-label="选择外观">
                    <button type="button" class="style-option" data-style="default" role="menuitemradio" aria-checked="true">
                        <span class="style-dot style-dot-default" aria-hidden="true"></span>
                        原版
                    </button>
                    <button type="button" class="style-option" data-style="gothic" role="menuitemradio" aria-checked="false">
                        <span class="style-dot style-dot-gothic" aria-hidden="true"></span>
                        哥特
                    </button>
                    <button type="button" class="style-option" data-style="tech" role="menuitemradio" aria-checked="false">
                        <span class="style-dot style-dot-tech" aria-hidden="true"></span>
                        科技
                    </button>
                    <button type="button" class="style-option" data-style="classical" role="menuitemradio" aria-checked="false">
                        <span class="style-dot style-dot-classical" aria-hidden="true"></span>
                        古风
                    </button>
                    <button type="button" class="style-option" data-style="editorial" role="menuitemradio" aria-checked="false">
                        <span class="style-dot style-dot-editorial" aria-hidden="true"></span>
                        编辑社
                    </button>
                    <button type="button" class="style-option" data-style="western" role="menuitemradio" aria-checked="false">
                        <span class="style-dot style-dot-western" aria-hidden="true"></span>
                        西方风
                    </button>
                </div>
            </div>
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="切换到深色模式" title="切换到深色模式">
                  <i class="fa-solid fa-moon" aria-hidden="true"></i>
                  <i class="fa-solid fa-sun" aria-hidden="true"></i>
                  <svg class="gothic-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                      <circle cx="12" cy="12" r="4.2"></circle>
                      <path d="M12 2.8v2.2M12 19v2.2M2.8 12H5M19 12h2.2M5.5 5.5l1.6 1.6M16.9 16.9l1.6 1.6M18.5 5.5l-1.6 1.6M7.1 16.9l-1.6 1.6"></path>
                  </svg>
                  <svg class="tech-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                      <circle cx="12" cy="12" r="4.4"></circle>
                      <path d="M12 2.6v2.4M12 19v2.4M2.6 12H5M19 12h2.4M5.4 5.4l1.7 1.7M16.9 16.9l1.7 1.7M18.6 5.4l-1.7 1.7M7.1 16.9l-1.7 1.7"></path>
                  </svg>
              </button>
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
        <a href="index.php" <?= $currentPage === 'home' ? 'class="active"' : '' ?>>首页</a>
        <a href="teacher.php" <?= $currentPage === 'teacher' ? 'class="active"' : '' ?>>班主任</a>
        <a href="classmates.php" <?= $currentPage === 'classmates' ? 'class="active"' : '' ?>>同学录</a>
        <a href="memories.php" <?= $currentPage === 'memories' ? 'class="active"' : '' ?>>时光回忆馆</a>
        <a href="messages.php" <?= $currentPage === 'messages' ? 'class="active"' : '' ?>>留言板</a>
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

<a class="download-notice" href="download.php" aria-label="前往下载博雅 APP">
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
