<?php
// 管理面板首页：各功能入口。仅管理员可见。
require_once __DIR__ . '/_layout.php';

$entries = [
    ['title' => '时光轴管理', 'desc' => '添加、编辑、删除班级时光事件。', 'href' => 'admin-memories.php'],
    ['title' => '留言管理', 'desc' => '编辑或删除留言板内容。', 'href' => 'admin-messages.php'],
    ['title' => '安全设置', 'desc' => '生成管理员口令 hash、批量升级学生明文口令、查看操作日志。', 'href' => 'admin-security.php'],
    ['title' => '旧留言导入', 'desc' => '把旧留言板的内容一次性导入成论坛帖子（可重复点，已导入的不会重复）。', 'href' => 'admin-migrate.php'],
    ['title' => '论坛管理', 'desc' => '帖子列表、置顶 / 锁定 / 删除 / 恢复。', 'href' => 'admin-forum.php'],
    ['title' => '账号管理', 'desc' => '同学名单、设称号、设论坛管理员、重置口令、分配用户名、新增同学。', 'href' => 'admin-users.php'],
];

admin_page_head('管理面板', ['plain' => true, 'back' => false, 'width' => 720]);
?>
<?php foreach ($entries as $entry): ?>
<div class="entry-card">
    <div>
        <strong><?= htmlspecialchars($entry['title']) ?></strong>
        <span><?= htmlspecialchars($entry['desc']) ?></span>
    </div>
    <a class="btn" href="<?= htmlspecialchars($entry['href']) ?>">进入</a>
</div>
<?php endforeach; ?>
<?php
admin_page_foot();
