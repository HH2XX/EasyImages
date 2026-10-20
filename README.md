# EasyImage 简单图床（HH2XX 维护版）

EasyImage 是一款无需数据库的 PHP 图床程序，支持多文件上传、图片直链、Markdown、HTML、BBCode、后台管理、上传历史、统计、压缩、水印、API、WebDAV 和 S3 兼容存储。

本项目由 [HH2XX](https://github.com/HH2XX) 维护，基于 [Icret/EasyImages2.0](https://github.com/icret/EasyImages2.0) 二次开发，遵循 GPL-2.0 许可证。

- 项目地址：[HH2XX/EasyImages](https://github.com/HH2XX/EasyImages)
- 上游项目：[icret/EasyImages2.0](https://github.com/icret/EasyImages2.0)
- 当前版本：`2.8.7`

## 主要功能

- 多文件拖拽、粘贴和 API 上传
- 本地、WebDAV、S3 兼容存储
- 直链、缩略图、Markdown、HTML、BBCode 链接生成
- 图片历史、统计、回收站、文件管理器
- 图片压缩、水印、尺寸限制和可选内容审核
- CSRF、上传 Token、路径边界和敏感目录保护
- 响应式前台、后台暗色模式和移动端布局

## 部署要求

- PHP 7.0 或更高版本，建议 PHP 8.1+
- `fileinfo`、`gd`、`openssl`、`mbstring`、`iconv`、`curl`
- Web 服务器允许 PHP 访问和写入 `config/`、`i/`
- 生产环境建议启用 HTTPS，并关闭 PHP 错误直接输出

## 调试与生产模式

程序默认关闭错误直接输出。只有服务器环境变量 `EASYIMAGE_DEBUG=1` 时才会在页面显示 PHP 错误；正式环境请不要设置该变量，并同时关闭 Web 服务器的详细错误输出。

本地调试完成后应停止 PHP 开发服务器，清除测试图片、日志、缓存、会话、Token、短链和锁文件，再进行打包发布。

## 安装与配置

1. 将源码上传到站点根目录。
2. 确认 `config/`、`i/` 可写；程序会按需创建运行时目录。
3. 首次部署前在 `config/config.php` 设置管理员账号、管理员密码哈希、网站域名和图片域名。
4. 可使用 `/app/reset_password.php` 生成密码哈希，或部署后从后台修改密码。
5. 网站域名和图片域名必须填写完整的 HTTPS 地址；如果二者相同可以使用同一个地址。
6. 根据需要配置 API Token、访客上传、WebDAV 或 S3。

## 正式发布流程

1. 备份服务器上的配置和上传目录，备份文件存放在站点目录之外。
2. 上传干净源码，确认 `config/` 和 `i/` 具有程序需要的写权限。
3. 在服务器配置真实的 HTTPS 域名、管理员账号和密码，不要上传本地配置覆盖服务器配置。
4. 在后台重新生成 API Token；启用 WebDAV、S3、FTP 或内容审核时，只在服务器配置真实密钥。
5. 验证首页、后台登录、单图上传、多图上传、粘贴上传、缩略图、下载、删除链接和 API 上传。
6. 确认 `EASYIMAGE_DEBUG` 未设置为 `1`，再检查 PHP 错误日志和 Web 服务器访问日志。
7. 首次安装完成后确认 `config/install.lock` 已生成并保留，用于防止重复安装；同时限制 `config/`、`admin/logs/` 和备份目录的静态访问。

发布源码中的 `config/config.php` 默认不包含具体域名、Token、远程存储密钥和运行日志。部署到服务器后必须设置真实域名，不能直接使用本地开发地址。

## 目录说明

- `admin/`：后台管理、统计和文件管理入口
- `api/`：API 上传和公共查询接口
- `app/`：核心业务逻辑
- `config/`：站点配置和运行配置
- `i/`：默认本地上传目录
- `install/`：安装相关目录
- `public/`：前端静态资源


## 安全建议

- 使用随机且唯一的管理员密码。
- 生产环境强制 HTTPS。
- 默认关闭 SVG 上传；只有确认内容清洗策略后再开启。
- 定期备份上传目录和配置，但不要公开备份文件。
- 不要将 `config/`、`admin/logs/` 和服务器备份目录暴露给静态下载。
- 发现安全问题请通过 GitHub Security Advisory 私下报告。

## 许可证

本项目使用 GPL-2.0 许可证。重新发布或二次开发时，请保留 HH2XX、上游项目和第三方依赖的版权声明。
