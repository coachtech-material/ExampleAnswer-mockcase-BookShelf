# Chapter 8: レビューいいね機能

## 🎯 このセクションで学ぶこと

このセクションでは、レビューに対する「いいね」機能を実装します。Chapter 7で学んだお気に入り機能と同様の「多対多リレーション」のパターンを、別のコンテキストで再度実践します。

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

Route::middleware('auth')->group(function () {
    // ... 既存のルート

    // Review Like management
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('likes.store');
    Route::delete('/reviews/{review}/unlike', [ReviewLikeController::class, 'destroy'])->name('likes.destroy');
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
    @if (auth()->user()->likedReviews->contains($review->id))
        {{-- いいね済み：解除ボタンを表示 --}}
        <form action="{{ route('likes.destroy', $review) }}" method="POST" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-500">
                ♥ {{ $review->likedByUsers->count() }}
            </button>
        </form>
    @else
        {{-- 未いいね：いいねボタンを表示 --}}
        <form action="{{ route('likes.store', $review) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="text-gray-500">
                ♡ {{ $review->likedByUsers->count() }}
            </button>
        </form>
    @endif
@else
    {{-- 未ログイン：いいね数のみ表示 --}}
    <span class="text-gray-500">♡ {{ $review->likedByUsers->count() }}</span>
@endauth
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `auth()->user()->likedReviews->contains($review->id)` | ログインユーザーがこのレビューにいいねしているか確認します。 | - |
| `$review->likedByUsers->count()` | このレビューにいいねしているユーザー数を取得します。 | `Review`モデルに`likedByUsers`リレーションを定義しておく必要があります。 |
| `@else` | 未ログインユーザー向けの表示です。 | いいねボタンは表示せず、いいね数のみ表示します。 |

---

## 8.5. Reviewモデルへのリレーション追加

いいね数を表示するために、`Review`モデルに`likedByUsers`リレーションを追加します。

```php
// app/Models/Review.php

public function likedByUsers()
{
    return $this->belongsToMany(User::class, 'review_likes');
}
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `belongsToMany(User::class, 'review_likes')` | `review_likes`中間テーブルを通じて、このレビューにいいねしたユーザーを取得します。 | `User`モデルの`likedReviews`リレーションの「逆方向」です。 |

> **🧠 先輩エンジニアの思考プロセス**
> 多対多リレーションは、両方向から定義することで、どちらのモデルからでも関連データにアクセスできるようになります。
> - `$user->likedReviews`: ユーザーがいいねしたレビュー一覧
> - `$review->likedByUsers`: レビューにいいねしたユーザー一覧

これで、レビューいいね機能の実装が完了しました。次のChapterでは、ランキング機能を実装していきます。
