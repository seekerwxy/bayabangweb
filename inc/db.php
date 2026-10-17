<?php
// 数据库连接与「结构同步」闸门。
//
// 这个站点连着两个互相独立的库（classmates 同学录 / messages 留言板），
// 两边用户不同、不能跨库 JOIN。以前有 6 处各写一遍 new mysqli，
// 其中 3 处（时光轴、留言管理、安全设置）连参数都是复制粘贴的。
//
// 现在统一成 boya_db('classmates') / boya_db('messages')：
//   - 同一个请求里只连一次（静态缓存）；
//   - 连接失败返回 null，由调用方决定是降级还是报错（各接口的既有行为不变）。
//
// 另外提供 boya_schema_once()：自动补列 / 建表这类「结构同步」以前是**每个请求都跑**
// （每次请求一次 SHOW COLUMNS，论坛甚至两次 CREATE TABLE IF NOT EXISTS）。
// 现在按「结构版本号 + 结果」记在 data/schema-state.json 里，成功过就不再跑；
// 失败则最多每小时重试一次，避免把线上拖死。data/ 不可写时会退化成原来每次跑的行为。

if (!function_exists('boya_db_config')) {
    /** 取某个库的配置段（classmates_db / messages_db）。 */
    function boya_db_config($name)
    {
        global $config;
        $section = isset($config[$name . '_db']) && is_array($config[$name . '_db'])
            ? $config[$name . '_db']
            : [];
        return [
            'host' => (string)($section['host'] ?? ''),
            'username' => (string)($section['username'] ?? ''),
            'password' => (string)($section['password'] ?? ''),
            'database' => (string)($section['database'] ?? ''),
        ];
    }
}

if (!function_exists('boya_db')) {
    /**
     * 连接指定库，失败返回 null。
     *
     * @param string $name 'classmates' | 'messages'
     * @return mysqli|null
     */
    function boya_db($name)
    {
        static $pool = [];
        if (array_key_exists($name, $pool)) {
            return $pool[$name];
        }

        $cfg = boya_db_config($name);
        if ($cfg['host'] === '' || $cfg['database'] === '') {
            error_log('DB config missing (' . $name . ')');
            return $pool[$name] = null;
        }

        // mysqli_report(MYSQLI_REPORT_OFF) 已在 config.php 里设好，连接失败不会抛异常，
        // 这里用 @ 再压一下 warning，错误统一走 error_log。
        $conn = @new mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
        if (!$conn instanceof mysqli || $conn->connect_error) {
            $reason = $conn instanceof mysqli ? (string)$conn->connect_error : 'connect failed';
            error_log('DB connect failed (' . $name . '): ' . $reason);
            return $pool[$name] = null;
        }
        $conn->set_charset('utf8mb4');
        return $pool[$name] = $conn;
    }
}

if (!function_exists('boya_schema_state_read')) {
    function boya_schema_state_read()
    {
        $file = boya_data_file('schema-state.json');
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('boya_schema_once')) {
    /**
     * 结构同步只做一次。
     *
     * @param string   $key     结构项名字（如 'classmates-columns'）
     * @param string   $version 结构版本号；改动同步逻辑时改这个值，就会重新跑一次
     * @param callable $sync    真正干活；返回 true 表示同步完成，false 表示还得重试
     * @return bool 这次有没有真的执行
     */
    function boya_schema_once($key, $version, callable $sync)
    {
        $state = boya_schema_state_read();
        $entry = isset($state[$key]) && is_array($state[$key]) ? $state[$key] : null;

        if ($entry && (string)($entry['version'] ?? '') === (string)$version) {
            // 成功过就永远跳过；失败过则每小时最多重试一次
            if (!empty($entry['ok']) || (time() - (int)($entry['at'] ?? 0)) < 3600) {
                return false;
            }
        }

        $ok = (bool)call_user_func($sync);

        $state[$key] = ['version' => (string)$version, 'ok' => $ok, 'at' => time()];
        if (!@file_put_contents(boya_data_file('schema-state.json'), json_encode($state), LOCK_EX)) {
            // 写不进去就退化成「每次请求都同步」——慢一点，但不会少做事
            error_log('schema state write failed: ' . boya_data_file('schema-state.json'));
        }
        return true;
    }
}
