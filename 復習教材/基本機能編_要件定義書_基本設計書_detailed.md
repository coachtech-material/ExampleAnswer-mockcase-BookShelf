# 書籍レビューアプリ 要件定義書・基本設計書（基本機能編）- 詳細版

## 1. はじめに

### 1.1. 本書の目的

本書は、書籍レビューアプリケーションの「基本機能編」開発における要件と設計を、実際のコードと1対1で対応付ける形で詳細に定義するものである。学習者は本書を参照することで、各機能がどのファイル、どのメソッドで実装されているかを正確に把握し、実装とレビューを効率的に進めることができる。

### 1.2. 前提条件

- 開発言語・フレームワークは PHP / Laravel (Laravel 10.x) を使用する。
- フロントエンドのBladeテンプレートは `coachtech-material/Preparedblade-mockcase-BookShelf` リポジトリの `basic` ブランチで提供される。
- データベースは MySQL を使用する。
- 開発環境は Docker および Laravel Sail を使用する。

## 2. 機能要件

### 2.1. 認証機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| C-1 | ユーザー新規登録 | ユーザーが名前、メールアドレス、パスワードを入力して新規登録できる。 | - `routes/auth.php` <br> - `app/Http/Controllers/Auth/RegisteredUserController.php` <br> - `resources/views/auth/register.blade.php` |
| C-2 | ログイン | 登録済みのメールアドレスとパスワードでログインできる。 | - `routes/auth.php` <br> - `app/Http/Controllers/Auth/AuthenticatedSessionController.php` <br> - `resources/views/auth/login.blade.php` |
| C-3 | ログアウト | ログイン状態からログアウトできる。 | - `routes/auth.php` <br> - `app/Http/Controllers/Auth/AuthenticatedSessionController.php` |

### 2.2. 書籍管理機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| C-4 | 書籍一覧表示 | 登録されている書籍がトップページに10件ずつのページネーションで表示される。 | - `routes/web.php` (`books.index`) <br> - `app/Http/Controllers/BookController.php` @ `index` <br> - `app/Models/Book.php` <br> - `resources/views/books/index.blade.php` |
| C-5 | 書籍詳細表示 | 書籍の詳細情報、関連ジャンル、投稿されたレビュー一覧が表示される。 | - `routes/web.php` (`books.show`) <br> - `app/Http/Controllers/BookController.php` @ `show` <br> - `app/Models/Book.php` (reviews, genresリレーション) <br> - `resources/views/books/show.blade.php` |
| C-6 | 書籍登録 | 新しい書籍を登録できる。バリデーションが機能し、登録後は詳細ページにリダイレクトされる。 | - `routes/web.php` (`books.create`, `books.store`) <br> - `app/Http/Controllers/BookController.php` @ `create`, `store` <br> - `app/Http/Requests/StoreBookRequest.php` <br> - `resources/views/books/create.blade.php` |
| C-7 | 書籍編集 | 自身が登録した書籍の情報のみ編集できる。更新後は詳細ページにリダイレクトされる。 | - `routes/web.php` (`books.edit`, `books.update`) <br> - `app/Http/Controllers/BookController.php` @ `edit`, `update` <br> - `app/Http/Requests/UpdateBookRequest.php` <br> - `app/Policies/BookPolicy.php` @ `update` <br> - `resources/views/books/edit.blade.php` |
| C-8 | 書籍削除 | 自身が登録した書籍のみ削除できる。削除後は一覧ページにリダイレクトされる。 | - `routes/web.php` (`books.destroy`) <br> - `app/Http/Controllers/BookController.php` @ `destroy` <br> - `app/Policies/BookPolicy.php` @ `delete` |

### 2.3. レビュー管理機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| C-9 | レビュー投稿 | 書籍詳細ページから5段階評価とコメントでレビューを投稿できる。 | - `routes/web.php` (`reviews.store`) <br> - `app/Http/Controllers/ReviewController.php` @ `store` <br> - `app/Http/Requests/StoreReviewRequest.php` <br> - `resources/views/books/show.blade.php` |
| C-10 | レビュー編集 | 自身が投稿したレビューのみ編集できる。 | - `routes/web.php` (`reviews.edit`, `reviews.update`) <br> - `app/Http/Controllers/ReviewController.php` @ `edit`, `update` <br> - `app/Http/Requests/UpdateReviewRequest.php` <br> - `app/Policies/ReviewPolicy.php` @ `update` <br> - `resources/views/reviews/edit.blade.php` |
| C-11 | レビュー削除 | 自身が投稿したレビューのみ削除できる。 | - `routes/web.php` (`reviews.destroy`) <br> - `app/Http/Controllers/ReviewController.php` @ `destroy` <br> - `app/Policies/ReviewPolicy.php` @ `delete` |

