
# Chapter 8: レビューいいね機能

このChapterでは、投稿されたレビューに対してユーザーが「いいね」を付けられる機能を実装します。この機能は、Chapter 7で実装した「お気に入り機能」と全く同じ構造（`users`と`reviews`の多対多リレーション）をしています。そのため、既存の実装パターンを再利用して効率的に開発を進めます。

---

## 8-1. 先輩エンジニアの思考プロセス：実装パターンの再利用

### Step 1: 機能の共通点を見抜く

- **お気に入り機能**: `User`が`Book`を「いいね」する。
- **レビューいいね機能**: `User`が`Review`を「いいね」する。

> **先輩エンジニアの思考:**
> 「これは構造が完全に一致しているな。`Book`が`Review`に変わっただけだ。中間テーブル（`review_likes`）を作成し、`User`モデルと`Review`モデルに`belongsToMany`リレーションを定義すれば、お気に入り機能と全く同じコードで実装できる。車輪の再発明はせず、既存の成功パターンを積極的に再利用しよう。これにより、開発スピードが向上し、コードの品質も安定する。」

### Step 2: `toggle`処理を再利用する

お気に入り機能と同様に、「いいね」も「いいね解除」も同じボタンで状態を反転させる`toggle`処理として実装します。

> **先輩エンジニアの思考:**
> 「`FavoriteController`の`toggle`メソッドのロジックをそのまま`ReviewLikeController`に持ってこよう。対象となるモデルが`Book`から`Review`に、リレーションが`favoriteBooks()`から`likedReviews()`に変わるだけだ。ルート定義も`POST /reviews/{review}/like`という単一のエンドポイントで実装する。」

---

## 8.2. 部品の作成と実装

### 1. コントローラーの作成

```bash
sail artisan make:controller ReviewLikeController
```

### 2. ルーティングの定義 (`routes/web.php`)

`middleware('auth')`グループ内にルートを追加します。

```php
// Review Like management
Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
```

### 3. コントローラーの実装 (`ReviewLikeController.php`)

`FavoriteController`の`toggle`メソッドを参考に、対象モデルとリレーション名を変更して実装します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    /**
     * いいね登録/解除処理 (トグル)
     */
    public function toggle(Review $review): RedirectResponse
    {
        $user = Auth::user();

        // 既にいいねしているかチェック
        if ($user->likedReviews()->where('review_id', $review->id)->exists()) {
            // 登録済みなら解除
            $user->likedReviews()->detach($review->id);
            $message = 'いいねを解除しました。';
        } else {
            // 未登録なら登録
            $user->likedReviews()->attach($review->id);
            $message = 'いいねしました。';
        }

        return back()->with('success', $message);
    }
}
```

---

## 8.3. ビューの実装

### 書籍詳細ページへのボタン追加 (`resources/views/books/show.blade.php`)

レビューを表示しているループの中で、各レビューに対して「いいね」ボタンを設置します。

```html
{{-- レビューのループ内 --}}
@foreach ($book->reviews as $review)
    <div>
        <p>{{ $review->user->name }}</p>
        <p>評価: {{ $review->rating }} ★</p>
        <p>{{ $review->comment }}</p>
        <p>いいね数: {{ $review->likedByUsers->count() }}</p>

        @auth
            <form action="{{ route('reviews.like', $review) }}" method="POST">
                @csrf
                @if (Auth::user()->likedReviews->contains($review))
                    <button type="submit">いいね解除</button>
                @else
                    <button type="submit">いいね</button>
                @endif
            </form>
        @endauth
    </div>
@endforeach
```

> **【学習のポイント】**
> - `Auth::user()->likedReviews`は、ログインユーザーがいいねした`Review`モデルのコレクションです。
> - `->contains($review)`で、そのコレクションの中にループで現在表示している`$review`が含まれているかを判定し、ボタンの表示を切り替えています。
> - `$review->likedByUsers->count()`で、そのレビューに紐づく「いいね」の総数を表示しています。これは`Review`モデルに定義した`likedByUsers()`リレーション（`belongsToMany`）のおかげで簡単に取得できます。

---

## 8.4. 動作確認

1.  書籍詳細ページで、各レビューに「いいね」ボタンが表示されていることを確認します。
2.  ボタンをクリックすると、「いいね解除」に切り替わり、いいね数が増えることを確認します。
3.  再度クリックすると、「いいね」に戻り、いいね数が減ることを確認します。

これで、既存の実装パターンを再利用して、レビューいいね機能を効率的に実装できました。
