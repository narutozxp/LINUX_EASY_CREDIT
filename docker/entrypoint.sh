#!/bin/bash
set -e

echo "Starting Reward Website..."

# 创建必要的目录
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/logs/orders
mkdir -p /run/nginx

# 设置权限
chown -R www-data:www-data /var/www/html/logs
chmod -R 777 /var/www/html/logs

# 检查配置文件
if [ ! -f "/var/www/html/config/config.php" ]; then
    echo "警告: config.php 未找到，请确保已配置 API 密钥"
    if [ -f "/var/www/html/config/config.example.php" ]; then
        echo "提示: 可以复制 config.example.php 到 config.php 并修改配置"
    fi
fi

# 启动 PHP-FPM
echo "Starting PHP-FPM..."
php-fpm -D

# 等待 PHP-FPM 启动
sleep 2

# 检查 PHP-FPM 是否正常运行
if ! pgrep -x "php-fpm" > /dev/null; then
    echo "错误: PHP-FPM 启动失败"
    exit 1
fi

echo "PHP-FPM started successfully"

# 启动 Nginx（前台运行）
echo "Starting Nginx..."
exec nginx -g 'daemon off;'
