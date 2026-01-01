'''
# Chapter 8: レビューいいね機能

このChapterでは、他のユーザーが投稿したレビューに対して「いいね」を付けたり解除したりできる機能を実装します。この機能は、Chapter 7で実装した「お気に入り機能」と構造的に全く同じです。このような「パターンの認識」は、エンジニアの生産性を飛躍的に向上させます。

---

## 8-1. 先輩エンジニアの思考プロセス：実装パターンの再利用

### Step 1: お気に入り機能との共通点と相違点を見抜く

まず、レビューいいね機能の要件と、既知のお気に入り機能を比較します。

| 項目 | お気に入り機能 (Chapter 7) | レビューいいね機能 (Chapter 8) |
|:---|:---|:---|
| **目的** | **書籍**をブックマークする | **レビュー**を評価する |
| **関係性** | `users` ↔ `books` | `users` ↔ `reviews` |
| **中間テーブル** | `favorites` | `review_likes` |
| **操作** | 登録・解除・一覧 | 登録・解除 |
| **リレーション** | `belongsToMany` | `belongsToMany` |

> **先輩エンジニアの思考:**
> 「これは、お気に入り機能と全く同じパターンだ。対象が『書籍』から『レビュー』に変わっただけ。コントローラーのロジック、ルーティングの設計、ビューでのボタンの出し分け方まで、ほとんどのコードを流用できる。新しい機能をゼロから考えるのではなく、『過去に実装したどのパターンに似ているか？』を考えることで、実装コストを大幅に削減できる。これが経験豊富なエンジニアの強みだ。」

この思考プロセスに基づき、お気に入り機能の実装を「テンプレート」として、レビューいいね機能を実装していきます。

### Step 2: 実装方針の決定

- **コントローラー (`ReviewLikeController`)**: `FavoriteController`を参考に、対象モデルを`Book`から`Review`に変更する。ロジックは`syncWithoutDetaching`と`detach`をそのまま流用する。
- **ルーティング (`routes/web.php`)**: `favorites`ルートを参考に、URLとコントローラー名を変更する。
- **ビュー (`books/show.blade.php`)**: お気に入りボタンのロジックを参考に、レビュー一覧の中でいいねボタンを実装する。

---

## 8.2. 部品の作成と実装

### 1. コントローラーの作成

```bash
sail artisan make:controller ReviewLikeController
```

### 2. ルーティングの定義 (`routes/web.php`)

いいね操作のルートを`middleware('auth')`グループ内に追加します。

```php
// Review Like management
// いいね登録
Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('likes.store');
// いいね解除
Route::delete('/reviews/{review}/unlike', [ReviewLikeController::class, 'destroy'])->name('likes.destroy');
```

### 3. コントローラーの実装 (`ReviewLikeController.php`)

`FavoriteController`とほぼ同じ構造です。`Book`が`Review`に、`favoriteBooks()`が`likedReviews()`に変わっただけです。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    /**
     * いいね登録処理
     */
    public function store(Request $request, Review $review): RedirectResponse
    {
        // 要件：レビューにいいねを登録する。
        // 思考：お気に入りと同様、重複を気にせず追加できる`syncWithoutDetaching`が最適。
        $request->user()->likedReviews()->syncWithoutDetaching($review->id);

        return back();
    }

    /**
     * いいね解除処理
     */
    public function destroy(Request $request, Review $review): RedirectResponse
    {
        // 要件：レビューのいいねを解除する。
        // 思考：シンプルに`detach`で関連を削除する。
        $request->user()->likedReviews()->detach($review->id);

        return back();
    }
}
```

---

## 8.3. ビューの実装

### 書籍詳細ページのレビュー一覧へのボタン追加 (`resources/views/books/show.blade.php`)

レビュー一覧をループしている中で、各レビューに対していいねボタンを設置します。

```html
@foreach ($book->reviews as $review)
    <div>
        <!-- レビュー内容の表示 -->
        <p>{{ $review->user->name }}: {{ $review->comment }}</p>

        <!-- いいね機能 -->
        @auth
            <div class="flex items-center">
                @if ($review->likedByUsers->contains(Auth::user()))
                    <!-- いいね解除ボタン -->
                    <form action="{{ route('likes.destroy', $review) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit">いいね解除 ({{ $review->likedByUsers->count() }})</button>
                    </form>
                @else
                    <!-- いいね登録ボタン -->
                    <form action="{{ route('likes.store', $review) }}" method="POST">
                        @csrf
                        <button type="submit">いいね ({{ $review->likedByUsers->count() }})</button>
                    </form>
                @endif
            </div>
        @endauth
        @guest
            <span>いいね ({{ $review->likedByUsers->count() }})</span>
        @endguest

        <!-- 編集・削除ボタンなど -->
        @can('update', $review)
            <a href="{{ route('reviews.edit', $review) }}">編集</a>
        @endcan
    </div>
@endforeach
```

> **【学習のポイント】**
> 1.  `$review->likedByUsers`は、`Review`モデルに定義した`belongsToMany`リレーションです。これを使うことで、そのレビューをいいねした全ユーザーのコレクションを取得できます。
> 2.  `->contains(Auth::user())`で、そのコレクションの中にログイン中のユーザーが含まれているかを判定し、ボタンを出し分けています。
> 3.  `->count()`で、コレクションの要素数、つまり「いいねの総数」を取得しています。
> 4.  これらの処理をビューで実現するために、`BookController@show`でレビュー情報を取得する際に、`$book->load(['reviews.user', 'reviews.likedByUsers'])`のように、いいね情報(`likedByUsers`)もEager Loadしておくことがパフォーマンス上重要になります。

---

## 8.4. 動作確認

1.  書籍詳細ページに表示されている各レビューに「いいね」ボタンが表示されていることを確認します。
2.  ボタンをクリックすると、「いいね解除」に切り替わり、いいね数が1増えることを確認します。
3.  再度クリックすると、「いいね」に戻り、いいね数が1減ることを確認します。
4.  別のユーザーでログインし、同じレビューにいいねをすると、いいね数がさらに1増えることを確認します。

これで、レビューいいね機能の実装が完了しました。お気に入り機能という既存のパターンを再利用することで、非常に効率的に実装できたことが体感できたはずです。
'''
