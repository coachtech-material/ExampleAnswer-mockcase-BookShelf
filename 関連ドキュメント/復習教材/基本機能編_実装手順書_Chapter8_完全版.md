# Chapter 8: お気に入り・いいね機能

このChapterでは、ユーザーのエンゲージメントを高めるための「お気に入り」と「いいね」機能を実装します。どちらも多対多リレーションシップの応用であり、非同期通信（Ajax）を使ってUI/UXを向上させるのがポイントです。

## 8-1. お気に入り機能の実装

ユーザーが気に入った書籍を登録できる「お気に入り」機能を実装します。

### Step 1: Controllerの作成とルーティング

お気に入り登録・解除のロジックを担う`FavoriteController`を作成します。

```bash
sail artisan make:controller FavoriteController
```

**`routes/web.php`**
```php
use App\Http\Controllers\FavoriteController;

Route::middleware('auth')->group(function () {
    // ...
    Route::post('books/{book}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('books/{book}/unfavorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
});
```

### Step 2: Controllerの実装

**`app/Http/Controllers/FavoriteController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function store(Book $book)
    {
        Auth::user()->favoriteBooks()->attach($book->id);
        return back()->with('success', 'お気に入りに登録しました。');
    }

    public function destroy(Book $book)
    {
        Auth::user()->favoriteBooks()->detach($book->id);
        return back()->with('success', 'お気に入りを解除しました。');
    }
}
```
> **コード解説:**
> - `attach($book->id)`: `favorites`中間テーブルに、現在のユーザーIDと対象の書籍IDのレコードを追加します。
> - `detach($book->id)`: `favorites`中間テーブルから、対応するレコードを削除します。

### Step 3: Bladeの実装

書籍詳細ページにお気に入りボタンを設置します。

**`resources/views/books/show.blade.php`**
```blade
{{-- ... --}}
@auth
    @if (Auth::user()->favoriteBooks()->where('book_id', $book->id)->exists())
        <form action="{{ route('favorites.destroy', $book) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit">お気に入り解除</button>
        </form>
    @else
        <form action="{{ route('favorites.store', $book) }}" method="POST">
            @csrf
            <button type="submit">お気に入り登録</button>
        </form>
    @endif
@endauth
```

## 8-2. いいね機能の実装

レビューに対する「いいね」機能も、お気に入りと同様の実装方針で作成します。

### Step 1: Controllerの作成とルーティング

```bash
sail artisan make:controller ReviewLikeController
```

**`routes/web.php`**
```php
use App\Http\Controllers\ReviewLikeController;

Route::middleware('auth')->group(function () {
    // ...
    Route::post('reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('review.like');
    Route::delete('reviews/{review}/unlike', [ReviewLikeController::class, 'destroy'])->name('review.unlike');
});
```

### Step 2: Controllerの実装

**`app/Http/Controllers/ReviewLikeController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    public function store(Review $review)
    {
        Auth::user()->likedReviews()->attach($review->id);
        return back();
    }

    public function destroy(Review $review)
    {
        Auth::user()->likedReviews()->detach($review->id);
        return back();
    }
}
```

### Step 3: Bladeの実装

レビュー一覧にいいねボタンといいね数を表示します。

**`resources/views/books/show.blade.php` (レビュー一覧部分)**
```blade
@forelse ($book->reviews as $review)
    <div class="border-t py-4">
        {{-- ... --}}
        <div class="flex items-center">
            @if (Auth::user()->likedReviews()->where('review_id', $review->id)->exists())
                <form action="{{ route('review.unlike', $review) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit">いいね解除</button>
                </form>
            @else
                <form action="{{ route('review.like', $review) }}" method="POST">
                    @csrf
                    <button type="submit">いいね</button>
                </form>
            @endif
            <span class="ml-2">{{ $review->likedByUsers->count() }}</span>
        </div>
    </div>
@empty
    <p>まだレビューはありません。</p>
@endforelse
```

> **思考プロセス (UI/UXの改善):**
> 現状の実装では、ボタンを押すたびにページ全体がリロードされてしまいます。これをJavaScript（Ajax）を使って非同期処理にすることで、ページをリロードすることなく「お気に入り」や「いいね」の状態をサーバーに送信し、ボタンの表示だけを動的に切り替えることができます。これにより、ユーザー体験が大幅に向上します。発展課題としてぜひ挑戦してみてください。

---

これで、ユーザーの参加を促すインタラクティブな機能が実装できました。次のChapterでは、検索機能を実装します。
