<?php
// 关键操作审计：JSON Lines 追加写入 data/admin-audit.log（data/ 由 .htaccess 拒绝外部访问）。
// 记录谁、什么时候、从哪个 IP、做了什么、对哪条数据做的。

function boya_audit_file()
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/admin-audit.log';
}

function boya_audit_client_ip()
{
    // 站点在 Cloudflare 后面，优先取真实访客 IP。
    // 这个头可以被伪造，所以只用于记录，不作为任何安全判断依据。
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim(explode(',', (string)$_SERVER[$key])[0]);
            if ($value !== '') {
                return substr($value, 0, 45);
            }
        }
    }
    return 'unknown';
}

function boya_audit_actor()
{
    if (!empty($_SESSION['admin_logged_in'])) {
        return 'admin';
    }
    if (!empty($_SESSION['classmate_name'])) {
        return 'classmate:' . $_SESSION['classmate_name'];
    }
    if (!empty($_SESSION['classmate_id'])) {
        return 'classmate#' . (int)$_SESSION['classmate_id'];
    }
    return 'guest';
}

function boya_audit($action, $detail = '', $actor = null)
{
    if ($actor === null) {
        $actor = boya_audit_actor();
    }

    $time = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    $line = json_encode([
        'time' => $time->format('Y-m-d H:i:s'),
        'actor' => (string)$actor,
        'ip' => boya_audit_client_ip(),
        'action' => (string)$action,
        'detail' => (string)$detail,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($line === false) {
        return;
    }
    @file_put_contents(boya_audit_file(), $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * 读取最近的审计记录（最新在前）。
 */
function boya_audit_tail($limit = 100)
{
    $file = boya_audit_file();
    if (!is_file($file)) {
        return [];
    }
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || !$lines) {
        return [];
    }
    // 简单截断：超过 2000 行只保留最后 1000 行
    if (count($lines) > 2000) {
        $lines = array_slice($lines, -1000);
        @file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
    }

    $rows = [];
    foreach (array_slice($lines, -$limit) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $rows[] = $row;
        }
    }
    return array_reverse($rows);
}
