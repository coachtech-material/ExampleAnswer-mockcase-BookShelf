# Chapter 02: 「データベースのGit」 - データベース設計とマイグレーション

## 🎯 このChapterの目標

このChapterでは、**Chapter 00で策定した「データベース基本設計書」を、実際のコード（マイグレーション）に落とし込む**プロセスを学びます。マイグレーションは、**「データベースの世界のGit」**のようなものです。変更履歴を追いかけられ、いつでも過去の状態に戻したり、他の人と共有したりできます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| マイグレーションとは何か | なぜ直接データベースを操作せず、PHPのコードでテーブル定義を管理するのか |
| 設計書からコードへの変換 | Chapter 00のテーブル定義書の「VARCHAR(13)」や「UNIQUE制約」をLaravelで表現する方法 |
| リレーションシップと外部キー | ER図のテーブル間の「線」を `foreignId()` と `constrained()` で表現する方法 |
| マイグレーションの実行 | 作成した設計図を元に、実際にデータベースにテーブルを作成する |

---

## 📖 背景知識：なぜマイグレーションから始めるのか？

アプリケーション開発は、多くの場合「データ」を中心に進みます。**Chapter 00でPMと合意形成した「データの形」**を最初にコードで確定させることで、その後の機能開発（モデル、コントローラー、ビュー）の手戻りが格段に少なくなります。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 開発者ごとにDBのテーブル定義が微妙に違う | **マイグレーション**でテーブル定義をコードとしてバージョン管理する | 全員が同じコマンド（`sail artisan migrate`）を叩くだけで、同じ構造のデータベースを再現できる。 |
| Chapter 00で決めた「ISBNは重複不可」が守られない | **UNIQUE制約**をマイグレーションで定義し、データベースレベルで不正なデータ入力を防ぐ | アプリケーションのバグで不正なデータが作られるのを防ぐ最後の砦。最初に定義するのが最も安全。 |
| 後からカラムを追加・変更するのが大変 | マイグレーションファイルを追加・編集し、変更履歴をコードで管理する | 誰が、いつ、どのような理由でDB構造を変更したかが一目瞭然になり、チーム開発がスムーズに進む。 |

---

## 📋 実装の手順

### 2.1. マイグレーションファイルの作成

```bash
sail artisan make:migration create_genres_table
sail artisan make:migration create_books_table
sail artisan make:migration create_reviews_table
sail artisan make:migration create_book_genre_table
sail artisan make:migration create_favorites_table
sail artisan make:migration create_review_likes_table
```

> **重要：マイグレーションのタイムスタンプ順序について**
> `book_genre` テーブルは `genres` テーブルへの外部キーを持つため、必ず **`genres` のマイグレーションが先に実行される**ようタイムスタンプの順序を確認してください。

---

## 💭 なぜこう作るのか？

| 設計書の判断 | マイグレーションでの表現 | 背景 |
|:---|:---|:---|
| ユーザー退会時にデータ連動削除 | `onDelete('cascade')` | Phase 4で決定した「Cascade」ルール |
| ISBN は NOT NULL + UNIQUE | `->unique()` (nullable なし) | 基本版ではISBNは必須入力 |
| 出版日は必須入力 | nullable なし | 基本版では出版日は必須 |
| コメントは必須 | `$table->text('comment')` (nullable なし) | 基本版ではコメントは必須入力 |
| 中間テーブルは複合主キー | `$table->primary(['col1', 'col2'])` | idカラムやtimestampsを持たないシンプルな構造 |

---

## 🚀 コードの実装

### 2.2.1. `create_books_table`

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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
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

### 2.2.2. `create_genres_table`

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

### 2.2.3. `create_book_genre_table`

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

### 2.2.4. `create_reviews_table`

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
            $table->tinyInteger('rating');
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

### 2.2.5. `create_favorites_table`

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

### 2.2.6. `create_review_likes_table`

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

### 2.3. マイグレーションの実行

```bash
sail artisan migrate
```

---

## 🔍 コードリーディング

### 設計書からコードへの変換対応表

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->id()` | `id` (BIGINT, PK, AUTO_INCREMENT) | Laravelの標準的な主キー定義。 |
| `$table->foreignId('user_id')->constrained()` | FK(`users.id`) | 命名規則に従っているため、`constrained()` だけで参照を自動設定。 |
| `->onDelete('cascade')` | **CASCADE DELETE** | 参照先レコードが削除された時、このレコードも自動削除。 |
| `$table->string('isbn', 13)->unique()` | `isbn` (VARCHAR(13), NOT NULL, UNIQUE) | 基本版ではISBNは必須入力。 |
| `$table->date('published_date')` | `published_date` (DATE, NOT NULL) | 基本版では出版日は必須。 |
| `$table->text('comment')` | `comment` (TEXT, NOT NULL) | 基本版ではコメントは必須。 |
| `$table->primary(['book_id', 'genre_id'])` | 複合主キー | 中間テーブルの主キー定義。idカラムは不要。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| 外部キーの書き方 | 「Laravel のマイグレーションで foreignId と constrained を使って外部キーを定義する方法を教えてください。onDelete('cascade') の使い方も含めて。」 |
| 複合主キー | 「Laravel のマイグレーションで2つのカラムの組み合わせに primary キーを設定する方法を教えてください。」 |
| nullable の判断 | 「Laravel のマイグレーションで nullable() を付けるべきカラムの判断基準を教えてください。」 |
| マイグレーションの実行順序 | 「Laravel のマイグレーションの実行順序はどう決まりますか？外部キーの依存関係がある場合の注意点を教えてください。」 |

---

## ✅ 動作確認

| 確認項目 | 確認方法 |
|:---|:---|
| マイグレーション成功 | `sail artisan migrate` 実行時にエラーが出ないこと |
| テーブルの存在 | phpMyAdmin で7テーブル + Laravel標準テーブルが存在すること |
| カラムの確認 | 各テーブルの構造タブでカラム名・型・NULL許容が設計書と一致すること |

---

## ✨ このChapterのまとめ

| 設計書の項目 | Laravelのコード | 解説 |
|:---|:---|:---|
| BIGINT, PK, AUTO_INCREMENT | `$table->id()` | 主キーの定義 |
| FK(`users.id`) | `$table->foreignId('user_id')->constrained()` | 外部キーの定義 |
| CASCADE DELETE | `->onDelete('cascade')` | 親レコード削除時の連動削除 |
| VARCHAR(13), NOT NULL, UNIQUE | `$table->string('isbn', 13)->unique()` | 桁数指定 + 必須 + 重複禁止 |
| TINYINT | `$table->tinyInteger('rating')` | 最小の整数型 |
| 複合主キー | `$table->primary(['col1', 'col2'])` | 中間テーブルの主キー |

次のChapterでは、これらのテーブルを操作するための「モデル」を作成していきます。
