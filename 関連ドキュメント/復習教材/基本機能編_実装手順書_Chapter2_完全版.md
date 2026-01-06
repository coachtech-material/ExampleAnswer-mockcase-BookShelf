# Chapter 2: データベース設計とマイグレーション

このChapterでは、書籍レビュー管理システムに必要なデータベーステーブルを設計し、Laravelのマイグレーション機能を使ってデータベースの構造を定義します。

---

## 2-1. 先輩エンジニアの思考プロセス：要件からテーブル設計へ

アプリケーション開発において、データベース設計は「家の基礎工事」に例えられます。ここでの設計が、後の機能実装のしやすさやアプリケーション全体のパフォーマンスに大きく影響します。

では、どのようにしてテーブル構造を考えていくのでしょうか？答えは**「要件定義書」**の中にあります。

### Step 1: 要件から「モノ」と「関係」を洗い出す

まず、要件定義書（`基本機能編_要件定義書_基本設計書_詳細度100%.md`）の機能要件一覧を眺めて、アプリケーションに登場する主要な「モノ（エンティティ）」を名詞として抜き出します。

- **ユーザー** (USERS)
- **書籍** (BOOKS)
- **レビュー** (REVIEWS)
- **ジャンル** (GENRES)
- **お気に入り** (FAVORITES)
- **いいね** (REVIEW_LIKES)
- **書籍ジャンル** (BOOK_GENRE)

次に、これらの「モノ」が互いにどういう「関係」にあるかを考えます。

- ユーザーは、**複数の**書籍を登録する (1対多)
- ユーザーは、**複数の**レビューを投稿する (1対多)
- 書籍には、**複数の**レビューが投稿される (1対多)
- ユーザーは、**複数の**書籍をお気に入り登録する (多対多)
- ユーザーは、**複数の**レビューに「いいね」する (多対多)
- 書籍は、**複数の**ジャンルに属する (多対多)

この「1対多」「多対多」の関係性を整理することが、テーブル設計の第一歩です。

### Step 2: ヒアリングで要件を具体化する

要件定義書だけでは分からない細かい仕様は、プロジェクトマネージャーや顧客にヒアリングして明確にします。良いエンジニアは、的確な質問で仕様の曖昧さをなくしていきます。

**【ヒアリング例】**

> **エンジニア**: 「書籍とジャンルの関係ですが、1冊の書籍は1つのジャンルにしか属しませんか？例えば『ハリー・ポッター』は『ファンタジー』であり『小説』でもある、といったケースは考慮しますか？」
> **PM**: 「良い質問ですね。複数のジャンルに属せるようにしましょう。」
> **→ 結果**: 書籍とジャンルは「多対多」の関係だとわかる。中間テーブル `book_genre` が必要になる。

> **エンジニア**: 「ユーザーが退会した場合、そのユーザーが登録した書籍やレビューはどう扱いますか？」
> **PM**: 「ユーザーが退会したら、その人のデータは全て削除してください。」
> **→ 結果**: 外部キー制約に `onDelete(\'cascade\')` を設定し、親レコード（ユーザー）の削除時に子レコード（書籍、レビューなど）も自動で削除されるように設計する。

### Step 3: ER図とテーブル定義書を作成する

洗い出した「モノ」と「関係」を元に、ER図（エンティティ関連図）とテーブル定義書を作成します。これらは、データベースの「設計図」となる重要なドキュメントです。

#### ER図 (Entity-Relationship Diagram)

