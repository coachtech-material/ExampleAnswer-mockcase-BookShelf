# Chapter 08: ワンタッチ切り替え - お気に入り機能を実装する

## 🎯 このChapterの目標

このチャプターでは、ユーザーが書籍を「お気に入り」に登録・解除する機能を実装します。多対多リレーションの `toggle()` メソッドを使い、1つのアクションで登録と解除を切り替える効率的な実装パターンを学びます。

---

## 📖 背景知識

お気に入り機能は、ユーザーと書籍の「多対多」の関係性を扱う典型的な例です。UIの観点からは1つのボタンで切り替える「トグル」方式が自然です。

---

## 📋 要件の確認

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| お気に入り一覧 | GET | `/favorites` | FavoriteController@index | 必要 |
| お気に入りトグル | POST | `/books/{book}/favorites` | FavoriteController@toggle | 必要 |

---

## 💭 なぜこう作るのか？

### Point 1: `toggle()` で登録・解除を一元化する

```php
// toggle() で1メソッドに集約
Auth::user()->favoriteBooks()->toggle($book->id);
```

### Point 2: `back()` でシームレスなUXを実現する

お気に入りボタンは複数のページから押される可能性があるため、`return back();` で元のページに戻します。

---

## 🚀 コードの実装

### `app/Http/Controllers/FavoriteController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function toggle(Book $book)
    {
        Auth::user()->favoriteBooks()->toggle($book->id);

        return back();
    }

    public function index()
    {
        $books = Auth::user()->favoriteBooks()->paginate(10);

        return view('favorites.index', compact('books'));
    }
}
```

---

## 🔍 コードリーディング

| コード | 解説 |
|:---|:---|
| `Auth::user()->favoriteBooks()` | `User` モデルの `favoriteBooks` リレーションを呼び出し。 |
| `->toggle($book->id)` | 中間テーブルにレコードがあれば削除、なければ追加。 |
| `return back()` | 直前のページにリダイレクト。 |
| `->paginate(10)` | お気に入り一覧を10件ずつページネーション。 |

---

## ✨ このChapterのまとめ

- **`toggle()` メソッド**: 1行で登録・解除を切り替え
- **`back()` リダイレクト**: どのページからでも自然に戻れるUX
- **リレーション経由のデータ取得**: `Auth::user()->favoriteBooks()->paginate(10)`

次の Chapter 09 では、レビューに対する**いいね機能**を実装します。
