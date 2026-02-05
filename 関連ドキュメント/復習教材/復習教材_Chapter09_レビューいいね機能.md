# Chapter 9: レビューいいね機能

## 🎯 このセクションで学ぶこと

このセクションでは、レビューに対する「いいね」機能を実装します。Chapter 8で学んだお気に入り機能と同様の「多対多リレーション」のパターンを、別のコンテキストで再度実践します。

- **パターンの再利用**: お気に入り機能と同じ設計パターンを、レビューいいね機能に適用します。
- **学習の定着**: 同じパターンを繰り返し実装することで、理解を深めます。
- **型定義の活用**: コントローラーのメソッドに戻り値の型を追加し、コードの可読性と堅牢性を向上させます。

---

## 🧠 先輩エンジニアの思考プロセス：パターンの認識と抽象化

お気に入り機能とレビューいいね機能は、技術的には全く同じパターンです。この「同じパターン」に気づき、抽象化して捉えることが、エンジニアとしての成長に繋がります。

| 機能 | 主体 (User) | 対象 (Target) | 中間テーブル | リレーション名 |
|:---|:---|:---|:---|:---|
| お気に入り | User | Book | favorites | favoriteBooks |
| いいね | User | Review | review_likes | likedReviews |

この構造は、**「ユーザーが何かをブックマークする」**という非常に汎用的なパターンです。このパターンを一度マスターすれば、例えば以下のような機能も同じ考え方で実装できます。

- ユーザーが商品を「欲しいものリスト」に追加する
- ユーザーが記事を「後で読む」リストに追加する
- ユーザーが他のユーザーを「フォロー」する

このように、具体的な機能の裏にある「構造のパターン」を見抜くことで、未知の機能要件にも迅速に対応できるようになります。

---

## 9.1. ReviewLikeController.php の実装

`ReviewLikeController`はChapter 6の「ルート定義とコントローラーの準備」で既に作成済みです。早速、中身を実装していきましょう。

`app/Http/Controllers/ReviewLikeController.php`を開き、以下の内容を記述してください。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    // Bladeの要求に合わせて toggle メソッドに変更
    public function toggle(Review $review): RedirectResponse
    {
        // ユーザーがすでにいいねしていれば解除、していなければ登録を自動で行う
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }
}
```

### 📖 コードリーディング：`toggle`メソッド

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function toggle(Review $review): RedirectResponse` | `toggle`という名前の公開メソッドを定義。引数で`Review`モデルを受け取る。 | `(Review $review)`は「ルートモデルバインディング」。URLの`{review}`の部分に対応するIDを持つ`Review`モデルのインスタンスが自動的にDI（依存性注入）される。 |
| **`: RedirectResponse`** | **戻り値の型定義**。このメソッドがリダイレクトレスポンスを返すことを明示します。 | これにより、メソッドの役割が「処理を行い、どこかへリダイレクトする」ことであると一目でわかります。意図しない値が返されるのを防ぎ、コードの堅牢性を高めます。 |
| `Auth::user()` | ログインしているユーザーの`User`モデルインスタンスを取得する。 | `Auth`ファサードを経由して、セッション情報から認証済みユーザーを取得している。 |
| `->likedReviews()` | `User`モデルに定義した`likedReviews`リレーション（`belongsToMany`）を取得する。 | これにより、`review_likes`中間テーブルを操作するためのクエリビルダが返される。 |
| `->toggle($review->id)` | `belongsToMany`リレーションの`toggle`メソッドを実行。 | 中間テーブルに`($user->id, $review->id)`の組み合わせが存在すれば削除し、存在しなければ追加する、という処理を自動で行ってくれる。 |
| `return back();` | ユーザーを直前のページ（いいねボタンを押したページ）にリダイレクトさせる。 | `back()`ヘルパー関数は、セッションに保存されている直前のURLにリダイレクトする便利な機能。 |

> **📝 ルート定義について**
> レビューいいね機能のルート定義は、Chapter 6で既に定義済みですが、Bladeファイル（`books/show.blade.php`）で使用されている`route('reviews.like')`という名前に合わせる必要があります。`routes/web.php`を開き、該当するルートの名前を修正してください。
> 
> ```php
> // routes/web.php
> 
> // ...
> // レビューいいね機能
> Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like'); // 'review.likes.toggle' から 'reviews.like' に変更
> // ...
> ```

これで、レビューいいね機能の実装は完了です。次のChapterでは、ランキング機能を実装していきます。
