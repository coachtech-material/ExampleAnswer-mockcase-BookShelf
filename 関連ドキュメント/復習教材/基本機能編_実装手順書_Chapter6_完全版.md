# Chapter 6: お気に入り & いいね機能

このChapterでは、書籍をお気に入り登録する機能と、レビューに「いいね」する機能を実装します。どちらも多対多のリレーションシップを利用する典型的な例です。

## 6-1. お気に入り機能の実装

ユーザーが気に入った書籍を保存しておけるように、お気に入り機能を実装します。

### Step 1: Controllerの作成

お気に入りの登録と解除を処理する`FavoriteController`を作成します。

```bash
sail artisan make:controller FavoriteController
```

### Step 2: ルーティングの設定

`routes/web.php`に、お気に入りの登録・解除を行うためのルートを追加します。

**`routes/web.php`**
```php
use App\Http\Controllers\FavoriteController;

Route::middleware("auth")->group(function () {
    // ... 他のルート

    // お気に入り登録
    Route::post("books/{book}/favorite", [FavoriteController::class, "store")->name("favorites.store");
    // お気に入り解除
    Route::delete("books/{book}/unfavorite", [FavoriteController::class, "destroy"])->name("favorites.destroy");
    // お気に入り一覧
    Route::get("favorites", [FavoriteController::class, "index"])->name("favorites.index");
});
```

**思考プロセス:**
- `POST /books/{book}/favorite`: 特定の書籍をお気に入りに追加するアクション。RESTの考え方に基づき、リソース（お気に入り）の作成なのでPOSTメソッドを使用します。
- `DELETE /books/{book}/unfavorite`: お気に入りを解除するアクション。リソースの削除なのでDELETEメソッドを使用します。

### Step 3: Controllerの実装

`FavoriteController`に、登録（`store`）、解除（`destroy`）、一覧表示（`index`）のロジックを実装します。

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
        Auth::user()->favorites()->attach($book);
        return back()->with("success", "お気に入りに追加しました。");
    }

    public function destroy(Book $book)
    {
        Auth::user()->favorites()->detach($book);
        return back()->with("success", "お気に入りを解除しました。");
    }

    public function index()
    {
        $favoriteBooks = Auth::user()->favorites()->with("genres")->paginate(10);
        return view("favorites.index", compact("favoriteBooks"));
    }
}
```

**コードリーディング:**
- `Auth::user()->favorites()`: `User`モデルに定義された`favorites`リレーション（多対多）を呼び出しています。
- `attach($book)`: 多対多リレーションの中間テーブル（`favorites`テーブル）に、現在のユーザーIDと指定された書籍IDのレコードを追加します。
- `detach($book)`: 中間テーブルからレコードを削除します。

### Step 4: Bladeテンプレートの編集

書籍詳細ページにお気に入りボタンを追加し、お気に入り一覧ページを作成します。

**`resources/views/books/show.blade.php`** (ボタン部分)
```blade
@auth
    @if (Auth::user()->favorites()->where("book_id", $book->id)->exists())
        {{-- お気に入り解除ボタン --}}
        <form action="{{ route("favorites.destroy", $book) }}" method="POST">
            @csrf
            @method("DELETE")
            <button type="submit">お気に入り解除</button>
        </form>
    @else
        {{-- お気に入り登録ボタン --}}
        <form action="{{ route("favorites.store", $book) }}" method="POST">
            @csrf
            <button type="submit">お気に入りに追加</button>
        </form>
    @endif
@endauth
```

**`resources/views/favorites/index.blade.php`** (新規作成)
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            お気に入り一覧
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @forelse ($favoriteBooks as $book)
                        <div class="mb-4">
                            <a href="{{ route("books.show", $book) }}">{{ $book->title }}</a>
                        </div>
                    @empty
                        <p>お気に入りに登録されている書籍はありません。</p>
                    @endforelse
                    {{ $favoriteBooks->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

## 6-2. レビューへの「いいね」機能

次に、他のユーザーのレビューに「いいね」を付ける機能を実装します。これも基本的な構造はお気に入り機能と同じです。

### Step 1: Controllerの作成

```bash
sail artisan make:controller ReviewLikeController
```

### Step 2: ルーティングの設定

**`routes/web.php`**
```php
use App\Http\Controllers\ReviewLikeController;

Route::middleware("auth")->group(function () {
    // ... 他のルート

    // いいね登録
    Route::post("reviews/{review}/like", [ReviewLikeController::class, "store"])->name("likes.store");
    // いいね解除
    Route::delete("reviews/{review}/unlike", [ReviewLikeController::class, "destroy"])->name("likes.destroy");
});
```

### Step 3: Controllerの実装

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
        Auth::user()->reviewLikes()->attach($review);
        return back();
    }

    public function destroy(Review $review)
    {
        Auth::user()->reviewLikes()->detach($review);
        return back();
    }
}
```

### Step 4: Bladeテンプレートの編集

レビュー一覧に「いいね」ボタンを追加します。

**`resources/views/books/show.blade.php`** (レビュー一覧部分)
```blade
@foreach ($book->reviews as $review)
    <div class="border-t py-4">
        {{-- ... レビュー内容 ... --}}

        {{-- いいね機能 --}}
        @auth
            @if ($review->likedBy(Auth::user()))
                <form action="{{ route("likes.destroy", $review) }}" method="POST">
                    @csrf
                    @method("DELETE")
                    <button type="submit">いいね解除 ({{ $review->likes->count() }})</button>
                </form>
            @else
                <form action="{{ route("likes.store", $review) }}" method="POST">
                    @csrf
                    <button type="submit">いいね ({{ $review->likes->count() }})</button>
                </form>
            @endif
        @endauth
        @guest
            <span>いいね ({{ $review->likes->count() }})</span>
        @endguest
    </div>
@endforeach
```

### Step 5: `Review`モデルにヘルパーメソッドを追加 (任意)

Bladeでの判定ロジックをシンプルにするため、`Review`モデルにヘルパーメソッドを追加すると便利です。

**`app/Models/Review.php`**
```php
// ...
class Review extends Model
{
    // ... 既存のコード

    public function likedBy(User $user): bool
    {
        return $this->likes()->where("user_id", $user->id)->exists();
    }

    // likesリレーションを定義
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, "review_likes");
    }
}
```

**コードリーディング:**
- `likedBy(User $user)`: 引数で渡されたユーザーが、このレビューに「いいね」しているかどうかを真偽値で返します。これにより、Blade側の`@if`文が `Auth::user()->reviewLikes()->where(...)` のような長い記述にならずに済みます。

## 6-3. 動作確認

1.  書籍詳細ページで「お気に入りに追加」ボタンを押し、成功メッセージが表示され、ボタンが「お気に入り解除」に変わることを確認します。
2.  ナビゲーションバーの「お気に入り」リンクから一覧ページにアクセスし、登録した書籍が表示されていることを確認します。
3.  「お気に入り解除」ボタンを押し、一覧から消えることを確認します。
4.  書籍詳細ページで、他人のレビューの「いいね」ボタンを押し、カウントが増え、ボタンが「いいね解除」に変わることを確認します。
5.  「いいね解除」ボタンを押し、カウントが減ることを確認します。

---

これで、ユーザーエンゲージメントを高めるための基本的な機能が実装できました。次のChapterでは、管理者向けの機能としてジャンル管理を実装します。
