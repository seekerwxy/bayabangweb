# 自托管的前端依赖

放在这里的文件随仓库部署，不依赖第三方 CDN（中国大陆访问 jsdelivr / cdnjs 经常很慢甚至失败，
留言板的 Markdown 渲染和 XSS 过滤都靠它们，所以自己托管更稳）。

| 文件 | 版本 | 上游地址 | SHA-384 |
| --- | --- | --- | --- |
| `marked.min.js` | marked v18.1.1（UMD 构建） | `https://cdn.jsdelivr.net/npm/marked@18.1.1/lib/marked.umd.js` | `sha384-rATToyt6602Jnq8c7w8HZwmyOUgPL7mzxX01FBWoV48ns/EgkakGozpES+PnaYOI` |
| `purify.min.js` | DOMPurify 3.1.6 | `https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js` | `sha384-+VfUPEb0PdtChMwmBcBmykRMDd+v6D/oFmB3rZM/puCMDYcIvF968OimRh4KQY9a` |

许可：marked 为 MIT，DOMPurify 为 Apache-2.0 / MPL-2.0（文件头已保留原始许可声明）。

## 升级步骤

1. 从上面的上游地址下载新版本（marked 用 `lib/marked.umd.js`，它会挂到 `window.marked`）；
2. 覆盖这里的同名文件；
3. 用 `sha384` 重新算一遍校验值并更新上表；
4. 前台打开留言板确认 Markdown 渲染与 XSS 过滤仍正常（`marked.parse()` + `DOMPurify.sanitize()`）。

## 还在用 CDN 的资源

Font Awesome 6.5.1 与 Cropper.js 1.5.13 仍走 cdnjs，但页面里已经加了 `integrity`（SRI）+ `crossorigin`，
CDN 内容被篡改时浏览器会拒绝加载：

| 资源 | SRI |
| --- | --- |
| `font-awesome/6.5.1/css/all.min.css` | `sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g` |
| `cropperjs/1.5.13/cropper.min.css` | `sha384-oMy41mb/qJnpJlpXOF57hSu2KGi47l/UV9+tPNrBOs7/ap5Vubj/3phrCtjutHMQ` |
| `cropperjs/1.5.13/cropper.min.js` | `sha384-r+ljwOAhwY4/kdyzMnuBg7MEVoWpTMp5EYUDntB/E9qzNwL9dAEcNrb2XaV+mJc2` |