テーブル間の関係性を視覚的に表現した図です。これにより、アプリケーション全体のデータ構造を直感的に把握できます。

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    BOOKS {
        bigint id PK
        bigint user_id FK
        string title
        string author
        string isbn
        date published_date
        text description
        string image_url
        timestamp created_at
        timestamp updated_at
    }

    REVIEWS {
        bigint id PK
        bigint user_id FK
        bigint book_id FK
        tinyint rating
        text comment
        timestamp created_at
        timestamp updated_at
    }

    GENRES {
        bigint id PK
        string name
        timestamp created_at
        timestamp updated_at
    }

    FAVORITES {
        bigint user_id PK, FK
        bigint book_id PK, FK
    }

    REVIEW_LIKES {
        bigint user_id PK, FK
        bigint review_id PK, FK
    }

    BOOK_GENRE {
        bigint book_id PK, FK
        bigint genre_id PK, FK
    }

    USERS ||--o{ BOOKS : "registers"
    USERS ||--o{ REVIEWS : "writes"
    BOOKS ||--o{ REVIEWS : "has"
    USERS ||--|{ FAVORITES : "favorites"
    BOOKS ||--|{ FAVORITES : "is favorited by"
    USERS ||--|{ REVIEW_LIKES : "likes"
    REVIEWS ||--|{ REVIEW_LIKES : "is liked by"
    BOOKS }|--|{ BOOK_GENRE : "has"
    GENRES }|--|{ BOOK_GENRE : "belongs to"
```

#### テーブル定義書

| テーブル名 | 物理名 | 用途 |
|:---|:---|:---|
| ユーザー | `users` | アプリケーションの利用者情報を格納する |
| 書籍 | `books` | 書籍の基本情報を格納する |
| レビュー | `reviews` | 書籍に対するレビュー情報を格納する |
| ジャンル | `genres` | 書籍のジャンルマスタ |
| お気に入り | `favorites` | ユーザーと書籍のお気に入り関係を格納する中間テーブル |
| レビューいいね | `review_likes` | ユーザーとレビューのいいね関係を格納する中間テーブル |
| 書籍ジャンル | `book_genre` | 書籍とジャンルの中間テーブル |

#### テーブル定義書（詳細）

**users**

| カラム名 | データ型 | 主キー | 外部キー | Null許可 | デフォルト値 | 説明 |
|:---|:---|:---:|:---:|:---:|:---|:---|
| id | bigint | ✔ | | | | ユーザーID |
| name | varchar(255) | | | | | ユーザー名 |
| email | varchar(255) | | | | | メールアドレス（一意） |
| email_verified_at | timestamp | | | ✔ | NULL | メール認証日時 |
| password | varchar(255) | | | | | パスワード |
| remember_token | varchar(100) | | | ✔ | NULL | ログイン維持用トークン |
| created_at | timestamp | | | ✔ | NULL | 作成日時 |
| updated_at | timestamp | | | ✔ | NULL | 更新日時 |

**books**

| カラム名 | データ型 | 主キー | 外部キー | Null許可 | デフォルト値 | 説明 |
|:---|:---|:---:|:---:|:---:|:---|:---|
| id | bigint | ✔ | | | | 書籍ID |
| user_id | bigint | | ✔ (users.id) | | | 登録したユーザーのID |
| title | varchar(255) | | | | | 書籍タイトル |
| author | varchar(255) | | | | | 著者名 |
| isbn | varchar(13) | | | | | ISBN（一意） |
| published_date | date | | | | | 出版日 |
| description | text | | | ✔ | NULL | 書籍の説明 |
| image_url | varchar(255) | | | ✔ | NULL | 書影画像のURL |
| created_at | timestamp | | | ✔ | NULL | 作成日時 |
| updated_at | timestamp | | | ✔ | NULL | 更新日時 |

**reviews**

| カラム名 | データ型 | 主キー | 外部キー | Null許可 | デフォルト値 | 説明 |
|:---|:---|:---:|:---:|:---:|:---|:---|
| id | bigint | ✔ | | | | レビューID |
| user_id | bigint | | ✔ (users.id) | | | 投稿したユーザーのID |
| book_id | bigint | | ✔ (books.id) | | | レビュー対象の書籍ID |
| rating | tinyint(unsigned) | | | | | 評価（1〜5） |
| comment | text | | | | | レビューコメント |
| created_at | timestamp | | | ✔ | NULL | 作成日時 |
| updated_at | timestamp | | | ✔ | NULL | 更新日時 |

**genres**

| カラム名 | データ型 | 主キー | 外部キー | Null許可 | デフォルト値 | 説明 |
|:---|:---|:---:|:---:|:---:|:---|:---|
| id | bigint | ✔ | | | | ジャンルID |
| name | varchar(255) | | | | | ジャンル名（一意） |
| created_at | timestamp | | | ✔ | NULL | 作成日時 |
| updated_at | timestamp | | | ✔ | NULL | 更新日時 |

**favorites** (中間テーブル)

| カラム名 | データ型 | 主キー | 外部キー | 説明 |
|:---|:---|:---:|:---:|:---|
| user_id | bigint | ✔ | ✔ (users.id) | ユーザーID |
| book_id | bigint | ✔ | ✔ (books.id) | 書籍ID |

**review_likes** (中間テーブル)

| カラム名 | データ型 | 主キー | 外部キー | 説明 |
|:---|:---|:---:|:---:|:---|
| user_id | bigint | ✔ | ✔ (users.id) | ユーザーID |
| review_id | bigint | ✔ | ✔ (reviews.id) | レビューID |

**book_genre** (中間テーブル)

| カラム名 | データ型 | 主キー | 外部キー | 説明 |
|:---|:---|:---:|:---:|:---|
| book_id | bigint | ✔ | ✔ (books.id) | 書籍ID |
| genre_id | bigint | ✔ | ✔ (genres.id) | ジャンルID |

---

## 2.2. マイグレーションファイルの作成と実行

設計が固まったら、Laravelのマイグレーション機能を使ってデータベースにテーブルを作成します。

### Step 1: マイグレーションファイルの作成

```bash
# usersテーブルはLaravelデフォルトで存在
sail artisan make:migration create_genres_table
sleep 1
sail artisan make:migration create_books_table
sleep 1
sail artisan make:migration create_reviews_table
sleep 1
sail artisan make:migration create_book_genre_table
sleep 1
sail artisan make:migration create_favorites_table
sleep 1
sail artisan make:migration create_review_likes_table
```

### Step 2: マイグレーションファイルの編集

作成された各マイグレーションファイルに、テーブルのカラム定義を追加していきます。

#### `database/migrations/xxxx_xx_xx_xxxxxx_create_genres_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
```

#### `database/migrations/xxxx_xx_xx_xxxxxx_create_books_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 誰が登録した書籍かを記録
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 13)->unique();
            $table->date('published_date');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
```

#### `database/migrations/xxxx_xx_xx_xxxxxx_create_reviews_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
```

#### `database/migrations/xxxx_xx_xx_xxxxxx_create_book_genre_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_genre', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('genre_id')->constrained()->onDelete('cascade');
            $table->primary(['book_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_genre');
    }
};
```

#### `database/migrations/xxxx_xx_xx_xxxxxx_create_favorites_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
```

#### `database/migrations/xxxx_xx_xx_xxxxxx_create_review_likes_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_likes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('review_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_likes');
    }
};
```

### Step 3: マイグレーションの実行

```bash
sail artisan migrate
```
