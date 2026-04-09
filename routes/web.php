<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- 1. 具体的な名前を持つ公開ルート (最優先) ---
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// --- 2. 認証必須ルート ---
Route::middleware('auth')->group(function () {

    // 書籍管理 (Resource)
    Route::resource('books', BookController::class)->except(['index', 'show']);

    // ジャンル管理
    Route::resource('genres', GenreController::class)->except(['show']);

    // レビュー管理
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り機能 (Bladeに合わせて toggle に変更)
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // レビューいいね機能 (▼ここを修正: Bladeに合わせて reviews.like / toggle に変更)
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});

// --- 3. ワイルドカードを含む公開ルート (最後に定義) ---
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');

// 認証機能用ルート、RouteServiceProvider.phpでミドルウェアを設定している場合は必要ない
// require __DIR__.'/auth.php';
