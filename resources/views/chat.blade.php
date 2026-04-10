<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MindWeave - 本地 AI 工作台</title>
    <script src="/vendor/marked.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            /* 暗色主题（默认）— 深夜盘房风格 */
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
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        body { transition: background 0.3s, color 0.3s; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-header h1 { flex: 1; }

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

        /* 搜索框 */
        .search-box {
            margin: 8px 16px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 9px 12px 9px 32px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            transition: all 0.15s;
        }
        .search-box input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-glow);
        }
        .search-box input::placeholder { color: var(--text-secondary); }
        .search-box .icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 13px;
        }
        .search-box .clear {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 12px;
            padding: 2px 6px;
            display: none;
        }
        .search-box .clear:hover { color: var(--text-primary); }
        .search-box.has-value .clear { display: block; }

        /* 搜索结果 */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .search-results.active { display: block; }
        .search-result-item {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            transition: background 0.15s;
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: var(--bg-tertiary); }
        .search-result-item .session-name {
            font-size: 12px;
            color: var(--accent);
            margin-bottom: 4px;
        }
        .search-result-item .snippet {
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .search-result-item .meta {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .search-no-results {
            padding: 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
        }

        /* 消息操作按钮 */
        .message-actions {
            display: flex;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.15s;
            margin-top: 6px;
        }
        .message:hover .message-actions { opacity: 1; }
        .message-action-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.15s;
            opacity: 0;
        }
        .message:hover .message-action-btn { opacity: 1; }
        .message-action-btn:hover {
            background: var(--bg-card);
            color: var(--text-primary);
            border-color: var(--accent);
        }
        .message.user .message-actions { justify-content: flex-end; }

        /* 消息编辑框 */
        .message-edit-box {
            width: 100%;
            min-height: 60px;
            padding: 10px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--accent);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            outline: none;
        }
        .message-edit-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            justify-content: flex-end;
        }
        .message-edit-actions button {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .message-edit-actions .save {
            background: var(--accent);
            color: #0a0a0f;
            border: none;
        }
        .message-edit-actions .save:hover { opacity: 0.9; }
        .message-edit-actions .cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        .message-edit-actions .cancel:hover {
            color: var(--text-primary);
            border-color: var(--text-secondary);
        }

        /* 快捷键提示 */
        .shortcut-hint {
            position: fixed;
            bottom: 80px;
            right: 24px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            color: var(--text-secondary);
            z-index: 50;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.2s;
            pointer-events: none;
        }
        .shortcut-hint.active {
            opacity: 1;
            transform: translateY(0);
        }
        .shortcut-hint kbd {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 2px 6px;
            font-family: monospace;
            font-size: 11px;
            color: var(--text-primary);
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
            white-space: pre-wrap;
            word-break: break-word;
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
            max-width: 100%;
        }

        /* 消息复制按钮 */
        .msg-copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
            line-height: 1;
        }
        .message:hover .msg-copy-btn { opacity: 1; }
        .msg-copy-btn:hover { background: var(--accent); color: #000; }
        .message { position: relative; }

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
        .modal p code {
            background: var(--bg-card);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            color: var(--accent);
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

        /* 模型列表项 */
        .model-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .model-item .name { color: var(--text-primary); font-weight: 500; }
        .model-item .meta { color: var(--text-secondary); font-size: 11px; margin-top: 2px; }
        .model-item .actions { display: flex; gap: 6px; }
        .model-item .del-btn {
            padding: 4px 10px;
            background: transparent;
            border: 1px solid var(--danger);
            color: var(--danger);
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .model-item .del-btn:hover { background: var(--danger); color: #fff; }
        .pull-status { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }

        /* 推荐模型卡片 */
        .recommend-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .recommend-card:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent);
        }
        .recommend-card .model-info {
            flex: 1;
            min-width: 0;
        }
        .recommend-card .model-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        .recommend-card .model-meta {
            color: var(--text-secondary);
            font-size: 11px;
        }
        .recommend-card .model-desc {
            color: var(--text-secondary);
            font-size: 11px;
            margin-top: 4px;
        }
        .recommend-card .dl-btn {
            padding: 6px 14px;
            background: linear-gradient(135deg, #b8943c, #8a6d28);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .recommend-card .dl-btn:hover {
            transform: scale(1.05);
        }
        .recommend-card .dl-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .recommend-card .installed-tag {
            padding: 4px 10px;
            background: var(--bg-tertiary);
            border: 1px solid var(--success);
            color: var(--success);
            border-radius: 4px;
            font-size: 11px;
        }
        [data-theme="light"] .recommend-card {
            background: #f5f6fa;
            border-color: #d8dbe8;
        }
        [data-theme="light"] .recommend-card:hover {
            background: #eceef5;
            border-color: #b8943c;
        }
        [data-theme="light"] .recommend-card .model-name {
            color: #1a1a2e;
        }
        [data-theme="light"] .recommend-card .model-meta,
        [data-theme="light"] .recommend-card .model-desc {
            color: #5a6078;
        }
        [data-theme="light"] .recommend-card .dl-btn {
            background: linear-gradient(135deg, #d4a945, #b8943c);
        }

        /* 思考气泡 */
        .think-block {
            border-left: 3px solid var(--accent);
            padding: 8px 12px;
            margin: 6px 0;
            background: var(--bg-tertiary);
            border-radius: 0 8px 8px 0;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .think-block summary {
            cursor: pointer;
            font-weight: 600;
            color: var(--accent);
            user-select: none;
            margin-bottom: 4px;
        }
        .think-block summary:hover { opacity: 0.8; }
        .think-block pre {
            white-space: pre-wrap;
            word-break: break-word;
            font-family: inherit;
            font-size: inherit;
            color: inherit;
        }

        /* ===== 亮色模式：分区域精确覆盖，写在 CSS 末尾确保优先级最高 ===== */
        [data-theme="light"] body,
        [data-theme="light"] .app {
            background: #f5f6fa !important;
        }
        /* 侧边栏整体 + 各子区域独立背景色 */
        [data-theme="light"] .sidebar {
            background: #eceef5 !important;
            border-right: 1px solid #d8dbe8 !important;
        }
        [data-theme="light"] .sidebar-header {
            background: #eceef5 !important;
            border-bottom-color: #d8dbe8 !important;
        }
        [data-theme="light"] .user-badge {
            background: #ffffff !important;
            border-color: #d8dbe8 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .sessions-label {
            color: #8890a4 !important;
        }
        [data-theme="light"] .sessions-list {
            background: #eceef5 !important; /* 继承 sidebar，保持一致 */
        }
        [data-theme="light"] .sidebar-footer {
            background: #eceef5 !important;
            border-top-color: #d8dbe8 !important;
        }
        [data-theme="light"] .sidebar-footer button {
            background: transparent !important;
            color: #5a6078 !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .sidebar-footer button:hover {
            background: #d8dbe8 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .sidebar-footer button.danger,
        [data-theme="light"] .sidebar-footer form button.danger {
            background: transparent !important;
            border-color: #e6a817 !important;
            color: #8a5a00 !important;
        }
        [data-theme="light"] .sidebar-footer button.danger:hover,
        [data-theme="light"] .sidebar-footer form button.danger:hover {
            background: #fef3e0 !important;
            border-color: #e6a817 !important;
            color: #8a5a00 !important;
        }
        [data-theme="light"] .sidebar-footer button.danger:active,
        [data-theme="light"] .sidebar-footer form button.danger:active {
            background: transparent !important;
        }
        /* 亮色模式滚动条 */
        [data-theme="light"] ::-webkit-scrollbar-track {
            background: transparent !important;
        }
        [data-theme="light"] ::-webkit-scrollbar-thumb {
            background: #c8cde0 !important;
        }
        [data-theme="light"] ::-webkit-scrollbar-thumb:hover {
            background: #a8adb8 !important;
        }
        /* 顶部 chat-header（主内容区顶栏） */
        [data-theme="light"] .chat-header {
            background: #ffffff !important;
            border-bottom-color: #d8dbe8 !important;
        }
        [data-theme="light"] .chat-header label {
            color: #5a6078 !important;
        }
        [data-theme="light"] .model-select {
            background: #ffffff !important;
            color: #1a1a2e !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .icon-btn {
            background: transparent !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .icon-btn:hover {
            background: #eceef5 !important;
            color: #1a1a2e !important;
        }
        /* 会话列表 */
        [data-theme="light"] .session-item {
            background: transparent !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .session-item:hover {
            background: #d8dbe8 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .session-item.active {
            background: #d8dbe8 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .session-item .title {
            color: inherit !important;
        }
        [data-theme="light"] .session-del {
            background: #dc2626 !important;
            color: #ffffff !important;
        }
        /* 消息区 */
        [data-theme="light"] .messagesContainer {
            background: #f5f6fa !important;
        }
        [data-theme="light"] .message .content,
        [data-theme="light"] .message .content * {
            color: #1a1a2e !important;
        }
        /* 输入区 */
        [data-theme="light"] .input-area {
            background: #ffffff !important;
            border-top-color: #d8dbe8 !important;
        }
        [data-theme="light"] .input-wrapper {
            background: #e4e7f0 !important;
            border-color: #c8cde0 !important;
        }
        [data-theme="light"] .input-wrapper textarea,
        [data-theme="light"] #userInput {
            background: transparent !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .input-wrapper textarea::placeholder,
        [data-theme="light"] #userInput::placeholder {
            color: #8890a4 !important;
        }
        [data-theme="light"] #sendBtn {
            background: #b8943c !important;
            color: #ffffff !important;
        }
        [data-theme="light"] #sendBtn:hover {
            background: #9a7a2e !important;
        }
        [data-theme="light"] .input-wrapper:focus-within {
            border-color: #b8943c !important;
            box-shadow: 0 0 0 3px rgba(184,148,60,0.2) !important;
        }
        /* 弹窗 */
        [data-theme="light"] .modal-overlay {
            background: rgba(0,0,0,0.3) !important;
        }
        [data-theme="light"] .modal {
            background: #ffffff !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .modal-content {
            background: #ffffff !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .modal-title {
            color: #1a1a2e !important;
            border-bottom-color: #d8dbe8 !important;
        }
        [data-theme="light"] .modal-close {
            color: #8890a4 !important;
        }
        [data-theme="light"] .modal-close:hover {
            background: #eceef5 !important;
        }
        /* 模型管理弹窗内部 */
        [data-theme="light"] .model-item {
            background: #f5f6fa !important;
            border-color: #d8dbe8 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .model-item:hover {
            background: #eceef5 !important;
        }
        [data-theme="light"] .model-item .name {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .model-item .meta {
            color: #5a6078 !important;
        }
        [data-theme="light"] #modelModal .modal h3,
        [data-theme="light"] #modelModal .modal p.sub {
            color: #1a1a2e !important;
        }
        [data-theme="light"] #hardwareInfo,
        [data-theme="light"] #recommendSection .recommend-card {
            background: #f5f6fa !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] #hardwareInfo * {
            color: #1a1a2e !important;
        }
        [data-theme="light"] #modelModal .hint-box {
            background: #f5f6fa !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] #modelModal .hint-box code,
        [data-theme="light"] #modelModal .modal p code {
            background: #e4e7f0 !important;
            color: #7a6348 !important;
        }
        [data-theme="light"] #modelList {
            color: #1a1a2e !important;
        }
        [data-theme="light"] #pullModelName {
            background: #ffffff !important;
            color: #1a1a2e !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] #pullBtn,
        [data-theme="light"] #modelModal .btn-primary {
            background: #b8943c !important;
            color: #ffffff !important;
        }
        [data-theme="light"] #cancelBtn {
            background: #dc2626 !important;
            color: #ffffff !important;
        }
        [data-theme="light"] #cancelBtn:hover {
            background: #b91c1c !important;
        }
        [data-theme="light"] #pullProgress {
            background: #f5f6fa !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] #checkResult {
            border-radius: 6px;
        }
        [data-theme="light"] #pullBar {
            background: #b8943c !important;
        }
        [data-theme="light"] #modelModal .btn-secondary {
            background: #f5f6fa !important;
            color: #1a1a2e !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .model-item .del-btn {
            background: #dc2626 !important;
            color: #ffffff !important;
        }
        [data-theme="light"] .model-item .del-btn:hover {
            background: #b91c1c !important;
        }
        [data-theme="light"] input[type="text"] {
            background: #ffffff !important;
            color: #1a1a2e !important;
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .btn-primary {
            background: #b8943c !important;
            color: #ffffff !important;
        }
        [data-theme="light"] .btn-primary:hover {
            background: #9a7a2e !important;
        }
        [data-theme="light"] button.danger {
            background: #fef3e0 !important;
            border: 1px solid #e6a817 !important;
            color: #8a5a00 !important;
        }
        [data-theme="light"] button.danger:hover {
            background: #fde68a !important;
            border-color: #d97706 !important;
            color: #92400e !important;
        }
        /* 新建对话按钮（金色渐变保持，但文字要深色） */
        [data-theme="light"] .new-chat-btn {
            background: linear-gradient(135deg, #b8943c 0%, #b8956a 100%) !important;
            color: #0a0a0f !important;
        }
        [data-theme="light"] .new-chat-btn:hover {
            box-shadow: 0 4px 12px rgba(184,148,60,0.3) !important;
        }
        /* ===== 消息气泡 ===== */
        /* 用户消息气泡 */
        [data-theme="light"] .message.user .content {
            background: #e4e7f0 !important;
            border-color: #c8cde0 !important;
            color: #1a1a2e !important;
        }
        /* 助手消息区域（透明，显示 messagesContainer 背景） */
        [data-theme="light"] .message.assistant .content {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .msg-copy-btn {
            background: #f0f0f5;
            border-color: #d4d4d8;
            color: #52525b;
        }
        [data-theme="light"] .msg-copy-btn:hover {
            background: var(--accent);
            color: #000;
        }
        /* 消息气泡内的 Markdown 元素 */
        [data-theme="light"] .message .content h1,
        [data-theme="light"] .message .content h2,
        [data-theme="light"] .message .content h3,
        [data-theme="light"] .message .content h4,
        [data-theme="light"] .message .content h5,
        [data-theme="light"] .message .content h6 {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message .content strong {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message .content em {
            color: #5a6078 !important;
        }
        [data-theme="light"] .message .content a {
            color: #b8943c !important;
        }
        [data-theme="light"] .message .content ul,
        [data-theme="light"] .message .content ol {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message .content li {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message .content table {
            border-color: #d8dbe8 !important;
        }
        [data-theme="light"] .message .content th {
            background: #eceef5 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message .content td {
            color: #1a1a2e !important;
            border-color: #e8ebf4 !important;
        }
        [data-theme="light"] .message .content blockquote {
            border-left-color: #b8943c !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .message .content blockquote p {
            color: #5a6078 !important;
        }
        [data-theme="light"] .message .content tr:nth-child(even) td {
            background: #f5f6fa !important;
        }
        /* 思考块 */
        [data-theme="light"] .think-block {
            background: #eceef5 !important;
            border-color: #d8dbe8 !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .think-block summary {
            color: #8890a4 !important;
        }
        [data-theme="light"] .think-block pre {
            background: #eceef5 !important;
            color: #5a6078 !important;
        }
        /* 思考块内嵌套的代码块（通常是思考过程代码） */
        [data-theme="light"] .think-block pre code {
            color: #5a6078 !important;
        }
        /* 消息操作按钮 - 亮色模式 */
        [data-theme="light"] .message-action-btn {
            background: #e4e7f0 !important;
            border-color: #c8cde0 !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .message-action-btn:hover {
            background: #d8dbe8 !important;
            border-color: #b8943c !important;
            color: #1a1a2e !important;
        }
        /* 搜索框 - 亮色模式 */
        [data-theme="light"] .search-box input {
            background: #ffffff !important;
            border-color: #d8dbe8 !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .search-box input::placeholder {
            color: #8890a4 !important;
        }
        [data-theme="light"] .search-box input:focus {
            border-color: #b8943c !important;
            box-shadow: 0 0 0 2px rgba(184,148,60,0.2) !important;
        }
        [data-theme="light"] .search-box .icon {
            color: #8890a4 !important;
        }
        [data-theme="light"] .search-box .clear {
            color: #8890a4 !important;
        }
        [data-theme="light"] .search-box .clear:hover {
            color: #1a1a2e !important;
        }
        /* 搜索结果 - 亮色模式 */
        [data-theme="light"] .search-results {
            background: #ffffff !important;
            border-color: #d8dbe8 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
        }
        [data-theme="light"] .search-result-item {
            border-color: #eceef5 !important;
        }
        [data-theme="light"] .search-result-item:hover {
            background: #f5f6fa !important;
        }
        [data-theme="light"] .search-result-item .session-name {
            color: #b8943c !important;
        }
        [data-theme="light"] .search-result-item .snippet {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .search-result-item .meta {
            color: #8890a4 !important;
        }
        [data-theme="light"] .search-no-results {
            color: #8890a4 !important;
        }
        /* 快捷键提示 - 亮色模式 */
        [data-theme="light"] .shortcut-hint {
            background: #ffffff !important;
            border-color: #d8dbe8 !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .shortcut-hint kbd {
            background: #e4e7f0 !important;
            border-color: #c8cde0 !important;
            color: #1a1a2e !important;
        }
        /* 消息编辑框 - 亮色模式 */
        [data-theme="light"] .message-edit-box {
            background: #ffffff !important;
            border-color: #b8943c !important;
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message-edit-actions .save {
            background: #b8943c !important;
            color: #ffffff !important;
        }
        [data-theme="light"] .message-edit-actions .cancel {
            background: transparent !important;
            border-color: #d8dbe8 !important;
            color: #5a6078 !important;
        }
        [data-theme="light"] .message-edit-actions .cancel:hover {
            color: #1a1a2e !important;
            border-color: #8890a4 !important;
        }
        /* 历史会话标签 - 亮色模式 */
        [data-theme="light"] .sessions-label {
            color: #8890a4 !important;
        }

        /* 亮色主题：代码块 + 行内代码 + 高亮 */
        [data-theme="light"] .message .content pre {
            background: #f8f8fa !important;
            border-color: #e4e4e8 !important;
        }
        [data-theme="light"] .message .content pre code {
            color: #1a1a2e !important;
        }
        [data-theme="light"] .message .content code:not(pre code) {
            background: #f0f0f5 !important;
            border-color: #d4d4d8 !important;
            color: #8b5a00 !important;
        }
        [data-theme="light"] .message .content mark {
            background: #fef3c7 !important;
            color: #92400e !important;
            padding: 1px 4px;
            border-radius: 3px;
        }
    </style>
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

    <script>
        const CURRENT_USER = {{ Js::from($username ?? 'guest') }};
        let currentModel = {{ Js::from(config('ollama.model')) }};
        let currentSessionId = null;
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

                if (!Array.isArray(models) || models.length === 0) {
                    s.innerHTML = `<option>${currentModel}</option>`;
                    return;
                }

                s.innerHTML = models.map(m => `<option value="${m.name}">${m.name}</option>`).join('');
                if (!models.find(m => m.name === currentModel)) {
                    currentModel = models[0].name;
                    s.value = currentModel;
                }
            } catch (e) {
                document.getElementById('modelSelect').innerHTML = `<option>${currentModel}</option>`;
            }
        }

        // ===== Sessions =====
        async function newChat() {
            try {
                const r = await fetch('/sessions', { method: 'POST', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
                const s = await r.json();
                currentSessionId = s.id;
                localStorage.setItem('mindweave-session', currentSessionId);
                allMessages = [];

                const greeting = '你好！我是 MindWeave，你的本地 AI 助手。有什么我可以帮助你的吗？';

                document.getElementById('messagesContainer').innerHTML = `
                    <div class="message assistant">
                        <div class="avatar">🤖</div>
                        <div class="content">${greeting}</div>
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
                list.innerHTML = sessions.map(s => {
                    const title = s.title || (s.messages[0]?.content?.substring(0,35) || '新对话');
                    return `<div class="session-item ${s.id === currentSessionId ? 'active' : ''}">
                        <span class="title" data-session-title="${title}" onclick="loadSession('${s.id}')">${esc(title)}</span>
                        <button class="session-del" onclick="event.stopPropagation(); renameSession('${s.id}', this.closest('.session-item').querySelector('.title').dataset.sessionTitle)" title="重命名">✏️</button>
                        <button class="session-del" onclick="event.stopPropagation(); delSession('${s.id}')" title="删除">🗑️</button>
                    </div>`;
                }).join('');
            } catch (e) { console.error(e); }
        }

        async function loadSession(id) {
            try {
                const r = await fetch(`/sessions/${id}`);
                const s = await r.json();
                currentSessionId = id;
                localStorage.setItem('mindweave-session', id);
                currentModel = s.model || currentModel;
                allMessages = s.messages || [];
                const container = document.getElementById('messagesContainer');
                document.getElementById('modelSelect').value = currentModel;
                container.innerHTML = allMessages.map((m, index) => {
                    const rendered = m.role === 'assistant'
                        ? (typeof marked !== 'undefined' ? marked.parse(m.content) : esc(m.content))
                        : esc(m.content);
                    const thinkHtml = m.think
                        ? `<details class="think-block"><summary>🤔 思考过程</summary><pre style="white-space:pre-wrap;word-break:break-word;">${esc(m.think)}</pre></details>`
                        : '';
                    const actionsHtml = `<div class="message-actions">
                        ${m.role === 'user' ? `<button class="message-action-btn" onclick="editMessage(${index})">✏️ 编辑</button>` : ''}
                        <button class="message-action-btn" onclick="deleteMessage(${index})">🗑️ 删除</button>
                        ${m.role === 'assistant' ? `<button class="message-action-btn" onclick="regenerateMessage(${index})">🔄 重新生成</button>` : ''}
                    </div>`;
                    return `<div class="message ${m.role}" data-index="${index}"><div class="avatar">${m.role === 'user' ? '👤' : '🤖'}</div><div class="content">${thinkHtml}${rendered}</div>${actionsHtml}</div>`;
                }).join('');
                addCopyButtons(container);
                container.scrollTop = container.scrollHeight;
                document.getElementById('chatTitle').textContent = (allMessages[0]?.content || '新对话').substring(0, 20);
                loadSessionsList();
            } catch (e) { console.error(e); }
        }

        async function delSession(id) {
            if (!confirm('删除此会话？')) return;
            const r = await fetch(`/sessions/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
            if (!r.ok) { toast('删除失败', 'error'); return; }
            if (currentSessionId === id) {
                localStorage.removeItem('mindweave-session');
                await newChat();
            }
            loadSessionsList();
            toast('已删除');
        }

        async function clearSession() {
            closeConfirm();
            if (!currentSessionId) { toast('暂无对话', 'error'); return; }
            const r = await fetch(`/sessions/${currentSessionId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
            if (!r.ok) { toast('清空失败', 'error'); return; }
            await newChat();
            toast('已清空');
        }

        // ===== Chat (SSE Streaming) =====
        async function sendMessage() {
            const input = document.getElementById('userInput');
            const msg = input.value.trim();
            if (!msg) return;

            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            input.value = '';
            input.style.height = 'auto';

            appendMsg('user', msg);
            const msgEl = appendMsg('assistant', '');
            let thinkContent = '';
            let thinkEl = null;
            let fullText = '';

            const MAX_RETRIES = 3;
            let lastError = '';

            for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
                try {
                    const r = await fetch('/chat/stream', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            message: msg,
                            model: currentModel,
                            session_id: currentSessionId,
                            system_prompt: ''
                        })
                    });

                    if (!r.ok) throw new Error('请求失败: ' + r.status);

                    const reader = r.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop() || '';

                        for (const line of lines) {
                            if (!line.startsWith('data: ')) continue;
                            const raw = line.slice(6);
                            if (raw === '[DONE]') continue;

                            let data;
                            try { data = JSON.parse(raw); } catch { continue; }

                            if (data.type === 'think') {
                                // 收到思考过程片段
                                thinkContent += data.content;
                                if (!thinkEl) {
                                    thinkEl = document.createElement('details');
                                    thinkEl.className = 'think-block';
                                    thinkEl.open = true;
                                    thinkEl.innerHTML = `<summary>🤔 思考过程</summary><pre></pre>`;
                                    msgEl.querySelector('.content').before(thinkEl);
                                }
                                thinkEl.querySelector('pre').textContent = thinkContent;
                                scrollBottom();
                            } else if (data.type === 'chunk') {
                                fullText = data.full;
                                msgEl.querySelector('.content').textContent = fullText;
                                scrollBottom();
                            } else if (data.type === 'done') {
                                currentSessionId = data.session_id;
                                localStorage.setItem('mindweave-session', currentSessionId);
                                // 最终渲染 Markdown，思考块保留原始文本
                                const rendered = typeof marked !== 'undefined'
                                    ? marked.parse(data.content)
                                    : esc(data.content);
                                msgEl.querySelector('.content').innerHTML = rendered;
                                addCopyButtons(msgEl.querySelector('.content'));
                                // 折叠思考块
                                if (thinkEl) thinkEl.open = false;
                                // 思考块内内容用 pre 包裹原始文本（保留格式）
                                if (thinkEl) {
                                    const pre = thinkEl.querySelector('pre');
                                    if (pre) {
                                        const pre2 = document.createElement('pre');
                                        pre2.style.whiteSpace = 'pre-wrap';
                                        pre2.style.wordBreak = 'break-word';
                                        pre2.style.fontFamily = 'inherit';
                                        pre2.style.fontSize = 'inherit';
                                        pre2.textContent = thinkContent;
                                        pre.replaceWith(pre2);
                                    }
                                }
                                scrollBottom();
                                loadSessionsList();
                            } else if (data.error) {
                                msgEl.querySelector('.content').textContent = data.error;
                            }
                        }
                    }
                    lastError = '';
                    break; // 成功，跳出重试循环
                } catch (e) {
                    lastError = e.message;
                    if (attempt < MAX_RETRIES) {
                        const delay = (attempt + 1) * 2000;
                        // 显示重试中状态
                        const retryEl = document.createElement('div');
                        retryEl.id = '__retryMsg';
                        retryEl.style.cssText = 'font-size:12px;color:var(--text-muted);padding:4px 0;text-align:center;';
                        retryEl.textContent = `⏳ Ollama 未响应，${delay/1000}s 后重试… (${attempt + 1}/${MAX_RETRIES})`;
                        msgEl.querySelector('.content').after(retryEl);
                        await new Promise(resolve => setTimeout(resolve, delay));
                        retryEl.remove();
                    }
                }
            }

            if (lastError) {
                msgEl.querySelector('.content').textContent = '请求失败: ' + lastError;
            }

            btn.disabled = false;
            input.focus();

            // 自动生成会话标题（首条消息后）
            if (currentSessionId && allMessages.length <= 2) {
                autoGenerateTitle(currentSessionId);
            }
        }

        // 自动生成语义化标题
        async function autoGenerateTitle(sessionId) {
            try {
                const r = await fetch(`/sessions/${sessionId}/auto-title`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() }
                });
                if (r.ok) {
                    const data = await r.json();
                    if (data.generated) {
                        loadSessionsList(); // 刷新侧边栏标题
                    }
                }
            } catch (e) {
                // 标题生成失败不影响主流程，静默忽略
            }
        }

        // ===== 主题切换 =====
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') !== 'light';
            html.setAttribute('data-theme', isDark ? 'light' : '');
            localStorage.setItem('mindweave-theme', isDark ? 'light' : '');
            document.getElementById('themeBtn').textContent = isDark ? '☀️' : '🌙';
        }
        (function initTheme() {
            const saved = localStorage.getItem('mindweave-theme');
            if (saved === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
                document.getElementById('themeBtn').textContent = '☀️';
            }
        })();

        function scrollBottom() {
            const c = document.getElementById('messagesContainer');
            c.scrollTop = c.scrollHeight;
        }

        // ===== 代码块一键复制 =====
        function addCopyButtons(container) {
            if (!container) return;
            container.querySelectorAll('pre').forEach(pre => {
                if (pre.querySelector('.copy-btn')) return; // 已加过
                const btn = document.createElement('button');
                btn.className = 'copy-btn';
                btn.textContent = '📋 复制';
                btn.style.cssText = 'position:absolute;top:8px;right:8px;background:var(--bg-tertiary);border:1px solid var(--border);color:var(--text-secondary);padding:3px 10px;border-radius:6px;font-size:11px;cursor:pointer;opacity:0;transition:opacity 0.2s;z-index:10;';
                btn.onclick = () => {
                    const code = pre.querySelector('code')?.textContent || pre.textContent;
                    navigator.clipboard.writeText(code).then(() => {
                        btn.textContent = '✅ 已复制';
                        setTimeout(() => btn.textContent = '📋 复制', 1500);
                    });
                };
                pre.style.position = 'relative';
                pre.onmouseenter = () => btn.style.opacity = '1';
                pre.onmouseleave = () => btn.style.opacity = '0';
                pre.appendChild(btn);
            });
        }

        function appendMsg(role, content, thinkContent) {
            const c = document.getElementById('messagesContainer');
            const d = document.createElement('div');
            d.className = `message ${role}`;

            // 助手消息渲染 Markdown，用户消息转义
            const rendered = role === 'assistant'
                ? (typeof marked !== 'undefined' ? marked.parse(content) : esc(content))
                : esc(content);

            const thinkHtml = thinkContent
                ? `<details class="think-block"><summary>🤔 思考过程</summary><pre style="white-space:pre-wrap;word-break:break-word;">${esc(thinkContent)}</pre></details>`
                : '';

            d.innerHTML = `<div class="avatar">${role === 'user' ? '👤' : '🤖'}</div><div class="content">${thinkHtml}${rendered}</div><button class="msg-copy-btn" onclick="copyMsgContent(this)" title="复制消息">📋</button>`;
            c.appendChild(d);
            c.scrollTop = c.scrollHeight;
            addCopyButtons(d); // 代码块复制按钮
            return d;
        }

        function esc(text) {
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        // 复制整条消息内容
        function copyMsgContent(btn) {
            const msgEl = btn.closest('.message');
            const contentEl = msgEl.querySelector('.content');
            const thinkEl = contentEl.querySelector('.think-block pre');
            let text = '';
            if (thinkEl) {
                text = '【思考过程】\n' + thinkEl.textContent + '\n\n';
            }
            // 获取主要内容（排除思考块）
            const mainContent = contentEl.cloneNode(true);
            const thinkBlock = mainContent.querySelector('.think-block');
            if (thinkBlock) thinkBlock.remove();
            text += mainContent.textContent.trim();
            
            navigator.clipboard.writeText(text).then(() => {
                btn.textContent = '✅';
                setTimeout(() => btn.textContent = '📋', 1500);
            }).catch(() => toast('复制失败', 'error'));
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

        function exportHTML() {
            if (!currentSessionId || !allMessages.length) { toast('暂无对话可导出', 'error'); return; }
            const sessionTitle = document.querySelector('.session-item.active .title')?.dataset?.sessionTitle || '对话记录';
            const html = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${esc(sessionTitle)} - MindWeave</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f17; color: #e4e4e7; padding: 20px; max-width: 800px; margin: 0 auto; line-height: 1.7; }
        h1 { color: #c9a85c; margin-bottom: 20px; font-size: 24px; }
        .msg { margin-bottom: 24px; padding: 16px; border-radius: 12px; }
        .user { background: #1a1a2e; border: 1px solid #2a2a3e; }
        .assistant { background: transparent; }
        .role { font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        .user .role { color: #a78bfa; }
        .assistant .role { color: #c9a85c; }
        .content { white-space: pre-wrap; word-break: break-word; }
        .content pre { background: #0d0d14; padding: 12px; border-radius: 8px; overflow-x: auto; margin: 10px 0; font-size: 13px; }
        .content code { background: #1a1a2e; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .footer { text-align: center; margin-top: 40px; color: #71717a; font-size: 12px; }
    </style>
</head>
<body>
    <h1>💭 ${esc(sessionTitle)}</h1>
    ${allMessages.map(m => `<div class="msg ${m.role}"><div class="role">${m.role === 'user' ? '👤 ' + CURRENT_USER : '🤖 MindWeave'}</div><div class="content">${m.role === 'assistant' ? marked.parse(m.content) : esc(m.content)}</div></div>`).join('\n')}
    <div class="footer">Exported from MindWeave · ${new Date().toLocaleString('zh-CN')}</div>
</body>
</html>`;
            download(html, `MindWeave_${Date.now()}.html`, 'text/html');
            toast('已导出 HTML');
        }

        function download(content, filename, type) {
            const b = new Blob([content], { type });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(b);
            a.download = filename;
            a.click();
            URL.revokeObjectURL(a.href);
        }

        // ===== Model Management Modal =====
        function openModelModal() {
            document.getElementById('modelModal').classList.add('active');
            document.getElementById('pullModelName').value = '';
            document.getElementById('checkResult').style.display = 'none';
            document.getElementById('pullProgress').style.display = 'none';
            loadModelList();
            loadHardwareInfo();
        }

        function closeModelModal() {
            document.getElementById('modelModal').classList.remove('active');
        }

        // 加载硬件信息
        async function loadHardwareInfo() {
            const hwInfo = document.getElementById('hardwareInfo');
            const hwSummary = document.getElementById('hwSummary');
            const hwDetails = document.getElementById('hwDetails');
            
            try {
                hwInfo.style.display = '';
                hwSummary.textContent = '正在检测硬件配置…';
                
                const r = await fetch('/hardware/recommend');
                const data = await r.json();
                
                const hw = data.hardware;
                const rec = data.recommendations;
                
                // 显示硬件摘要
                hwSummary.innerHTML = `<strong>${hw.memory_gb}GB 内存</strong> · ${hw.cpu_cores} 核心 · ${hw.gpu ? hw.gpu_models.join(', ') : '无独显'}`;
                
                // 显示详细信息
                const details = [];
                details.push(`🖥️ 可用内存：${hw.available_memory_gb}GB`);
                if (hw.gpu) {
                    details.push(`🎮 GPU：${hw.gpu_models.join(', ')}`);
                    if (hw.vram_mb > 0) details.push(`💾 显存：${hw.vram_mb}MB`);
                }
                hwDetails.innerHTML = details.join(' · ');
                
                // 显示推荐模型
                renderRecommendations(rec, hw);
            } catch (e) {
                hwSummary.textContent = '硬件检测失败：' + e.message;
            }
        }

        // 渲染推荐模型列表
        function renderRecommendations(rec, hw) {
            const section = document.getElementById('recommendSection');
            const container = document.getElementById('recommendList');
            
            section.style.display = '';
            
            // 根据推荐等级排序
            let html = '';
            
            // 获取已安装模型列表
            fetch('/models').then(r => r.json()).then(installedModels => {
                const installedNames = (installedModels || []).map(m => m.name);
                
                rec.models.forEach(m => {
                    const isInstalled = installedNames.includes(m.name);
                    html += `
                        <div class="recommend-card">
                            <div class="model-info">
                                <div class="model-name">${m.name}</div>
                                <div class="model-meta">${m.params} 参数 · ${m.size}</div>
                                ${m.description ? `<div class="model-desc">${m.description}</div>` : ''}
                            </div>
                            ${isInstalled 
                                ? '<span class="installed-tag">✓ 已安装</span>' 
                                : `<button class="dl-btn" onclick="quickDownload('${m.name}')">下载</button>`
                            }
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            });
        }

        // 快速下载（点击推荐模型的下载按钮）
        function quickDownload(modelName) {
            document.getElementById('pullModelName').value = modelName;
            pullModel();
        }

        // 刷新硬件检测
        function refreshHardware() {
            loadHardwareInfo();
            loadModelList();
        }

        async function loadModelList() {
            const container = document.getElementById('modelList');
            try {
                const r = await fetch('/models');
                const models = await r.json();

                if (!Array.isArray(models) || models.length === 0) {
                    container.innerHTML = '<div style="color:var(--text-secondary);font-size:13px;text-align:center;padding:20px;">暂无已安装模型。请在下方输入模型名称开始下载。</div>';
                    return;
                }

                container.innerHTML = models.map(m => `
                    <div class="model-item">
                        <div>
                            <div class="name">${m.name}</div>
                            ${m.size ? `<div class="meta">${formatSize(m.size)}</div>` : ''}
                        </div>
                        <div class="actions">
                            <button class="del-btn" onclick="deleteModel('${m.name.replace(/'/g, "\\'")}')">删除</button>
                        </div>
                    </div>`).join('');
            } catch (e) {
                container.innerHTML = '<div style="color:var(--danger);font-size:13px;">无法加载模型列表，Ollama 可能未运行。</div>';
            }
        }

        function formatSize(bytes) {
            if (!bytes) return '';
            const gb = bytes / (1024 ** 3);
            if (gb >= 1) return gb.toFixed(1) + ' GB';
            const mb = bytes / (1024 ** 2);
            if (mb >= 1) return mb.toFixed(0) + ' MB';
            return (bytes / 1024).toFixed(0) + ' KB';
        }

        async function checkModel() {
            const name = document.getElementById('pullModelName').value.trim();
            if (!name) { toast('请输入模型名称', 'error'); return; }

            const btn = document.getElementById('pullBtn');
            btn.disabled = true;
            btn.textContent = '检查中…';

            const resultBox = document.getElementById('checkResult');
            resultBox.style.display = 'none';

            try {
                const r = await fetch(`/models/check/${encodeURIComponent(name)}`);
                const data = await r.json();

                if (data.valid) {
                    resultBox.style.display = 'block';
                    resultBox.style.background = '#f0fdf4';
                    resultBox.style.border = '1px solid #86efac';
                    resultBox.style.color = '#166534';
                    resultBox.textContent = `✅ 模型存在（${formatSize(data.size) || '大小未知'}），可以直接下载`;
                    btn.disabled = false;
                    btn.textContent = '下载';
                } else {
                    resultBox.style.display = 'block';
                    resultBox.style.background = '#fef2f2';
                    resultBox.style.border = '1px solid #fca5a5';
                    resultBox.style.color = '#991b1b';
                    resultBox.textContent = '❌ ' + (data.error || '未找到该模型，请检查名称是否正确');
                    btn.disabled = true;
                    btn.textContent = '下载';
                }
            } catch (e) {
                resultBox.style.display = 'block';
                resultBox.style.background = '#fef2f2';
                resultBox.style.border = '1px solid #fca5a5';
                resultBox.style.color = '#991b1b';
                resultBox.textContent = '❌ 无法连接到 Ollama，请确认服务已启动';
                btn.disabled = true;
                btn.textContent = '下载';
            }
        }

        let pullAbortController = null;

        async function pullModel() {
            const name = document.getElementById('pullModelName').value.trim();
            if (!name) { toast('请输入模型名称', 'error'); return; }

            const btn = document.getElementById('pullBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const progress = document.getElementById('pullProgress');
            const status = document.getElementById('pullStatus');
            const bar = document.getElementById('pullBar');

            btn.disabled = true;
            cancelBtn.style.display = '';
            progress.style.display = '';
            status.textContent = '正在检查模型是否支持…';
            bar.style.width = '5%';

            try {
                // 步骤一：先验证模型
                const checkResult = document.getElementById('checkResult');
                status.textContent = `验证模型「${name}」…`;
                bar.style.width = '10%';
                checkResult.style.display = 'none';

                const checkR = await fetch(`/models/check/${encodeURIComponent(name)}`);
                const checkData = await checkR.json();

                if (!checkData.valid) {
                    checkResult.style.display = '';
                    checkResult.style.background = '#fef3f3';
                    checkResult.style.color = '#c00';
                    checkResult.innerHTML = `❌ ${checkData.error || '检测失败'}`;

                    progress.style.display = 'none';
                    btn.disabled = false;
                    cancelBtn.style.display = 'none';
                    toast('检测失败：' + (checkData.error || '未知错误'), 'error');
                    return;
                }

                if (checkData.exists) {
                    checkResult.style.display = '';
                    checkResult.style.background = '#fef9e7';
                    checkResult.style.color = '#8a6d3b';
                    checkResult.innerHTML = `⚠️ 模型「${name}」已存在，无需下载`;

                    progress.style.display = 'none';
                    btn.disabled = false;
                    cancelBtn.style.display = 'none';
                    toast('模型已存在', 'warning');
                    refreshHardware();
                    return;
                }

                // 检测通过，开始下载
                checkResult.style.display = '';
                checkResult.style.background = '#f0fdf4';
                checkResult.style.color = '#166534';
                checkResult.innerHTML = `✅ 模型「${name}」验证通过，开始下载…`;

                bar.style.width = '20%';
                status.textContent = `模型验证通过！开始下载…`;

                // 步骤二：轮询方式（非 SSE，避免阻塞页面导航）
                pullAbortController = new AbortController();
                let waited = 0;
                let pullDone = false;

                while (!pullDone && waited < 3600) { // 最多轮询 1 小时
                    if (pullAbortController.signal.aborted) {
                        status.textContent = '⏹ 已停止下载';
                        toast('已停止下载');
                        pullDone = true;
                        break;
                    }

                    try {
                        const r = await fetch(`/models/pull/status?name=${encodeURIComponent(name)}`, {
                            signal: pullAbortController.signal
                        });
                        const data = await r.json();

                        if (data.status === 'done') {
                            bar.style.width = '100%';
                            status.textContent = '✅ 下载完成！';
                            pullDone = true;
                        } else if (data.status === 'error') {
                            status.textContent = '❌ ' + (data.error || '下载失败');
                            bar.style.width = '0%';
                            pullDone = true;
                        } else {
                            waited = data.waited || waited + 3;
                            const pct = Math.min(20 + waited * 0.6, 95);
                            bar.style.width = pct + '%';
                            status.textContent = `下载中…（${waited}s）`;
                        }
                    } catch (e) {
                        if (e.name === 'AbortError') {
                            pullDone = true;
                            break;
                        }
                        // 网络波动，继续轮询
                    }

                    if (!pullDone) {
                        await new Promise(r => setTimeout(r, 3000));
                        waited += 3;
                    }
                }

                if (!pullDone) {
                    status.textContent = '⏹ 下载超时，已后台继续';
                    toast('下载超时，模型将在后台继续下载');
                }

                // 完成后刷新列表
                await loadModelList();
                await loadModels();

            } catch (e) {
                status.textContent = '❌ 下载失败：' + e.message;
                bar.style.width = '0%';
            }

            btn.disabled = false;
            cancelBtn.style.display = 'none';
            progress.style.display = 'none';
            pullAbortController = null;
        }

        async function cancelPull() {
            if (pullAbortController) {
                pullAbortController.abort();
                document.getElementById('cancelBtn').disabled = true;
                document.getElementById('cancelBtn').textContent = '取消中…';
            }

            const name = document.getElementById('pullModelName').value.trim();
            if (!name) return;

            try {
                await fetch(`/models/pull/cancel/${encodeURIComponent(name)}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken() }
                });
            } catch (_) {}

            document.getElementById('pullStatus').textContent = '⏹ 已停止下载';
            document.getElementById('pullBtn').disabled = false;
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('pullProgress').style.display = 'none';
            toast('已停止下载');
        }

        async function cancelPull() {
            const name = document.getElementById('pullModelName').value.trim();
            if (!name) return;

            const cancelBtn = document.getElementById('cancelBtn');
            const status = document.getElementById('pullStatus');
            cancelBtn.disabled = true;
            cancelBtn.textContent = '取消中…';
            status.textContent = '正在停止下载…';

            try {
                await fetch(`/models/pull/cancel/${encodeURIComponent(name)}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
                status.textContent = '⏹ 已停止下载';
                toast('已停止下载');
            } catch (e) {
                status.textContent = '取消失败';
            }

            document.getElementById('pullBtn').disabled = false;
            cancelBtn.style.display = 'none';
            document.getElementById('pullProgress').style.display = 'none';
        }

        async function deleteModel(name) {
            if (!confirm(`确定删除模型「${name}」？此操作不可恢复。`)) return;
            try {
                await fetch(`/models/${encodeURIComponent(name)}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
                toast(`已删除 ${name}`);
                loadModelList();
                loadModels();
            } catch (e) {
                toast('删除失败: ' + e.message, 'error');
            }
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
            const r = await fetch('/data-dir', { method: 'POST', headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': getCsrfToken()}, body: JSON.stringify({path}) });
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
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
                await fetch(`/admin/users/${username}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': getCsrfToken() } });
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

        // 恢复上次会话（刷新后不丢失上下文）
        const savedSession = localStorage.getItem('mindweave-session');
        if (savedSession) {
            loadSession(savedSession);
        }

        // 恢复角色模式

        // ========== 快捷键支持 ==========
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K: 聚焦搜索框
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            // Ctrl/Cmd + N: 新建对话
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                newChat();
            }
            // Ctrl/Cmd + /: 显示快捷键提示
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                showShortcutHint();
            }
            // ESC: 关闭弹窗和搜索结果
            if (e.key === 'Escape') {
                closeSearchResults();
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });

        function showShortcutHint() {
            const hint = document.getElementById('shortcutHint');
            hint.classList.add('active');
            setTimeout(() => hint.classList.remove('active'), 3000);
        }

        // ========== 搜索功能 ==========
        let searchDebounceTimer = null;

        function handleSearch(query) {
            const box = document.getElementById('searchBox');
            const clearBtn = box.querySelector('.clear');

            if (query.trim()) {
                box.classList.add('has-value');
                clearBtn.style.display = 'block';
            } else {
                box.classList.remove('has-value');
                clearBtn.style.display = 'none';
                closeSearchResults();
                return;
            }

            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => performSearch(query), 300);
        }

        function handleSearchKeyDown(e) {
            if (e.key === 'Escape') {
                clearSearch();
            }
        }

        async function performSearch(query) {
            if (query.length < 2) return;

            try {
                const r = await fetch(`/search?q=${encodeURIComponent(query)}`);
                const data = await r.json();
                renderSearchResults(data.results || []);
            } catch (e) {
                console.error('搜索失败:', e);
            }
        }

        function renderSearchResults(results) {
            const container = document.getElementById('searchResults');

            if (results.length === 0) {
                container.innerHTML = '<div class="search-no-results">未找到匹配的消息</div>';
                container.classList.add('active');
                return;
            }

            container.innerHTML = results.map(r => `
                <div class="search-result-item" onclick="jumpToMessage('${r.session_id}', ${r.message_index})">
                    <div class="session-name">${escapeHtml(r.session_title)}</div>
                    <div class="snippet">${escapeHtml(r.snippet)}</div>
                    <div class="meta">${r.role === 'user' ? '👤 用户' : '🤖 助手'} · ${formatTime(r.timestamp)}</div>
                </div>
            `).join('');

            container.classList.add('active');
        }

        function closeSearchResults() {
            document.getElementById('searchResults').classList.remove('active');
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            input.value = '';
            input.focus();
            handleSearch('');
        }

        async function jumpToMessage(sessionId, messageIndex) {
            // 加载对应会话
            await loadSession(sessionId);
            closeSearchResults();

            // 滚动到对应消息
            setTimeout(() => {
                const messages = document.querySelectorAll('.message');
                if (messages[messageIndex]) {
                    messages[messageIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    messages[messageIndex].style.animation = 'highlight 1s ease';
                    setTimeout(() => {
                        messages[messageIndex].style.animation = '';
                    }, 1000);
                }
            }, 100);
        }

        // ========== 消息编辑/重发 ==========
        function editMessage(index) {
            const messageEl = document.querySelectorAll('.message')[index];
            if (!messageEl) return;

            const contentEl = messageEl.querySelector('.content');
            const originalContent = allMessages[index].content;

            // 创建编辑框
            contentEl.innerHTML = `
                <textarea class="message-edit-box" id="editBox_${index}">${escapeHtml(originalContent)}</textarea>
                <div class="message-edit-actions">
                    <button class="cancel" onclick="cancelEdit(${index}, '${escapeHtml(originalContent).replace(/'/g, "\\'")}')">取消</button>
                    <button class="save" onclick="saveEdit(${index})">保存</button>
                </div>
            `;

            document.getElementById(`editBox_${index}`).focus();
        }

        // 获取 CSRF Token
        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        async function saveEdit(index) {
            const newContent = document.getElementById(`editBox_${index}`).value.trim();
            if (!newContent) {
                toast('内容不能为空', 'error');
                return;
            }

            try {
                const r = await fetch(`/sessions/${currentSessionId}/messages/${index}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ content: newContent })
                });

                const data = await r.json();
                if (data.success) {
                    allMessages[index] = data.message;
                    loadSession(currentSessionId);
                    toast('已保存');
                } else {
                    toast(data.error || '保存失败', 'error');
                }
            } catch (e) {
                toast('保存失败', 'error');
            }
        }

        function cancelEdit(index, originalContent) {
            loadSession(currentSessionId);
        }

        async function deleteMessage(index) {
            if (!confirm('删除这条消息及其之后的所有消息？')) return;

            try {
                const r = await fetch(`/sessions/${currentSessionId}/messages/${index}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await r.json();
                if (data.success) {
                    allMessages.splice(index);
                    loadSession(currentSessionId);
                    toast('已删除');
                } else {
                    toast(data.error || '删除失败', 'error');
                }
            } catch (e) {
                toast('删除失败', 'error');
            }
        }

        async function regenerateMessage(index) {
            try {
                const r = await fetch(`/sessions/${currentSessionId}/messages/${index}/regenerate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await r.json();
                if (data.success) {
                    // 重新发送消息
                    document.getElementById('userInput').value = data.message;
                    document.getElementById('modelSelect').value = data.model;
                    sendMessage();
                } else {
                    toast(data.error || '重新生成失败', 'error');
                }
            } catch (e) {
                toast('重新生成失败', 'error');
            }
        }

        // ========== 会话重命名 ==========
        async function renameSession(sessionId, currentTitle) {
            const newTitle = prompt('输入新会话名称：', currentTitle || '');
            if (newTitle === null) return;

            try {
                const r = await fetch(`/sessions/${sessionId}/rename`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ title: newTitle.trim() })
                });

                const data = await r.json();
                if (data.success) {
                    loadSessionsList();
                    toast('已重命名');
                } else {
                    toast(data.error || '重命名失败', 'error');
                }
            } catch (e) {
                toast('重命名失败', 'error');
            }
        }

        // ========== 辅助函数 ==========
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatTime(isoString) {
            if (!isoString) return '';
            const date = new Date(isoString);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return '刚刚';
            if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前';
            if (diff < 86400000) return Math.floor(diff / 3600000) + '小时前';

            return date.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' });
        }

        // 添加高亮动画
        const style = document.createElement('style');
        style.textContent = `
            @keyframes highlight {
                0% { background: rgba(201,168,92,0.3); }
                100% { background: transparent; }
            }
        `;
        document.head.appendChild(style);
    </script>

    <!-- 快捷键提示 -->
    <div class="shortcut-hint" id="shortcutHint">
        <kbd>Ctrl</kbd> + <kbd>K</kbd> 搜索 · <kbd>Ctrl</kbd> + <kbd>N</kbd> 新建 · <kbd>Ctrl</kbd> + <kbd>/</kbd> 帮助
    </div>
</body>
</html>
