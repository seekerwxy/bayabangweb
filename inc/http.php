<?php
// HTTP 层的公共工具：JSON 输入 / 输出、请求信息。
//
// 以前每个接口文件都各写一套（api/classmates.php 的 json_response、
// _forum.php 的 forum_json、api/upload.php 的 upload_response、
// api/admin-memories.php 的 timeline_response、api/admin-messages.php 的
// admin_messages_response），参数顺序和返回形状都不一样。这里收成一套，
// 同时保留旧名字作为薄别名，避免一次改完所有调用点。

if (!function_exists('boya_json')) {
    /**
     * 输出 JSON 并结束请求。
     *
     * 约定：
     *   - 状态码 < 400 时补 'ok' => true，>= 400 时补 'ok' => false（没有就补，有就不动，
     *     所以对现有前端是纯新增字段，不会破坏任何已有判断）；
     *   - 一律 no-store：Cloudflare 或浏览器缓存住旧 JSON 会导致「改了却没变」。
     */
    function boya_json($payload, $status = 200)
    {
        $status = (int)$status;
        // 只给「关联数组」补 ok；裸数组（如同学录列表、时光轴列表）必须原样输出，
        // 否则前端拿到的就从 [{...}] 变成 {0:{...}, ok:true} 了。
        $isList = is_array($payload)
            && function_exists('array_is_list')
            && array_is_list($payload);
        if (is_array($payload) && !$isList && !isset($payload['ok'])) {
            $payload['ok'] = $status < 400;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('boya_json_ok')) {
    /** 成功响应：['ok' => true] + 业务字段。 */
    function boya_json_ok(array $extra = [], $status = 200)
    {
        boya_json(array_merge(['ok' => true], $extra), $status);
    }
}

if (!function_exists('boya_json_error')) {
    /** 失败响应：['ok' => false, 'message' => ...] + 额外字段（如 needLogin / needUsername）。 */
    function boya_json_error($message, $status = 400, array $extra = [])
    {
        boya_json(array_merge(['ok' => false, 'message' => (string)$message], $extra), $status);
    }
}

if (!function_exists('boya_json_input')) {
    /**
     * 读取请求体：优先按 JSON 解析（前台页面用 JSON 提交），
     * 拿不到就退回 $_POST（后台页面用普通表单提交）。
     */
    function boya_json_input()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $raw = file_get_contents('php://input');
        $input = json_decode((string)$raw, true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        if (!is_array($input)) {
            $input = [];
        }
        return $cached = $input;
    }
}

if (!function_exists('boya_require_method')) {
    /** 只允许某个 HTTP 方法，否则 405。 */
    function boya_require_method($method)
    {
        $actual = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (strtoupper($actual) !== strtoupper((string)$method)) {
            boya_json_error('仅支持 ' . strtoupper((string)$method), 405);
        }
    }
}

if (!function_exists('boya_client_ip')) {
    /**
     * 访客 IP（站点在 Cloudflare 后面，优先取真实 IP）。
     * 这些头都可以被伪造，所以只用于「去重 / 记录」，绝不作为安全判断依据。
     */
    function boya_client_ip()
    {
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
}

// ---------------------------------------------------------------------------
// 旧名字的薄别名（过渡用）。
// 逐个替换掉调用点之后可以整段删除；现在保留是为了让「改基础设施」和
// 「改业务代码」这两件事可以分开做、分开回滚。
// ---------------------------------------------------------------------------

if (!function_exists('forum_json')) {
    /** 原 _forum.php 的助手：输出 JSON 并结束（参数顺序与历史一致）。 */
    function forum_json($payload, $status = 200)
    {
        boya_json($payload, $status);
    }
}

if (!function_exists('timeline_response')) {
    /** 原 api/admin-memories.php 的助手（用 success/error 字段，前端就这么读的）。 */
    function timeline_response($payload, $status = 200)
    {
        boya_json($payload, $status);
    }
}

if (!function_exists('admin_messages_response')) {
    /** 原 api/admin-messages.php 的助手。 */
    function admin_messages_response($payload, $status = 200)
    {
        boya_json($payload, $status);
    }
}
