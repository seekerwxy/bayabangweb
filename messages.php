<?php $currentPage = 'messages'; include '_header.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博雅班留言板</title>
    <meta name="description" content="博雅班留言板，同学们可以在这里留下对班级的祝福和期许。">
    <link rel="stylesheet" href="css/beauty.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>
<body>

    <main class="main">
        <section class="page-header">
            <div class="badge">Message Board</div>
            <h2>留言板</h2>
        </section>
        <div class="message-form-section">
            <h3>写下你的话</h3>
            <p class="form-desc">对班级的祝福、对未来的期许，或者任何想说的话，都会留在这里。</p>
            <form id="messageForm">
                <div class="form-group">
                    <label for="messageTitle">标题</label>
                    <input type="text" id="messageTitle" name="title" placeholder="给你的留言起个名字" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="messageContent">正文（支持 <a href="text/Markdown.html" target="_blank">Markdown</a>）</label>
                    <textarea id="messageContent" name="content" placeholder="支持 **粗体**、*斜体*、[链接](url) 等语法..." required maxlength="2000"></textarea>
                </div>
                <div class="form-group">
                    <label for="authorName">署名</label>
                    <input type="text" id="authorName" name="author" placeholder="你的名字" required maxlength="30">
                </div>
                <div class="form-group">
                    <label>文字颜色（可选）</label>
                    <div id="colorPicker" class="color-picker">
                        <span class="color-swatch" style="background: #000000" data-color="#000000"></span>
                        <span class="color-swatch" style="background: #e60000" data-color="#e60000"></span>
                        <span class="color-swatch" style="background: #ff9900" data-color="#ff9900"></span>
                        <span class="color-swatch" style="background: #008000" data-color="#008000"></span>
                        <span class="color-swatch" style="background: #0000ff" data-color="#0000ff"></span>
                        <span class="color-swatch" style="background: #800080" data-color="#800080"></span>
    					<span class="color-swatch" style="background: #ff1493" data-color="#ff1493" title="深粉"></span>
    					<span class="color-swatch" style="background: #00ced1" data-color="#00ced1" title="青色"></span>
    					<span class="color-swatch" style="background: #32cd32" data-color="#32cd32" title="亮绿"></span>
    					<span class="color-swatch" style="background: #ffd700" data-color="#ffd700" title="金色"></span>
    					<span class="color-swatch" style="background: #ff4500" data-color="#ff4500" title="橙红"></span>
   					    <span class="color-swatch" style="background: #8a2be2" data-color="#8a2be2" title="蓝紫"></span>
                        <input type="hidden" id="messageColor" name="color" value="">
                    </div>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn">发布留言</button>
                <div class="form-feedback" id="formFeedback"></div>
            </form>         
        </div>
        <div class="messages-section">
            <h3>大家的祝福</h3>
            <p class="sub">每一张便利贴，都是一份温暖</p>
            <div class="messages-list" id="messagesList">
                <div class="empty-message">正在加载留言…</div>
            </div>
        </div>
    </main>

    <script>
       
        // ---------- 颜色快捷插入（点击彩色圆点） ----------
        document.querySelectorAll('.color-swatch').forEach(dot => {
            dot.addEventListener('click', function() {
                const color = this.dataset.color;
                const textarea = document.getElementById('messageContent');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selectedText = textarea.value.substring(start, end) || '文字';
                const tag = `[color=${color}]${selectedText}[/color]`;
                textarea.setRangeText(tag, start, end, 'end');
                textarea.focus();
            });
        });

        // ---------- 留言板 API 配置 ----------
        const API_BASE = '/api/messages.php';

        // 获取所有留言
        async function fetchMessages() {
            try {
                const response = await fetch(API_BASE, {
                    method: 'GET',
                    mode: 'same-origin',
                    cache: 'no-cache'
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const messages = await response.json();
                renderMessages(messages);
            } catch (error) {
                console.error('获取留言出错:', error);
                document.getElementById('messagesList').innerHTML =
                    '<div class="empty-message">暂时无法加载留言，请稍后再试</div>';
            }
        }

            // 提交新留言
        async function submitMessage(data) {
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                mode: 'same-origin'
            });
            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message || '提交失败');
            }
            return response.json();
        }

        // 安全的 HTML 转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 将 [color=#rrggbb]...[/color] 转换为 <span style="color:#rrggbb">...</span>
        function convertColorTags(text) {
            const colorRegex = /\[color=(#[0-9a-fA-F]{6})\](.*?)\[\/color\]/g;
            return text.replace(colorRegex, (match, color, inner) => {
                // 内部文本可包含任意字符（但不支持嵌套），直接构建 span
                return `<span style="color:${color}">${inner}</span>`;
            });
        }

        // 渲染留言列表
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!messages || messages.length === 0) {
                container.innerHTML = '<div class="empty-message">还没有留言，快来写下第一条吧 ✍️</div>';
                return;
            }

            const sorted = [...messages].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            container.innerHTML = sorted.map(msg => {
                const time = new Date(msg.created_at).toLocaleString('zh-CN', {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                });

                // 标题（若存在）
                const titleHtml = msg.title ? `<h4 class="msg-title">${escapeHtml(msg.title)}</h4>` : '';

                // 正文：先转换颜色标记，再解析 Markdown
                let bodyHtml = convertColorTags(msg.content);
                bodyHtml = marked.parse(bodyHtml);

                // 判断是否是 id=7 的留言
                const isHighlight = Number(msg.id) === 1;
                const cardClass = isHighlight ? 'message-card glow-card' : 'message-card';

                return `
                    <div class="${cardClass}">
                        ${titleHtml}
                        <div class="message-body">${bodyHtml}</div>
                        <div class="message-meta">
                            <span class="message-author">—— ${escapeHtml(msg.author)}</span>
                            <span class="message-time">${time}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // 表单提交处理
        document.getElementById('messageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            const feedback = document.getElementById('formFeedback');
            const titleInput = document.getElementById('messageTitle');
            const contentInput = document.getElementById('messageContent');
            const authorInput = document.getElementById('authorName');

            const title = titleInput.value.trim();
            const content = contentInput.value.trim();
            const author = authorInput.value.trim();

            if (!author || !content) {
                feedback.textContent = '请至少填写署名和正文';
                feedback.className = 'form-feedback error';
                return;
            }
            if (content.length > 2000) {
                feedback.textContent = '正文不能超过2000字符';
                feedback.className = 'form-feedback error';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '发布中...';
            feedback.textContent = '';
            feedback.className = 'form-feedback';

            try {
                await submitMessage({ title, content, author });
                titleInput.value = '';
                contentInput.value = '';
                authorInput.value = '';
                feedback.textContent = '留言发布成功！';
                feedback.className = 'form-feedback success';
                fetchMessages();
            } catch (error) {
                feedback.textContent = error.message || '发布失败，请稍后重试';
                feedback.className = 'form-feedback error';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '发布留言';
            }
        });

        // 页面初始化
        fetchMessages();
    </script>

    <?php include '_footer.php'; ?>

</body>
</html>