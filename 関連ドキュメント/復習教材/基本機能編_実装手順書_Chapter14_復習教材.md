# Chapter 14: ルート定義の完全版（設計思想のまとめ）

## 🎯 このセクションで学ぶこと

このセクションでは、これまでに実装してきた全機能のルート定義を確認し、全体の設計思想を振り返ります。

- **ルート設計の全体像**: 認証の有無、HTTPメソッド、URLパターンの設計思想を理解します。
- **RESTful設計**: リソース指向のURL設計について理解を深めます。
- **ルートグループ**: ミドルウェアによるルートのグループ化を理解します。

---

## 🧠 先輩エンジニアの思考プロセス：ルート設計の原則

ルートを設計する際、以下の原則に従います。

| 原則 | 説明 | 例 |
|:---|:---|:---|
| **RESTful** | リソース（名詞）に対する操作（動詞）をHTTPメソッドで表現 | `GET /books`（一覧）, `POST /books`（作成） |
| **認証の分離** | 認証が必要なルートと不要なルートを明確に分離 | `Route::middleware(\'auth\')->group(...)` |
| **ネストの適切な深さ** | 親子関係があるリソースは1階層までネスト | `/books/{book}/reviews`（OK）, `/users/{user}/books/{book}/reviews`（深すぎ） |

---

## 13.1. ルート定義の完全版

```php
// routes/web.php

<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

// ========================================
// 認証不要のルート
// ========================================

// トップページ
Route::get(\'/\', [BookController::class, \'index\'])->name(\'root\');

// 書籍関連（閲覧のみ）
Route::get(\'/books/search\', [BookController::class, \'search\\])->name(\'books.search\');
Route::resource(\'books\', BookController::class)->only([\'index\', \'show\']);

// ジャンル関連（閲覧のみ）
Route::resource(\'genres\', GenreController::class)->only([\'show\']);

// ランキング
Route::get(\'/ranking\', [RankingController::class, \'index\\])->name(\'ranking.index\');

// ========================================
// 認証が必要なルート
// ========================================

Route::middleware(\'auth\')->group(function () {
    // プロフィール管理
    Route::get(\'/profile\', [ProfileController::class, \'edit\\])->name(\'profile.edit\');
    Route::patch(\'/profile\', [ProfileController::class, \'update\\])->name(\'profile.update\');
    Route::delete(\'/profile\', [ProfileController::class, \'destroy\\])->name(\'profile.destroy\');

    // 書籍管理（CRUD）
    Route::resource(\'books\', BookController::class)->except([\'index\', \'show\']);

    // レビュー管理
    Route::resource(\'reviews\', ReviewController::class)->except([\'index\', \'show\']);

    // お気に入り管理
    Route::post(\'/books/{book}/favorite\', [FavoriteController::class, \'store\\])->name(\'favorites.store\');
    Route::delete(\'/books/{book}/unfavorite\', [FavoriteController::class, \'destroy\\])->name(\'favorites.destroy\');
    Route::get(\'/favorites\', [FavoriteController::class, \'index\\])->name(\'favorites.index\');

    // レビューいいね管理
    Route::post(\'/reviews/{review}/like\', [ReviewLikeController::class, \'store\\])->name(\'likes.store\');
    Route::delete(\'/reviews/{review}/unlike\', [ReviewLikeController::class, \'destroy\\])->name(\'likes.destroy\');

    // ジャンル管理（CRUD）
    Route::resource(\'genres\', GenreController::class)->except([\'show\']);
});

// 認証ルート（Breezeが生成）
require __DIR__.\'/auth.php\';
```

---

## 13.2. ルート一覧表
### 認証不要のルート

| HTTPメソッド | URL | コントローラー@メソッド | ルート名 | 説明 |
|:---|:---|:---|:---|:---|
| GET | `/` | `BookController@index` | `root` | トップページ |
| GET | `/books` | `BookController@index` | `books.index` | 書籍一覧 |
| GET | `/books/search` | `BookController@search` | `books.search` | 書籍検索 |
| GET | `/books/{book}` | `BookController@show` | `books.show` | 書籍詳細 |
| GET | `/genres/{genre}` | `GenreController@show` | `genres.show` | ジャンル別一覧 |
| GET | `/ranking` | `RankingController@index` | `ranking.index` | ランキング |

### 認証が必要なルート（書籍管理）

| HTTPメソッド | URL | コントローラー@メソッド | ルート名 | 説明 |
|:---|:---|:---|:---|:---|
| GET | `/books/create` | `BookController@create` | `books.create` | 書籍登録フォーム |
| POST | `/books` | `BookController@store` | `books.store` | 書籍登録処理 |
| GET | `/books/{book}/edit` | `BookController@edit` | `books.edit` | 書籍編集フォーム |
| PUT/PATCH | `/books/{book}` | `BookController@update` | `books.update` | 書籍更新処理 |
| DELETE | `/books/{book}` | `BookController@destroy` | `books.destroy` | 書籍削除処理 |

### 認証が必要なルート（レビュー管理）

