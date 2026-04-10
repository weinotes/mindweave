# MindWeave 版本记录

> 每次升级前必读本文档，确保平滑过渡。

---

## 当前版本：v1.3.2

**发布日期:** 2026-04-10
**技术栈:** Laravel 11 + Ollama + SQLite + marked.js
**运行端口:** 3456

### v1.3.2 功能优化（2026-04-10）

| 类别 | 改动 |
|------|------|
| 重构 | `chat.blade.php` 拆分为 `public/css/chat.css` + `public/js/chat.js`，模板从 2991 行减至 234 行 |
| 体验 | 回车发送消息（`Enter` 发送，`Shift+Enter` 换行） |
| 体验 | Ollama 离线时首次发送前检测，实时提示连接失败原因 |
| 安全 | 密码 MD5 → bcrypt，已有用户下次登录自动升级（零破坏性迁移） |
| 杂项 | 依赖版本锁定（`composer.lock`）；`.env.example` 补全 `OLLAMA_HOST`/`OLLAMA_MODEL` |
| 杂项 | 删除废弃 `streamPullModel` SSE 下载代码（121 行） |

### v1.3.2 修复问题（2026-04-10）

| 问题 | 修复方案 |
|------|----------|
| 新用户安装后无默认账号 | 首次访问自动创建 `admin / admin` |
| 登录页无注册入口 | 改为 Tab 切换（登录/注册），支持创建多人账号 |
| 旧 guest 自动登录逻辑 | 移除，统一走登录流程 |

### v1.3.1 修复问题

| 问题 | 修复方案 |
|------|----------|
| SSE 流式输出失效 | 改用 `popen()` + `curl` 命令行替代 PHP HTTP 流，绕过缓冲问题 |
| 刷新后回到新对话 | 使用 `localStorage` 持久化 session ID，刷新自动恢复 |
| 亮色模式滚动条黑块 | 添加 `[data-theme="light"] ::-webkit-scrollbar-thumb` 样式覆盖 |
| 思考过程（think）字段丢失 | 后端 JSON 解析时正确提取 `message.think` 字段 |

### v1.3.0 新增功能

| 功能 | 状态 | 说明 |
|------|------|------|
| 会话搜索 | ✅ | 全局搜索历史消息，支持模糊匹配 |
| 快捷键支持 | ✅ | `Ctrl+K`搜索、`Ctrl+N`新建、`Ctrl+/`帮助、`ESC`关闭 |
| 消息编辑 | ✅ | 点击编辑按钮修改已发送消息 |
| 消息删除 | ✅ | 删除单条消息及其后续消息 |
| 重新生成 | ✅ | 重新生成助手回复 |
| 会话重命名 | ✅ | 自定义会话标题 |

### v1.3.0 新增路由

```
GET  /search                    → 搜索消息
PATCH /sessions/{id}/rename    → 重命名会话
PATCH /sessions/{id}/messages/{index}     → 编辑消息
DELETE /sessions/{id}/messages/{index}    → 删除消息
POST /sessions/{id}/messages/{index}/regenerate → 重新生成
```

---

## v1.2.1

**发布日期:** 2026-04-10

### v1.2.1 新增功能

| 功能 | 状态 | 说明 |
|------|------|------|
| 硬件检测 | ✅ | `HardwareController` 检测 CPU/内存/GPU |
| 模型推荐 | ✅ | 根据硬件配置自动推荐适合模型 |
| Apple Silicon 识别 | ✅ | 统一内存架构检测 + 优化推荐 |
| NVIDIA GPU 检测 | ✅ | VRAM 大小识别 |
| SSE 流式下载 | ✅ | 实时进度条 + 剩余时间估算 |
| 下载前验证 | ✅ | `GET /models/check/{name}` 检查模型是否可用 |
| 取消下载 | ✅ | `POST /models/pull/cancel/{name}` |
| 亮色主题完善 | ✅ | 弹窗/按钮/进度条/推荐卡片样式 |

### v1.2.1 修复问题

| 问题 | 修复 |
|------|------|
| 可用内存显示 0GB | macOS 改为统计 free+inactive+speculative |
| 深色模式"刷新检测"看不清 | 添加 `color:var(--text-secondary)` |
| 点击下载无反应 | 端口混淆(8080→3456)，统一启动脚本 |

### 新增路由

```
GET  /hardware              → HardwareController@info
GET  /hardware/recommend    → HardwareController@recommend
GET  /models/check/{name}   → ChatController@checkModel
GET  /models/pull/stream    → ChatController@streamPullModel (SSE)
POST /models/pull/cancel/{name} → ChatController@cancelPull
```

### 新增文件

```
app/Http/Controllers/HardwareController.php
```

---

## v1.1.0 版本说明

