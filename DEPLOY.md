# 自动部署说明

每次把代码推送到 GitHub 的 main 分支，GitHub Actions 会自动通过 FTP 把代码上传到 InfinityFree，网站更新到 bayabang.top。Cloudflare 继续只负责域名解析；数据库仍然留在 InfinityFree，不会被部署覆盖。

## 首次配置：添加 GitHub Secrets

打开 GitHub 仓库页面：`Settings -> Secrets and variables -> Actions -> New repository secret`，依次添加下面 6 个 Secret：

| Secret 名称 | 值从哪里复制 |
| --- | --- |
| `FTP_SERVER` | `BayaTopFTP.txt` 里的“主机名” |
| `FTP_USERNAME` | `BayaTopFTP.txt` 里的“用户名” |
| `FTP_PASSWORD` | `BayaTopFTP.txt` 里的“密码” |
| `CONFIG_LOCAL_PHP` | 本机 `config.local.php` 的完整内容（包括开头的 `<?php`） |
| `CLOUDFLARE_API_TOKEN` | Cloudflare 后台创建的 API 令牌（需要 `Zone.Cache Purge` 权限） |
| `CLOUDFLARE_ZONE_ID` | Cloudflare 后台 `bayabang.top` 概览页右侧的“区域 ID” |

`CONFIG_LOCAL_PHP` 要粘贴整份文件内容。以后改了数据库配置，也要同步更新这个 Secret。

`CLOUDFLARE_API_TOKEN` 创建方法：登录 Cloudflare -> 我的个人资料 -> API 令牌 -> 创建令牌 -> 使用模板“清除缓存”，区域权限选择 `bayabang.top`。这个令牌只用于部署后自动清除缓存，不会泄露给任何人。

如果没配置这两个 Secret，部署仍然会照常上传文件，只是不会自动清除 Cloudflare 缓存，网站可能暂时显示旧页面。

## 日常使用

1. 修改代码
2. `git add .`
3. `git commit -m "说明"`
4. `git push`

推送成功后，到 GitHub 仓库的 `Actions` 页面查看部署是否成功。

## 目录结构（很重要，改错会白忙）

本账号用 FTP 登录后，根目录里同时能看到 `bayabang.top/` 和 `htdocs/`：`bayabang.top` 是附加域名，它的网站根目录是 `bayabang.top/htdocs/`，**不是** `htdocs/`。所以 workflow 里的 `server-dir` 固定为：

```yaml
server-dir: bayabang.top/htdocs/
```

两个容易踩的坑：

- **不要加前导 `/`**。加了斜杠会被 FTP-Deploy-Action 理解成「FTP 服务器根目录」而不是登录目录，文件就传错地方了。
- `server-dir` 必须落在某个 `htdocs` 文件夹内。InfinityFree 会自动删除上传到 htdocs 之外的文件；免费主机的单文件上限则是 HTML/PHP/JS 1 MB、其他文件 10 MB，超限的文件也会被自动删除。

想确认文件到底传到了哪里，可以临时给 workflow 加上 `dry-run: true` 跑一次：日志会列出所有将要执行的动作，不会真的改动服务器。

## 目录约定

- `api/`：只放接口脚本（`.php`），不放用户上传的文件；后台接口也在这里（`api/admin-memories.php`、`api/admin-messages.php`）。
- `uploads/classmates/`：同学自己上传的头像，只存在服务器上，不进仓库、不参与部署。
- `photos/`：网站自带的照片，随仓库一起部署。
- `text/`：站内文本与 APP 安装包。
- `data/`：运行期数据（登录限速 `login-guard.json`、审计日志 `admin-audit.log`），只存在服务器上，`.htaccess` 禁止外部访问。
- `js/vendor/`：自托管的前端依赖（marked、DOMPurify），版本与校验值见 `js/vendor/README.md`。
- 根目录 `.htaccess`：强制 HTTPS + 安全响应头 + 屏蔽不该被访问的文件。

## 留言板（帖子 + 回复）

