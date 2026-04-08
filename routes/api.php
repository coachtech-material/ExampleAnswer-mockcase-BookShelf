<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 公開API v1。Basic 段階では認証なしの CRUD として実装する。
|
*/

Route::prefix('v1')->group(function () {
    Route::apiResource('books', BookController::class);
});
