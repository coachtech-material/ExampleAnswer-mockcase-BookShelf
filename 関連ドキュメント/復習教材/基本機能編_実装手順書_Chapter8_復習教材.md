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
        return view("favorites.index", compact("books"));
    }
}
```

#### 📖 コードリーディング：`toggle`メソッド

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function toggle(Book $book)` | `toggle`という名前の公開メソッドを定義。引数で`Book`モデルを受け取る。 | `(Book $book)`は「ルートモデルバインディング」というLaravelの機能。URLの`{book}`の部分に対応するIDを持つ`Book`モデルのインスタンスが自動的にDI（依存性注入）される。 |
| `Auth::user()` | ログインしているユーザーの`User`モデルインスタンスを取得する。 | `Auth`ファサード（Laravelの便利な機能への入り口）を経由して、セッション情報から認証済みユーザーを取得している。 |
| `->favoriteBooks()` | `User`モデルに定義した`favoriteBooks`リレーション（`belongsToMany`）を取得する。 | これにより、`favorites`中間テーブルを操作するためのクエリビルダが返される。 |
| `->toggle($book->id)` | `belongsToMany`リレーションの`toggle`メソッドを実行。`$book->id`を中間テーブルに追加、または削除する。 | `toggle`は「切り替える」という意味。中間テーブルに`($user->id, $book->id)`の組み合わせが存在すれば削除し、存在しなければ追加する、という処理を自動で行ってくれる非常に便利なメソッド。 |
| `return back();` | ユーザーを直前のページにリダイレクトさせる。 | お気に入りボタンを押した元のページ（書籍詳細ページなど）にユーザーを戻すことで、シームレスなUXを提供する。`back()`はLaravelが提供するヘルパー関数。 |

#### 📖 コードリーディング：`index`メソッド

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function index()` | お気に入り一覧ページを表示するためのメソッド。 | `Route::get("/favorites", ...)`に対応する。 |
| `$books = Auth::user()->favoriteBooks()->paginate(10);` | ログインユーザーがお気に入りに登録した書籍を10件ずつ取得する。 | `favoriteBooks()`で中間テーブルを経由して`books`テーブルからデータを取得し、`paginate(10)`でページネーションを適用している。 |
| `return view("favorites.index", compact("books"));` | `resources/views/favorites/index.blade.php`ビューを返す。その際に`$books`変数をビューに渡す。 | `compact("books")`はPHPの関数で、`["books" => $books]`という連想配列を作成するのと同じ意味。ビュー側では`$books`という変数名でデータにアクセスできる。 |

---

これで、お気に入り機能の実装は完了です。次のChapterでは、レビューに対する「いいね」機能を実装していきます。
