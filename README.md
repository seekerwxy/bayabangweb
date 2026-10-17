# bayabangweb
浙江省温州市第二外国语学校初中部25级博雅班的纪念网站，仅作班级体内使用；
纪录班级的大小事，目前仍在更新；
该仓库为网站源代码，由DeepSeek搭建（怎么不算Vibe Coding呢）

## 目录结构

```
inc/                        被 include 的 PHP 库（目录本身禁止外部访问，见 .htaccess）
  bootstrap.php             唯一引导文件：常量、时区、错误处理、加载其余库
  http.php                  JSON 输入输出（boya_json / boya_json_error）、客户端 IP
  session.php               会话 + CSRF（同学 / 管理员两套作用域）
  db.php                    连接池 boya_db() + 结构同步闸门 boya_schema_once()
  assets.php                静态资源 URL + 内容哈希版本号（boya_asset / boya_url）
  member.php                同学账号：会话标识、用户名规则、同学录库、结构补齐
  forum.php                 留言板：建表、文本清洗、跨库取用户名/头像、浏览量去重
  admin.php                 管理员身份：CSRF、口令校验、登录/退出
  avatars.php               头像目录、文件名规则、旧文件清理、配额
  photo.php                 图片路径归一化 + 存在性校验 + 目录白名单
  theme.php                 明暗/外观初始化脚本 + 六种外观选项（前后台共用）
  audit.php                 操作审计（写 data/admin-audit.log）
  rate_limit.php            发帖/回复频率与每日配额（文件存储）
  login_guard.php           登录失败限速（文件存储，换 cookie/IP 都绕不过）

index.php teacher.php classmates.php memories.php messages.php post.php personal.php download.php
                            ↑ 前台页面（网站根目录）
_head.php _header.php _footer.php _login_modal.php
                            ↑ 前台公共片段（只有 HTML/结构，交互都在 js/ 里）
_site.php                   站点根前缀 boya_site_prefix()（零依赖，可随时 include）
config.php                  引导配置：站点路径前缀、加载 config.local.php、关掉 mysqli 异常（必须入库）
config.local.php            数据库与管理员凭据（不入库，部署时由 Secret 生成）
.htaccess                   强制 HTTPS + 安全响应头 + 屏蔽不该被访问的文件（含 inc/）+ 自定义错误页

error-pages/                自定义错误页（纯静态，由 .htaccess 的 ErrorDocument 挂上）
  400.html 403.html 404.html 500.html 503.html
                            黑底「流血数字」页；每页自包含（内联样式 + 内联脚本），互不依赖
                            注意：不能叫 errors/，那是 InfinityFree 自带的目录，FTP 写不进去

api/                        接口脚本，只放 .php
  classmates.php            同学录：列表 / 资料 / 登录 / 改资料 / 改口令 / 设用户名 / 退出
  session.php               当前登录状态 + CSRF token
  user.php                  用户空间：资料 / 动态时间线 / 回复
  forum.php                 帖子列表 / 帖子详情（只读）
  forum-write.php           发帖 / 回复 / 删除自己的内容（需登录）
  memories.php              时光轴事件（只读）
  upload.php                头像上传（base64 → uploads/classmates/）
  admin-forum.php           置顶 / 锁定 / 删除 / 恢复（管理员或 is_admin 同学）
  admin-users.php           账号管理：称号 / 管理员 / 重置口令 / 分配用户名 / 新增同学
  admin-memories.php admin-messages.php admin-migrate-messages.php   ← 后台接口（仅管理员）

admin-files/                后台页面，只放 HTML/JS（接口都去 api/ 调）
  _auth.php                 后台守卫：未登录跳登录页（接口调用返回 401）
  _layout.php               后台页面骨架：head / 页头 / 「返回面板 + 退出登录」
  login.php admin-list.php admin-memories.php admin-messages.php
  admin-security.php admin-migrate.php admin-forum.php admin-users.php
  edit-profile.php          同学自助编辑资料（前台页面，不需要管理员）

css/
  beauty.css                原版主题 + 全部组件的基础变量
  gothic.css tech.css classical.css editorial.css western.css
                            五种外观，都是 html[data-style="…"] 作用域下的覆盖样式
  forum.css                 留言板样式（用 beauty.css 的变量，六种外观自适应）
  admin-ui.css              后台公共样式（表头/卡片/表格/按钮/弹窗/提示）
  admin-dark.css            后台暗色模式覆盖

js/
  boya-ui.js                全站交互：明暗、外观切换、设置弹窗、用户菜单、汉堡菜单、提示、复制邮箱
  forum.js                  留言板公共方法（Markdown 渲染、作者行、发帖/管理接口、提示）
  boya-auth.js              同学登录弹窗与登录/退出（messages.php、post.php 共用）
  admin-common.js           后台公共方法（esc / fetchJson / postForm / showResult / 分页）
  pages/*.js                各页面自己的逻辑（classmates、memories、messages、post、personal、media）
  vendor/                   自托管依赖：marked、DOMPurify（见 js/vendor/README.md）

photos/                     网站自带的照片（随仓库部署）
uploads/classmates/         同学上传的头像（只存在服务器上，不参与部署）
api/photos/classmates/      旧头像目录（历史数据，仅保留能访问到文件的老记录）
data/                       运行期数据：登录限速、发帖限速、审计日志、结构同步标记（禁止外部访问）
text/                       站内文本与 APP 安装包
```

账号模型：`classmates.id` 就是 **UID**，`username` 是登录名与论坛显示名（唯一，3-20 位、
不能与同学真实姓名重复，7 天只能改一次），`name` 是真实姓名，另有昵称、头像、座右铭、称号、论坛管理员标记、
加入时间、最后活跃。登录用**用户名或真实姓名**都可以；论坛帖子旁显示「用户名 + UID + 昵称 + 称号」，
点进去是他的**个人主页**（`/user/123`）：动态（帖子 + 回复时间线）/ 资料 / 回复三个页签，
真实姓名只在主页的「资料」页签里出现（联系方式对游客也可见）。

## 改动须知

- 所有 PHP 文件里都**不要**出现会直接生效的 `?>`（注释里也不行）：行注释里的 `?>` 会结束 PHP 模式，
  后面整段代码会被当成 HTML 输出，表现为 `php -l` 报 "Unmatched '}'"。
- 静态资源一律用 `boya_asset('css/x.css')` / 站内链接用 `boya_url('post.php')`，
  它们输出「站点根绝对路径 + 内容哈希版本号」，在 `/user/2` 这类重写网址下也不会 404。
- 结构同步（补列、建表）都走 `boya_schema_once()`：改了同步逻辑要顺手改版本号常量
  （`inc/bootstrap.php` 里的 `BOYA_SCHEMA_*`），否则线上不会重跑。
- JSON 接口统一用 `inc/http.php` 的 `boya_json()` / `boya_json_error()`：
  自动补 `ok` 字段、自动带 `Cache-Control: no-store`。

部署方式、目录约定、安全设置和常见问题见 [DEPLOY.md](DEPLOY.md)。