- **数据表**：`forum_posts`、`forum_replies` 建在留言那个库（`liuyan`）里，第一次访问接口时自动创建；`classmates` 表会自动补齐账号字段（`username`、`created_at`、`last_active_at`、`title`、`is_admin`），**不需要手动跑 SQL**。
- **已弃用字段 `signature`**：资料上的「个性签名」和「座右铭」原本各存一列、个人主页上重复显示，现在**只保留 `quote`（座右铭）**。代码里已不再读写 `classmates.signature`，并且每次访问接口时都会自动把还没搬过来的签名并进空着的座右铭、然后删掉这一列（见 `_member.php` 的 `$deprecated`，删列失败只写日志、不影响站点）。想立刻手动处理，两种方式选一个：

  1）后台「账号管理」页点 **「清理重复的个性签名字段」**按钮（幂等，可重复点；只填空着的座右铭，不覆盖同学自己填的）；

  2）在 phpMyAdmin 里按顺序执行（**确认上一条成功再执行下一条**）：

  ```sql
  -- 1) 看看有多少行需要搬运：座右铭为空、签名非空
  SELECT COUNT(*) FROM classmates WHERE (quote IS NULL OR quote = '') AND signature <> '';

  -- 2) 搬运（WHERE 里带条件，不会覆盖同学自己填的座右铭）
  UPDATE classmates SET quote = signature
   WHERE (quote IS NULL OR quote = '') AND signature IS NOT NULL AND signature <> '';

  -- 3) 删除重复的列。想更保险可以先改名留个备份，确认没问题再删：
  -- 方案 A：留备份（老版本 MySQL/MariaDB 用这句，一路都兼容）
  ALTER TABLE classmates CHANGE COLUMN signature signature_legacy varchar(100) DEFAULT NULL;
  -- 方案 B：直接删（唯一不可逆的一步，确认前两步无误再执行）
  -- ALTER TABLE classmates DROP COLUMN signature;
  ```

  方案 A 之后如果一切正常，再执行 `ALTER TABLE classmates DROP COLUMN signature_legacy;` 收尾。
  注意 `signature` 是 `varchar(100)`，`quote` 是更宽的列，搬运不会截断。
- **账号是正式的用户账号**：`classmates.id` 就是 **UID**，`username` 是登录名 / 论坛显示名（唯一、3-20 位、不能与同学真实姓名重复、7 天只能改一次），`name` 是真实姓名，`nickname` 是昵称；登录可以用**用户名或真实姓名**。
- **前台显示**：帖子/回复旁显示 **用户名 + UID + 昵称 + 称号 + 管理徽章**，点名字进**个人主页**（`/user/123`，等价于 `personal.php?id=123`）；真实姓名只在个人主页的「资料」页签里出现。
- **个人主页（用户空间）**：头像 / 用户名 / 昵称 / 称号 / UID / 最后活跃 / 帖子数 / 回复数 / 座右铭，下面三个页签——**动态**（帖子 + 回复混排的时间线）、**资料**（原来的一切字段，联系方式对游客也可见）、**回复**。数据来自 `api/user.php`。
- **留言列表的卡片结构**：`messages.php` 里每条帖子是 `<div class="forum-item" data-post="…">`，标题才是 `<a>`。**不要**把整张卡片改回 `<a>`：作者行里也有一个链接，`<a>` 不能嵌套 `<a>`，浏览器会在内层链接处强行闭合外层，卡片样式就只套住一个空 `<a>`、标题和摘要全跑到卡片外面（线上踩过这个坑）。点卡片空白处跳详情是靠 `#postList` 上那个委托 click 事件。
- **管理权限**：① 后台「论坛管理」页（`admin-files/admin-forum.php`）的 config 管理员；② 在「账号管理」里被标为 `is_admin` 的同学，论坛页面上会直接出现管理按钮。可置顶 / 取消置顶 / 锁定 / 解锁 / 删除 / 恢复，动作全部写审计日志。
- **账号管理**（`admin-files/admin-users.php`）：名单与搜索、设称号、设/取消论坛管理员、重置口令（新口令只显示一次，日志绝不记录口令）、指定用户名、给未设用户名的同学批量分配 `boya001` 这种、新增同学（自动生成初始口令）、清理重复的个性签名字段。
- **权限**：游客只能看；发帖/回复必须登录并已设置用户名，删除只能删自己 10 分钟内的内容。
- **防滥用**：发帖 60 秒 1 条、回复 15 秒 1 条、每人每天最多 20 帖 / 100 回复；记录写在 `data/rate-limit.json`，与 Cookie/IP 无关。标题 ≤60 字、正文 ≤5000 字、回复 ≤2000 字，并且服务端会拦掉 `<script>`、`onclick=` 这类片段（前端还有 DOMPurify 第二道）。
- **旧留言导入**：后台 →「旧留言导入」，把旧 `messages` 表的内容一次性变成帖子（作者显示原署名并带「历史留言」标记）。操作是幂等的，导过的行会记 `migrated_post_id`，重复点不会产生第二份，旧数据不会被删改。
- **接口缓存**：留言板的所有 JSON 接口都带 `Cache-Control: no-store`，避免 Cloudflare 或浏览器缓存住旧内容。
- **旧接口已删除**：原来的 `api/messages.php`（允许任何人匿名 POST）已经移除，下次部署会自动从服务器删掉它，避免有人绕过新规则继续往旧表里灌内容。
- **下一期计划**：@某人回复、引用楼中楼、点赞、搜索、回复通知。

