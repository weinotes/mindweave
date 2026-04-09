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
Route::post('/logout', [LoginController::class, 'logout']);

// Protected routes
Route::middleware('password')->group(function () {
    Route::get('/', [ChatController::class, 'index']);
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::get('/models', [ChatController::class, 'models']);

    // Sessions
    Route::post('/sessions', [ChatController::class, 'createSession']);
    Route::get('/sessions', [ChatController::class, 'listSessions']);
    Route::get('/sessions/{id}', [ChatController::class, 'getSession']);
    Route::delete('/sessions/{id}', [ChatController::class, 'deleteSession']);

    // Data directory
    Route::get('/data-dir', [ChatController::class, 'getDataDir']);
    Route::post('/data-dir', [ChatController::class, 'setDataDir']);

    // User management (admin)
    Route::get('/admin/users', [ChatController::class, 'adminUsers']);
    Route::post('/admin/users', [ChatController::class, 'adminCreateUser']);
    Route::delete('/admin/users/{username}', [ChatController::class, 'adminDeleteUser']);
    Route::post('/admin/users/{username}/password', [ChatController::class, 'adminSetPassword']);
});
