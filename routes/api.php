<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\MediaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Burada uygulamanın JSON tabanlı API uç noktalarını tanımlıyoruz.
| Varsayım: Laravel Sanctum ile token bazlı auth kullanılacak.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Siteler
    Route::get('sites', [SiteController::class, 'index']);
    Route::post('sites', [SiteController::class, 'store']);
    Route::get('sites/{site}', [SiteController::class, 'show']);
    Route::put('sites/{site}', [SiteController::class, 'update']);
    Route::delete('sites/{site}', [SiteController::class, 'destroy']);

    // Sayfalar
    Route::get('sites/{site}/pages', [PageController::class, 'index']);
    Route::post('sites/{site}/pages', [PageController::class, 'store']);
    Route::get('sites/{site}/pages/{page}', [PageController::class, 'show']);
    Route::put('sites/{site}/pages/{page}', [PageController::class, 'update']);
    Route::delete('sites/{site}/pages/{page}', [PageController::class, 'destroy']);
    Route::post('sites/{site}/pages/{page}/duplicate', [PageController::class, 'duplicate']);
    Route::post('sites/{site}/pages/{page}/publish', [PageController::class, 'publish']);
    Route::post('sites/{site}/pages/{page}/rollback', [PageController::class, 'rollback']);

    // Medya
    Route::post('media/upload', [MediaController::class, 'upload']);
    Route::get('media', [MediaController::class, 'index']);
    Route::delete('media/{media}', [MediaController::class, 'destroy']);
});


