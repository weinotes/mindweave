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

        // Allow guest access for API routes if accessing guest sessions
        $route = $request->route();
        if ($route) {
            $routeUri = $route->uri();
            // Check if accessing a guest session via API
            if (str_starts_with($routeUri, 'sessions/')) {
                $id = $request->route('id');
                if ($id && $this->userService->getSession('guest', $id)) {
                    $request->session()->put('username', 'guest');
                    return $next($request);
                }
            }
            // Allow chat API for guest sessions
            if ($routeUri === 'chat' || $routeUri === 'chat/stream') {
                $sessionId = $request->input('session_id');
                if ($sessionId && $this->userService->getSession('guest', $sessionId)) {
                    $request->session()->put('username', 'guest');
                    return $next($request);
                }
            }
        }

        // Multi-user: must log in
        return redirect('/login');
    }
}
