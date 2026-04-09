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
        return view('login', compact('users'));
    }

    public function authenticate(Request $request)
    {
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');

        // Single-user mode: no users created yet, auto-login as guest
        $users = $this->userService->listUsers();
        if (empty($users)) {
            $request->session()->put('username', 'guest');
            return redirect('/');
        }

        // Multi-user mode: must select username
        if (empty($username)) {
            return back()->withErrors(['username' => '请选择或输入用户名']);
        }

        // Check if user exists
        $user = $this->userService->getUser($username);
        if (!$user) {
            return back()->withErrors(['username' => '用户不存在']);
        }

        // Verify password (empty password = no password required)
        if (!empty($user['password']) && md5($password) !== $user['password']) {
            return back()->withErrors(['password' => '密码错误']);
        }

        $request->session()->put('username', $username);
        return redirect('/');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('username');
        return redirect('/login');
    }
}
