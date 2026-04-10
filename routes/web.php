<?php
/**
 * @author Davey (https://github.com/weinotes)
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/register', [LoginController::class, 'register']);
Route::post('/logout', [LoginController::class, 'logout']);

// Protected routes
Route::middleware('password')->group(function () {
    Route::get('/', [ChatController::class, 'index']);
    Route::post('/chat', [ChatController::class, 'chat']);          // 同步响应（非流式兜底）
    Route::post('/chat/stream', [ChatController::class, 'streamChat']); // 流式响应（SSE）
    Route::get('/models', [ChatController::class, 'models']);
    Route::get('/models/check/{name}', [ChatController::class, 'checkModel']);  // 验证模型是否可用
    Route::post('/models/pull', [ChatController::class, 'pullModel']);     // 非流式下载（兜底）
    // Route::get('/models/pull/stream', [ChatController::class, 'streamPullModel']); // 已废弃，前端改用轮询
    Route::post('/models/pull/cancel/{name}', [ChatController::class, 'cancelPull']); // 取消下载
    Route::get('/models/pull/status', [ChatController::class, 'pullStatus']);    // 轮询下载状态
    Route::delete('/models/{name}', [ChatController::class, 'deleteModel']); // 删除模型
    
    // 硬件检测与模型推荐
    Route::get('/hardware', [App\Http\Controllers\HardwareController::class, 'info']);
    Route::get('/hardware/recommend', [App\Http\Controllers\HardwareController::class, 'recommend']);

    // Sessions
    Route::post('/sessions', [ChatController::class, 'createSession']);
    Route::get('/sessions', [ChatController::class, 'listSessions']);
    Route::get('/sessions/{id}', [ChatController::class, 'getSession']);
    Route::delete('/sessions/{id}', [ChatController::class, 'deleteSession']);
    Route::patch('/sessions/{id}/rename', [ChatController::class, 'renameSession']); // 重命名会话
    Route::post('/sessions/{id}/auto-title', [ChatController::class, 'autoTitle']); // 自动生成标题

    // Messages
    Route::patch('/sessions/{id}/messages/{index}', [ChatController::class, 'editMessage']); // 编辑消息
    Route::delete('/sessions/{id}/messages/{index}', [ChatController::class, 'deleteMessage']); // 删除单条消息
    Route::post('/sessions/{id}/messages/{index}/regenerate', [ChatController::class, 'regenerateMessage']); // 重新生成回复

    // Search
    Route::get('/search', [ChatController::class, 'searchMessages']); // 全局搜索消息

    // Data directory
    Route::get('/data-dir', [ChatController::class, 'getDataDir']);
    Route::post('/data-dir', [ChatController::class, 'setDataDir']);

    // User management (admin)
    Route::get('/admin/users', [ChatController::class, 'adminUsers']);
    Route::post('/admin/users', [ChatController::class, 'adminCreateUser']);
    Route::delete('/admin/users/{username}', [ChatController::class, 'adminDeleteUser']);
    Route::post('/admin/users/{username}/password', [ChatController::class, 'adminSetPassword']);
});