## 安全设置（代码里已经做好，了解即可）

- **部署走 FTPS**：workflow 里的 `protocol` 是 `ftps`（21 端口显式 TLS）。明文 FTP 会把 `config.local.php`（数据库口令 + 管理员口令）暴露在网络上，所以别为了省事改回 `ftp`；万一 TLS 握手失败，先看 Actions 日志，临时改回后请尽快改回来。
- **强制 HTTPS**：根目录 `.htaccess` 会把 http 请求 301 到 https，并兼容 Cloudflare 的 Flexible / Full / Full(strict)（不会死循环）。建议同时在 Cloudflare 打开 `SSL/TLS → Edge Certificates → Always Use HTTPS`。
- **安全响应头**：`.htaccess` 里设了 `X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy`、`Permissions-Policy` 和 HSTS（30 天）。
- **管理员口令建议存 hash**：后台 →「安全设置」→ 输入新口令（≥12 位）→ 把生成的 `$2y$...` 填进 `CONFIG_LOCAL_PHP` Secret 的 `admin_db.password` → push。旧明文写法仍然兼容，但页面会提示“仍是明文”。
- **学生口令**：同学登录时会自动把明文升级成 `password_hash()`；也可以到「安全设置 → 批量升级明文口令」一次性处理完（不需要知道明文）。
- **登录限速**：记录写在 `data/login-guard.json`，按「账号」计数，不依赖 Cookie 或 IP —— 换浏览器、清 Cookie、伪造 `CF-Connecting-IP` 都绕不过。单账号连续 5 次失败锁 15 分钟，另有全局闸门（15 分钟 200 次）防止海量喷洒。
- **操作审计**：登录成功/失败、时光轴增删改、留言编辑删除、头像上传都会写进 `data/admin-audit.log`（JSON Lines，最多保留约 1000 行），后台「安全设置」页可看最近 100 条。
- **上传配额**：`uploads/classmates/` 文件数上限 3000；同学换头像时旧文件自动删除（只删本站生成的「32 位十六进制 + 图片扩展名」文件名，不会误删别的）。
- **前端依赖**：`marked`、`DOMPurify` 已自托管到 `js/vendor/`（不依赖 CDN，中国大陆访问更稳，也不怕 CDN 被篡改）；Font Awesome 与 Cropper.js 仍走 cdnjs，但加了 `integrity`(SRI)。
- **口令轮换**：几个数据库口令互相独立；`memories_db` 那段已经删掉（从未被代码使用），可以从 Secret 里一并删掉。改口令顺序：InfinityFree 控制面板改 → 更新 `CONFIG_LOCAL_PHP` Secret → push。
- **图片路径白名单**：`_photo.php` 只接受 `/photos/`、`/uploads/`、`/text/`、`/api/photos/`（旧头像目录）下的图片，或站点根目录下的单个图片文件，并且必须是图片扩展名、不能含 `..`。
- **出问题怎么回滚**：`.htaccess` 写错会导致整站 500，用 FTP 把根目录的 `.htaccess` 改名或删除即可立刻恢复。

### 把头统一到新目录（可选，不急）

新上传的头像已经写进 `uploads/classmates/`；老数据里存的是 `/api/photos/classmates/…`，只要服务器上那些文件还在就照旧能显示。想彻底统一：

