# Chapter 7: お気に入り機能

このChapterでは、ユーザーが書籍をお気に入りに追加・解除できる機能を実装します。

---

## 7.1. コントローラーの作成

```bash
sail artisan make:controller FavoriteController
```

---

## 7.2. FavoriteControllerの実装

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function store(Book $book)
    {
        Auth::user()->favoriteBooks()->syncWithoutDetaching($book->id);
        return back();
    }

    public function destroy(Book $book)
    {
        Auth::user()->favoriteBooks()->detach($book->id);
        return back();
    }

    public function index()
    {
        $books = Auth::user()->favoriteBooks()->paginate(10);
        return view("favorites.index", compact("books"));
    }
}
```

---

## 7.3. ルートの追加

`routes/web.php` の認証必須ルートに以下を追加します。

```php
// Favorite management
Route::post('/books/{book}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
Route::delete('/books/{book}/unfavorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
```

---

## 7.4. 動作確認

1. 書籍詳細ページでお気に入りボタンをクリックし、お気に入りに追加できることを確認
2. お気に入り一覧ページ (`/favorites`) でお気に入りに追加した書籍が表示されることを確認
3. お気に入り解除ボタンをクリックし、お気に入りから削除できることを確認

これで、お気に入り機能の実装が完了しました。
