<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MindWeave - 本地 AI 工作台</title>
    <script src="/vendor/marked.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-tertiary: #1a1a24;
            --bg-card: #16161e;
            --border: #2a2a3a;
            --border-light: #22222e;
            --text-primary: #e4e4e7;
            --text-secondary: #71717a;
            --accent: #c9a85c;
            --accent-glow: rgba(201,168,92,0.3);
            --danger: #ef4444;
            --success: #22c55e;
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #0d0d14 50%, #0f0f18 100%);
            color: var(--text-primary);
        }

        .app { display: flex; height: 100vh; overflow: hidden; }

        /* ====== 侧边栏 ====== */
        .sidebar {
            width: 270px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid var(--border-light);
        }

        .sidebar-header h1 {
            font-size: 19px;
            font-weight: 600;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-badge {
            padding: 6px 12px;
            margin: 12px 20px 0;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-badge .avatar {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--accent), #b8956a);
            color: #0a0a0f;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .new-chat-btn {
            margin: 12px 16px;
            padding: 11px 16px;
            background: linear-gradient(135deg, var(--accent) 0%, #b8956a 100%);
            color: #0a0a0f;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .new-chat-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .sessions-label {
            padding: 4px 20px;
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sessions-list {
            flex: 1;
            overflow-y: auto;
            padding: 4px 12px;
        }

        .session-item {
            padding: 10px 14px;
            margin-bottom: 2px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-secondary);
            transition: all 0.15s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            position: relative;
            display: flex;
            align-items: center;
        }

        .session-item:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .session-item.active { background: var(--bg-tertiary); color: var(--accent); }
        .session-item .title { flex: 1; overflow: hidden; text-overflow: ellipsis; }

        .session-del {
            opacity: 0;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            cursor: pointer;
            margin-left: 4px;
            flex-shrink: 0;
            transition: opacity 0.15s;
        }

        .session-item:hover .session-del { opacity: 1; }

        .sidebar-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-footer button {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar-footer button:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .sidebar-footer button.danger:hover { border-color: var(--danger); color: var(--danger); }

        /* ====== 主聊天区 ====== */
        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        .chat-header {
            padding: 14px 24px;
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-primary);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .chat-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .model-select {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .chat-header-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .icon-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .icon-btn:hover { background: var(--bg-card); color: var(--text-primary); border-color: var(--accent); }

        /* ====== 消息 ====== */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .message {
            max-width: 780px;
            margin: 0 auto 20px;
            display: flex;
            gap: 14px;
            animation: fadeInUp 0.25s ease;
        }

        /* 用户消息靠右 */
        .message.user {
            flex-direction: row-reverse;
        }

        /* 助手消息靠左 */
        .message.assistant {
            flex-direction: row;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message .avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .message.user .avatar { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .message.assistant .avatar { background: linear-gradient(135deg, var(--accent), #b8956a); }

        .message .content {
            flex: 1;
            line-height: 1.75;
            font-size: 15px;
            overflow-wrap: break-word;
            max-width: calc(100% - 50px);
        }

        /* 用户消息气泡样式 */
        .message.user .content {
            color: #e4e4e7;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px 14px 4px 14px;
            padding: 12px 16px;
        }

        /* 助手消息气泡样式 */
        .message.assistant .content {
            color: #d4d4d8;
            background: transparent;
            padding: 0;
        }

        /* Markdown 渲染内容 */
        .message .content p { margin: 0 0 10px; }
        .message .content p:last-child { margin-bottom: 0; }
        .message .content strong { color: #f4f4f5; font-weight: 600; }
        .message .content em { color: #a1a1aa; font-style: italic; }

        /* 代码块 */
        .message .content pre {
            background: #0d0d14;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            margin: 10px 0;
            overflow-x: auto;
            position: relative;
        }
        .message .content pre code {
            font-family: 'SF Mono', 'Fira Code', 'Fira Mono', Menlo, monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #e4e4e7;
            background: none;
            padding: 0;
        }
        /* 行内代码 */
        .message .content code:not(pre code) {
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 12.5px;
            background: #0d0d14;
            border: 1px solid var(--border);
            color: var(--accent);
            padding: 2px 6px;
            border-radius: 5px;
        }
        /* 引用块 */
        .message .content blockquote {
            border-left: 3px solid var(--accent);
            margin: 10px 0;
            padding: 8px 14px;
            color: #71717a;
            background: rgba(201,168,92,0.05);
            border-radius: 0 8px 8px 0;
        }
        .message .content blockquote p { margin: 0; }
        /* 列表 */
        .message .content ul,
        .message .content ol {
            margin: 8px 0;
            padding-left: 20px;
        }
        .message .content li { margin: 4px 0; line-height: 1.7; }
        .message .content ul li { list-style-type: disc; }
        .message .content ol li { list-style-type: decimal; }
        /* 标题 */
        .message .content h1,
        .message .content h2,
        .message .content h3 {
            color: #f4f4f5;
            margin: 14px 0 6px;
            font-weight: 600;
        }
        .message .content h1 { font-size: 18px; }
        .message .content h2 { font-size: 16px; }
        .message .content h3 { font-size: 15px; }
        /* 链接 */
        .message .content a { color: var(--accent); text-decoration: underline; }
        .message .content a:hover { opacity: 0.8; }
        /* 分割线 */
        .message .content hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 14px 0;
        }
        /* 表格 */
        .message .content table {
            width: auto;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 13px;
        }
        .message .content th,
        .message .content td {
            border: 1px solid var(--border);
            padding: 7px 12px;
            text-align: left;
        }
        .message .content th {
            background: var(--bg-tertiary);
            color: #f4f4f5;
        }
        .message .content tr:nth-child(even) td { background: rgba(255,255,255,0.02); }

        /* ====== 输入区 ====== */
        .input-area {
            padding: 14px 32px 24px;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-light);
            flex-shrink: 0;
        }

        .input-wrapper {
            max-width: 780px;
            margin: 0 auto;
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            transition: all 0.3s;
        }

        .input-wrapper:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .input-wrapper textarea {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 15px;
            resize: none;
            outline: none;
            min-height: 26px;
            max-height: 140px;
            line-height: 1.6;
        }

        .input-wrapper textarea::placeholder { color: var(--text-secondary); }

        .input-wrapper button {
            background: linear-gradient(135deg, var(--accent), #b8956a);
            color: #0a0a0f;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
            align-self: flex-end;
        }

        .input-wrapper button:hover:not(:disabled) { transform: translateY(-1px); }
        .input-wrapper button:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ====== 滚动条 ====== */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        /* ====== Modal ====== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
        }

        .modal-overlay.active { opacity: 1; visibility: visible; }

        .modal {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            width: 440px;
            max-width: 92vw;
            max-height: 85vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: transform 0.2s;
            position: relative;
        }

        .modal-overlay.active .modal { transform: translateY(0); }

        .modal h3 {
            font-size: 16px;
            margin-bottom: 6px;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal p.sub {
            color: var(--text-secondary);
            font-size: 12px;
            margin-bottom: 20px;
        }

        /* Tabs */
        .modal-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: var(--bg-tertiary);
            padding: 4px;
            border-radius: 10px;
        }

        .modal-tabs button {
            flex: 1;
            padding: 8px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.15s;
        }

        .modal-tabs button.active {
            background: var(--bg-card);
            color: var(--accent);
            font-weight: 500;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* User list */
        .user-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }

        .user-row {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            background: var(--bg-tertiary);
            border-radius: 10px;
            gap: 10px;
        }

        .user-row .name {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        .user-row .badge {
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 10px;
            background: var(--bg-card);
            color: var(--text-secondary);
        }

        .user-row .actions { display: flex; gap: 6px; }

        .user-row button {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            transition: all 0.15s;
        }

        .user-row button:hover { background: var(--bg-card); color: var(--text-primary); }
        .user-row button.danger:hover { border-color: var(--danger); color: var(--danger); }

        /* Form */
        .form-row {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .form-row input {
            flex: 1;
            padding: 10px 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }

        .form-row input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }

        .form-row button {
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--accent), #b8956a);
            color: #0a0a0f;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .form-row button:hover { transform: translateY(-1px); }

        .modal-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-secondary {
            flex: 1;
            padding: 10px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-secondary:hover { background: var(--bg-tertiary); color: var(--text-primary); }

        .hint-box {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 14px;
            padding: 10px 12px;
            background: var(--bg-tertiary);
            border-radius: 8px;
            line-height: 1.6;
        }

        .hint-box code {
            color: var(--accent);
            font-family: monospace;
            font-size: 10px;
            background: var(--bg-card);
            padding: 1px 5px;
            border-radius: 4px;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 10px 22px;
            border-radius: 12px;
            font-size: 13px;
            opacity: 0;
            transition: all 0.3s;
            z-index: 2000;
            pointer-events: none;
        }

        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.success { border-color: var(--success); }
        .toast.error { border-color: var(--danger); }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>✨ MindWeave</h1>
            </div>

            <div class="user-badge">
                <span class="avatar">{{ strtoupper(substr($username ?? 'G', 0, 1)) }}</span>
                <span>{{ $username ?? 'guest' }}</span>
            </div>

            <button class="new-chat-btn" onclick="newChat()">+ 新建对话</button>

            <div class="sessions-label">历史会话</div>
            <div class="sessions-list" id="sessionsList"></div>

            <div class="sidebar-footer">
                <button onclick="openTab('account')">👤 {{ $username ?? 'guest' }}</button>
                <button onclick="openTab('users')">👥 用户管理</button>
                <form method="POST" action="/logout" style="margin:0;">
                    @csrf
                    <button type="submit" class="danger">🚪 退出登录</button>
                </form>
            </div>
        </aside>

        <main class="main">
            <header class="chat-header">
                <div class="chat-header-left">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="color: var(--text-secondary); font-size: 12px; white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                            模型
                        </label>
                        <select class="model-select" id="modelSelect"></select>
                    </div>
                </div>
                <div class="chat-header-right">
                    <button class="icon-btn" onclick="exportMarkdown()">📄 MD</button>
                    <button class="icon-btn" onclick="exportJSON()">📋 JSON</button>
                    <button class="icon-btn" onclick="confirmClear()">🗑️ 清空</button>
                </div>
            </header>

            <div class="messages-container" id="messagesContainer">
                <div class="message assistant">
                    <div class="avatar">🤖</div>
                    <div class="content">你好！我是 MindWeave，你的本地 AI 助手。有什么我可以帮助你的吗？</div>
                </div>
            </div>

            <div class="input-area">
                <div class="input-wrapper">
                    <textarea
                        id="userInput"
                        placeholder="输入消息... (Shift+Enter 换行)"
                        rows="1"
                        onkeydown="handleKeyDown(event)"
                        oninput="autoResize(this)"
                    ></textarea>
                    <button id="sendBtn" onclick="sendMessage()">发送</button>
                </div>
            </div>
        </main>
    </div>

    <!-- ========== 设置弹窗 ========== -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal">
            <h3>⚙️ 设置</h3>

            <div class="modal-tabs">
                <button class="active" onclick="switchTab(this, 'account')">账号</button>
                <button onclick="switchTab(this, 'users')">用户管理</button>
                <button onclick="switchTab(this, 'storage')">存储</button>
            </div>

            <!-- 账号 Tab -->
            <div class="tab-content active" id="tab-account">
                <p class="sub">当前登录：<strong>{{ $username ?? 'guest' }}</strong></p>

                <div class="form-row">
                    <input type="password" id="newPwd" placeholder="设置新密码（留空不修改）">
                    <input type="password" id="confirmPwd" placeholder="确认密码">
                </div>
                <div class="form-row">
                    <input type="password" id="currentPwd" placeholder="如需修改密码，请先输入当前密码">
                    <button onclick="saveMyPassword()">保存</button>
                </div>
                <div class="hint-box">
                    💡 密码将以 MD5 加密存储。设置后每次访问需输入密码验证。
                </div>
            </div>

            <!-- 用户管理 Tab -->
            <div class="tab-content" id="tab-users">
                <p class="sub">创建账号后，用户可独立管理自己的对话记录。</p>

                <div class="form-row">
                    <input type="text" id="newUsername" placeholder="新用户名（2-20位字母/数字/下划线）">
                    <button onclick="createUser()">创建</button>
                </div>

                <div id="userList"></div>

                <div class="hint-box">
                    👤 访客账号（guest）无需密码可直接进入。<br>
                    🔐 每个用户可设置独立密码，数据完全隔离。
                </div>
            </div>

            <!-- 存储 Tab -->
            <div class="tab-content" id="tab-storage">
                <p class="sub">数据存放位置</p>
                <div class="form-row">
                    <select id="storagePreset" onchange="onStoragePresetChange(this.value)" style="flex:0 0 120px;">
                        <option value="custom">自定义</option>
                        <option value="~/mindweave/userdata">~/mindweave/userdata</option>
                        <option value="~/Documents/mindweave-data">~/Documents/mindweave-data</option>
                        <option value="~/Desktop/mindweave-data">~/Desktop/mindweave-data</option>
                    </select>
                    <input type="text" id="storagePath" placeholder="完整路径，如 /Users/xxx/data" style="flex:1;">
                </div>
                <div class="form-row">
                    <button onclick="saveStoragePath()">保存路径</button>
                </div>
                <div class="hint-box" id="storageHint">💡 当前数据路径将在保存后切换。切换后旧数据会自动迁移。</div>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeSettings()" style="flex:1">关闭</button>
            </div>
        </div>
    </div>

    <!-- 确认清空弹窗 -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal">
            <h3>🗑️ 确认清空</h3>
            <p class="sub" style="margin-bottom:20px;">确定要清空当前对话吗？此操作不可撤销。</p>
            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeConfirm()">取消</button>
                <button class="btn-secondary" style="border-color:var(--danger);color:var(--danger)" onclick="clearSession()">确认清空</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const CURRENT_USER = {{ Js::from($username ?? 'guest') }};
        let currentSessionId = null;
        let currentModel = 'poet-qwen:latest';
        let allMessages = [];

        // Auto-resize textarea
        function autoResize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 140) + 'px';
        }

        // Toast
        function toast(msg, type = 'success') {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = `toast ${type} show`;
            setTimeout(() => t.className = 'toast', 2500);
        }

        // ===== Models =====
        async function loadModels() {
            try {
                const r = await fetch('/models');
                const models = await r.json();
                const s = document.getElementById('modelSelect');
                s.innerHTML = models.map(m => `<option value="${m}">${m}</option>`).join('');
                if (models.length) currentModel = models[0];
            } catch (e) {
                document.getElementById('modelSelect').innerHTML = '<option>poet-qwen:latest</option>';
            }
        }

        // ===== Sessions =====
        async function newChat() {
            try {
                const r = await fetch('/sessions', { method: 'POST' });
                const s = await r.json();
                currentSessionId = s.id;
                allMessages = [];
                document.getElementById('messagesContainer').innerHTML = `
                    <div class="message assistant">
                        <div class="avatar">🤖</div>
                        <div class="content">你好！我是 MindWeave，你的本地 AI 助手。有什么我可以帮助你的吗？</div>
                    </div>`;
                document.getElementById('chatTitle').textContent = '新对话';
                document.getElementById('userInput').value = '';
                document.getElementById('userInput').style.height = 'auto';
                document.getElementById('userInput').focus();
                loadSessionsList();
            } catch (e) { console.error(e); }
        }

        async function loadSessionsList() {
            try {
                const r = await fetch('/sessions');
                const sessions = await r.json();
                const list = document.getElementById('sessionsList');
                list.innerHTML = sessions.map(s => `
                    <div class="session-item ${s.id === currentSessionId ? 'active' : ''}">
                        <span class="title" onclick="loadSession('${s.id}')">${s.messages[0]?.content?.substring(0,35) || '新对话'}…</span>
                        <button class="session-del" onclick="event.stopPropagation(); delSession('${s.id}')">删除</button>
                    </div>`).join('');
            } catch (e) { console.error(e); }
        }

        async function loadSession(id) {
            try {
                const r = await fetch(`/sessions/${id}`);
                const s = await r.json();
                currentSessionId = id;
                allMessages = s.messages || [];
                const container = document.getElementById('messagesContainer');
                container.innerHTML = allMessages.map(m => `
                    <div class="message ${m.role}">
                        <div class="avatar">${m.role === 'user' ? '👤' : '🤖'}</div>
                        <div class="content">${esc(m.content)}</div>
                    </div>`).join('');
                container.scrollTop = container.scrollHeight;
                document.getElementById('chatTitle').textContent = (allMessages[0]?.content || '新对话').substring(0, 20);
                loadSessionsList();
            } catch (e) { console.error(e); }
        }

        async function delSession(id) {
            if (!confirm('删除此会话？')) return;
            await fetch(`/sessions/${id}`, { method: 'DELETE' });
            if (currentSessionId === id) await newChat();
            loadSessionsList();
            toast('已删除');
        }

        async function clearSession() {
            closeConfirm();
            if (!currentSessionId) { toast('暂无对话', 'error'); return; }
            await fetch(`/sessions/${currentSessionId}`, { method: 'DELETE' });
            await newChat();
            toast('已清空');
        }

        // ===== Chat =====
        async function sendMessage() {
            const input = document.getElementById('userInput');
            const msg = input.value.trim();
            if (!msg) return;

            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            input.value = '';
            input.style.height = 'auto';

            appendMsg('user', msg);
            const loadingEl = appendMsg('assistant', '思考中…');

            try {
                const r = await fetch('/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg, model: currentModel, session_id: currentSessionId })
                });
                const data = await r.json();
                currentSessionId = data.session_id;
                loadingEl.remove();
                appendMsg('assistant', data.response);
                loadSessionsList();
            } catch (e) {
                loadingEl.remove();
                appendMsg('assistant', '请求失败: ' + e.message);
            }
            btn.disabled = false;
            input.focus();
        }

        function appendMsg(role, content) {
            const c = document.getElementById('messagesContainer');
            const d = document.createElement('div');
            d.className = `message ${role}`;

            // 助手消息渲染 Markdown，用户消息转义
            const rendered = role === 'assistant'
                ? (typeof marked !== 'undefined' ? marked.parse(content) : esc(content))
                : esc(content);

            d.innerHTML = `<div class="avatar">${role === 'user' ? '👤' : '🤖'}</div><div class="content">${rendered}</div>`;
            c.appendChild(d);
            c.scrollTop = c.scrollHeight;
            return d;
        }

        function esc(text) {
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        function handleKeyDown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        }

        // ===== Export =====
        function exportMarkdown() {
            if (!currentSessionId || !allMessages.length) { toast('暂无对话可导出', 'error'); return; }
            const c = allMessages.map(m => `**${m.role === 'user' ? '👤 ' + CURRENT_USER : '🤖 MindWeave'}**\n\n${m.content}\n\n---\n`).join('');
            download(`# MindWeave 对话记录\n\n${c}`, `MindWeave_${Date.now()}.md`, 'text/markdown');
            toast('已导出 MD');
        }

        function exportJSON() {
            if (!currentSessionId || !allMessages.length) { toast('暂无对话可导出', 'error'); return; }
            download(JSON.stringify(allMessages, null, 2), `MindWeave_${Date.now()}.json`, 'application/json');
            toast('已导出 JSON');
        }

        function download(content, filename, type) {
            const b = new Blob([content], { type });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(b);
            a.download = filename;
            a.click();
            URL.revokeObjectURL(a.href);
        }

        // ===== Settings Modal =====
        function openTab(tab) {
            document.getElementById('settingsModal').classList.add('active');
            const btn = [...document.querySelectorAll('.modal-tabs button')].find(b => b.textContent.includes(tab === 'account' ? '账号' : '用户'));
            if (btn) switchTab(btn, tab);
            if (tab === 'users') loadUserList();
            if (tab === 'storage') loadStoragePath();
        }

        function switchTab(btn, tab) {
            document.querySelectorAll('.modal-tabs button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.getElementById(`tab-${tab}`).classList.add('active');
            if (tab === 'users') loadUserList();
            if (tab === 'storage') loadStoragePath();
        }

        function onStoragePresetChange(val) {
            document.getElementById('storagePath').value = val === 'custom' ? '' : val;
        }

        async function loadStoragePath() {
            try {
                const r = await fetch('/data-dir');
                const d = await r.json();
                const path = d.path || '';
                document.getElementById('storagePath').value = path;
                const opts = [...document.querySelectorAll('#storagePreset option')];
                document.getElementById('storagePreset').value = opts.find(o => o.value === path) ? path : 'custom';
            } catch(e) { console.error(e); }
        }

        async function saveStoragePath() {
            const path = document.getElementById('storagePath').value.trim();
            if (!path) { toast('请输入或选择路径'); return; }
            const r = await fetch('/data-dir', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({path}) });
            const d = await r.json();
            if (d.error) { toast(d.error); return; }
            toast('路径已保存，数据已迁移');
        }

        function closeSettings() {
            document.getElementById('settingsModal').classList.remove('active');
        }

        async function saveMyPassword() {
            const np = document.getElementById('newPwd').value;
            const cp = document.getElementById('confirmPwd').value;
            const cur = document.getElementById('currentPwd').value;

            if (np && np !== cp) { toast('两次密码不一致', 'error'); return; }
            if (np && np.length < 4) { toast('密码至少4位', 'error'); return; }

            try {
                const r = await fetch(`/admin/users/${CURRENT_USER}/password`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password: np })
                });
                const d = await r.json();
                if (d.success) {
                    document.getElementById('newPwd').value = '';
                    document.getElementById('confirmPwd').value = '';
                    document.getElementById('currentPwd').value = '';
                    toast(np ? '密码已设置' : '密码已清除');
                } else {
                    toast(d.error || '保存失败', 'error');
                }
            } catch (e) { toast('保存失败', 'error'); }
        }

        async function loadUserList() {
            try {
                const r = await fetch('/admin/users');
                const users = await r.json();
                const list = document.getElementById('userList');
                if (!users.length) {
                    list.innerHTML = '<p style="color:var(--text-secondary);font-size:13px;text-align:center;padding:12px;">暂无其他用户</p>';
                    return;
                }
                list.innerHTML = users.map(u => `
                    <div class="user-row">
                        <span class="name">${u.username}</span>
                        <span class="badge">${u.password ? '🔐' : '👤'}</span>
                        <div class="actions">
                            <button onclick="resetUserPwd('${u.username}')">改密</button>
                            <button class="danger" onclick="deleteUser('${u.username}')">删除</button>
                        </div>
                    </div>`).join('');
            } catch (e) { console.error(e); }
        }

        async function createUser() {
            const name = document.getElementById('newUsername').value.trim();
            if (!name || !/^[a-zA-Z0-9_]{2,20}$/.test(name)) {
                toast('用户名需2-20位字母/数字/下划线', 'error');
                return;
            }
            try {
                const r = await fetch('/admin/users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: name })
                });
                const d = await r.json();
                if (d.error) { toast(d.error, 'error'); return; }
                document.getElementById('newUsername').value = '';
                loadUserList();
                toast(`用户「${name}」已创建`);
            } catch (e) { toast('创建失败', 'error'); }
        }

        async function resetUserPwd(username) {
            const pwd = prompt(`为 ${username} 设置新密码（留空清除密码）：`);
            if (pwd === null) return;
            try {
                const r = await fetch(`/admin/users/${username}/password`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password: pwd })
                });
                const d = await r.json();
                if (d.success) toast(`密码已${pwd ? '设置' : '清除'}`);
                else toast(d.error || '失败', 'error');
            } catch (e) { toast('失败', 'error'); }
        }

        async function deleteUser(username) {
            if (username === 'guest') { toast('不能删除访客', 'error'); return; }
            if (!confirm(`删除用户「${username}」？其所有对话记录将同步删除。`)) return;
            try {
                await fetch(`/admin/users/${username}`, { method: 'DELETE' });
                loadUserList();
                toast('已删除');
            } catch (e) { toast('删除失败', 'error'); }
        }

        // Confirm modal
        function confirmClear() {
            if (!currentSessionId) { toast('暂无对话', 'error'); return; }
            document.getElementById('confirmModal').classList.add('active');
        }

        function closeConfirm() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        // Click overlay to close
        document.querySelectorAll('.modal-overlay').forEach(o => {
            o.addEventListener('click', e => { if (e.target === o) o.classList.remove('active'); });
        });

        // Init
        loadModels();
        loadSessionsList();
        document.getElementById('userInput').focus();
    </script>
</body>
</html>
