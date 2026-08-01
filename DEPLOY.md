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

## 不会被动的东西

- `api/photos` 下用户上传的头像图片：部署时被排除，不会覆盖也不会删除。
- InfinityFree 上的三个 MySQL 数据库：只由网站代码读取，部署不会改动数据。

## 常见问题

- 部署后网站没变化：先看 `Actions` 是否成功；再确认上传目录。当前 `server-dir` 设置为 `htdocs/`，如果文件被传到 `htdocs/htdocs`，把 workflow 里的 `server-dir` 改成 `/` 再试。
- FTP 连接报 TLS/加密错误：把 workflow 里的 `protocol` 从 `ftp` 改成 `ftps`。
- 只改了数据库密码：更新 `CONFIG_LOCAL_PHP` Secret，然后重新 push 或到 `Actions` 页面点 `Run workflow`。
- 部署成功后网站还是旧页面：确认 `CLOUDFLARE_API_TOKEN` 和 `CLOUDFLARE_ZONE_ID` 两个 Secret 已配置；也可以到 Cloudflare 后台手动“清除所有缓存”立即生效。
