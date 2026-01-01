# Chapter 2: DB設計とモデルの作成

このChapterでは、アプリケーションのデータ構造を設計し、Laravelのマイグレーションとモデルを作成します。DB設計はアプリケーションの土台であり、ここでの設計ミスは後々大きな手戻りにつながります。現場のエンジニアがどのように考えてDB設計を行うのかを、一緒に追体験していきましょう。

## 2-1. 要件定義書からDB設計を導く思考プロセス

まず、詳細度50%の要件定義書を見て、どのようなテーブルが必要かを考えます。

### 要件定義書から読み取れる情報

| 機能 | 必要なデータ |
|---|---|
| ユーザー登録・ログイン | ユーザー情報（名前、メールアドレス、パスワード） |
| 書籍管理 | 書籍情報（タイトル、著者、ISBN、出版日、説明、登録者） |
| レビュー管理 | レビュー情報（評価、コメント、投稿者、対象書籍） |
| ジャンル管理 | ジャンル情報（名前）、書籍との紐付け |
| お気に入り | ユーザーと書籍の紐付け |
| いいね | ユーザーとレビューの紐付け |

### PMへのヒアリングシート（DB設計に関する質問）

要件定義書だけでは分からない部分をPMに確認します。

1. **書籍とジャンルの関係**: 1つの書籍は複数のジャンルに属することができますか？
   - **回答例**: はい、多対多の関係です。
2. **お気に入り・いいねの制約**: 同じユーザーが同じ書籍/レビューに複数回登録できますか？
   - **回答例**: いいえ、1ユーザーにつき1回のみです。
3. **書籍削除時の挙動**: 書籍を削除した場合、紐付くレビューやお気に入りはどうなりますか？
   - **回答例**: カスケード削除（一緒に削除）してください。

### ER図（エンティティ関連図）

PMへのヒアリング結果を踏まえ、以下のようなER図を設計します。

```
users
  ├── 1:N ──> books (user_id)
  ├── 1:N ──> reviews (user_id)
  ├── M:N ──> books (favorites 中間テーブル)
  └── M:N ──> reviews (review_likes 中間テーブル)

books
  ├── 1:N ──> reviews (book_id)
  └── M:N ──> genres (book_genre 中間テーブル)

genres
  └── M:N ──> books (book_genre 中間テーブル)
```

## 2-2. マイグレーションファイルの作成

設計が固まったら、マイグレーションファイルを作成していきます。

### Step 1: マイグレーションファイルの生成

```bash
# 書籍テーブル
sail artisan make:migration create_books_table

# ジャンルテーブル
sail artisan make:migration create_genres_table

# 書籍-ジャンル中間テーブル
sail artisan make:migration create_book_genre_table

# レビューテーブル
sail artisan make:migration create_reviews_table

# お気に入り中間テーブル
sail artisan make:migration create_favorites_table

# いいね中間テーブル
sail artisan make:migration create_review_likes_table
```

### Step 2: 各マイグレーションファイルの編集

**`database/migrations/xxxx_xx_xx_xxxxxx_create_books_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 13)->unique();
            $table->date('published_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
```

**コードリーディング:**
- `$table->id();`: `id` カラム（BIGINT UNSIGNED, AUTO_INCREMENT, PRIMARY KEY）を作成します。
- `$table->foreignId('user_id')->constrained()->cascadeOnDelete();`: `user_id` カラムを作成し、`users` テーブルの `id` への外部キー制約を設定します。`cascadeOnDelete()` により、ユーザーが削除されると、そのユーザーが登録した書籍も自動的に削除されます。
- `$table->string('isbn', 13)->unique();`: 最大13文字の文字列カラムを作成し、一意制約を設定します。ISBNは重複してはいけないためです。
- `$table->date('published_date')->nullable();`: 日付型のカラムを作成します。`nullable()` により、NULL値を許容します（出版日が不明な場合があるため）。

**`database/migrations/xxxx_xx_xx_xxxxxx_create_genres_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
```

