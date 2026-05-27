# Chapter 08: ワンタッチ切り替え - お気に入り機能を実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、ユーザーが書籍を「お気に入り」に登録・解除する機能を実装します。多対多リレーションの `toggle()` メソッドを使い、1つのアクションで登録と解除を切り替える効率的な実装パターンを学びます。

- **多対多リレーションの操作**: `belongsToMany` リレーションで定義した中間テーブル（`favorites`）を操作する方法を学びます
- **`toggle` メソッド**: 1つのアクションで登録と解除を切り替える効率的な実装方法を学びます
- **リレーションを利用したデータ取得**: ログインユーザーに紐づくお気に入り書籍一覧を取得する方法を学びます

## 1. はじめに 📖

### お気に入り機能のパターン

お気に入り機能は、ユーザーと書籍の「多対多」の関係性を扱う典型的な例です。1人のユーザーは複数の本をお気に入りにでき、1冊の本は複数のユーザーからお気に入りにされます。

このような機能では、「登録」と「解除」を別々のアクション（`store` / `destroy`）として実装することもできますが、UIの観点からは1つのボタンで切り替える「トグル」方式が自然です。Laravelの `belongsToMany` リレーションには、まさにこの用途のための `toggle()` メソッドが用意されています。

## 2. 要件の確認 📋

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| お気に入り一覧 | GET | `/favorites` | FavoriteController@index | 必要 |
| お気に入りトグル | POST | `/books/{book}/favorites` | FavoriteController@toggle | 必要 |

## 3. 先輩エンジニアの思考プロセス 💭

### Point 1: `toggle()` で登録・解除を一元化する

Laravelの `belongsToMany` リレーションには、中間テーブルのレコードを自動で付け外ししてくれる `toggle()` メソッドがあります。中間テーブルに指定したIDが存在すれば削除し、存在しなければ追加するという処理を1行で実現します。

```php
// ❌ 登録と解除を別メソッドで実装（冗長）
public function store(Book $book) { ... }
public function destroy(Book $book) { ... }

// ✅ toggle() で1メソッドに集約
public function toggle(Book $book) {
    Auth::user()->favoriteBooks()->toggle($book->id);
}
```

### Point 2: `back()` でシームレスなUXを実現する

お気に入りボタンは書籍詳細ページや書籍一覧ページなど、複数の場所から押される可能性があります。`return back();` を使うことで、どのページから操作しても元のページに自然に戻れます。

## 4. 実装 🚀

`FavoriteController` は Chapter 06 の「ルート定義とコントローラーの準備」で既に作成済みです。中身を実装していきましょう。

### `app/Http/Controllers/FavoriteController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧を表示
     */
    public function index(): View
    {
        $books = Auth::user()->favoriteBooks()->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りを追加/削除（トグル）
     */
    public function toggle(Book $book): RedirectResponse
    {
        Auth::user()->favoriteBooks()->toggle($book->id);

        return back();
    }
}
```

## 5. コードの詳細解説 🔍

### `toggle` メソッド

| コード / 構文 | 解説 |
|:---|:---|
| `toggle(Book $book): RedirectResponse` | ルートモデルバインディングで `Book` モデルを受け取り、リダイレクトレスポンスを返します。 |
| `Auth::user()` | ログインしているユーザーの `User` モデルインスタンスを取得します。`Auth` ファサードを経由してセッションから認証済みユーザーを取得しています。 |
| `->favoriteBooks()` | `User` モデルに定義した `favoriteBooks` リレーション（`belongsToMany`）を呼び出します。`favorites` 中間テーブルを操作するためのクエリビルダが返されます。 |
| `->toggle($book->id)` | `belongsToMany` リレーションの `toggle` メソッドを実行します。中間テーブルに `(user_id, book_id)` の組み合わせが存在すれば削除し、存在しなければ追加します。 |
| `return back();` | ユーザーを直前のページにリダイレクトします。`back()` はLaravelのヘルパー関数で、セッションに保存されている直前のURLにリダイレクトします。 |

### `index` メソッド

| コード / 構文 | 解説 |
|:---|:---|
| `index(): View` | お気に入り一覧ページを表示するメソッド。`Route::get('/favorites', ...)` に対応します。 |
| `Auth::user()->favoriteBooks()->paginate(10)` | ログインユーザーがお気に入りに登録した書籍を10件ずつ取得します。`favoriteBooks()` で中間テーブルを経由して `books` テーブルからデータを取得し、`paginate(10)` でページネーションを適用します。 |
| `return view('favorites.index', compact('books'))` | `resources/views/favorites/index.blade.php` ビューを返し、`$books` 変数を渡します。 |

## 6. この実装にたどり着くための調べ方 🧐

| 疑問 | プロンプト例 |
|:---|:---|
| `attach()` / `detach()` / `toggle()` の違い | 「Laravel の `belongsToMany` リレーションで提供される `attach`, `detach`, `toggle`, `sync` の挙動の違いを、お気に入り機能を例にコード付きで教えてください。」 |
| お気に入り中間テーブルの一意性 | 「Laravel で『1ユーザー1書籍につき1お気に入り』を担保したい場合、マイグレーションでの複合 unique 制約と Eloquent 側での toggle 利用は併用すべきですか？それぞれの役割を説明してください。」 |
| 認証ガードと `back()` の挙動 | 「Laravel で `Route::middleware('auth')->group(...)` 内に `back()` するコントローラを置くとき、未認証ユーザーが直接エンドポイントを叩いたらどうなりますか？」 |

---

## 7. 動作確認 ✅

| 確認項目 | 確認方法 |
|:---|:---|
| お気に入り登録 | 書籍詳細画面でお気に入りボタンを押し、星アイコンが「登録済」表示に切り替わること。`favorites` テーブルに `(user_id, book_id)` の行が追加されていること |
| お気に入り解除 | 同じ書籍でもう一度ボタンを押すと「未登録」に戻り、`favorites` テーブルから該当行が削除されること |
| お気に入り一覧 | `/favorites` にアクセスすると、ログインユーザーがお気に入りした書籍が一覧表示される（ページネーション 10 件） |
| 未認証時のリダイレクト | ログアウト状態で `/favorites` にアクセスするとログイン画面にリダイレクトされること |
| 他ユーザーのお気に入りが混ざらない | 別ユーザーでログインし直したとき、お気に入り一覧に前のユーザーの書籍が表示されないこと |

---

## 8. まとめ ✨

このチャプターでは、お気に入り機能を実装しました。

- **`toggle()` メソッドの活用**: `belongsToMany` リレーションの `toggle()` で、お気に入りの登録・解除を1行で実現しました
- **`back()` によるシームレスなUX**: お気に入りボタンをどのページから押しても、元のページに自然に戻れるようにしました
- **リレーション経由のデータ取得**: `Auth::user()->favoriteBooks()->paginate(10)` で、ログインユーザーのお気に入り書籍一覧を効率的に取得しました

次の Chapter 09 では、レビューに対する**いいね機能**を実装します。お気に入り機能と同じ `toggle` パターンを、別のコンテキストで再実践します。
