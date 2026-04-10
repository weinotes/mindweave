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
