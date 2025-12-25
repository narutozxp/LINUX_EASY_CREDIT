# 部署文档

本文档介绍如何在生产环境中持久化运行打赏网站项目。

---

## 📋 部署方案对比

| 方案 | 性能 | 稳定性 | HTTPS | 复杂度 | 适用场景 |
|------|------|--------|-------|--------|----------|
| **Docker + Nginx + PHP-FPM** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ✅ | ⭐ | 🔥 生产环境（强烈推荐）|
| **1Panel + OpenResty + PHP-FPM** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ✅ | ⭐⭐ | 生产环境（推荐）|
| **Nginx + PHP-FPM** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ✅ | ⭐⭐⭐ | 生产环境 |
| **Systemd + PHP 内置服务器** | ⭐⭐ | ⭐⭐⭐ | ❌ | ⭐ | 测试环境 |
| **PHP 内置服务器** | ⭐ | ⭐ | ❌ | ⭐ | 本地开发 |

> 💡 **推荐使用 Docker 部署**：环境一致、快速部署、易于迁移。详细文档请查看 [DOCKER.md](DOCKER.md)

---

## 🎯 方案一：1Panel + OpenResty（推荐）

### 适用场景
- 已安装 1Panel 面板
- 需要图形化管理
- 需要 HTTPS 支持
- 生产环境部署

### 部署步骤

#### 1. 安装 PHP（如未安装）

在 1Panel 面板中：
1. **运行环境** → **PHP**
2. 点击 **安装**，选择 PHP 8.3 或更高版本
3. 等待安装完成

#### 2. 创建网站

1. **网站** → **创建网站**
2. 填写配置：
   ```
   域名：tip.yourdomain.com（改成你的域名）
   类型：PHP 项目
   PHP 版本：8.3
   网站目录：/home/paygo/reward-website
   运行方式：PHP-FPM
   ```
3. 点击 **确定** 创建

#### 3. 配置 Nginx

点击网站 → **配置** → 编辑配置文件，确保包含以下内容：

```nginx
server {
    listen 80;
    server_name tip.yourdomain.com;  # 改成你的域名

    root /home/paygo/reward-website;
    index index.html index.php;

    charset utf-8;

    # 访问日志
    access_log /www/wwwlogs/reward-website-access.log;
    error_log /www/wwwlogs/reward-website-error.log;

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # PHP 处理
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-83.sock;  # PHP 8.3 的 socket
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    # API 路由
    location /api/ {
        try_files $uri $uri/ =404;
    }

    # 默认路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 安全配置：禁止访问隐藏文件
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # 安全配置：禁止访问配置目录
    location ~* ^/(config|logs)/ {
        deny all;
    }
}
```

#### 4. 查看 PHP-FPM Socket 路径

在 1Panel 中确认 PHP-FPM socket 路径：
- **运行环境** → **PHP** → 选择你的 PHP 版本 → 查看配置

常见路径：
- PHP 8.3: `/tmp/php-cgi-83.sock`
- PHP 8.2: `/tmp/php-cgi-82.sock`
- PHP 8.1: `/tmp/php-cgi-81.sock`

**在 Nginx 配置中修改为对应路径**。

#### 5. 设置目录权限

```bash
# 切换到项目目录
cd /home/paygo/reward-website

# 设置所有者（1Panel 默认使用 www）
sudo chown -R www:www /home/paygo/reward-website

# 设置目录权限
sudo chmod -R 755 /home/paygo/reward-website

# 确保 logs 目录可写
mkdir -p logs
sudo chmod 755 logs

# 确保订单目录可写
mkdir -p logs/orders
sudo chmod 755 logs/orders
```

#### 6. 申请 SSL 证书（可选但推荐）

1. 在 1Panel 网站列表中，点击网站
2. 点击 **SSL** → **申请证书**
3. 选择 **Let's Encrypt**
4. 点击 **申请**
5. 等待证书申请成功

申请成功后，网站会自动启用 HTTPS。

**记得修改配置文件中的回调地址为 HTTPS**：
```php
// config/config.php
'notify_url' => 'https://tip.yourdomain.com/api/notify.php',
'return_url' => 'https://tip.yourdomain.com/success.html',
```

#### 7. 重启服务

在 1Panel 中：
- **网站** → 找到你的网站 → 点击 **重启**

或命令行：
```bash
sudo systemctl restart openresty
```

