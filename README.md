# PHP 8.1.29 多平台构建

基于固定的 PHP 8.1.29 扩展清单，提供 CLI、FPM 和 Xdebug 3.4.7。扩展需求以 `extensions.json` 为准；每个安装包内的 `build-extensions.json` 和 `build-libraries.json` 记录实际构建内容。

| 安装包 | 已验证环境 | 结果 |
| --- | --- | --- |
| `php-8.1.29-macos-arm64.tar.gz` | macOS 15.3.1 Apple Silicon | CLI、全部目标扩展、功能测试及真实 FPM 请求通过 |
| `php-8.1.29-linux-arm64-glibc2.17.tar.gz` | 干净的 CentOS 7 ARM64 容器，glibc 2.17 | CLI、全部目标扩展、功能测试及真实 FPM 请求通过 |
| `php-8.1.29-linux-x86_64-glibc2.17.tar.gz` | Ubuntu 22.04.3 x86_64，glibc 2.35 | CLI、全部目标扩展、功能测试及真实 FPM 请求通过 |

发布流程见 [PUBLISH.md](PUBLISH.md)。本地待上传附件放在 `release-assets/`，该目录已被 Git 忽略；安装包由 GitHub Releases 分发。

## 安装

选择与操作系统匹配的压缩包，先用同目录的 `SHA256SUMS` 校验，然后解压到固定目录：

```sh
LC_ALL=C shasum -a 256 -c SHA256SUMS
mkdir -p php-8.1.29
tar -xzf php-8.1.29-macos-arm64.tar.gz -C php-8.1.29
mise link php@8.1.29 "$PWD/php-8.1.29"
```

Linux 使用对应的 Linux 压缩包。压缩包根目录直接包含 `bin/` 和 `modules/`。`mise link` 只是注册构建目录；项目的 PHP 版本选择仍由项目 mise 配置管理。

直接检查运行时及 Xdebug：

```sh
cd php-8.1.29
bin/php -n -v
bin/php -n -d zend_extension="$PWD/modules/xdebug.so" -v
bin/php-fpm -n -v
```

编译期默认 ini 路径是 `/usr/local/etc/php/php.ini`，扫描目录是 `/usr/local/etc/php/conf.d`。可以沿用项目的 `PHP_INI_SCAN_DIR`，但其中的 Xdebug 路径应指向当前安装包的 `modules/xdebug.so`。

## 验证

验证脚本使用独立 ini 环境，不依赖现有项目配置：

```sh
PACKAGE=/absolute/path/to/php-8.1.29
"$PACKAGE/bin/php" -n -d zend_extension="$PACKAGE/modules/xdebug.so" smoke.php extensions.json
"$PACKAGE/bin/php" -n fpm-smoke.php "$PACKAGE"
```

`smoke.php` 检查完整扩展清单、event 扩展未加载、Xdebug 版本，并实际执行 SQLite、MessagePack、GMP、ICU、OpenSSL、Sodium、GD、Imagick、XSL、libxml 错误处理及 ZIP 读写。`fpm-smoke.php` 启动临时 FPM 实例，通过 Unix socket 发起真实 FastCGI 请求，验证 PHP 版本、FPM SAPI、Redis 和 Xdebug，并在结束后清理临时实例。

## 构建记录

- 构建工具：static-php-cli 2.8.5，提交 `4318ef8fa32a02460ec1554746674a7bc42b49fa`。
- PHP 和扩展源码复用已验证的原构建下载缓存。
- 保留原构建的 Protobuf 4.31.1、libxml2 2.13.8、libxslt 1.1.43 和 Xdebug 3.4.7。
- PHP `ext/intl/config.m4` 的 C++ 标准由 11 调整为 17，以匹配 ICU。
- ARM64 补充构建使用 `spc-php81-libxml.patch` 启用构建工具自带的 PHP 8.1/libxml 2.12+ 回调签名修复，解决新版 Clang 的类型检查错误。
- macOS：Apple Silicon 原生编译，构建目标 macOS 12.0；已在 macOS 15.3.1 ARM64 运行验证。最低版本是构建目标，未在 macOS 12 实机验证。只依赖系统动态库和系统 Framework。
- Linux ARM64：使用独立 ARM64 CentOS 7 / GCC 10 构建容器，保留 glibc 2.17 目标。安装包已移出构建目录，在原始 CentOS 7 ARM64 镜像中验证通过；运行时依赖系统 glibc ≥ 2.17、libstdc++、libgcc 等标准库。

- Linux x86_64：复用 2026-09-22 的现有 构建；已复制到独立临时目录，在 Ubuntu 22.04.3 x86_64 上执行完整 CLI 和 FPM 验证。ELF 版本依赖最高为 GLIBC 2.17，运行时仍需要系统 libstdc++、libgcc 等标准库。此次仅重新打包，未重新编译。

`Dockerfile.linux-arm64` 使用已通过官方 SHA256 校验导入的 CentOS 7 ARM64 本地镜像 `php81-build-centos-arm64:7`；对应官方 manifest 为 `centos@sha256:73f11afcbb50d8bc70eab9f0850b3fa30e61a419bc48cf426e63527d14a8373b`。

这些安装包可作为 GitHub Releases 附件分发。每个平台的运行验证结果见包内 `build-info.json`、`cli-verification.txt` 和 `fpm-verification.txt`。
