# bayabangweb
浙江省温州市第二外国语学校初中部25级博雅班的纪念网站，仅作班级体内使用；
纪录班级的大小事，目前仍在更新；
该仓库为网站源代码，由DeepSeek搭建（怎么不算Vibe Coding呢）

## 目录结构

```
index.php teacher.php classmates.php memories.php messages.php personal.php download.php
                            ↑ 前台页面（网站根目录）
_head.php _header.php _footer.php
                            ↑ 前台公共片段
_photo.php                  图片路径工具（归一化 + 存在性校验 + 目录白名单）
_member.php                 同学账号：会话、CSRF、同学录库连接（含 username 字段自动补齐）
_login_guard.php            登录失败限速（文件存储，换 cookie/IP 都绕不过）
_rate_limit.php             发帖/回复频率与每日配额（同一个文件存储思路）
_audit.php                  操作审计（写 data/admin-audit.log）
_forum.php                  留言板公共库：建表、文本清洗、跨库取用户名/头像
config.php                  引导文件：加载配置、关掉 mysqli 异常（必须入库）
config.local.php            数据库与管理员凭据（不入库，部署时由 Secret 生成）
.htaccess                   强制 HTTPS + 安全响应头 + 屏蔽不该被访问的文件

messages.php                留言板：帖子列表（游客可读，登录才能发帖）
post.php                    帖子详情 + 回复

api/                        接口脚本，只放 .php
  classmates.php messages.php memories.php upload.php
  session.php               当前登录状态 + CSRF token
  forum.php                 帖子列表 / 帖子详情（只读）
  forum-write.php           发帖 / 回复 / 删除自己的内容（需登录）
  admin-memories.php admin-messages.php admin-migrate-messages.php   ← 后台接口（仅管理员）

admin-files/                后台页面，只放 HTML/JS（接口都去 api/ 调）
  login.php admin-list.php admin-memories.php admin-messages.php
  admin-security.php admin-migrate.php edit-profile.php _auth.php

css/ js/                    样式与脚本
  css/forum.css             留言板样式（用 beauty.css 的变量，六种外观自适应）
  js/forum.js               留言板前端公共方法（登录、发帖、Markdown 渲染）
  js/vendor/                自托管依赖：marked、DOMPurify（见 js/vendor/README.md）
photos/                     网站自带的照片（随仓库部署）
uploads/classmates/         同学上传的头像（只存在服务器上，不参与部署）
data/                       运行期数据：登录限速、发帖限速、审计日志（禁止外部访问）
text/                       站内文本与 APP 安装包
```

留言板的账号模型：登录用**真实姓名或论坛用户名**都可以；论坛上只显示**论坛用户名**（在
`admin-files/edit-profile.php` 里设置，3-20 位、唯一、不能与同学真实姓名重复），真实姓名不出现在论坛上。

部署方式、目录约定、安全设置和常见问题见 [DEPLOY.md](DEPLOY.md)。