#### 8. 测试访问

访问：`https://tip.yourdomain.com/index.html`（如果启用了 HTTPS）

---

## 🔧 方案二：Nginx + PHP-FPM（传统部署）

### 适用场景
- 未使用 1Panel
- 传统 VPS 部署
- 需要完全控制配置

### 部署步骤

#### 1. 安装依赖

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install -y nginx php-fpm php-curl php-json php-mbstring
```

**CentOS/RHEL:**
```bash
sudo yum install -y nginx php-fpm php-curl php-json php-mbstring
```

#### 2. 查找 PHP-FPM Socket

```bash
# 查找 socket 文件
sudo find /var/run /run -name "*fpm.sock" 2>/dev/null

# 常见路径：
# Ubuntu/Debian: /run/php/php8.3-fpm.sock
# CentOS/RHEL: /var/run/php-fpm/php-fpm.sock
```

#### 3. 创建 Nginx 配置

```bash
sudo nano /etc/nginx/sites-available/reward-website
```

配置内容：
```nginx
server {
    listen 80;
    server_name tip.yourdomain.com;  # 改成你的域名

    root /home/paygo/reward-website;
    index index.html index.php;

    charset utf-8;

    # 访问日志
    access_log /var/log/nginx/reward-website-access.log;
    error_log /var/log/nginx/reward-website-error.log;

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # PHP 处理
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;  # 改成实际路径
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    # API 路由
    location /api/ {
        try_files $uri $uri/ =404;
    }

    # 默认路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 安全配置
    location ~ /\. {
        deny all;
    }

    location ~* ^/(config|logs)/ {
        deny all;
    }
}
```

#### 4. 启用网站

```bash
# 创建软链接
sudo ln -s /etc/nginx/sites-available/reward-website /etc/nginx/sites-enabled/

# 测试配置
sudo nginx -t

# 重启 Nginx
sudo systemctl restart nginx

# 启动并设置 PHP-FPM 开机自启
sudo systemctl start php-fpm
sudo systemctl enable php-fpm
```

#### 5. 设置权限

```bash
# 设置所有者（Nginx 默认使用 www-data 或 nginx）
sudo chown -R www-data:www-data /home/paygo/reward-website

# 设置权限
sudo chmod -R 755 /home/paygo/reward-website
mkdir -p /home/paygo/reward-website/logs/orders
sudo chmod -R 755 /home/paygo/reward-website/logs
```

#### 6. 配置 HTTPS（推荐）

使用 Certbot 申请 Let's Encrypt 免费证书：

```bash
# Ubuntu/Debian
sudo apt install -y certbot python3-certbot-nginx

# CentOS/RHEL
sudo yum install -y certbot python3-certbot-nginx

# 申请证书
sudo certbot --nginx -d tip.yourdomain.com

# 自动续期测试
sudo certbot renew --dry-run
```

#### 7. 测试访问

访问：`https://tip.yourdomain.com/index.html`

---

## ⚡ 方案三：Systemd + PHP 内置服务器

### 适用场景
- 测试环境
- 小流量网站
- 不需要 HTTPS

### 部署步骤

#### 1. 创建 Systemd 服务文件

```bash
sudo nano /etc/systemd/system/reward-website.service
```

内容：
```ini
[Unit]
Description=Reward Website PHP Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/home/paygo/reward-website
ExecStart=/usr/bin/php -S 0.0.0.0:80
Restart=always
RestartSec=5
StandardOutput=append:/home/paygo/reward-website/logs/server.log
StandardError=append:/home/paygo/reward-website/logs/server.log

[Install]
WantedBy=multi-user.target
```

**如果使用非 80 端口**（如 8080），修改 `ExecStart`：
```ini
ExecStart=/usr/bin/php -S 0.0.0.0:8080
```

#### 2. 启动服务

```bash
# 重载 systemd
sudo systemctl daemon-reload

# 启动服务
sudo systemctl start reward-website

# 设置开机自启
sudo systemctl enable reward-website

# 查看状态
sudo systemctl status reward-website
```

#### 3. 管理服务

```bash
# 停止服务
sudo systemctl stop reward-website

# 重启服务
sudo systemctl restart reward-website

# 查看日志
sudo journalctl -u reward-website -f

# 查看应用日志
tail -f /home/paygo/reward-website/logs/server.log
```

#### 4. 开放防火墙端口

