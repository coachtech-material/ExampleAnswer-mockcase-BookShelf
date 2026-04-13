# Chapter 09: パターンの反復 - レビューいいね機能を実装する

## 🎯 このChapterの目標

このチャプターでは、レビューに対する「いいね」機能を実装します。Chapter 08 で学んだお気に入り機能と同じ「多対多リレーション + toggle」パターンを、別のコンテキストで再実践します。

---

## 📖 背景知識

### 同じパターンに気づく力

| 機能 | 主体 (User) | 対象 (Target) | 中間テーブル | リレーション名 |
|:---|:---|:---|:---|:---|
| お気に入り | User | Book | favorites | favoriteBooks |
| いいね | User | Review | review_likes | likedReviews |

この構造は、**「ユーザーが何かをブックマークする」**という非常に汎用的なパターンです。

---

## 📋 要件の確認

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| いいねトグル | POST | `/reviews/{review}/like` | ReviewLikeController@toggle | 必要 |

---

## 🚀 コードの実装

### `app/Http/Controllers/ReviewLikeController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    public function toggle(Review $review)
    {
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }
}
```

---

## 🔍 コードリーディング

### お気に入り機能との対比

| 要素 | お気に入り（Chapter 08） | いいね（Chapter 09） |
|:---|:---|:---|
| コントローラー | `FavoriteController` | `ReviewLikeController` |
| 対象モデル | `Book` | `Review` |
| リレーション名 | `favoriteBooks()` | `likedReviews()` |
| 中間テーブル | `favorites` | `review_likes` |
| 処理 | `toggle($book->id)` | `toggle($review->id)` |

---

## ✨ このChapterのまとめ

- **パターンの再実践**: Chapter 08 と同じ `toggle()` パターンをレビューいいねに適用
- **抽象化の視点**: 主体・対象・中間テーブルを入れ替えるだけで同じ構造が再利用可能

次の Chapter 10 では、レビューの平均評価に基づいて書籍を**ランキング表示**する機能を実装します。
