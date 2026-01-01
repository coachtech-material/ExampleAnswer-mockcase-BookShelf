# Chapter 13: ルート定義の完全版

このChapterでは、これまで実装してきた全機能のルート定義をまとめます。

---

## 13.1. 最終的な `routes/web.php`

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/search', [BookController::class, 'search'])->name('books.search');
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Genre management
    Route::resource('genres', GenreController::class)->except(['show']);

    // Book management
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // Review management
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Favorite management
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/books/{book}/unfavorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // Review Like management
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('likes.store');
    Route::delete('/reviews/{review}/unlike', [ReviewLikeController::class, 'destroy'])->name('likes.destroy');
});

// Public book show route
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// 認証ルート
require __DIR__.'/auth.php';
```

---

## 13.2. ルート構成の解説

### パブリックルート（認証不要）
- `/` - トップページ（書籍一覧）
- `/books` - 書籍一覧
- `/books/search` - 書籍検索
- `/books/{book}` - 書籍詳細
- `/genres/{genre}` - ジャンル別書籍一覧
- `/ranking` - ランキング

### 認証必須ルート
- **ジャンル管理**: 一覧・作成・編集・削除
- **書籍管理**: 作成・編集・削除
- **レビュー管理**: 投稿・編集・削除
- **お気に入り**: 追加・解除・一覧
- **いいね**: 追加・解除

これで、全機能のルート定義が完了しました。
