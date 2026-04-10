<?php
/**
 * @author Davey (https://github.com/weinotes)
 */

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function show(Request $request)
    {
        // Already logged in
        if ($request->session()->get('username')) {
            return redirect('/');
        }

        $users = $this->userService->listUsers();

        // First install: auto-create default admin account
        if (empty($users)) {
            $this->userService->createUser('admin', 'admin');
            $users = $this->userService->listUsers();
        }

        return view('login', compact('users'));
    }

    public function authenticate(Request $request)
    {
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');

        // Multi-user mode: must select username
        if (empty($username)) {
            return back()->withErrors(['username' => '请选择或输入用户名']);
        }

        // Check if user exists
        $user = $this->userService->getUser($username);
        if (!$user) {
            return back()->withErrors(['username' => '用户不存在']);
        }

        // Verify password via UserService (handles MD5→bcrypt auto-migration)
        if (!empty($user['password']) && !$this->userService->verifyPassword($username, $password)) {
            return back()->withErrors(['password' => '密码错误']);
        }

        $request->session()->put('username', $username);
        return redirect('/');
    }

    public function register(Request $request)
    {
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');

        if (empty($username) || strlen($username) < 2) {
            return back()->withErrors(['username' => '用户名至少2个字符']);
        }
        if (strlen($password) < 4) {
            return back()->withErrors(['password' => '密码至少4个字符']);
        }

        $users = $this->userService->listUsers();
        if (array_key_exists($username, $users)) {
            return back()->withErrors(['username' => '用户名已存在']);
        }

        $this->userService->createUser($username, $password);
        $request->session()->put('username', $username);
        return redirect('/');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('username');
        return redirect('/login');
    }
}
