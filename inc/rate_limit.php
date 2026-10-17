<?php
// 通用频率限制 / 每日配额：文件存储，不依赖 Cookie 或 IP（换浏览器、伪造 IP 头都绕不过）。
//
// 用法：
//   $r = boya_rate_hit('post|member:12', 1, 60);        // 60 秒最多 1 次
//   if (!$r['allowed']) { ... 让用户等 $r['retry_after'] 秒 ... }
//   $d = boya_rate_hit('postday|member:12', 20, 86400); // 每天最多 20 次
//
// 说明：读-改-写没有加锁，极端并发下可能多放行一两次；对班级网站足够，
// 真正的硬限制由每日配额兜底。

require_once __DIR__ . '/bootstrap.php';

function boya_rate_file()
{
    return boya_data_file('rate-limit.json');
}

function boya_rate_load()
{
    $file = boya_rate_file();
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

function boya_rate_save($data)
{
    // 键太多就丢掉最旧的一部分，文件不会无限增长
    if (count($data) > 400) {
        $data = array_slice($data, -400, null, true);
    }
    @file_put_contents(boya_rate_file(), json_encode($data), LOCK_EX);
}

/**
 * 记一次命中并判断是否放行。
 * 返回 ['allowed' => bool, 'retry_after' => 秒, 'used' => 窗口内次数]
 */
function boya_rate_hit($key, $maxHits, $windowSeconds)
{
    $now = time();
    $maxHits = max(1, (int)$maxHits);
    $windowSeconds = max(1, (int)$windowSeconds);

    $data = boya_rate_load();
    $hits = $data[$key] ?? [];
    if (!is_array($hits)) {
        $hits = [];
    }

    // 只保留窗口内的记录
    $kept = [];
    foreach ($hits as $ts) {
        $ts = (int)$ts;
        if ($ts > $now - $windowSeconds) {
            $kept[] = $ts;
        }
    }

    if (count($kept) >= $maxHits) {
        sort($kept);
        $retry = max(1, $windowSeconds - ($now - (int)$kept[0]));
        $data[$key] = $kept;
        boya_rate_save($data);
        return ['allowed' => false, 'retry_after' => $retry, 'used' => count($kept)];
    }

    $kept[] = $now;
    $data[$key] = $kept;
    boya_rate_save($data);
    return ['allowed' => true, 'retry_after' => 0, 'used' => count($kept)];
}
