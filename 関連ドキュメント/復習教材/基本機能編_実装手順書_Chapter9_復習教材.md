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

## 8.1. コントローラーの作成

```bash
sail artisan make:controller ReviewLikeController
```

---

## 8.2. ReviewLikeControllerの実装

```php
// app/Http/Controllers/ReviewLikeController.php

<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    public function store(Review $review)
    {
        Auth::user()->likedReviews()->syncWithoutDetaching($review->id);

        return back();
    }

    public function destroy(Review $review)
    {
        Auth::user()->likedReviews()->detach($review->id);

        return back();
    }
}
```

### 8.2.1. コードリーディング：お気に入り機能との比較

| お気に入り機能 | レビューいいね機能 | 説明 |
|:---|:---|:---|
| `Auth::user()->favoriteBooks()` | `Auth::user()->likedReviews()` | リレーション名が異なるだけ |
| `->syncWithoutDetaching($book->id)` | `->syncWithoutDetaching($review->id)` | 対象のIDが異なるだけ |
| `->detach($book->id)` | `->detach($review->id)` | 対象のIDが異なるだけ |

> **💡 ポイント: DRY原則とパターン認識**
> 同じパターンを認識できるようになると、新しい機能を実装する際に「これは〇〇と同じパターンだ」と気づけるようになります。これにより、実装スピードが上がり、バグも減ります。

---

## 8.3. ルートの追加

```php
// routes/web.php

use App\Http\Controllers\ReviewLikeController;

// ... (他のルート)

Route::middleware(\'auth\')->group(function () {
    // ... 既存のルート

    // Review Like management
    Route::post(\'/reviews/{review}/like\', [ReviewLikeController::class, \'store\'])->name(\'likes.store\');
    Route::delete(\'/reviews/{review}/unlike\', [ReviewLikeController::class, \'destroy\'])->name(\'likes.destroy\');
});
```

| ルート | HTTPメソッド | 説明 |
|:---|:---|:---|
| `/reviews/{review}/like` | POST | レビューにいいねを追加 |
| `/reviews/{review}/unlike` | DELETE | レビューのいいねを解除 |

---

## 8.4. Bladeテンプレートでのいいねボタン実装例

レビュー表示部分で、いいねボタンを表示する際の実装例です。

```blade
{{-- いいねボタン --}}
@auth
    @if (Auth::user()->isLiking($review))
        <form action="{{ route(\'likes.destroy\', $review) }}" method="POST">
            @csrf
            @method(\'DELETE\')
            <button type="submit">いいね解除</button>
        </form>
    @else
        <form action="{{ route(\'likes.store\', $review) }}" method="POST">
            @csrf
            <button type="submit">いいね</button>
        </form>
    @endif
@endauth
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `Auth::user()->isLiking($review)` | ログインユーザーがいいね済みか確認します。 | このメソッドはUserモデルに独自実装する必要があります。 |

---

## 8.5. Reviewモデルへのリレーション追加

いいね数を表示するために、`Review`モデルに`likedByUsers`リレーションを追加します。

```php
// app/Models/Review.php

// ... (既存のコード)

public function likedByUsers()
{
    return $this->belongsToMany(User::class, \'review_likes\');
}
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `belongsToMany(User::class, \'review_likes\')` | `review_likes`中間テーブルを通じて、このレビューにいいねしたユーザーを取得します。 | `User`モデルの`likedReviews`リレーションの「逆方向」です。 |

> **🧠 先輩エンジニアの思考プロセス**
> 多対多リレーションは、両方向から定義することで、どちらのモデルからでも関連データにアクセスできるようになります。
> - `$user->likedReviews`: ユーザーがいいねしたレビュー一覧
> - `$review->likedByUsers`: レビューにいいねしたユーザー一覧

これで、レビューいいね機能の実装が完了しました。次のChapterでは、ランキング機能を実装していきます。
