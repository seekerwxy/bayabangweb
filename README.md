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
_photo.php                  图片路径工具（归一化 + 存在性校验）
config.php                  引导文件：加载配置、关掉 mysqli 异常（必须入库）
config.local.php            数据库与管理员凭据（不入库，部署时由 Secret 生成）

api/                        接口脚本，只放 .php
  classmates.php messages.php memories.php upload.php
  admin-memories.php admin-messages.php   ← 后台接口（仅管理员）

admin-files/                后台页面，只放 HTML/JS（接口都去 api/ 调）
  login.php admin-list.php admin-memories.php admin-messages.php edit-profile.php _auth.php

css/ js/                    样式与脚本
photos/                     网站自带的照片（随仓库部署）
uploads/classmates/         同学上传的头像（只存在服务器上，不参与部署）
text/                       站内文本与 APP 安装包
```

部署方式、目录约定和常见问题见 [DEPLOY.md](DEPLOY.md)。
