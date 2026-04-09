<?php
/**
 * @author Davey (https://github.com/weinotes)
 */

namespace App\Http\Middleware;

use App\Services\UserService;
use Closure;

class CheckPassword
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function handle($request, Closure $next)
    {
        // Already logged in
        if ($request->session()->get('username')) {
            return $next($request);
        }

        // No users created yet → single-user/guest mode, auto-login
        $users = $this->userService->listUsers();
        if (empty($users)) {
            $request->session()->put('username', 'guest');
            return $next($request);
        }

        // Multi-user: must log in
        return redirect('/login');
    }
}
