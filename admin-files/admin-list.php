<?php
require_once __DIR__ . '/_auth.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博雅班管理页面列表</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 32px 20px; }
        .container { max-width: 720px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        h1 { font-size: 1.35rem; font-weight: 600; }
        .logout { color: #666; font-size: .85rem; }
        .card { background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; padding: 18px 20px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .card strong { display: block; margin-bottom: 4px; }
        .card span { color: #666; font-size: .85rem; }
        .btn { background: #111; color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; text-decoration: none; font-size: .85rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>管理面板</h1>
        <form method="post" action="?logout=1" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <button class="logout" type="submit" style="border:0;background:none;cursor:pointer;padding:0;">退出登录</button>
        </form>
    </div>
    <div class="card">
        <div><strong>时光轴管理</strong><span>添加、编辑、删除班级时光事件。</span></div>
        <a class="btn" href="admin-memories.php">进入</a>
    </div>
    <div class="card">
        <div><strong>留言管理</strong><span>编辑或删除留言板内容。</span></div>
        <a class="btn" href="admin-messages.php">进入</a>
    </div>
</div>
</body>
</html>