### 2.4. その他の機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| C-12 | お気に入り | 書籍のお気に入り登録・解除ができ、お気に入り一覧ページで確認できる。 | - `routes/web.php` (`favorites.toggle`, `favorites.index`) <br> - `app/Http/Controllers/FavoriteController.php` @ `toggle`, `index` <br> - `app/Models/User.php` (favoriteBooksリレーション) <br> - `resources/views/favorites/index.blade.php` |
| C-13 | いいね | レビューへの「いいね」登録・解除ができる。 | - `routes/web.php` (`reviews.like`) <br> - `app/Http/Controllers/ReviewLikeController.php` @ `toggle` <br> - `app/Models/User.php` (likedReviewsリレーション) |
| C-14 | ジャンル管理 | ジャンルのCRUD（一覧、登録、編集、削除）ができる。 | - `routes/web.php` (`genres.index`, `genres.create`, `genres.store`, `genres.edit`, `genres.update`, `genres.destroy`) <br> - `app/Http/Controllers/GenreController.php` <br> - `app/Http/Requests/StoreGenreRequest.php`, `UpdateGenreRequest.php` <br> - `resources/views/genres/` |
| C-15 | ランキング | レビューの平均評価点に基づいた書籍ランキングが表示される。 | - `routes/web.php` (`ranking.index`) <br> - `app/Http/Controllers/RankingController.php` @ `index` <br> - `resources/views/ranking/index.blade.php` |

## 3. データベース設計 (ER図)

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email
    }
    BOOKS {
        bigint id PK
        bigint user_id FK
        string title
        string author
    }
    REVIEWS {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        tinyint rating
        text comment
    }
    GENRES {
        bigint id PK
        string name
    }
    FAVORITES (user_book) {
        bigint user_id PK, FK
        bigint book_id PK, FK
    }
    REVIEW_LIKES (user_review) {
        bigint user_id PK, FK
        bigint review_id PK, FK
    }
    BOOK_GENRE (book_genre) {
        bigint book_id PK, FK
        bigint genre_id PK, FK
    }
    USERS ||--o{ BOOKS : "投稿"
    USERS ||--o{ REVIEWS : "投稿"
    BOOKS ||--o{ REVIEWS : "に対する"
    USERS ||--|{ FAVORITES : "お気に入り"
    BOOKS ||--|{ FAVORITES : "される"
    USERS ||--|{ REVIEW_LIKES : "いいね"
    REVIEWS ||--|{ REVIEW_LIKES : "される"
    BOOKS }|--|{ BOOK_GENRE : "所属"
    GENRES }|--|{ BOOK_GENRE : "持つ"
```

**関連ファイル:** `database/migrations/`

## 4. 非機能要件

| No. | 項目 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| D-1 | N+1問題の防止 | `with()` を使用したEager Loadingが適切に行われ、非効率なクエリが発行されていないか。 | - `app/Http/Controllers/BookController.php` @ `index`, `show` <br> - `app/Http/Controllers/GenreController.php` @ `show` |
| D-2 | 認可処理 | `Policy` を使用して、他人のリソースを編集・削除できないように正しく制御されているか。 | - `app/Policies/BookPolicy.php` <br> - `app/Policies/ReviewPolicy.php` <br> - `app/Http/Controllers/BookController.php` (`authorize`メソッド) <br> - `app/Http/Controllers/ReviewController.php` (`authorize`メソッド) |
| D-3 | バリデーション | `FormRequest` を使用して、バリデーションがコントローラから分離され、適切なルールが設定されているか。 | - `app/Http/Requests/` ディレクトリ配下の各Requestファイル |
| D-4 | テスト | 主要な機能に対して、正常系・異常系のFeatureテストが記述されているか。 | - `tests/Feature/` ディレクトリ配下の各テストファイル |
| E-1 | Git利用 | コミットの粒度が適切か（1コミット1機能など）。メッセージは変更内容が分かりやすいように記述されているか。 | - `git log` で確認 |
