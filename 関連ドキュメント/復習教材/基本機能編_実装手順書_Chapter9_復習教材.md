# Chapter 9: レビューいいね機能

## 🎯 このセクションで学ぶこと

このセクションでは、レビューに対する「いいね」機能を実装します。Chapter 8で学んだお気に入り機能と同様の「多対多リレーション」のパターンを、別のコンテキストで再度実践します。

- **パターンの再利用**: お気に入り機能と同じ設計パターンを、レビューいいね機能に適用します。
- **学習の定着**: 同じパターンを繰り返し実装することで、理解を深めます。

---

## 🧠 先輩エンジニアの思考プロセス：パターンの認識

お気に入り機能とレビューいいね機能は、技術的には全く同じパターンです。

| 機能 | 主体 | 対象 | 中間テーブル | リレーション名 |
|:---|:---|:---|:---|:---|
| お気に入り | User | Book | favorites | favoriteBooks |
| いいね | User | Review | review_likes | likedReviews |

このように、**「ユーザーが何かをお気に入り/いいねする」**というパターンは、多くのWebアプリケーションで共通して使われます。一度理解すれば、様々な場面で応用できます。

---

## 9.1. ReviewLikeController.php の実装

`ReviewLikeController`はChapter 6の「ルート定義とコントローラーの準備」で既に作成済みです。早速、中身を実装していきましょう。

`app/Http/Controllers/ReviewLikeController.php`を開き、以下の内容を記述してください。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    // Bladeの要求に合わせて toggle メソッドに変更
    public function toggle(Review $review)
    {
        // ユーザーがすでにいいねしていれば解除、していなければ登録を自動で行う
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }
}
```

> **📝 ルート定義について**
> レビューいいね機能のルート定義も、Chapter 6で既に`toggle`メソッドを使用する形で定義済みです。そのため、`routes/web.php`を修正する必要はありません。

これで、レビューいいね機能の実装は完了です。次のChapterでは、ランキング機能を実装していきます。
