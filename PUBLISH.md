# 发布到 GitHub

## 1. 创建空仓库

在 GitHub 新建名为 `php-runtime` 的仓库（名称可自行调整），选择公开或私有。不要勾选自动创建 README、.gitignore 或许可证。本地文件已准备好。

## 2. 提交并推送文档、清单和验证脚本

本地已经初始化 Git，尚未提交，也未设置远程地址。将下面的 `YOUR_ACCOUNT` 替换为 GitHub 账号；也可以把整个远程地址换成仓库页面提供的 HTTPS 地址。

```sh
cd /Users/mitirrli/Projects/hl56/php-runtime
git add .
git commit -m "Add PHP 8.1.29 multi-platform release files"
git remote add origin git@github.com:YOUR_ACCOUNT/php-runtime.git
git push -u origin main
```

`release-assets/` 已被 `.gitignore` 排除，里面的安装包和校验文件会在下一步作为 Release 附件上传。

## 3. 上传运行包

打开 GitHub 仓库的 Releases，创建一个 Release：

- 标签：`v8.1.29`
- 目标分支：`main`
- 标题：`PHP 8.1.29 — Linux x86_64 / Linux ARM64 / macOS ARM64`
- 附件：将本地 `release-assets/` 中的三个 `.tar.gz` 和 `SHA256SUMS` 全部拖入附件区。
- 说明：三个构建均包含 CLI、FPM 和 Xdebug 3.4.7；已通过完整目标扩展加载、功能测试和真实 FastCGI 请求验证。Linux ARM64 在 CentOS 7 / glibc 2.17 验证，Linux x86_64 在 Ubuntu 22.04.3 验证；macOS 在 15.3.1 Apple Silicon 验证。

最后发布 Release。以后直接从该 Release 下载对应平台附件，再按 README 接入 mise 即可。

## 本地附件校验

```sh
cd /Users/mitirrli/Projects/hl56/php-runtime/release-assets
LC_ALL=C shasum -a 256 -c SHA256SUMS
```

官方说明：[推送现有本地仓库](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github)、[管理 Releases](https://docs.github.com/en/repositories/releasing-projects-on-github/managing-releases-in-a-repository)。
