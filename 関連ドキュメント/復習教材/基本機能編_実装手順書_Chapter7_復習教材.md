# Chapter 7: お気に入り機能

## 🎯 このセクションで学ぶこと

このセクションでは、ユーザーが書籍を「お気に入り」に登録・解除する機能を実装します。

- **多対多リレーションの操作**: `belongsToMany`リレーションで定義した中間テーブル（`favorites`）を操作する方法を学びます。
- **`syncWithoutDetaching`と`detach`**: 多対多リレーションでのデータ追加・削除の方法を学びます。
- **トグル操作**: 同じボタンで「お気に入り登録」と「お気に入り解除」を切り替えるUIパターンを学びます。

---

## 🧠 先輩エンジニアの思考プロセス：お気に入り機能の設計

お気に入り機能は、ユーザーと書籍の「多対多」の関係を表現します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| 1人のユーザーは複数の書籍をお気に入りにできる | `belongsToMany`リレーションを使用 | 中間テーブル（`favorites`）で関係を管理する。 |
| 1つの書籍は複数のユーザーにお気に入りされる | 同上 | 同上 |
| 同じ書籍を2回お気に入りに登録できない | `syncWithoutDetaching`を使用 | 重複登録を防ぎつつ、既存のお気に入りを保持する。 |

---

## 7.1. コントローラーの作成

```bash
sail artisan make:controller FavoriteController
```

---

## 7.2. FavoriteControllerの実装

```php
// app/Http/Controllers/FavoriteController.php

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
        return view('favorites.index', compact('books'));
    }
}
```

### 7.2.1. コードリーディング：`store`メソッド

```php
public function store(Book $book)
{
    Auth::user()->favoriteBooks()->syncWithoutDetaching($book->id);
    return back();
}
```

| 部分 | 説明 | 戻り値 |
|:---|:---|:---|
| `Auth::user()` | 現在ログインしているユーザーを取得します。 | `User` |
| `->favoriteBooks()` | `User`モデルの`favoriteBooks`リレーション（`belongsToMany`）を取得します。 | `BelongsToMany` |
| `->syncWithoutDetaching($book->id)` | 指定したIDを中間テーブルに追加します。既存のレコードは削除しません。 | `array` |
| `return back()` | 直前のページにリダイレクトします。 | `RedirectResponse` |

> **💡 ポイント: `syncWithoutDetaching` vs `attach` vs `sync`**
> 
> | メソッド | 動作 | 重複時の挙動 |
> |:---|:---|:---|
> | `attach($id)` | 中間テーブルにレコードを追加 | エラー（重複キー違反） |
> | `syncWithoutDetaching($id)` | 中間テーブルにレコードを追加 | 何もしない（安全） |
> | `sync([$id])` | 中間テーブルを指定したIDのみに置き換え | 他のレコードが削除される |
> 
> お気に入り登録には`syncWithoutDetaching`が最適です。

### 7.2.2. コードリーディング：`destroy`メソッド

```php
public function destroy(Book $book)
{
    Auth::user()->favoriteBooks()->detach($book->id);
    return back();
}
```

| 部分 | 説明 | 戻り値 |
|:---|:---|:---|
| `->detach($book->id)` | 中間テーブルから指定したIDのレコードを削除します。 | `int`（削除されたレコード数） |

### 7.2.3. コードリーディング：`index`メソッド

```php
public function index()
{
    $books = Auth::user()->favoriteBooks()->paginate(10);
    return view('favorites.index', compact('books'));
}
```

| 部分 | 説明 | 戻り値 |
|:---|:---|:---|
| `Auth::user()->favoriteBooks()` | ログインユーザーのお気に入り書籍を取得するクエリビルダーを返します。 | `BelongsToMany` |
| `->paginate(10)` | 10件ずつページネーションします。 | `LengthAwarePaginator` |

---

## 7.3. ルートの追加

```php
// routes/web.php

Route::middleware('auth')->group(function () {
    // ... 既存のルート

    // Favorite management
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/books/{book}/unfavorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
});
```

| ルート | HTTPメソッド | 説明 |
|:---|:---|:---|
| `/books/{book}/favorite` | POST | 書籍をお気に入りに登録 |
| `/books/{book}/unfavorite` | DELETE | 書籍をお気に入りから解除 |
| `/favorites` | GET | お気に入り一覧を表示 |

---

## 7.4. ビューの作成

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/favorites
touch resources/views/favorites/index.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 7.5. Bladeテンプレートでのお気に入りボタン実装例

書籍詳細ページや一覧ページで、お気に入りボタンを表示する際の実装例です。

```blade
{{-- お気に入りボタン --}}
@auth
    @if (auth()->user()->favoriteBooks->contains($book->id))
        {{-- お気に入り済み：解除ボタンを表示 --}}
        <form action="{{ route('favorites.destroy', $book) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-500">
                ★ お気に入り解除
            </button>
        </form>
    @else
        {{-- 未登録：登録ボタンを表示 --}}
        <form action="{{ route('favorites.store', $book) }}" method="POST">
            @csrf
            <button type="submit" class="text-gray-500">
                ☆ お気に入り登録
            </button>
        </form>
    @endif
@endauth
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `@auth` | ログインしている場合のみ表示します。 | 未ログインユーザーにはボタンを表示しません。 |
| `auth()->user()->favoriteBooks->contains($book->id)` | ログインユーザーのお気に入りに、この書籍が含まれているか確認します。 | `contains`メソッドはコレクションのメソッドです。 |
| `@method('DELETE')` | HTMLフォームでDELETEメソッドを擬似的に送信します。 | HTMLフォームはGETとPOSTしかサポートしないため、Laravelの仕組みで対応します。 |

これで、お気に入り機能の実装が完了しました。次のChapterでは、レビューいいね機能を実装していきます。
