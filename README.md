# EasyImage 简单图床（HH2XX 维护版）

[![PHP](https://img.shields.io/badge/php-5.6%20--%208.x-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0-yellowgreen.svg)](LICENSE)

EasyImage 是一款无需数据库的 PHP 图床程序，支持多文件上传、图片直链、Markdown/HTML/BBCode 链接生成、后台管理、上传历史、统计、图片压缩、水印、鉴黄、API、WebDAV 与 S3 兼容存储。

本项目由 [HH2XX](https://github.com/HH2XX) 维护，基于 [Icret/EasyImages2.0](https://github.com/icret/EasyImages2.0) 二次开发，遵循 GPL-2.0 许可证。

- 当前仓库：[HH2XX/EasyImages](https://github.com/HH2XX/EasyImages)
- 上游项目：[icret/EasyImages2.0](https://github.com/icret/EasyImages2.0)
- 当前版本：`2.8.6`

公开源码中不包含运行日志、会话、上传文件、远程存储密钥或个人收款信息。

## 快速开始

1. 将源码上传到 Web 根目录。
2. 确认 PHP 已启用 `fileinfo`、`gd`、`openssl`、`mbstring`、`iconv` 等扩展。
3. 确认 `config/`、`i/` 可写；运行后程序会按需创建日志和缓存目录。
4. 首次访问站点会进入 `/install/index.php` 安装引导。
5. 安装时请修改默认管理员账号和密码。
6. 安装完成后按需删除 `/install/` 目录。

安装引导会要求设置管理员账号和密码。首次部署建议使用随机、唯一的管理员密码；默认使用本地 `/i/` 存储，API Token、访客上传和远程存储均需由部署者自行配置。

## 远程存储

后台进入 `图床设置` -> `上传渠道` 可配置上传方式。公开版默认关闭远程存储，使用者需要自行填写自己的 WebDAV 或 S3 参数。

WebDAV 示例：`https://dav.example.com/remote.php/dav/files/username`

S3 兼容存储可用于 Cloudflare R2、Backblaze B2、MinIO、腾讯 COS、阿里 OSS、七牛 Kodo 等兼容 S3 API 的对象存储。填写 `Endpoint`、`Bucket`、`Region`、`Access Key`、`Secret Key` 后可在后台测试。

## 目录说明

- `admin/`：后台管理、统计、文件管理入口
- `api/`：上传与公共查询接口
- `app/`：核心业务逻辑
- `config/`：站点配置，公开源码内只保留安全默认值
- `docs/`：使用文档
- `i/`：默认本地上传目录
- `install/`：安装引导
- `public/`：前端静态资源


## 许可证

本项目使用 GPL-2.0 许可证。请保留 HH2XX 的维护署名、上游项目和第三方依赖的版权声明。
