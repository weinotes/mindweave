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
        $model = $request->input('model', 'poet-qwen:latest');
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
            $models = array_map(fn($m) => $m['name'], $data['models'] ?? []);
            return response()->json($models ?: ['poet-qwen:latest']);
        } catch (\Exception $e) {
            return response()->json(['poet-qwen:latest']);
        }
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
}
