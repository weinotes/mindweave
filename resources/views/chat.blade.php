<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MindWeave - 本地 AI 工作台</title>
    <script src="/vendor/marked.min.js"></script>
    <link rel="stylesheet" href="/css/chat.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>✨ MindWeave</h1>
                <button class="icon-btn" onclick="toggleTheme()" id="themeBtn" title="切换亮/暗主题" style="font-size:16px;padding:4px 6px;margin-left:auto;">🌙</button>
            </div>

            <div class="user-badge">
                <span class="avatar">{{ strtoupper(substr($username ?? 'G', 0, 1)) }}</span>
                <span>{{ $username ?? 'guest' }}</span>
            </div>

            <button class="new-chat-btn" onclick="newChat()">+ 新建对话</button>

            <div class="search-box" id="searchBox">
                <span class="icon">🔍</span>
                <input type="text" id="searchInput" placeholder="搜索历史消息... (Ctrl+K)" oninput="handleSearch(this.value)" onkeydown="handleSearchKeyDown(event)">
                <button class="clear" onclick="clearSearch()">✕</button>
                <div class="search-results" id="searchResults"></div>
            </div>

            <div class="sessions-label">历史会话</div>
            <div class="sessions-list" id="sessionsList"></div>

            <div class="sidebar-footer">
                <button onclick="openTab('account')">👤 {{ $username ?? 'guest' }}</button>
                <button onclick="openTab('users')">👥 用户管理</button>
                <form method="POST" action="/logout" style="margin:0;">
                    @csrf
                    <button type="submit" class="danger" style="background:transparent;border-color:#e6a817;color:#8a5a00;">🚪 退出登录</button>
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
                        <button class="icon-btn" onclick="openModelModal()" title="模型管理" style="font-size:14px;padding:4px 8px;">⚙️</button>
                    </div>
                </div>
                <div class="chat-header-right">
                    <span id="chatTitle" style="font-size:13px;color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">新对话</span>
                    <button class="icon-btn" onclick="exportMarkdown()">📄 MD</button>
                    <button class="icon-btn" onclick="exportJSON()">📋 JSON</button>
                    <button class="icon-btn" onclick="exportHTML()">🌐 HTML</button>
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

    <!-- ========== 模型管理弹窗 ========== -->
    <div class="modal-overlay" id="modelModal">
        <div class="modal" style="max-width: 560px;">
            <h3>🧠 模型管理</h3>
            
            <!-- 硬件信息区域 -->
            <div id="hardwareInfo" style="display:none;margin-bottom:16px;padding:12px;background:var(--bg-tertiary);border-radius:8px;font-size:12px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <span id="hwIcon">💻</span>
                    <span id="hwSummary" style="color:var(--text-secondary);"></span>
                </div>
                <div id="hwDetails" style="color:var(--text-secondary);line-height:1.6;"></div>
            </div>
            
            <!-- 推荐模型区域 -->
            <div id="recommendSection" style="display:none;margin-bottom:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:13px;font-weight:500;color:var(--accent);">📋 推荐模型</span>
                    <button onclick="refreshHardware()" style="font-size:11px;padding:4px 8px;background:transparent;border:1px solid var(--border);border-radius:4px;cursor:pointer;color:var(--text-secondary);">刷新检测</button>
                </div>
                <div id="recommendList" style="display:grid;gap:8px;max-height:200px;overflow-y:auto;"></div>
            </div>

            <!-- 手动下载区域 -->
            <div style="margin-bottom:14px;">
                <p class="sub" style="margin-bottom:10px;font-size:11px;">或手动输入模型名称下载：</p>
                <div class="form-row" style="margin-bottom:10px;">
                    <input type="text" id="pullModelName" placeholder="例如：llama3.2:latest 或 qwen2.5:3b" style="flex:1;" onkeydown="if(event.key==='Enter')pullModel()">
                    <button onclick="pullModel()" id="pullBtn">检测并下载</button>
                    <button onclick="cancelPull()" id="cancelBtn" style="display:none;background:#dc2626;color:#fff;flex:0 0 auto;">停止</button>
                </div>
            </div>
            <div id="checkResult" style="display:none;margin-bottom:10px;padding:8px 12px;border-radius:6px;font-size:12px;"></div>
            <div id="pullProgress" style="display:none;margin-bottom:16px;padding:10px 12px;background:var(--bg-tertiary);border-radius:8px;font-size:12px;color:var(--text-secondary);">
                <div id="pullStatus" style="margin-bottom:6px;">正在检查模型…</div>
                <div style="height:3px;background:var(--bg-primary);border-radius:2px;overflow:hidden;">
                    <div id="pullBar" style="height:100%;width:0%;background:var(--accent);transition:width 0.3s;border-radius:2px;"></div>
                </div>
            </div>

            <!-- 已安装模型 -->
            <div style="margin-bottom:8px;">
                <span style="font-size:13px;font-weight:500;">📦 已安装模型</span>
            </div>
            <div id="modelList" style="max-height:200px;overflow-y:auto;"></div>

            <div class="hint-box" style="margin-top:12px;">
                💡 推荐基于您的硬件配置。模型大小为估算值，实际占用可能略有不同。
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeModelModal()" style="flex:1">关闭</button>
            </div>
        </div>
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
                    💡 密码以 MD5 简单加密（内网使用，勿暴露外网）。设置后每次访问需输入密码验证。
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

    <div class="shortcut-hint" id="shortcutHint">
        <kbd>Ctrl</kbd> + <kbd>K</kbd> 搜索 · <kbd>Ctrl</kbd> + <kbd>N</kbd> 新建 · <kbd>Ctrl</kbd> + <kbd>/</kbd> 帮助
    </div>
    <script src="/js/chat.js"></script>
</body>
</html>
