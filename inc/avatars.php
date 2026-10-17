<?php
// 同学头像文件的相关工具：存放目录、文件名规则、旧文件清理、配额检查。
//
// 以前这些逻辑散在 api/classmates.php（删除旧头像）和 api/upload.php（目录、配额、
// 文件名生成）两处，规则改一次要改两个文件；现在集中在这里。
//
// 两个目录的历史背景：
//   /uploads/classmates/     新目录（当前写入）
//   /api/photos/classmates/  旧目录（老数据里的头像路径还指向这里，文件在就照旧能显示）

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('boya_avatar_dirs')) {
    /** 允许写/删头像的目录（相对站点根，带首尾斜杠）。 */
    function boya_avatar_dirs()
    {
        return ['/uploads/classmates/', '/api/photos/classmates/'];
    }
}

if (!function_exists('boya_avatar_dir')) {
    /** 当前新头像的存放目录（相对站点根的 Web 路径）。 */
    function boya_avatar_dir()
    {
        return '/uploads/classmates/';
    }
}

if (!function_exists('boya_avatar_abs_dir')) {
    /** 当前新头像目录的磁盘绝对路径（不存在则创建）。 */
    function boya_avatar_abs_dir()
    {
        $dir = BOYA_ROOT . boya_avatar_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('boya_avatar_file_name')) {
    /**
     * 是不是「本站生成的头像文件名」——只有这种文件才允许被自动删除。
     *
     * 新版本：32 位十六进制 + 图片扩展名；旧版本：avatar_<13位左右十六进制>.jpeg。
     * 十六进制段不再限制为 32 位：早期用 uniqid() 生成的 avatar_<13位hex>.jpeg
     * 用老规则匹配不到，导致这些旧头像永远删不掉、越积越多。
     */
    function boya_avatar_file_name($name)
    {
        return preg_match('/^(?:avatar_)?[0-9a-f]{8,64}\.(?:jpg|jpeg|png|webp)$/i', (string)$name) === 1;
    }
}

if (!function_exists('boya_avatar_new_name')) {
    /** 生成一个新头像文件名（随机、不可猜、扩展名与上传白名单一致）。 */
    function boya_avatar_new_name($extension)
    {
        $extension = strtolower((string)$extension);
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}

if (!function_exists('boya_avatar_max_files')) {
    /** 目录文件数上限：免费主机只有 30000 inode / 5GB，不定上限谁都能把配额塞满。 */
    function boya_avatar_max_files()
    {
        return 3000;
    }
}

if (!function_exists('boya_avatar_count')) {
    function boya_avatar_count($absoluteDir)
    {
        $files = @glob(rtrim((string)$absoluteDir, '/') . '/*');
        return is_array($files) ? count($files) : 0;
    }
}

if (!function_exists('boya_remove_old_avatar')) {
    /**
     * 删除被替换掉的头像文件，避免上传目录无限膨胀。
     *
     * 只处理两个头像目录下的「本站生成的文件名」，其他路径、其他文件名一律不碰。
     */
    function boya_remove_old_avatar($oldAvatar, $newAvatar)
    {
        $old = (string)$oldAvatar;
        if ($old === '' || $old === (string)$newAvatar) {
            return;
        }
        // 只认这两个目录，并且只按「文件名」判断，不做任何路径层面的猜测
        if (!preg_match('#^/(uploads|api/photos)/classmates/([^/]+)$#', $old, $matches)) {
            return;
        }
        if (!boya_avatar_file_name($matches[2])) {
            return;
        }

        $path = BOYA_ROOT . '/' . $matches[1] . '/classmates/' . $matches[2];
        if (is_file($path) && !@unlink($path)) {
            error_log('old avatar delete failed: ' . $path);
        }
    }
}
