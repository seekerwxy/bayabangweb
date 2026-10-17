<?php
// 外观（明暗 + 主题）相关的公共数据与脚本。
//
// 以前这些散在三处：_head.php 里的内联初始化脚本、_header.php 里手写的 6 个移动端
// 外观按钮、_footer.php 里手写的 6 个设置弹窗按钮，另外 7 个后台页面又各抄了一份
// 简化版初始化脚本。现在只有这里一份。

if (!function_exists('boya_style_options')) {
    /**
     * 可选外观。key 对应 html[data-style] 的取值，也就是 css/<key>.css 的开关
     * （default 用 css/beauty.css 的原版配色）。
     */
    function boya_style_options()
    {
        return [
            ['key' => 'default', 'label' => '原版'],
            ['key' => 'gothic', 'label' => '哥特'],
            ['key' => 'tech', 'label' => '科技'],
            ['key' => 'classical', 'label' => '古风'],
            ['key' => 'editorial', 'label' => '编辑社'],
            ['key' => 'western', 'label' => '西方风'],
        ];
    }
}

if (!function_exists('boya_style_is_known')) {
    function boya_style_is_known($style)
    {
        foreach (boya_style_options() as $option) {
            if ($option['key'] === $style) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('boya_theme_init_script')) {
    /**
     * 明暗 + 外观的初始化脚本，必须在 CSS 之前执行（否则会先闪一下默认配色）。
     *
     * 只用 window.localStorage 和 URL 查询串，不依赖任何库；异常时退回浅色 + 原版，
     * 保证页面一定能正常显示（隐私模式下 localStorage 可能直接抛异常）。
     */
    function boya_theme_init_script()
    {
        $styles = [];
        foreach (boya_style_options() as $option) {
            $styles[] = $option['key'];
        }
        $stylesJson = json_encode($styles, JSON_UNESCAPED_SLASHES);

        return <<<HTML
<script>
(function () {
    try {
        var savedTheme = localStorage.getItem('theme');
        var theme = (savedTheme === 'dark' || savedTheme === 'light')
            ? savedTheme
            : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);

        var known = {$stylesJson};
        var params = new URLSearchParams(window.location.search);
        var paramStyle = params.get('style');
        var savedStyle = localStorage.getItem('boya-style');
        var style = 'default';
        if (known.indexOf(paramStyle) >= 0) {
            style = paramStyle;
            try { localStorage.setItem('boya-style', style); } catch (e) {}
        } else if (known.indexOf(savedStyle) >= 0) {
            style = savedStyle;
        }
        document.documentElement.setAttribute('data-style', style);
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-style', 'default');
    }
})();
</script>
HTML;
    }
}

if (!function_exists('boya_style_option_buttons')) {
    /**
     * 生成外观按钮。
     *
     * @param string $cssClass 额外类名（移动端菜单用 style-option style-option-mobile）
     * @param bool   $radio    是否输出 role=menuitemradio（设置弹窗里需要）
     */
    function boya_style_option_buttons($cssClass = 'style-option', $radio = false)
    {
        $html = '';
        foreach (boya_style_options() as $option) {
            $key = htmlspecialchars($option['key'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8');
            $html .= '<button type="button" class="' . $cssClass . '" data-style="' . $key . '"'
                . ($radio ? ' role="menuitemradio" aria-checked="false"' : '')
                . '>'
                . '<span class="style-dot style-dot-' . $key . '" aria-hidden="true"></span>'
                . $label
                . '</button>';
        }
        return $html;
    }
}
