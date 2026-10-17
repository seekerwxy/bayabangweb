<?php
// 安全设置页（仅管理员）：生成管理员口令 hash、批量升级学生明文口令、查看操作审计。
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../inc/audit.php';

$error = '';
$hashResult = '';
$upgradeResult = null;

$conn = boya_db('classmates');
$dbError = null;
if (!$conn) {
    error_log('security page DB connect failed');
    $dbError = '数据库连接失败，暂时无法统计学生口令状态';
}

$adminPassword = $config['admin_db']['password'] ?? '';
$adminIsHash = boya_admin_password_is_hash($adminPassword);
$adminIsSet = is_string($adminPassword) && $adminPassword !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF 校验失败，请刷新页面后重试';
    } else {
        $action = $_POST['action'] ?? '';

        // ---------- 生成管理员口令 hash ----------
        if ($action === 'hash') {
            $new = (string)($_POST['new_password'] ?? '');
            if (mb_strlen($new) < 12) {
                $error = '新口令至少 12 位（建议 16 位以上随机字符串）';
            } else {
                $hashResult = password_hash($new, PASSWORD_DEFAULT);
                boya_audit('admin-hash-generate', 'length=' . mb_strlen($new));
            }
        }

        // ---------- 把学生明文口令批量升级成 hash ----------
        if ($action === 'upgrade') {
            if ($dbError) {
                $error = $dbError;
            } else {
                $bcryptLike = '$2%';
                $argonLike = '$argon2%';
                $stmt = $conn->prepare("SELECT id, name, password FROM classmates
                                        WHERE password IS NOT NULL AND password <> ''
                                          AND password NOT LIKE ? AND password NOT LIKE ?
                                        ORDER BY id");
                if (!$stmt) {
                    error_log('security upgrade prepare failed: ' . $conn->error);
                    $error = '查询失败，请稍后重试';
                } else {
                    $stmt->bind_param('ss', $bcryptLike, $argonLike);
                    $stmt->execute();
                    $rows = $stmt->get_result();
                    $update = $conn->prepare('UPDATE classmates SET password = ? WHERE id = ?');
                    $upgraded = 0;
                    $failed = 0;
                    if ($update) {
                        while ($row = $rows->fetch_assoc()) {
                            $hash = password_hash((string)$row['password'], PASSWORD_DEFAULT);
                            $id = (int)$row['id'];
                            $update->bind_param('si', $hash, $id);
                            if ($update->execute()) {
                                $upgraded++;
                            } else {
                                $failed++;
                            }
                        }
                        $update->close();
                    } else {
                        error_log('security upgrade update-prepare failed: ' . $conn->error);
                        $error = '更新语句准备失败，请稍后重试';
                    }
                    $stmt->close();
                    $upgradeResult = ['upgraded' => $upgraded, 'failed' => $failed];
                    boya_audit('student-password-upgrade', 'upgraded=' . $upgraded . ' failed=' . $failed);
                }
            }
        }
    }
}

// ---------- 统计 ----------
$totalStudents = null;
$plainStudents = null;
$emptyStudents = null;
if (!$dbError) {
    $res = $conn->query('SELECT COUNT(*) AS c FROM classmates');
    if ($res) {
        $totalStudents = (int)($res->fetch_assoc()['c'] ?? 0);
    }

    $bcryptLike = '$2%';
    $argonLike = '$argon2%';
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM classmates
                            WHERE password IS NOT NULL AND password <> ''
                              AND password NOT LIKE ? AND password NOT LIKE ?");
    if ($stmt) {
        $stmt->bind_param('ss', $bcryptLike, $argonLike);
        $stmt->execute();
        $plainStudents = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
    }

    $res = $conn->query("SELECT COUNT(*) AS c FROM classmates WHERE password IS NULL OR password = ''");
    if ($res) {
        $emptyStudents = (int)($res->fetch_assoc()['c'] ?? 0);
    }
}

