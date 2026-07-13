<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人主页</title>
    <link rel="stylesheet" href="css/beauty.css">
    <style>
        /* 个人主页专用样式 */
        .profile-container {
            max-width: 700px;
            margin: 0 auto 40px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            padding: 30px 35px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #f4f3f1;
            border: 1px dashed #c0c0c0;
            flex-shrink: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar-placeholder {
            font-size: 28px;
            color: #b0b0b0;
        }
        .profile-name {
            font-family: var(--font-serif);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .profile-nickname {
            font-size: 16px;
            color: #888;
            margin-bottom: 6px;
        }
        .profile-quote {
            font-style: italic;
            color: #5c5c5c;
            font-size: 15px;
        }
        .profile-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            margin-top: 20px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 15px;
            color: #1a1a1a;
            word-break: break-word;
        }
        .detail-value.empty {
            color: #ccc;
            font-style: italic;
        }
        .profile-actions {
            margin-top: 28px;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 24px;
        }
        .edit-btn {
            display: inline-block;
            background: #1a1a1a;
            color: #fff;
            padding: 8px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        .edit-btn:hover {
            background: #333;
        }
        .back-link {
            display: inline-block;
            margin-right: 16px;
            color: #5c5c5c;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-details {
                grid-template-columns: 1fr;
            }
            .profile-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="nav" id="navbar">
        <div class="nav-inner">
            <a href="index.html" class="nav-badge" title="班级首页">
                <img src="photos/banhui.jpg" alt="班徽" width="38" height="38"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <span class="nav-badge-placeholder" style="display:none;">班徽</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.html">首页</a></li>
                <li><a href="teacher.html">班主任</a></li>
                <li><a href="classmates.html" class="active">同学录</a></li>
                <li><a href="memories.html">时光回忆馆</a></li>
                <li><a href="messages.html">留言板</a></li>
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
            <a href="index.html">首页</a>
            <a href="teacher.html">班主任专属空间</a>
            <a href="classmates.html" class="active">同学录</a>
            <a href="memories.html">时光回忆馆</a>
            <a href="messages.html">留言板</a>
        </div>
    </nav>

    <main class="main">
        <section class="page-header">
            <div class="badge">Profile</div>
            <h2>个人主页</h2>
        </section>

        <div id="profileContent">
            <div class="empty-message">加载中...</div>
        </div>
    </main>

    <footer class="footer">
        <span>献给最好的班级和班主任</span>
        <span class="footer-line">— 我已燃尽 —</span>
    </footer>

    <script>
        // 汉堡菜单
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
            // 头像
            const avatarHtml = data.avatar
                ? `<img src="${escapeHtml(data.avatar)}" alt="头像" onerror="this.onerror=null;this.parentElement.innerHTML='<span class=\'profile-avatar-placeholder\'>${escapeHtml(data.name.charAt(0))}</span>';">`
                : `<span class="profile-avatar-placeholder">${escapeHtml(data.name.charAt(0))}</span>`;

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
                    val = '<span class="empty">未填写</span>';
                }
                detailsHtml += `
                    <div class="detail-item">
                        <span class="detail-label">${f.label}</span>
                        <span class="detail-value">${val}</span>
                    </div>
                `;
            });

            // 判断是否本人（通过 localStorage 存储的登录信息）
            const storedName = localStorage.getItem('profile_name');
            const storedPass = localStorage.getItem('profile_password');
            const isSelf = (storedName && storedName === data.name);

            const editBtnHtml = isSelf
                ? `<a href="edit-profile.html" class="edit-btn">编辑我的资料</a>`
                : `<span style="color:#888;font-size:13px;">如需修改，请登录本人账号</span>`;

            const html = `
                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            ${avatarHtml}
                        </div>
                        <div>
                            <div class="profile-name">${escapeHtml(data.name)}</div>
                            ${data.nickname ? `<div class="profile-nickname">“${escapeHtml(data.nickname)}”</div>` : ''}
                            ${data.quote ? `<div class="profile-quote">“${escapeHtml(data.quote)}”</div>` : ''}
                        </div>
                    </div>
                    <div class="profile-details">
                        ${detailsHtml}
                    </div>
                    <div class="profile-actions">
                        <a href="classmates.html" class="back-link">← 返回同学录</a>
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
</body>
</html>