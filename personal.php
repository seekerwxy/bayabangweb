<?php $currentPage = 'classmates'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人主页</title>
    <link rel="stylesheet" href="css/beauty.css">
</head>
<body>

    <main class="main">
        <section class="page-header">
            <div class="badge">Profile</div>
            <h2>个人主页</h2>
        </section>

        <div id="profileContent">
            <div class="empty-message">加载中...</div>
        </div>
    </main>

    <script>
        // 汉堡菜单（不变）
        (function() {
            const btn = document.getElementById('hamburgerBtn');
            const dropdown = document.getElementById('mobileDropdown');
            if (!btn || !dropdown) return;
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('visible');
                btn.classList.toggle('open');
                btn.setAttribute('aria-expanded', dropdown.classList.contains('visible'));
            });
            dropdown.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    dropdown.classList.remove('visible');
                    btn.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                });
            });
            document.addEventListener('click', function(event) {
                if (!dropdown.contains(event.target) && event.target !== btn && !btn.contains(event.target)) {
                    dropdown.classList.remove('visible');
                    btn.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        })();

        // ---------- 获取并渲染个人资料 ----------
        const API_BASE = '/api/classmates.php';
        const container = document.getElementById('profileContent');
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');

        if (!id) {
            container.innerHTML = '<div class="empty-message">缺少同学ID</div>';
        } else {
            fetch(`${API_BASE}?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.message) {
                        container.innerHTML = `<div class="empty-message">${data.message}</div>`;
                        return;
                    }
                    renderProfile(data);
                })
                .catch(err => {
                    container.innerHTML = '<div class="empty-message">加载失败，请重试</div>';
                    console.error(err);
                });
        }

        function renderProfile(data) {
            // 头像 —— 使用新的前缀类
            const avatarHtml = data.avatar
                ? `<img src="${escapeHtml(data.avatar)}" alt="头像" onerror="this.onerror=null;this.parentElement.innerHTML='<span class=\'personal-profile-avatar-placeholder\'>${escapeHtml(data.name.charAt(0))}</span>';">`
                : `<span class="personal-profile-avatar-placeholder">${escapeHtml(data.name.charAt(0))}</span>`;

            // 性别显示
            const genderMap = { '男': '♂ 男', '女': '♀ 女', '其他': '其他' };
            const genderDisplay = genderMap[data.gender] || '其他';

            const fields = [
                { label: '昵称/外号', key: 'nickname' },
                { label: '性别', key: 'gender', value: genderDisplay },
                { label: '家乡/出生地', key: 'hometown' },
                { label: '兴趣爱好', key: 'hobbies' },
                { label: '特长/技能', key: 'skills' },
                { label: '联系方式', key: 'contact_info' },
                { label: '生日', key: 'birthday' },
                { label: '座右铭', key: 'quote' }
            ];

            let detailsHtml = '';
            fields.forEach(f => {
                let val = f.value !== undefined ? f.value : data[f.key];
                if (f.key === 'birthday' && val) {
                    // 已经格式化
                }
                if (!val || val === '') {
                    val = '<span class="personal-empty">未填写</span>';
                }
                detailsHtml += `
                    <div class="personal-detail-item">
                        <span class="personal-detail-label">${f.label}</span>
                        <span class="personal-detail-value">${val}</span>
                    </div>
                `;
            });

            // 判断是否本人（通过 localStorage 存储的登录信息）
            const storedName = localStorage.getItem('profile_name');
            const storedPass = localStorage.getItem('profile_password');
            const isSelf = (storedName && storedName === data.name);

            const editBtnHtml = isSelf
                ? `<a href="edit-profile.html" class="personal-edit-btn">编辑我的资料</a>`
                : `<span style="color:#888;font-size:13px;">如需修改，请登录本人账号</span>`;

            const html = `
                <div class="personal-profile-container">
                    <div class="personal-profile-header">
                        <div class="personal-profile-avatar">
                            ${avatarHtml}
                        </div>
                        <div>
                            <div class="personal-profile-name">${escapeHtml(data.name)}</div>
                            ${data.nickname ? `<div class="personal-profile-nickname">“${escapeHtml(data.nickname)}”</div>` : ''}
                            ${data.quote ? `<div class="personal-profile-quote">“${escapeHtml(data.quote)}”</div>` : ''}
                        </div>
                    </div>
                    <div class="personal-profile-details">
                        ${detailsHtml}
                    </div>
                    <div class="personal-profile-actions">
                        <a href="classmates.html" class="personal-back-link">← 返回同学录</a>
                        ${editBtnHtml}
                    </div>
                </div>
            `;
            container.innerHTML = html;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>

    <?php include '_footer.php'; ?>

</body>
</html>