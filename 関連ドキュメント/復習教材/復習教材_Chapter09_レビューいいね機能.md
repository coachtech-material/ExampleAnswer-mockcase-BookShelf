# Chapter 09: パターンの反復 - レビューいいね機能を実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、レビューに対する「いいね」機能を実装します。Chapter 08 で学んだお気に入り機能と同じ「多対多リレーション + toggle」パターンを、別のコンテキストで再実践することで理解を定着させます。

- **パターンの再利用**: お気に入り機能と同じ設計パターンを、レビューいいね機能に適用します
- **学習の定着**: 同じパターンを繰り返し実装することで、理解を深めます

## 1. はじめに 📖

### 同じパターンに気づく力

お気に入り機能とレビューいいね機能は、技術的には全く同じパターンです。この「同じパターン」に気づき、抽象化して捉えることが、エンジニアとしての成長に繋がります。

| 機能 | 主体 (User) | 対象 (Target) | 中間テーブル | リレーション名 |
|:---|:---|:---|:---|:---|
| お気に入り | User | Book | favorites | favoriteBooks |
| いいね | User | Review | review_likes | likedReviews |

この構造は、**「ユーザーが何かをブックマークする」**という非常に汎用的なパターンです。このパターンを一度マスターすれば、以下のような機能も同じ考え方で実装できます:

- ユーザーが商品を「欲しいものリスト」に追加する
- ユーザーが記事を「後で読む」リストに追加する
- ユーザーが他のユーザーを「フォロー」する

## 2. 要件の確認 📋

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| いいねトグル | POST | `/reviews/{review}/like` | ReviewLikeController@toggle | 必要 |

## 3. 先輩エンジニアの思考プロセス 💭

### Point 1: Chapter 08 と同じ設計判断

お気に入り機能で `toggle()` パターンを選択した理由がそのまま適用できます。ユーザーがいいねボタンを押したとき、中間テーブル `review_likes` にレコードがあれば解除、なければ登録。これを `toggle()` 一発で実現します。

### Point 2: コントローラーは最小限に

レビューいいねは `toggle` メソッドのみで完結するため、コントローラーは非常にシンプルです。一覧画面は不要（いいね数は書籍詳細ページで表示）なので、`index` メソッドもありません。

## 4. 実装 🚀

`ReviewLikeController` は Chapter 06 の「ルート定義とコントローラーの準備」で既に作成済みです。中身を実装していきましょう。

### `app/Http/Controllers/ReviewLikeController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    /**
     * いいねを追加/削除（トグル）
     */
    public function toggle(Review $review): RedirectResponse
    {
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }
}
```

## 5. コードの詳細解説 🔍

| コード / 構文 | 解説 |
|:---|:---|
| `toggle(Review $review): RedirectResponse` | ルートモデルバインディングで `Review` モデルを受け取り、リダイレクトレスポンスを返します。 |
| `Auth::user()->likedReviews()` | `User` モデルに定義した `likedReviews` リレーション（`belongsToMany`）を呼び出します。`review_likes` 中間テーブルを操作するためのクエリビルダが返されます。 |
| `->toggle($review->id)` | 中間テーブルに `(user_id, review_id)` の組み合わせが存在すれば削除し、存在しなければ追加します。Chapter 08 の `favoriteBooks()->toggle()` と全く同じパターンです。 |
| `return back();` | ユーザーを直前のページ（いいねボタンを押した書籍詳細ページ）にリダイレクトします。 |

### お気に入り機能との対比

| 要素 | お気に入り（Chapter 08） | いいね（Chapter 09） |
|:---|:---|:---|
| コントローラー | `FavoriteController` | `ReviewLikeController` |
| 対象モデル | `Book` | `Review` |
| リレーション名 | `favoriteBooks()` | `likedReviews()` |
| 中間テーブル | `favorites` | `review_likes` |
| 処理 | `toggle($book->id)` | `toggle($review->id)` |

## 8. まとめ ✨

このチャプターでは、レビューいいね機能を実装しました。

- **パターンの再実践**: Chapter 08 のお気に入り機能と同じ `toggle()` パターンを、レビューいいねに適用しました
- **抽象化の視点**: 「ユーザーが何かをブックマークする」という汎用パターンとして認識し、主体・対象・中間テーブルを入れ替えるだけで同じ構造が再利用できることを確認しました

次の Chapter 10 では、レビューの平均評価に基づいて書籍を**ランキング表示**する機能を実装します。SQLの集計クエリとEloquentの連携を学びます。
