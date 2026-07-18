<?php $currentPage = 'home'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博雅班首页</title>
    <meta name="description" content="班级纪念册，献给班主任和同学们的回忆与祝福。">
    <link rel="stylesheet" href="css/beauty.css">
</head>
<body>
	
    <!-- 通知 
	<div class="domain-notice" id="domainNotice">
        <span><strong>[六一专属]</strong>六一儿童节快乐～</span>
        <button class="domain-notice-close" id="closeNotice" aria-label="关闭通知">✕</button>
    </div>    
    -->

    <!-- ==================== 主体内容 ==================== -->
    <main class="main">

        <!-- Hero 欢迎区域 -->
        <section class="hero">
            <div class="hero-badge">Class Album</div>
            <h1 class="hero-title">博采众长如星璀璨，雅量高致似翰无涯</h1>
            <p class="hero-slogan">献给班主任和同学们</p>

            <!-- 班级大合照 -->
            <div class="hero-photo" id="heroPhoto">
                <img
                  id="heroImg"
                  src="photos\hezhao.jpg"
                  alt="班级大合照"
                  loading="lazy"
                  onload="handleHeroImageLoad()"
                  onerror="handleHeroImageError()"
                >
                <span class="hero-photo-placeholder">📷 班级大合照</span>
            </div>
        </section>

        <!-- 板块导航入口 -->
        <p class="section-label">探索纪念册</p>
        <div class="cards-grid">

            <a href="teacher.php" class="card">
                <span class="card-number">01</span>
                <h3 class="card-title">班主任专属空间</h3>
                <p class="card-desc">老师的抓拍瞬间、经典语录，以及全班同学想对您说的一句句话，都在这里静静安放。</p>
                <span class="card-arrow">进入 →</span>
            </a>

            <a href="classmates.php" class="card">
                <span class="card-number">02</span>
                <h3 class="card-title">同学录</h3>
                <p class="card-desc">比较简陋，不过后续“可能”更新</p>
                <span class="card-arrow">进入 →</span>
            </a>

            <a href="memories.php" class="card">
                <span class="card-number">03</span>
                <h3 class="card-title">时光回忆馆</h3>
                <p class="card-desc">我们的故事还未完结，不过目前已经有了一些碎片</p>
                <span class="card-arrow">进入 →</span>
            </a>

            <a href="messages.php" class="card">
                <span class="card-number">04</span>
                <h3 class="card-title">留言板</h3>
                <p class="card-desc">写给班级的祝福，写给未来自己的话。像一面温暖的便利贴墙，每一张都值得被看见。</p>
                <span class="card-arrow">进入 →</span>
            </a>

        </div>
    </main>

    <script>

        // ---------- 合照显示处理（解决 onload 缓存不触发问题） ----------
        function handleHeroImageLoad() {
            const img = document.getElementById('heroImg');
            if (img) {
                img.classList.add('loaded');
            }
        }

        function handleHeroImageError() {
            const img = document.getElementById('heroImg');
            if (img) {
                img.classList.remove('loaded');
            }
        }

        // 页面加载完成后再次检查图片状态（双保险）
        window.addEventListener('load', function() {
            const img = document.getElementById('heroImg');
            if (img && img.complete && img.naturalWidth > 0) {
                img.classList.add('loaded');
            } else if (img && img.complete && img.naturalWidth === 0) {
                img.classList.remove('loaded');
            }
        });
        
    </script>

    <?php include '_footer.php'; ?>

</body>
</html>