<?php
// 登录失败限速（服务端、文件存储）
//
// 为什么不用 $_SESSION：换一个 cookie 就重置了，等于没有限速。
// 这里按“作用域 + 账号”计数，与 IP / Cookie 无关，所以
//   * 换浏览器、清 cookie 绕不过去；
//   * 伪造 CF-Connecting-IP / X-Forwarded-For 也绕不过去。
// 记录写在 data/login-guard.json，data/ 由 .htaccess 拒绝外部访问。

require_once __DIR__ . '/bootstrap.php';

function boya_login_guard_file()
{
    return boya_data_file('login-guard.json');
}

function boya_login_guard_key($scope, $identifier)
{
    return $scope . '|' . strtolower(trim((string)$identifier));
}

function boya_login_guard_load()
{
    $file = boya_login_guard_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function boya_login_guard_save($data)
{
    // 只保留最近 300 条，文件不会无限增长
    if (count($data) > 300) {
        $data = array_slice($data, -300, null, true);
    }
    @file_put_contents(
        boya_login_guard_file(),
        json_encode($data, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * 是否被锁定。返回 ['blocked' => bool, 'retry_after' => 秒]
 */
function boya_login_guard_check($scope, $identifier, $windowSeconds = 900)
{
    $key = boya_login_guard_key($scope, $identifier);
    $now = time();
    $data = boya_login_guard_load();
    $entry = $data[$key] ?? null;
    if (!is_array($entry)) {
        return ['blocked' => false, 'retry_after' => 0];
    }

    $until = (int)($entry['until'] ?? 0);
    if ($until > $now) {
        return ['blocked' => true, 'retry_after' => $until - $now];
    }

    // 窗口已过且没有生效中的锁定：清掉计数
    if ((int)($entry['first'] ?? 0) + $windowSeconds < $now) {
        unset($data[$key]);
        boya_login_guard_save($data);
    }
    return ['blocked' => false, 'retry_after' => 0];
}

/**
 * 记一次失败；达到阈值就锁定。
 */
function boya_login_guard_fail($scope, $identifier, $maxFails = 5, $lockSeconds = 900, $windowSeconds = 900)
{
    $key = boya_login_guard_key($scope, $identifier);
    $now = time();
    $data = boya_login_guard_load();
    $entry = $data[$key] ?? null;
    if (!is_array($entry) || (int)($entry['first'] ?? 0) + $windowSeconds < $now) {
        $entry = ['fails' => 0, 'first' => $now, 'until' => 0];
    }

    $entry['fails'] = (int)($entry['fails'] ?? 0) + 1;
    if ($entry['fails'] >= $maxFails) {
        $entry['until'] = $now + $lockSeconds;
    }
    $data[$key] = $entry;
    boya_login_guard_save($data);
    return $entry;
}

/**
 * 登录成功后清掉失败计数。
 */
function boya_login_guard_clear($scope, $identifier)
{
    $key = boya_login_guard_key($scope, $identifier);
    $data = boya_login_guard_load();
    if (isset($data[$key])) {
        unset($data[$key]);
        boya_login_guard_save($data);
    }
}

/**
 * 全局闸门：防止有人用海量不同账号名喷洒。
 * 阈值故意放高，避免影响正常同学登录。
 */
function boya_login_guard_global_check($scope)
{
    return boya_login_guard_check($scope . '-global', '*');
}

function boya_login_guard_global_fail($scope)
{
    return boya_login_guard_fail($scope . '-global', '*', 200, 900, 900);
}

/**
 * 统一的“是否被限速”判断，返回锁定时长（秒），0 表示放行。
 */
function boya_login_guard_blocked_seconds($scope, $identifier)
{
    $own = boya_login_guard_check($scope, $identifier);
    if ($own['blocked']) {
        return $own['retry_after'];
    }
    $global = boya_login_guard_global_check($scope);
    if ($global['blocked']) {
        return $global['retry_after'];
    }
    return 0;
}