1. 用 FTP 把 `api/photos/classmates/` 下的图片移动到 `uploads/classmates/`；
2. 在 phpMyAdmin 里执行（`REPLACE` 能同时修掉两类老数据：还是 `/api/photos/` 前缀的，
   以及前缀已经改成 `/uploads/classmates/`、但文件其实还留在旧目录、显示成 404 的）：

   ```sql
   UPDATE classmates
   SET avatar = REPLACE(avatar, '/api/photos/classmates/', '/uploads/classmates/')
   WHERE avatar LIKE '/api/photos/classmates/%';

   -- 只看名单（确认要改哪些人）：
   SELECT id, name, avatar FROM classmates
   WHERE avatar REGEXP '^/uploads/classmates/avatar_[0-9a-f]+[.](jpe?g|png|webp)$';

   -- 确认后再更新：
   UPDATE classmates
   SET avatar = REPLACE(avatar, '/uploads/classmates/', '/api/photos/classmates/')
   WHERE avatar REGEXP '^/uploads/classmates/avatar_[0-9a-f]+[.](jpe?g|png|webp)$';
   ```

   第二条只针对旧版 `avatar_<十六进制>.<扩展名>` 命名——这类文件当初存在 `api/photos/classmates/`，
   前缀被批量替换后新目录里并没有它们。**执行前先跑上面那条 `SELECT` 核对名单**：
3. 前台确认头像都正常后，再删掉 `api/photos/` 目录。

### 头像文件丢失了怎么办（已发生过）

`uploads/classmates/` 下 8 张 `avatar_<十六进制>.jpeg` 曾经整批消失（数据库里还留着路径，前台显示成
首字占位符），原因是 `.github/workflows/deploy.yml` 的 `exclude` 只排除了 `*.jpg/*.png/*.webp`，
**漏了 `.jpeg`**：FTP-Deploy-Action 就按差分把这些服务器上独有的文件当成「仓库里已删除」清掉了。
现在 `exclude` 已补齐 jpg/jpeg/JPG/JPEG/png/PNG/webp/WEBP 全部大小写组合。

**文件已不可恢复**（`api/photos/classmates/` 里也没有同名备份），由同学重新上传即可：

1. 打开 `https://bayabang.top/admin-files/edit-profile.php` 登录；
2. 选一张正方形照片 → 裁剪 → 上传，页面会显示「头像上传并保存成功 ✅」，数据库同时更新；
3. 换头像时旧文件会被自动删除（`api/classmates.php` 的 `boya_remove_old_avatar()`，
   现在认 `avatar_<hex>` 这类旧命名，不再只认 32 位十六进制）。

上传目录的文件数上限仍是 3000（`api/upload.php`），清理不会再漏掉旧命名，不会再越积越多。

## 不会被动的东西

- `config.php`：所有接口和后台的必需引导文件，**必须入库并随部署上传**（已在 `.gitignore` 中放行）。它不含任何密码。
- `config.local.php`：保存数据库和管理员凭据，不入库，由 `CONFIG_LOCAL_PHP` Secret 在部署时生成后上传。
- 用户上传的头像图片：存在 `uploads/classmates/`（旧的在 `api/photos/classmates/`），部署时被排除，不会覆盖也不会删除。
- InfinityFree 上的三个 MySQL 数据库：只由网站代码读取，部署不会改动数据。

FTP-Deploy-Action 是按「上次部署留下的状态文件」做差分的：**从仓库里删掉的文件会在下次部署时从服务器删除**，而手工放在服务器上、从未被这个 Action 上传过的文件不会被扫描删除。改动 `server-dir` 后，新目录会被当成首次发布，只上传、不删除。

## 常见问题

- 部署后网站没变化：先看 `Actions` 是否成功；再按上面的「目录结构」核对 `server-dir`，并用 `dry-run` 确认真实落点。
- PHP 页面全部 500／空白，但 css、图片、js 正常：目标目录里缺 `config.php`。它是必需文件，用 `git ls-files config.php` 确认它已经在仓库里。
- FTP 连接报 TLS/加密错误：把 workflow 里的 `protocol` 从 `ftp` 改成 `ftps`。
- 只改了数据库密码：更新 `CONFIG_LOCAL_PHP` Secret，然后重新 push 或到 `Actions` 页面点 `Run workflow`。
- 部署成功后网站还是旧页面：确认 `CLOUDFLARE_API_TOKEN` 和 `CLOUDFLARE_ZONE_ID` 两个 Secret 已配置；也可以到 Cloudflare 后台手动“清除所有缓存”立即生效。