**`database/migrations/xxxx_xx_xx_xxxxxx_create_book_genre_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('book_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // 同じ書籍に同じジャンルを複数回紐付けることを防ぐ
            $table->unique(['book_id', 'genre_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_genre');
    }
};
```

**思考プロセス**: 中間テーブルの命名規則
- Laravelの規約では、多対多リレーションの中間テーブル名は「関連する2つのテーブル名を単数形・アルファベット順に並べてアンダースコアで繋ぐ」となっています。
- `book` と `genre` → `book_genre` (アルファベット順で `b` < `g`)
- この規約に従うことで、モデルでリレーションを定義する際に、中間テーブル名を明示的に指定する必要がなくなります。

**`database/migrations/xxxx_xx_xx_xxxxxx_create_reviews_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->timestamps();

            // 同じユーザーが同じ書籍に複数レビューを投稿することを防ぐ
            $table->unique(['user_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
```

**コードリーディング:**
- `$table->unsignedTinyInteger('rating');`: 0〜255の範囲の整数を格納するカラムを作成します。評価は1〜5の範囲なので、`TINYINT UNSIGNED` で十分です。
- `$table->unique(['user_id', 'book_id']);`: 複合ユニーク制約を設定します。これにより、同じユーザーが同じ書籍に対して複数のレビューを投稿することを防ぎます。

**`database/migrations/xxxx_xx_xx_xxxxxx_create_favorites_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
```

**`database/migrations/xxxx_xx_xx_xxxxxx_create_review_likes_table.php`:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'review_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_likes');
    }
};
```

### Step 3: マイグレーションの実行

すべてのマイグレーションファイルを作成したら、データベースにテーブルを作成します。

```bash
sail artisan migrate
```

phpMyAdmin (`http://localhost:8080`) でテーブルが正しく作成されていることを確認してください。

## 2-3. モデルの作成

次に、各テーブルに対応するEloquentモデルを作成し、リレーションを定義します。

### Step 1: モデルファイルの生成

```bash
sail artisan make:model Book
sail artisan make:model Genre
sail artisan make:model Review
```

`User` モデルは最初から存在するため、作成不要です。`favorites` と `review_likes` は中間テーブルなので、通常はモデルを作成しません（Eloquentが自動的に処理します）。

### Step 2: 各モデルファイルの編集

**`app/Models/User.php`:**

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * ユーザーが登録した書籍
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * ユーザーが投稿したレビュー
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * ユーザーがお気に入りに登録した書籍（多対多）
     */
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites')->withTimestamps();
    }

    /**
     * ユーザーがいいねしたレビュー（多対多）
     */
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes')->withTimestamps();
    }
}
```

**コードリーディング:**
- `HasMany`: 1対多のリレーションを定義します。1人のユーザーは複数の書籍を登録できます。
- `BelongsToMany`: 多対多のリレーションを定義します。第2引数に中間テーブル名を指定します。
- `withTimestamps()`: 中間テーブルの `created_at` と `updated_at` を自動的に更新するようにします。

**`app/Models/Book.php`:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * 書籍を登録したユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 書籍に紐付くジャンル（多対多）
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    /**
     * 書籍に投稿されたレビュー
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * 書籍をお気に入りに登録したユーザー（多対多）
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }
}
```

**コードリーディング:**
- `$fillable`: マスアサインメント（`Book::create($data)` のような一括代入）を許可するカラムを指定します。セキュリティ上、許可するカラムを明示的に指定することが重要です。
- `$casts`: カラムの型変換を指定します。`published_date` を `date` にキャストすることで、Carbonインスタンスとして扱えるようになります。
- `BelongsTo`: 多対1のリレーションを定義します。書籍は1人のユーザーに属します。

**`app/Models/Genre.php`:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * ジャンルに紐付く書籍（多対多）
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withTimestamps();
    }
}
```

**`app/Models/Review.php`:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
    ];

    /**
     * レビューを投稿したユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * レビュー対象の書籍
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * レビューにいいねしたユーザー（多対多）
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_likes')->withTimestamps();
    }
}
```

---

これでDB設計とモデルの作成が完了しました。次のChapterでは、認証機能を実装していきます。
