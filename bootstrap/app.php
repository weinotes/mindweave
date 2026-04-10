<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'password' => \App\Http\Middleware\CheckPassword::class,
        ]);
        
        // 禁用特定路由的 CSRF 验证
        $middleware->validateCsrfTokens(except: [
            'chat',
            'chat/stream',
            'sessions/*/messages/*',
            'sessions/*/rename',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
