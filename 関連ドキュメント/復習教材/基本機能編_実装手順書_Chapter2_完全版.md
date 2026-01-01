# Chapter 2: データベース設計とマイグレーション

このChapterでは、書籍レビュー管理システムに必要なデータベーステーブルを設計し、マイグレーションファイルを作成します。

---

## 2.1. マイグレーションファイルの確認

本プロジェクトのリポジトリには、既にマイグレーションファイルが含まれています。`database/migrations` ディレクトリを確認してください。

```bash
ls database/migrations/
```

以下のファイルが存在することを確認します：

- `YYYY_MM_DD_XXXXXX_create_genres_table.php`
- `YYYY_MM_DD_XXXXXX_create_books_table.php`
- `YYYY_MM_DD_XXXXXX_create_reviews_table.php`
- `YYYY_MM_DD_XXXXXX_create_book_genre_table.php`
- `YYYY_MM_DD_XXXXXX_create_favorites_table.php`
- `YYYY_MM_DD_XXXXXX_create_review_likes_table.php`

> **重要：マイグレーションファイルについて**
> リポジトリに既にマイグレーションファイルが存在するため、**`sail artisan make:migration` コマンドは実行しないでください**。実行すると同じ名前のファイルが重複して作成され、マイグレーション実行時に「Table already exists」エラーが発生します。

> **重要：マイグレーションの実行順序について**
> マイグレーションファイルはタイムスタンプ順に実行されます。`book_genre` テーブルは `genres` テーブルと `books` テーブルに外部キーで依存しているため、必ず **`genres` と `books` のマイグレーションが先に実行される必要があります**。

## 2.2. マイグレーションファイルの内容確認

リポジトリに含まれているマイグレーションファイルの内容を確認しましょう。各ファイルは以下のように定義されています。

### `database/migrations/YYYY_MM_DD_XXXXXX_create_books_table.php`

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

### `database/migrations/YYYY_MM_DD_XXXXXX_create_reviews_table.php`

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

### `database/migrations/YYYY_MM_DD_XXXXXX_create_genres_table.php`

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

### `database/migrations/YYYY_MM_DD_XXXXXX_create_book_genre_table.php`

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

### `database/migrations/YYYY_MM_DD_XXXXXX_create_favorites_table.php`

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

### `database/migrations/YYYY_MM_DD_XXXXXX_create_review_likes_table.php`

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

## 2.3. マイグレーションの実行

```bash
sail artisan migrate
```
