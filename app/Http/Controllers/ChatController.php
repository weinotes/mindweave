<?php
/**
 * @author Davey (https://github.com/weinotes)
 */

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function index(Request $request)
    {
        $username = $request->session()->get('username');
        $users = $this->userService->listUsers();
        $sessions = $username ? $this->userService->listUserSessions($username) : [];
        return view('chat', compact('username', 'users', 'sessions'));
    }

    public function chat(Request $request)
    {
        $username = $request->session()->get('username');
        $message = $request->input('message');
        $model = $request->input('model', config('ollama.model'));
        $sessionId = $request->input('session_id');

        // Auto-create guest session
        if (!$sessionId) {
            $session = $this->userService->createSession($username ?: 'guest');
            $sessionId = $session['id'];
        }

        // Load or create session
        $session = $username
            ? $this->userService->getSession($username, $sessionId)
            : $this->userService->getSession('guest', $sessionId);

        if (!$session) {
            $session = [
                'id' => $sessionId,
                'model' => $model,
                'messages' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];
        }

        // Add user message
        $session['messages'][] = [
            'role' => 'user',
            'content' => $message,
            'timestamp' => now()->toISOString()
        ];

        // Call Ollama API
        $response = $this->callOllama($model, $session['messages']);

        // Add assistant response
        $session['messages'][] = [
            'role' => 'assistant',
            'content' => $response,
            'timestamp' => now()->toISOString()
        ];
        $session['updated_at'] = now()->toISOString();
        $session['model'] = $model;

        // Save
        $saveUser = $username ?: 'guest';
        $this->userService->saveSession($saveUser, $sessionId, $session);

        return response()->json([
            'response' => $response,
            'session_id' => $sessionId
        ]);
    }

    public function models()
    {
        try {
            $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');
            $response = file_get_contents($host . '/api/tags');
            $data = json_decode($response, true);
            // 返回模型名列表（含大小）
            return response()->json($data['models'] ?? []);
        } catch (\Exception $e) {
            return response()->json(['error' => '无法获取模型列表，Ollama 可能未运行'], 500);
        }
    }

    public function checkModel(Request $request, $name)
    {
        $model = trim(urldecode($name));
        if (empty($model)) {
            return response()->json(['valid' => false, 'error' => '模型名称为空'], 400);
        }

        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');

        // 1. 检测 Ollama 是否运行
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ]);
        $tagsResult = @file_get_contents($host . '/api/tags', false, $context);
        if ($tagsResult === false) {
            return response()->json([
                'valid' => false,
                'error' => '无法连接到 Ollama，请确认 Ollama 已启动',
            ], 503);
        }

        // 2. 检查模型是否已存在
        $tagsData = json_decode($tagsResult, true);
        $existingModels = $tagsData['models'] ?? [];
        foreach ($existingModels as $m) {
            if ($m['name'] === $model || $m['name'] === $model . ':latest') {
                return response()->json([
                    'valid' => true,
                    'exists' => true,
                    'error' => '模型已存在，无需下载',
                    'model' => $m,
                ]);
            }
        }

        // 3. Ollama 运行正常，模型不存在，可以下载
        return response()->json([
            'valid' => true,
            'exists' => false,
            'message' => '模型验证通过，准备下载',
        ]);
    }

    /**
     * SSE 流式下载模型，客户端断开时自动取消。
     */
    public function streamPullModel(Request $request)
    {
        $model = trim($request->input('model', ''));
        if (empty($model)) {
            return response()->json(['error' => '模型名称不能为空'], 400);
        }

        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');

        return response()->stream(function () use ($host, $model) {
            $pidFile = storage_path('app/pull_pid.txt');

            // SSE 头部
            echo "data: " . json_encode(['type' => 'start', 'model' => $model]) . "\n\n";
            flush();

            // 启动后台 curl 进程，stdout 输出 JSON 行流
            $cmd = sprintf(
                'curl -s -X POST %s/api/pull -d \'{"name":"%s","stream":true}\' 2>/dev/null',
                escapeshellarg($host),
                escapeshellarg($model)
            );
            $handle = popen($cmd . ' > /dev/null 2>&1 & echo $!', 'r');
            $pid = trim(fgets($handle));
            pclose($handle);

            if ($pid) {
                file_put_contents($pidFile, $pid);
            }

            // 轮询 Ollama 推送状态（从 /api/show 实时检查）
            $maxWait = 120; // 最多等 2 分钟
            $waited = 0;
            $lastStatus = '';
            $pullSuccess = false;
            $lastPulled = null;

            $waitedFile = storage_path('app/pull_waited.txt');
            while ($waited < $maxWait) {
                // 写入等待时间，供轮询接口读取
                @file_put_contents($waitedFile, $waited);

                // 检查子进程是否还在（如果还在跑说明还没拉完）
                if ($pid && posix_kill($pid, 0) === false) {
                    // 进程已退出，检查是否成功
                    $status = trim(@file_get_contents($host . '/api/show', false, stream_context_create([
                        'http' => ['timeout' => 5, 'ignore_errors' => true, 'method' => 'POST',
                            'header' => "Content-Type: application/json\r\n",
                            'content' => json_encode(['name' => $model])]
                    ])));
                    $decoded = json_decode($status, true);
                    if (!isset($decoded['error'])) {
                        $pullSuccess = true;
                        break;
                    }
                }

                // 通过 /tags 检查是否已存在（拉取完成的标志）
                $tagsJson = @file_get_contents($host . '/api/tags', false, stream_context_create([
                    'http' => ['timeout' => 5, 'ignore_errors' => true]
                ]));
                $tags = json_decode($tagsJson ?: '', true);
                $found = false;
                if (!empty($tags['models'])) {
                    foreach ($tags['models'] as $m) {
                        if ($m['name'] === $model) {
                            $lastPulled = $m;
                            $found = true;
                            break;
                        }
                    }
                }

                if ($found) {
                    $pullSuccess = true;
                    echo "data: " . json_encode([
                        'type' => 'done',
                        'model' => $model,
                        'size' => $lastPulled['size'] ?? null,
                    ]) . "\n\n";
                    flush();
                    break;
                }

                // 每 3 秒发送一次进度 ping
                $waited += 3;
                echo "data: " . json_encode([
                    'type' => 'progress',
                    'model' => $model,
                    'waited' => $waited,
                ]) . "\n\n";
                flush();
                sleep(3);
            }

            // 清理 PID 文件
            if (file_exists($pidFile)) {
                @unlink($pidFile);
            }

            if (!$pullSuccess) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'error' => "模型 {$model} 下载超时或失败",
                ]) . "\n\n";
                flush();
            }

            // 清理
            @unlink(storage_path('app/pull_waited.txt'));
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * 取消正在下载的模型（杀掉后台 curl 进程）。
     */
    public function cancelPull(Request $request, $name)
    {
        $model = trim(urldecode($name));
        $pidFile = storage_path('app/pull_pid.txt');

        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && is_numeric($pid)) {
                posix_kill((int)$pid, SIGTERM);
            }
            @unlink($pidFile);
        }
        @unlink(storage_path('app/pull_waited.txt'));

        // Ollama 的取消实际上需要 DELETE /api/pull?name=xxx
        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');
        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => 10,
                'ignore_errors' => true,
            ]
        ]);
        @file_get_contents($host . '/api/pull?name=' . urlencode($model), false, $context);

        return response()->json(['success' => true, 'model' => $model]);
    }

    public function pullModel(Request $request)
    {
        $model = trim($request->input('model', ''));
        if (empty($model)) {
            return response()->json(['error' => '模型名称不能为空'], 400);
        }

        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');
        $payload = json_encode(['name' => $model, 'stream' => false]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ]
        ]);

        $result = @file_get_contents($host . '/api/pull', false, $context);

        return response()->json([
            'success' => true,
            'model' => $model,
            'message' => "已开始下载模型 {$model}",
        ]);
    }

    /**
     * 轮询下载状态（非 SSE，避免阻塞页面导航）
     */
    public function pullStatus(Request $request)
    {
        $model = trim($request->input('name', ''));
        if (empty($model)) {
            return response()->json(['error' => '模型名称不能为空'], 400);
        }

        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');

        // 检查模型是否已存在（下载完成的标志）
        $tagsJson = @file_get_contents($host . '/api/tags', false, stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true]
        ]));
        $tags = json_decode($tagsJson ?: '', true);

        if (!empty($tags['models'])) {
            foreach ($tags['models'] as $m) {
                if ($m['name'] === $model) {
                    return response()->json([
                        'status' => 'done',
                        'model' => $model,
                        'size' => $m['size'] ?? null,
                    ]);
                }
            }
        }

        // 检查 PID 文件，看后台进程是否还在
        $pidFile = storage_path('app/pull_pid.txt');
        $running = false;
        $waited = 0;

        if (file_exists($pidFile)) {
            $pid = trim(@file_get_contents($pidFile));
            if ($pid && function_exists('posix_kill')) {
                $running = @posix_kill((int)$pid, 0);
            }
            // 读取等待时间
            $metaFile = storage_path('app/pull_waited.txt');
            if (file_exists($metaFile)) {
                $waited = (int)trim(@file_get_contents($metaFile));
            }
        }

        if (!$running && file_exists($pidFile)) {
            // 进程已退出但模型不存在，说明下载失败
            @unlink($pidFile);
            return response()->json([
                'status' => 'error',
                'error' => '下载进程已退出但模型未就绪，可能下载失败',
            ]);
        }

        if ($waited >= 3600) {
            return response()->json([
                'status' => 'error',
                'error' => '下载超时（超过1小时）',
            ]);
        }

        return response()->json([
            'status' => 'downloading',
            'model' => $model,
            'waited' => $waited,
        ]);
    }

    public function deleteModel(Request $request, $name)
    {
        $name = urldecode($name);
        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');

        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => 30,
                'ignore_errors' => true,
            ]
        ]);

        $result = @file_get_contents($host . '/api/delete?name=' . urlencode($name), false, $context);

        return response()->json(['success' => true, 'model' => $name]);
    }

    public function streamChat(Request $request)
    {
        $username = $request->session()->get('username');
        $message = $request->input('message');
        $model = $request->input('model', config('ollama.model'));
        $sessionId = $request->input('session_id');
        $systemPrompt = $request->input('system_prompt');

        // Auto-create guest session
        if (!$sessionId) {
            $session = $this->userService->createSession($username ?: 'guest');
            $sessionId = $session['id'];
        }

        $saveUser = $username ?: 'guest';
        $session = $this->userService->getSession($saveUser, $sessionId);

        if (!$session) {
            $session = [
                'id' => $sessionId,
                'model' => $model,
                'messages' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];
        }

        // Add user message
        $session['messages'][] = [
            'role' => 'user',
            'content' => $message,
            'timestamp' => now()->toISOString()
        ];

        // Build SSE response
        return response()->stream(function () use ($model, $session, $saveUser, $sessionId, $systemPrompt) {
            $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');
            $timeout = config('ollama.timeout', 120);
            $url = $host . '/api/chat';

            // 构建消息列表，如有系统提示词则添加
            $messages = $session['messages'];
            if ($systemPrompt) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => $systemPrompt
                ]);
            }

            $payload = [
                'model' => $model,
                'messages' => $messages,
                'stream' => true,
            ];

            $jsonPayload = json_encode($payload);
            $tempFile = tempnam(sys_get_temp_dir(), 'ollama_');
            file_put_contents($tempFile, $jsonPayload);

            $cmd = "curl -s -N -X POST " . escapeshellarg($url) . " -H 'Content-Type: application/json' -d @{$tempFile} --max-time " . (int)$timeout . " 2>&1";

            $handle = popen($cmd, 'r');
            if (!$handle) {
                echo "data: " . json_encode(['error' => '无法连接到 Ollama 服务']) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
                @unlink($tempFile);
                return;
            }

            $fullContent = '';
            $fullThink = '';

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;

                $data = json_decode($line, true);
                if (!$data) continue;

                if (isset($data['message']['think'])) {
                    $fullThink .= $data['message']['think'];
                    echo "data: " . json_encode(['type' => 'think', 'content' => $data['message']['think'], 'full' => $fullThink]) . "\n\n";
                    flush();
                }

                if (isset($data['message']['content'])) {
                    $fullContent .= $data['message']['content'];
                    echo "data: " . json_encode(['type' => 'chunk', 'content' => $data['message']['content'], 'full' => $fullContent]) . "\n\n";
                    flush();
                }

                if (!empty($data['done'])) {
                    $session['messages'][] = [
                        'role' => 'assistant',
                        'content' => $fullContent,
                        'think' => $fullThink ?: null,
                        'timestamp' => now()->toISOString(),
                    ];
                    $session['updated_at'] = now()->toISOString();
                    $session['model'] = $model;
                    $this->userService->saveSession($saveUser, $sessionId, $session);

                    echo "data: " . json_encode(['type' => 'done', 'session_id' => $sessionId, 'content' => $fullContent]) . "\n\n";
                    echo "data: [DONE]\n\n";
                    flush();
                    break;
                }
            }

            pclose($handle);
            @unlink($tempFile);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function createSession(Request $request)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $session = $this->userService->createSession($username);
        return response()->json($session);
    }

    public function listSessions(Request $request)
    {
        $username = $request->session()->get('username') ?: 'guest';
        return response()->json($this->userService->listUserSessions($username));
    }

    public function getSession(Request $request, $id)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $session = $this->userService->getSession($username, $id);
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }
        return response()->json($session);
    }

    public function deleteSession(Request $request, $id)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $this->userService->deleteSession($username, $id);
        return response()->json(['success' => true]);
    }

    // ============ Admin endpoints ============

    public function getDataDir(Request $request)
    {
        return response()->json(['path' => $this->userService->getDataDir()]);
    }

    public function setDataDir(Request $request)
    {
        $path = trim($request->input('path', ''));
        if (empty($path)) {
            return response()->json(['error' => '路径不能为空'], 400);
        }
        $result = $this->userService->setDataDir($path);
        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }
        return response()->json($result);
    }

    public function adminUsers(Request $request)
    {
        // Anyone logged in can see user list (simple security for now)
        $users = $this->userService->listUsers();
        return response()->json($users);
    }

    public function adminCreateUser(Request $request)
    {
        $username = trim($request->input('username'));
        $password = $request->input('password', '');

        if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{2,20}$/', $username)) {
            return response()->json(['error' => '用户名需为2-20位字母、数字或下划线'], 400);
        }

        if ($this->userService->getUser($username)) {
            return response()->json(['error' => '用户已存在'], 400);
        }

        $user = $this->userService->createUser($username, $password);
        return response()->json($user);
    }

    public function adminDeleteUser(Request $request, $username)
    {
        if ($username === 'guest') {
            return response()->json(['error' => '不能删除访客账号'], 400);
        }
        $this->userService->deleteUser($username);
        return response()->json(['success' => true]);
    }

    public function adminSetPassword(Request $request, $username)
    {
        $password = $request->input('password', '');
        if (strlen($password) > 0 && strlen($password) < 4) {
            return response()->json(['error' => '密码至少4位'], 400);
        }
        $this->userService->setPassword($username, $password);
        return response()->json(['success' => true]);
    }

    private function callOllama($model, $messages)
    {
        $host = config('ollama.host', 'http://localhost:11434');
        $timeout = config('ollama.timeout', 120);
        $url = rtrim($host, '/') . '/api/chat';
        $payload = ['model' => $model, 'messages' => $messages, 'stream' => false];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return '无法连接到 Ollama 服务。请确保 Ollama 正在运行。';
        }

        $data = json_decode($response, true);
        return $data['message']['content'] ?? '无响应';
    }

    // ============ 会话管理扩展 ============

    /**
     * 重命名会话
     */
    public function renameSession(Request $request, $id)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $session = $this->userService->getSession($username, $id);

        if (!$session) {
            return response()->json(['error' => '会话不存在'], 404);
        }

        $title = trim($request->input('title', ''));
        if (empty($title)) {
            return response()->json(['error' => '标题不能为空'], 400);
        }

        $session['title'] = $title;
        $session['updated_at'] = now()->toISOString();
        $this->userService->saveSession($username, $id, $session);

        return response()->json(['success' => true, 'title' => $title]);
    }

    /**
     * 搜索所有会话中的消息
     */
    public function searchMessages(Request $request)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $query = trim($request->input('q', ''));

        if (empty($query) || strlen($query) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $sessions = $this->userService->listUserSessions($username);
        $results = [];
        $queryLower = mb_strtolower($query);

        foreach ($sessions as $session) {
            if (empty($session['messages'])) continue;

            foreach ($session['messages'] as $index => $msg) {
                $content = $msg['content'] ?? '';
                if (mb_stripos($content, $query) !== false) {
                    // 提取上下文片段
                    $snippet = $this->extractSnippet($content, $query);
                    $results[] = [
                        'session_id' => $session['id'],
                        'session_title' => $session['title'] ?? $this->generateSessionTitle($session),
                        'message_index' => $index,
                        'role' => $msg['role'],
                        'snippet' => $snippet,
                        'timestamp' => $msg['timestamp'] ?? $session['updated_at'],
                    ];
                }
            }
        }

        // 按时间倒序
        usort($results, fn($a, $b) => ($b['timestamp'] ?? '') <=> ($a['timestamp'] ?? ''));

        return response()->json(['results' => $results, 'total' => count($results), 'query' => $query]);
    }

    /**
     * 编辑单条消息
     */
    public function editMessage(Request $request, $id, $index)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $session = $this->userService->getSession($username, $id);

        if (!$session) {
            return response()->json(['error' => '会话不存在'], 404);
        }

        $index = (int) $index;
        if (!isset($session['messages'][$index])) {
            return response()->json(['error' => '消息不存在'], 404);
        }

        $content = trim($request->input('content', ''));
        if (empty($content)) {
            return response()->json(['error' => '内容不能为空'], 400);
        }

        $session['messages'][$index]['content'] = $content;
        $session['messages'][$index]['edited_at'] = now()->toISOString();
        $session['updated_at'] = now()->toISOString();

        $this->userService->saveSession($username, $id, $session);

        return response()->json(['success' => true, 'message' => $session['messages'][$index]]);
    }

    /**
     * 删除单条消息
     */
    public function deleteMessage(Request $request, $id, $index)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $session = $this->userService->getSession($username, $id);

        if (!$session) {
            return response()->json(['error' => '会话不存在'], 404);
        }

        $index = (int) $index;
        if (!isset($session['messages'][$index])) {
            return response()->json(['error' => '消息不存在'], 404);
        }

        // 删除该消息及之后的所有消息（保持对话连贯性）
        array_splice($session['messages'], $index);
        $session['updated_at'] = now()->toISOString();

        $this->userService->saveSession($username, $id, $session);

        return response()->json(['success' => true, 'deleted_from' => $index]);
    }

    /**
     * 重新生成某条消息的回复
     */
    public function regenerateMessage(Request $request, $id, $index)
    {
        $username = $request->session()->get('username') ?: 'guest';
        $session = $this->userService->getSession($username, $id);

        if (!$session) {
            return response()->json(['error' => '会话不存在'], 404);
        }

        $index = (int) $index;
        if (!isset($session['messages'][$index])) {
            return response()->json(['error' => '消息不存在'], 404);
        }

        // 找到对应用户消息（通常是上一条）
        $userMsgIndex = $index - 1;
        if ($userMsgIndex < 0 || $session['messages'][$userMsgIndex]['role'] !== 'user') {
            return response()->json(['error' => '无法找到对应用户消息'], 400);
        }

        // 删除该回复及之后的所有消息
        array_splice($session['messages'], $index);
        $session['updated_at'] = now()->toISOString();
        $this->userService->saveSession($username, $id, $session);

        // 返回需要重新发送的消息内容
        return response()->json([
            'success' => true,
            'message' => $session['messages'][$userMsgIndex]['content'],
            'model' => $session['model'] ?? config('ollama.model'),
        ]);
    }

    // ============ 辅助方法 ============

    private function extractSnippet(string $content, string $query, int $contextLength = 50): string
    {
        $pos = mb_stripos($content, $query);
        if ($pos === false) return mb_substr($content, 0, 100) . '...';

        $start = max(0, $pos - $contextLength);
        $length = mb_strlen($query) + $contextLength * 2;
        $snippet = mb_substr($content, $start, $length);

        if ($start > 0) $snippet = '...' . $snippet;
        if ($start + $length < mb_strlen($content)) $snippet .= '...';

        return $snippet;
    }

    private function generateSessionTitle(array $session): string
    {
        if (!empty($session['messages'])) {
            $firstMsg = $session['messages'][0]['content'] ?? '';
            return mb_substr($firstMsg, 0, 20) . (mb_strlen($firstMsg) > 20 ? '...' : '');
        }
        return '新会话 ' . substr($session['id'], 0, 8);
    }

    /**
     * 用 LLM 自动生成会话标题
     */
    public function autoTitle(Request $request, string $id)
    {
        $username = session('username', 'guest');
        $session = $this->userService->getSession($username, $id);
        if (!$session) {
            return response()->json(['error' => '会话不存在'], 404);
        }

        // 已有自定义标题则跳过
        $currentTitle = $session['title'] ?? '';
        if (!empty($currentTitle) && !str_starts_with($currentTitle, '新会话') && !str_starts_with($currentTitle, mb_substr($session['messages'][0]['content'] ?? '', 0, 20))) {
            return response()->json(['title' => $currentTitle, 'generated' => false]);
        }

        // 从前 2 条消息提取上下文
        $messages = array_slice($session['messages'], 0, 4);
        $context = '';
        foreach ($messages as $m) {
            $role = $m['role'] === 'user' ? '用户' : '助手';
            $content = mb_substr($m['content'] ?? '', 0, 200);
            $context .= "{$role}: {$content}\n";
        }

        if (empty(trim($context))) {
            return response()->json(['title' => $currentTitle ?: '新对话', 'generated' => false]);
        }

        // 调用 Ollama 生成标题
        $host = rtrim(config('ollama.host', 'http://localhost:11434'), '/');
        $url = $host . '/api/chat';
        $payload = [
            'model' => config('ollama.model', 'qwen2.5:3b'),
            'messages' => [
                ['role' => 'system', 'content' => '你是一个标题生成器。根据对话内容生成一个简短标题（不超过15个字），只输出标题，不加引号、不加标点。'],
                ['role' => 'user', 'content' => "请为以下对话生成标题：\n{$context}"],
            ],
            'stream' => false,
            'options' => ['num_predict' => 30],
        ];

        try {
            $result = $this->callOllama($url, $payload);
            $title = trim($result['message']['content'] ?? '');
            // 清理可能的引号和标点
            $title = trim($title, '「」""\'"《》【】');
            if (mb_strlen($title) > 30) {
                $title = mb_substr($title, 0, 30) . '…';
            }
            if (empty($title)) {
                $title = $this->generateSessionTitle($session);
            }

            // 保存标题
            $session['title'] = $title;
            $this->userService->saveSession($username, $id, $session);

            return response()->json(['title' => $title, 'generated' => true]);
        } catch (\Throwable $e) {
            // 生成失败，回退到简单标题
            $title = $this->generateSessionTitle($session);
            $session['title'] = $title;
            $this->userService->saveSession($username, $id, $session);
            return response()->json(['title' => $title, 'generated' => false]);
        }
    }
}
