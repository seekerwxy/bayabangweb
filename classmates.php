<?php $currentPage = 'classmates'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>同学录</title>
    <link rel="stylesheet" href="css/beauty.css">
</head>
<body>

    <main class="main">
        <section class="page-header">
            <div class="badge">Classmates</div>
            <h2>同学录</h2>
        </section>

        <div class="members-grid" id="membersGrid">
            <div class="empty-message">正在加载同学信息…</div>
        </div>

        <!-- 编辑我的信息入口 -->
        <div class="edit-profile-section">
            <div class="edit-profile-card">
                <h3>编辑我的信息</h3>
                <p>更新你的头像、生日、座右铭，让同学们更了解你！</p>
                <a href="admin-files/edit-profile.php" class="edit-profile-btn">去编辑 →</a>
            </div> 
        </div>
    </main>

    <script>

        // ---------- 同学录逻辑 ----------
        const API_BASE = '/api/classmates.php';

        async function fetchClassmates() {
            try {
                const resp = await fetch(API_BASE);
                const data = await resp.json();
                renderClassmates(data);
            } catch (err) {
                document.getElementById('membersGrid').innerHTML = '<div class="empty-message">加载失败，请刷新重试</div>';
            }
        }

        function formatBirthday(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '';
            const month = date.getMonth() + 1;
            const day = date.getDate();
            return `${month}月${day}日`;
        }

        function renderClassmates(classmates) {
            const grid = document.getElementById('membersGrid');
            if (!classmates.length) {
                grid.innerHTML = '<div class="empty-message">还没有同学信息，快让大家都来完善吧~</div>';
                return;
            }
            grid.innerHTML = classmates.map(member => {
                const placeholder = member.name.charAt(0);
                const birthdayStr = formatBirthday(member.birthday);
                const avatar = (member.avatar && member.avatar !== 'null' && member.avatar.trim() !== '') 
                                ? member.avatar.trim() 
                                : '';

                // 卡片内容（含头像、姓名、昵称、生日、座右铭）
                let cardContent = '';
                if (avatar) {
                    cardContent = `
                        <div class="member-avatar">
                            <img src="${escapeHtml(avatar)}" alt="${escapeHtml(member.name)}" loading="lazy"
                                 onload="this.classList.add('loaded')"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <span class="member-avatar-placeholder" style="display:none;">${placeholder}</span>
                        </div>
                    `;
                } else {
                    cardContent = `
                        <div class="member-avatar">
                            <span class="member-avatar-placeholder">${placeholder}</span>
                        </div>
                    `;
                }
                // 增加昵称显示（如果有）
                const nicknameHtml = member.nickname ? `<div class="member-nickname">${escapeHtml(member.nickname)}</div>` : '';

                return `
                    <a href="personal.php?id=${member.id}" class="member-link">
                        <div class="member-card">
                            ${cardContent}
                            <div class="member-name">${escapeHtml(member.name)}</div>
                            ${nicknameHtml}
                            <div class="member-birthday">${birthdayStr || '🎂'}</div>
                            <div class="member-quote">${escapeHtml(member.quote || '')}</div>
                        </div>
                    </a>
                `;
            }).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        fetchClassmates();
    </script>

    <?php include '_footer.php'; ?>

</body>
</html>