| 功能 | 状态 | 说明 |
|------|------|------|
| 流式输出（SSE） | ✅ | `/chat/stream` 接口，实时打字机效果 |
| 模型管理界面 | ✅ | 弹窗内查看/下载/删除已安装模型 |
| 轮询下载进度 | ✅ | 每5秒检查一次，最长等待2分钟 |
| 模型大小显示 | ✅ | GB/MB/KB 自动换算 |
| 模型选择器升级 | ✅ | 支持 name/size 结构，兼容新 API 格式 |
| 会话模型记忆 | ✅ | 加载历史会话时自动切换对应模型 |
| 🧠 思考过程展示 | ✅ | Ollama `think: true`，`<details>` 可折叠 |
| 📋 代码块一键复制 | ✅ | hover 显示复制按钮，复制原始代码 |
| 🌓 亮/暗主题切换 | ✅ | 侧边栏 🌙 按钮，localStorage 持久化 |
| 🔁 Ollama 断线重试 | ✅ | 自动重试3次（间隔 2/4/6s），显示倒计时 |

---

## v1.0.0 版本说明（基础版）

### 核心功能

| 功能 | 状态 | 说明 |
|------|------|------|
| 多模型切换 | ✅ | 自动读取 Ollama 模型列表 |
| 多用户/会话 | ✅ | 每个用户独立数据目录（userdata/用户名/） |
| 密码保护 | ✅ | MD5 存储，可选为账号设密码 |
| Markdown 渲染 | ✅ | marked.js，支持代码高亮、列表、表格 |
| 对话导出 | ✅ | MD 和 JSON 两种格式 |
| 上下文记忆 | ✅ | 多轮连续对话 |
| 存储路径配置 | ✅ | 用户可自定义 userdata 位置 |
| 深色主题界面 | ✅ | Deep Marine 风格 |
| 诗词排版 | ✅ | white-space: pre-wrap 保留换行和缩进 |

### 文件结构

```
mindweave/
├── app/
│   ├── Http/Controllers/
│   │   ├── ChatController.php    # 聊天核心：/chat POST, /models GET
│   │   └── LoginController.php  # 认证：/login POST, /logout GET
│   ├── Middleware/
│   │   └── CheckPassword.php    # 首次访问自动创建 guest 用户
│   └── Services/
│       └── UserService.php      # 用户/会话 CRUD，密码 MD5
├── config/
│   └── ollama.php               # Ollama 连接配置（host/port）
├── resources/views/
│   ├── chat.blade.php            # 主界面（CSS + JS + HTML）
│   └── login.blade.php           # 登录页
├── docs/
│   ├── index.html                # 用户文档（内含使用说明、FAQ）
│   └── screenshot.png            # 界面截图（手动截取）
├── userdata/                     # 用户数据目录（已加入 .gitignore）
│   ├── userdata_path.txt         # 存储路径记录（已加入 .gitignore）
│   └── [用户名]/sessions.json    # 各用户会话数据
└── database/database.sqlite      # SQLite 数据库（首次运行时自动创建）
```

### 关键配置参考

**.env 配置项：**
```env
OLLAMA_HOST=http://localhost:11434    # Ollama API 地址
OLLAMA_MODEL=poet-qwen:latest         # 默认模型
DB_CONNECTION=sqlite
SESSION_DRIVER=database
```

**Ollama config/config/ollama.php：**
```php
'port' => 11434,
'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
'model' => env('OLLAMA_MODEL', 'poet-qwen:latest'),
'timeout' => 120,
```

**端口信息：**
- MindWeave Web 服务：**3456**（`php artisan serve --port=3456`）
- Ollama API：**11434**（`ollama serve`）

---

## 升级指南

### 升级前检查清单

- [ ] 备份 `userdata/` 目录
- [ ] 备份 `.env` 文件
- [ ] 记录当前版本号（`cat VERSION.md`）
- [ ] 确认 Ollama 版本（`ollama --version`）
- [ ] 确认 PHP 版本（`php -v`，需要 ^8.2）
- [ ] 记录当前模型列表（`ollama list`）

### 升级后必做事项

1. `composer install` 更新依赖
2. `php artisan key:generate`（如有需要）
3. `php artisan migrate`（如有数据库迁移）
4. 清除缓存：`php artisan config:clear && php artisan view:clear`
5. 测试核心功能：发送消息、切换模型、导出对话
6. 更新 `VERSION.md` 中的版本号和变更说明

---

## 已知限制

1. **无群组/分享功能**：纯私有部署
2. **密码 MD5 哈希**：内网场景足够，如有外网暴露建议改用 bcrypt
3. **Session ID 简单**：8位 MD5 前缀 + 时间戳，内网场景无枚举风险
4. **Windows 兼容性**：CPU 检测已修复，但未在 Windows 环境完整测试
5. **无单元测试**：快速迭代期，后续补充
6. **模型删除后状态**：删除模型后前端 `currentModel` 可能仍指向旧模型，需刷新页面
7. **导出功能依赖消息**：空对话无法导出

---

## 未来升级方向（v1.4.0 候选）

| 功能 | 优先级 | 说明 |
|------|--------|------|
| 单元测试 | 中 | PHPUnit 测试覆盖核心功能 |
| Windows 完整测试 | 中 | 验证所有功能在 Windows 上正常 |
| 多语言支持 | 低 | i18n 化 |
| 性能监控 | 低 | 记录响应时间、内存占用 |

---

*本文档随每次版本发布更新，由 T博士 维护。*

---

## 历史版本

### v1.0.0（初始版本）— 2026-04-09
- 初始发布
- GitHub: https://github.com/weinotes/mindweave
- Commit: `d998338`
