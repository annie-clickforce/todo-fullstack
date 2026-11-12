<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    // 速率限制：同一個 IP 每 1 分鐘最多 5 次；第三個參數是計數器前綴，讓註冊、登入各自計算
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1,register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/todos', [TodoController::class, 'index']);
        Route::post('/todos', [TodoController::class, 'store']);
        Route::get('/todos/{id}', [TodoController::class, 'show']);
        Route::patch('/todos/{id}', [TodoController::class, 'update']);
        Route::delete('/todos/{id}', [TodoController::class, 'destroy']);
    });
});
