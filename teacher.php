<?php $currentPage = 'teacher'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班主任专属空间</title>
    <link rel="stylesheet" href="css/beauty.css">
</head>
<body>

    <main class="main">

        <!-- 页面标题 -->
        <section class="page-header">
            <div class="badge">For Our Teacher</div>
            <h2>班主任专属空间</h2>
        </section>

        <!-- 班主任小档案 -->
        <div class="teacher-profile">
            <div class="teacher-photo" id="teacherPhoto">
            <!-- ★ 替换 src 为班主任正方形照片路径 ★ -->
            <img
              id="teacherImg"
              src="photos\qikaijie.jpg"
              alt="班主任照片"
              loading="lazy"
              onload="handleTeacherImageLoad()"
              onerror="handleTeacherImageError()"
            >
            <span class="teacher-photo-placeholder">📷 老师</span>
            </div>
            <div class="teacher-info">
                <div class="teacher-name">戚老师</div>
                <p class="teacher-bio">
                    我们的班主任，别名杰哥，教数学。爱笑，幽默。
                </p>
                <div>
                    <span class="teacher-quote">“上课前先讲点废话吧”</span>
                    <span class="teacher-quote">“错的站起来”</span>
                    <span class="teacher-quote">“这么多人错哒？！”</span>
                </div>
            </div>
        </div>

        <!-- 班主任格言 -->
        <div class="teacher-message">
            <h3>班主任格言</h3>
            <blockquote>
                遵循数学学习规律，追求简约而不简单的课堂。且教且思，且思且行。
            </blockquote>
        </div>

        <!-- 我们想对您说 -->
        <div class="message-wall">
            <h3>我们想对您说</h3>
            <p class="sub">同学们献给最爱的班主任</p>
            <div class="messages-grid">
                <div class="message-card">
                    <div class="student">—— 陈嘉林</div>
                    <div class="words">戚老师，您在课堂上的谆谆教诲指引着我们走向数学的奇妙世界，您的教导使我们能够放心学。因为您，我们才成为了更好的自己。</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 叶煜昊</div>
                    <div class="words">戚老师，您辛苦了！您在大合唱比赛中为我们寻找老师，协调，练就我们的嗓子。谢谢你</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 钟辰睿</div>
                    <div class="words">戚老师认真负责，课上积极帮助有需要的学生！老师！您辛苦了！</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 匿名</div>
                    <div class="words">戚老师人还是很好的。希望三年班主任都是戚老师。</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 曾梓瑜</div>
                    <div class="words">亲爱的戚老师，感谢您对我长达一年的关怀与深深教诲！</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 王梵羽</div>
                    <div class="words">感谢您为我们班的付出，愿意花大把时间在学生上。你的教学实力十分丰富，课堂有个性。你是个好班主任，祝老师天天开心，不会被扣工资。</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 王煊怡</div>
                    <div class="words">祝戚老师每天早点下班，任务少一点。</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 吴祎可</div>
                    <div class="words">感谢您在数学课上的辛苦付出。祝您“柿柿”顺意，工资翻倍！！！</div>
                </div>
                <div class="message-card">
                    <div class="student">—— ycm</div>
                    <div class="words">刚遇见时，认为您不苟言笑，但在相处中看见了您幽默的一面。班主任节快乐！！！工资++</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 李依静</div>
                    <div class="words">班主任节快乐！愿您的生活像美丽的园一样圆满，烦恼如平行线永不相交。</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 匿名</div>
                    <div class="words">戚老师您辛苦了，我们绝不会忘记你的教诲！祝您：一帆风顺；两只老虎；三只松鼠；四季平安</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 蒋璐</div>
                    <div class="words">戚老师您上课认真细致，在大合唱训练时悉心指导，严格要求我们用心唱好歌。您辛苦了！班主任节快乐！</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 匿名</div>
                    <div class="words">您的教诲让我们走进数学的世界，您的指引使我们忘却了悲伤。您辛苦了！</div>
                </div>
                <div class="message-card">
                    <div class="student">—— 未完待续</div>
                    <div class="words">留言还在收集中……</div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // 班主任照片显示逻辑（避免缓存不触发 onload）
        function handleTeacherImageLoad() {
            const img = document.getElementById('teacherImg');
            if (img) {
                img.classList.add('loaded');
            }
        }

        function handleTeacherImageError() {
            const img = document.getElementById('teacherImg');
            if (img) {
                img.classList.remove('loaded');
            }
        }

        // 页面加载完成后再次检查
        window.addEventListener('load', function() {
            const img = document.getElementById('teacherImg');
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