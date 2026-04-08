<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReportController;

use Illuminate\Support\Facades\Route;

// トップページ（書籍一覧）
Route::get('/', [BookController::class, 'index'])->name('home');

// 書籍関連（認証不要）
Route::get('/books', [BookController::class, 'index'])->name('books.index');


// ランキング（認証不要）
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// 認証が必要なルート
Route::middleware('auth')->group(function () {
    // ジャンル管理
    Route::resource('genres', GenreController::class)->except(['show']);

    // 書籍管理（createは{book}より先に定義）
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.searchByIsbn');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // レビュー管理
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // いいね
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');


    // マイ読書レポート
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

// 書籍詳細（認証不要、{book}パラメータを含むため最後に定義）
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// ジャンル別書籍一覧（認証不要）
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');

require __DIR__.'/auth.php';
