<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 公開API v1。
| 読み取り系（index/show）は認証不要、書き込み系（store/update/destroy）は
| 応用機能で Laravel Sanctum によるトークン認証を必須化する。
|
*/

Route::prefix('v1')->group(function () {
    // 読み取り系（認証不要）
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    // 書き込み系（Sanctum 認証必須）
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
