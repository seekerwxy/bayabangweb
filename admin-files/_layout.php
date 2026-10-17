<?php
// 后台页面统一骨架：登录守卫 + <head> + 页头 + 页脚。
//
// 以前 7 个后台页面各写一遍 doctype / head / 主题初始化脚本 / <style> / 页头 /
// 「返回面板 + 退出登录」表单，改一次样式要改 7 个文件。现在页面只需要：
//
//   require_once __DIR__ . '/_layout.php';
//   admin_page_head('📅 时光轴管理', ['stats' => '<span class="stats" id="totalCount">-</span>']);
//   ... 页面内容 ...
//   admin_page_foot();
//
// 页面自己的 JS 从 window.BOYA_ADMIN 里取 CSRF token 和站点根，
// 不用再在每个页面的脚本块里插 PHP 短标签。

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../inc/assets.php';
require_once __DIR__ . '/../inc/theme.php';   // boya_theme_init_script()：与前台同一份明暗/外观初始化

if (!function_exists('admin_logout_form')) {
    /** 「退出登录」表单（POST + CSRF，由 _auth.php 处理）。 */
    function admin_logout_form()
    {
        return '<form method="post" action="?logout=1" class="admin-logout-form">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(admin_csrf_token()) . '">'
            . '<input type="hidden" name="logout" value="1">'
            . '<button class="logout" type="submit">退出登录</button>'
            . '</form>';
    }
}

if (!function_exists('admin_page_head')) {
    /**
     * 输出页面头部（直到页头结束，后面由页面自己写内容）。
     *
     * $options:
     *   width        容器最大宽度（像素），默认 1000
     *   title        <title> 里的文字，默认与页头标题相同（页头带 emoji 时可单独指定）
     *   stats        标题右侧的统计节点 HTML
     *   actions      右侧额外按钮 HTML（如「添加事件」）
     *   back         是否显示「返回面板」链接，默认 true
     *   logout       是否显示「退出登录」，默认 true
     *   plain        是否使用无下边框的极简页头（管理面板首页用）
     *   description  可选的 meta description
     *   robots       meta robots，默认 noindex, nofollow
     */
    function admin_page_head($title, array $options = [])
    {
        $o = array_merge([
            'width' => 1000,
            'title' => '',
            'stats' => '',
            'actions' => '',
            'back' => true,
            'logout' => true,
            'plain' => false,
            'description' => '',
            'robots' => 'noindex, nofollow',
        ], $options);

        $siteName = '温二外25级博雅班';
        $pageTitle = (string)$o['title'] !== '' ? (string)$o['title'] : (string)$title;

        $actions = [];
        if ($o['back']) {
            $actions[] = '<a class="admin-link" href="admin-list.php">返回面板</a>';
        }
        if ($o['logout']) {
            $actions[] = admin_logout_form();
        }
        if ((string)$o['actions'] !== '') {
            $actions[] = (string)$o['actions'];
        }

        $bootConfig = json_encode([
            'csrf' => admin_csrf_token(),
            'root' => boya_site_prefix(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $html = "<!DOCTYPE html>\n"
            . '<html lang="zh-CN">' . "\n"
            . "<head>\n"
            . "    <meta charset=\"UTF-8\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
            . '    <title>' . htmlspecialchars($pageTitle) . ' | ' . $siteName . "</title>\n";
        if ((string)$o['description'] !== '') {
            $html .= '    <meta name="description" content="' . htmlspecialchars($o['description']) . "\">\n";
        }
        $html .= '    <meta name="robots" content="' . htmlspecialchars($o['robots']) . "\">\n"
            . '    ' . boya_theme_init_script() . "\n"
            . '    <link rel="stylesheet" href="' . boya_asset('css/admin-ui.css') . "\">\n"
            . '    <link rel="stylesheet" href="' . boya_asset('css/admin-dark.css') . "\">\n"
            . "</head>\n"
            . "<body>\n"
            . '<script>window.BOYA_ADMIN = ' . $bootConfig . ";</script>\n"
            . '<div class="container" style="max-width: ' . (int)$o['width'] . "px\">\n"
            . '    <div class="admin-header' . ($o['plain'] ? ' admin-header--plain' : '') . "\">\n"
            . "        <div class=\"admin-header-main\">\n"
            . '            <h1>' . htmlspecialchars($title) . "</h1>\n"
            . ($o['stats'] !== '' ? '            ' . $o['stats'] . "\n" : '')
            . "        </div>\n"
            . "        <div class=\"admin-header-actions\">\n"
            . '            ' . implode("\n            ", $actions) . "\n"
            . "        </div>\n"
            . "    </div>\n";

        echo $html;
    }
}

if (!function_exists('admin_page_foot')) {
    /** 关掉容器与文档。 */
    function admin_page_foot()
    {
        echo "</div>\n</body>\n</html>\n";
    }
}