```bash
# UFW 防火墙
sudo ufw allow 80/tcp

# Firewalld 防火墙
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --reload
```

---

## 🔍 故障排查

### 1. 502 Bad Gateway

**原因**：PHP-FPM 未运行或 socket 路径错误

**解决**：
```bash
# 检查 PHP-FPM 状态
sudo systemctl status php-fpm
# 或
sudo systemctl status php8.3-fpm

# 启动 PHP-FPM
sudo systemctl start php-fpm

# 检查 socket 文件是否存在
ls -la /run/php/*.sock
ls -la /tmp/php-cgi-*.sock
```

### 2. 403 Forbidden

**原因**：目录权限不足

**解决**：
```bash
# 检查目录权限
ls -la /home/paygo/reward-website

# 修复权限
sudo chown -R www-data:www-data /home/paygo/reward-website
# 或（1Panel）
sudo chown -R www:www /home/paygo/reward-website

# 设置正确权限
sudo chmod -R 755 /home/paygo/reward-website
```

### 3. 404 Not Found

**原因**：Nginx 配置错误或文件不存在

**解决**：
```bash
# 检查 root 路径是否正确
sudo nginx -T | grep "root"

# 检查文件是否存在
ls -la /home/paygo/reward-website/index.html

# 测试 Nginx 配置
sudo nginx -t
```

### 4. PHP 文件被下载而不是执行

**原因**：PHP 配置未生效

**解决**：
```bash
# 检查 Nginx 配置中的 fastcgi_pass
sudo nginx -T | grep "fastcgi_pass"

# 确保包含 location ~ \.php$ 配置块

# 重启服务
sudo systemctl restart nginx
sudo systemctl restart php-fpm
```

### 5. 回调通知收不到

**原因**：notify_url 配置错误或无法访问

**解决**：
```bash
# 检查配置文件
cat config/config.php | grep notify_url

# 测试回调地址是否可访问
curl -I https://tip.yourdomain.com/api/notify.php

# 查看回调日志
tail -f logs/$(date +%Y-%m-%d).log

# 确保 Linux.do Credit 后台配置的地址和本地一致
```

### 6. 查看日志

```bash
# Nginx 错误日志
sudo tail -f /var/log/nginx/error.log
sudo tail -f /www/wwwlogs/reward-website-error.log  # 1Panel

# PHP-FPM 日志
sudo tail -f /var/log/php-fpm/error.log
sudo tail -f /var/log/php8.3-fpm.log

# 应用日志
tail -f /home/paygo/reward-website/logs/$(date +%Y-%m-%d).log
```

---

## 🔒 安全加固

### 1. 隐藏 PHP 版本

编辑 `php.ini`：
```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

修改：
```ini
expose_php = Off
```

重启 PHP-FPM：
```bash
sudo systemctl restart php-fpm
```

### 2. 限制访问敏感目录

已在 Nginx 配置中添加：
```nginx
location ~* ^/(config|logs)/ {
    deny all;
}
```

### 3. 启用 HTTPS

**强烈建议生产环境启用 HTTPS**，防止中间人攻击。

使用 Let's Encrypt 免费证书（参见方案二第6步）。

### 4. 配置防火墙

```bash
# 仅开放必要端口
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### 5. 定期更新

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y  # Ubuntu/Debian
sudo yum update -y                       # CentOS/RHEL

# 更新 PHP
sudo apt upgrade php* -y
```

---

## 📊 性能优化

### 1. 启用 PHP OPcache

编辑 `php.ini`：
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### 2. 调整 PHP-FPM 进程数

编辑 PHP-FPM 配置：
```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

根据服务器配置调整：
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### 3. 启用 Gzip 压缩

在 Nginx 配置中添加：
```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;
```

---

## 📚 相关文档

- [README.md](README.md) - 项目介绍和快速开始
- [QUICKSTART.md](QUICKSTART.md) - 快速开始指南
- [DOCKER.md](DOCKER.md) - 🔥 Docker 部署指南（推荐）
- [API.md](API.md) - API 接口文档
- [THEME.md](THEME.md) - UI 主题自定义

---

## 📧 技术支持

- **Linux.do Credit 文档**: https://credit.linux.do/docs
- **GitHub 仓库**: https://github.com/Razewang/LINUX_EASY_CREDIT

---

**文档版本**: v1.0
**最后更新**: 2025-12-25
**维护者**: Reward Website Project