| HTTPメソッド | URL | コントローラー@メソッド | ルート名 | 説明 |
|:---|:---|:---|:---|:---|
| GET | `/reviews/create` | `ReviewController@create` | `reviews.create` | レビュー投稿フォーム |
| POST | `/reviews` | `ReviewController@store` | `reviews.store` | レビュー投稿処理 |
| GET | `/reviews/{review}/edit` | `ReviewController@edit` | `reviews.edit` | レビュー編集フォーム |
| PUT/PATCH | `/reviews/{review}` | `ReviewController@update` | `reviews.update` | レビュー更新処理 |
| DELETE | `/reviews/{review}` | `ReviewController@destroy` | `reviews.destroy` | レビュー削除処理 |

### 認証が必要なルート（お気に入り・いいね）

| HTTPメソッド | URL | コントローラー@メソッド | ルート名 | 説明 |
|:---|:---|:---|:---|:---|
| POST | `/books/{book}/favorite` | `FavoriteController@store` | `favorites.store` | お気に入り登録 |
| DELETE | `/books/{book}/unfavorite` | `FavoriteController@destroy` | `favorites.destroy` | お気に入り解除 |
| GET | `/favorites` | `FavoriteController@index` | `favorites.index` | お気に入り一覧 |
| POST | `/reviews/{review}/like` | `ReviewLikeController@store` | `likes.store` | いいね登録 |
| DELETE | `/reviews/{review}/unlike` | `ReviewLikeController@destroy` | `likes.destroy` | いいね解除 |

### 認証が必要なルート（ジャンル管理）

| HTTPメソッド | URL | コントローラー@メソッド | ルート名 | 説明 |
|:---|:---|:---|:---|:---|
| GET | `/genres` | `GenreController@index` | `genres.index` | ジャンル一覧 |
| GET | `/genres/create` | `GenreController@create` | `genres.create` | ジャンル登録フォーム |
| POST | `/genres` | `GenreController@store` | `genres.store` | ジャンル登録処理 |
| GET | `/genres/{genre}/edit` | `GenreController@edit` | `genres.edit` | ジャンル編集フォーム |
| PUT/PATCH | `/genres/{genre}` | `GenreController@update` | `genres.update` | ジャンル更新処理 |
| DELETE | `/genres/{genre}` | `GenreController@destroy` | `genres.destroy` | ジャンル削除処理 |

---

## 13.3. 設計のポイント

### 13.3.1. 認証の有無による分類

```
認証不要（誰でもアクセス可能）
├── 書籍一覧・詳細・検索
├── ジャンル別一覧
└── ランキング

認証必要（ログインユーザーのみ）
├── 書籍の登録・編集・削除
├── レビューの投稿・編集・削除
├── お気に入りの登録・解除
├── いいねの登録・解除
└── ジャンルの管理
```

> **💡 ポイント**
> 「閲覧」は誰でも可能、「変更」はログインユーザーのみ、という原則に従っています。

### 13.3.2. RESTfulなURL設計

| 操作 | HTTPメソッド | URL | 説明 |
|:---|:---|:---|:---|
| 一覧表示 | GET | `/resources` | リソースの一覧を取得 |
| 詳細表示 | GET | `/resources/{id}` | 特定のリソースを取得 |
| 作成フォーム | GET | `/resources/create` | 作成フォームを表示 |
| 作成処理 | POST | `/resources` | 新しいリソースを作成 |
| 編集フォーム | GET | `/resources/{id}/edit` | 編集フォームを表示 |
| 更新処理 | PUT/PATCH | `/resources/{id}` | リソースを更新 |
| 削除処理 | DELETE | `/resources/{id}` | リソースを削除 |

### 13.3.3. ネストしたリソース

```
/books/{book}/reviews  → 書籍に紐づくレビュー
/books/{book}/favorite → 書籍に対するお気に入り
/reviews/{review}/like → レビューに対するいいね
```

> **🧠 先輩エンジニアの思考プロセス**
> ネストは1階層までに抑えます。深くネストすると、URLが長くなり、コントローラーの責務も複雑になります。

---

## 13.4. まとめ

このチュートリアルでは、以下の機能を実装しました。

| Chapter | 機能 | 学んだこと |
|:---|:---|:---|
| 1 | 環境構築 | Laravel Sail, Docker |
| 2 | データベース設計 | マイグレーション, 外部キー |
| 3 | モデル | Eloquent, リレーションシップ |
| 4 | 認証 | Breeze, ミドルウェア |
| 5 | 書籍管理 | CRUD, FormRequest, Policy |
| 6 | レビュー機能 | ネストしたリソース, 認可 |
| 7 | お気に入り | 多対多リレーション |
| 8 | いいね | パターンの再利用 |
| 9 | ランキング | 集計クエリ |
| 10 | 検索 | LIKE検索, クエリパラメータ |
| 11 | ジャンル別一覧 | リレーションを活用した絞り込み |
| 12 | ジャンル管理 | CRUDパターンの再実践 |
| 13 | ルート設計 | RESTful, ルートグループ |

これで、基本機能編の実装が完了しました。お疲れ様でした！
