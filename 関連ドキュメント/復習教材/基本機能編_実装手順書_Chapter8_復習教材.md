## Chapter 8: お気に入り機能

### 🎯 このセクションで学ぶこと

このセクションでは、ユーザーが書籍を「お気に入り」に登録・解除する機能を実装します。

- **多対多リレーションの操作**: `belongsToMany`リレーションで定義した中間テーブル（`favorites`）を操作する方法を学びます。
- **`toggle`メソッド**: 1つのアクションで登録と解除を切り替える効率的な実装方法を学びます。
- **リレーションを利用したデータ取得**: ログインユーザーに紐づくお気に入り書籍一覧を取得する方法を学びます。

---

### 🧠 先輩エンジニアの思考プロセス：お気に入り機能の設計

お気に入り機能は、ユーザーと書籍の「多対多」の関係性を扱う典型的な例です。これをどうスマートに実装するか、設計のポイントを見ていきましょう。

| 設計・実装のポイント | 思考プロセス |
|:---|:---|
| **1. DB設計** | 1人のユーザーは複数の本をお気に入りにでき、1冊の本は複数のユーザーからお気に入りにされる。これは典型的な「多対多」の関係。→ `users`と`books`をつなぐ中間テーブル`favorites`（`user_id`, `book_id`）を用意する。（Chapter 2で実装済み） |
| **2. モデルリレーション** | Userモデルからお気に入りのBookモデルを、Bookモデルからお気に入りにしているUserモデルを取得できるようにしたい。→ `User`モデルと`Book`モデルに`belongsToMany`リレーションを定義する。（Chapter 3で実装済み） |
| **3. コントローラーの責務** | お気に入り登録と解除は、UI上は1つのボタンで行われることが多い。「登録/解除」という状態を切り替える（トグルする）操作が求められる。→ `store`（登録）と`destroy`（削除）の2つのメソッドを用意するのではなく、**`toggle`という1つのメソッドで両方の処理を扱う**のが効率的でスマートだ。 |
| **4. `toggle`メソッドの実装** | Laravelの`belongsToMany`リレーションには、中間テーブルのレコードを自動で付け外ししてくれる`toggle()`という便利なメソッドが存在する。これを使わない手はない。→ `Auth::user()->favoriteBooks()->toggle($book->id);` の一行で、登録・解除のロジックが完結する。 |

---

### 8.1. FavoriteControllerの実装

`FavoriteController`はChapter 6の「ルート定義とコントローラーの準備」で既に作成済みです。早速、中身を実装していきましょう。

`app/Http/Controllers/FavoriteController.php`を以下のように修正します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * 書籍をお気に入り登録・解除する
     */
    public function toggle(Book $book)
    {
        // ログインしているユーザーのお気に入り書籍リレーションに対して、toggleメソッドを実行
        // toggleメソッドは、中間テーブルに指定したIDが存在すれば削除し、存在しなければ追加する
        Auth::user()->favoriteBooks()->toggle($book->id);

        // 直前のページにリダイレクトする
        return back();
    }

    /**
     * ログインユーザーのお気に入り書籍一覧を表示する
     */
    public function index()
    {
        // ログインしているユーザーのお気に入り書籍をページネーションで取得
        $books = Auth::user()->favoriteBooks()->paginate(10);

        // favorites.indexビューを返す
        return view('favorites.index', compact('books'));
    }
}
```

> **📝 注意**
> 完全手順書.mdでは、`store`と`destroy`の代わりに`toggle`メソッドを実装しています。これは、UI/UXの観点から、1つのボタンで登録と解除を切り替える方が一般的であるためです。しかし、Chapter 6で定義したルートは`favorites.store`と`favorites.destroy`のままで問題ありません。Blade側で、お気に入り状態に応じてどちらのルートにフォームを送信するかを切り替えることで、結果的に`toggle`と同じような動作を実現できるためです。
> ここでは、よりシンプルで推奨される`toggle`メソッドの実装を採用し、ルート定義は後ほど`toggle`を呼び出すように修正します。

---

### 8.2. ルート定義の修正

Chapter 6で仮置きしたルート定義を、`toggle`メソッドを使用するように修正します。

`routes/web.php`を開き、お気に入り関連のルートを以下のように変更してください。

**変更前**
```php
// routes/web.php

// ...
Route::middleware('auth')->group(function () {
    // ...
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/books/{book}/unfavorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    // ...
});
```

**変更後**
```php
// routes/web.php

// ...
Route::middleware('auth')->group(function () {
    // ...
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle'); // 変更
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    // ...
});
```

`store`と`destroy`の2つのルートを、`toggle`を呼び出す1つの`POST`ルートにまとめました。これにより、Blade側からの呼び出しもシンプルになります。

> **💡 ポイント**
> `name()`でルートに名前を付けておくことで、Blade側で`route('favorites.toggle', $book)`のように簡単にURLを生成できます。URLの構造が変わっても、Bladeファイルを修正する必要がないため、非常に便利です。

これで、お気に入り機能の実装は完了です。次のChapterでは、レビューに対する「いいね」機能を実装していきます。