$logs = boya_audit_tail(100);

admin_page_head('🔐 安全设置', ['width' => 1000, 'title' => '安全设置']);
?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- 管理员口令 -->
    <div class="card">
        <h2>管理员口令</h2>
        <?php if (!$adminIsSet): ?>
            <div class="alert alert-error">配置里没有读到管理员口令，请检查 <code>config.local.php</code> 的 <code>admin_db</code>。</div>
        <?php elseif ($adminIsHash): ?>
            <div class="alert alert-ok">当前以 <code>password_hash()</code> 哈希形式保存。</div>
        <?php else: ?>
            <div class="alert">当前以<b>明文</b>保存在 <code>config.local.php</code> 里。站点一直支持这种写法，<b>保持不动完全可以</b>，本页也不会去改它。</div>
        <?php endif; ?>

        <p><b>（可选）</b>生成管理员口令 hash：只是把你在下面输入的内容算成一个哈希并显示出来，<b>不写入任何文件或数据库，也不会改掉现在的口令</b>。只有你主动把这串填进 GitHub 的 <code>CONFIG_LOCAL_PHP</code> Secret 并 push，登录口令才会变；不填就什么都不变。</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <input type="hidden" name="action" value="hash">
            <input type="password" name="new_password" placeholder="输入新口令（至少 12 位，建议 16 位以上随机）" autocomplete="new-password">
            <button class="btn" type="submit" style="margin-top:10px;">生成 hash</button>
        </form>

        <?php if ($hashResult !== ''): ?>
            <p style="margin-top:14px;">把下面这串填到 GitHub 仓库的 <code>CONFIG_LOCAL_PHP</code> Secret 里，替换 <code>'password' =&gt; '...'</code> 的值，然后 push（或在 Actions 里 Run workflow）：</p>
            <p><code><?= htmlspecialchars($hashResult) ?></code></p>
            <p class="muted">旧的明文口令在替换前仍然可用；替换后请确认新口令能登录，并清掉浏览器里保存的旧口令。</p>
        <?php endif; ?>
    </div>

    <!-- 学生口令 -->
    <div class="card">
        <h2>学生口令</h2>
        <?php if ($dbError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($dbError) ?></div>
        <?php else: ?>
            <p>
                共 <b><?= $totalStudents === null ? '?' : (int)$totalStudents ?></b> 位同学；
                仍为明文 <b><?= $plainStudents === null ? '?' : (int)$plainStudents ?></b> 位；
                空口令 <b><?= $emptyStudents === null ? '?' : (int)$emptyStudents ?></b> 位（无法登录，需要单独重置）。
            </p>
            <p class="muted">这一块<b>只影响同学录里的学生账号，不碰管理员账号</b>；也<b>不会改掉同学的密码本身</b>——只是把数据库里的保存形式从明文换成哈希，同学仍然用原来那个密码登录。同学自己登录时也会自动完成这一步，所以这个按钮只是把剩下的一次性做完。</p>
            <?php if ($upgradeResult !== null): ?>
                <div class="alert alert-ok">已升级 <?= (int)$upgradeResult['upgraded'] ?> 条，失败 <?= (int)$upgradeResult['failed'] ?> 条。</div>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('确定把所有明文口令升级为 hash 吗？同学仍用原口令登录。');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                <input type="hidden" name="action" value="upgrade">
                <button class="btn btn-warn" type="submit">批量升级明文口令</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- 审计日志 -->
    <div class="card">
        <h2>最近操作（最新 100 条）</h2>
        <?php if (!$logs): ?>
            <p class="muted">还没有记录。登录、增删改留言/时光轴、上传头像都会写在这里。</p>
        <?php else: ?>
            <table>
                <thead><tr><th>时间</th><th>操作者</th><th>IP</th><th>动作</th><th>对象</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)($row['time'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($row['actor'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($row['ip'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($row['action'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($row['detail'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php
admin_page_foot();
