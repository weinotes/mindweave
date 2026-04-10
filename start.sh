#!/bin/bash
# MindWeave 启动脚本
# 用法: ./start.sh

cd "$(dirname "$0")"

# 颜色定义
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== MindWeave 启动中 ===${NC}"

# 检查 .env 是否存在
if [ ! -f .env ]; then
    echo -e "${YELLOW}复制 .env 配置文件...${NC}"
    cp .env.example .env
fi

# 检查应用密钥
if grep -q "APP_KEY=" .env && ! grep -qE "^APP_KEY=[a-zA-Z0-9:]{50,}" .env; then
    echo -e "${YELLOW}生成应用密钥...${NC}"
    php artisan key:generate --force
fi

# 检查数据库
if ! php artisan migrate --force 2>/dev/null; then
    echo -e "${YELLOW}运行数据库迁移...${NC}"
    php artisan migrate --force
fi

# 检查用户目录是否存在（无 Eloquent，改为直接查文件）
if [ ! -f "userdata/users/"*.json ] 2>/dev/null; then
    echo -e "${YELLOW}首次启动，自动以访客模式运行${NC}"
fi

# 检查 Ollama 是否运行
if ! curl -s http://127.0.0.1:11434/api/tags > /dev/null 2>&1; then
    echo -e "${YELLOW}警告: Ollama 未运行，请先启动 Ollama${NC}"
    echo -e "  macOS: 打开 Ollama 应用"
    echo -e "  Linux: ollama serve"
fi

# 启动服务器
echo -e "${GREEN}启动 Laravel 开发服务器...${NC}"
echo -e "${GREEN}=======================================${NC}"
echo -e "访问地址: ${YELLOW}http://127.0.0.1:3456${NC}"
echo -e "按 Ctrl+C 停止服务"
echo -e "${GREEN}=======================================${NC}"

php artisan serve --host=0.0.0.0 --port=3456
