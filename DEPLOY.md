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

- `api/`：只放接口脚本（`.php`），不放用户上传的文件。
- `uploads/classmates/`：同学自己上传的头像，只存在服务器上，不进仓库、不参与部署。
- `photos/`：网站自带的照片，随仓库一起部署。
- `text/`：站内文本与 APP 安装包。

### 把老头像迁到新目录（可选，不急）

新上传的头像已经写进 `uploads/classmates/`；老数据里存的是 `/api/photos/classmates/…`，只要服务器上那些文件还在就照旧能显示。想彻底统一：

1. 用 FTP 把 `api/photos/classmates/` 下的图片移动到 `uploads/classmates/`；
2. 在 phpMyAdmin 里执行：

   ```sql
   UPDATE classmates SET avatar = REPLACE(avatar, '/api/photos/classmates/', '/uploads/classmates/')
   WHERE avatar LIKE '/api/photos/classmates/%';
   ```

3. 前台确认头像都正常后，再删掉 `api/photos/` 目录。

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
