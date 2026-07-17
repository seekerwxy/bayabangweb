<?php
$currentPage = $currentPage ?? '';
?>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

    <div class="nav-mobile-dropdown" id="mobileDropdown">
        <a href="index.php" <?= $currentPage === 'home' ? 'class="active"' : '' ?>>首页</a>
        <a href="teacher.php" <?= $currentPage === 'teacher' ? 'class="active"' : '' ?>>班主任</a>
        <a href="classmates.php" <?= $currentPage === 'classmates' ? 'class="active"' : '' ?>>同学录</a>
        <a href="memories.php" <?= $currentPage === 'memories' ? 'class="active"' : '' ?>>时光回忆馆</a>
        <a href="messages.php" <?= $currentPage === 'messages' ? 'class="active"' : '' ?>>留言板</a>
    </div>
</nav>