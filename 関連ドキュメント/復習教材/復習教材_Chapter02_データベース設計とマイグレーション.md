# Chapter 02: 「データベースのGit」 - データベース設計とマイグレーショ���

## 🎯 このセクションで学ぶこと

このChapterでは、**Chapter 00で策定した「データベース基本設計書」を、実際のコード（マイグレーション）に落とし込む**プロセスを学びます。マイグレーションは、**「データベースの世界のGit」**のようなものです。変更履歴を追いかけられ、いつでも過去の状態に戻したり、他の人と共有したりできます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| マイグレーションとは何か | なぜ直接データベースを操作せず、PHPのコードでテーブル定義を管理するのか |
| 設計書からコードへの変換 | Chapter 00のテーブル定義書の「VARCHAR(13)」や「UNIQUE制約」をLaravelで表現する方法 |
| リレーションシップと外部キー | ER図のテーブル間の「線」を `foreignId()` と `constrained()` で表現する方法 |
| マイグレーションの実行 | 作成した設計図を元に、実際にデータベースにテーブルを作成する |

---

## 1. はじめに 📖

アプリケーション開発は、多くの場合「データ」を中心に進みます。**Chapter 00でPMと合意形成した「データの形」**を最初にコードで確定させることで、その後の機能開発（モデル、コントローラー、ビュー）の手戻りが格段に少なくなります。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 開発者ごとにDBのテーブル定義が微妙に違う | **マイグレーション**でテーブル定義をコードとしてバージョン管理する | 全員が同じコマンド（`sail artisan migrate`）を叩くだけで、同じ構造のデータベースを再現できる。 |
| Chapter 00で決めた「ISBNは重複不可」が守られない | **UNIQUE制約**をマイグレーションで定義し、データベースレベルで不正なデータ入力を防ぐ | アプリケーションのバグで不正なデータが作られるのを防ぐ最後の砦。最初に定義するのが最も安全。 |
| 後からカラムを追加・変更するのが大変 | マイグレーションファイルを追加・編集し、変更履歴をコードで管理する | 誰が、いつ、どのような理由でDB構造を変更したかが一目瞭然になり、チーム開発がスムーズに進む。 |

---

## 2. 要件の確認 📋

### 2.1. マイグレーションファイルの作成

まずは、各テーブルの設計図となるマイグレーションファイルを作成します。

```bash
sail artisan make:migration create_books_table
sail artisan make:migration create_genres_table
sail artisan make:migration create_book_genre_table
sail artisan make:migration create_reviews_table
sail artisan make:migration create_favorites_table
sail artisan make:migration create_review_likes_table
```

> **重要：マイグレーションのタイムスタンプ順序について**
> `make:migration` を連続実行するとタイムスタンプが同一になり、ファイル名のアルファベット順で実行される場合があります。`book_genre` テーブルは `genres` テーブルへの外部キーを持つため、必ず **`genres` のマイグレーションが先に実行される**ようタイムスタンプの順序を確認してください。もし問題が発生した場合は、ファイル名のタイムスタンプ部分を手動で修正して正しい順序にしてください。

### 2.2. マイグレーションファイルへの記述

作成された各���イグレーションファイルに、**Chapter 00のテーブル定義書**の内容をコードで記述していきます。

---

## 3. 先輩エンジニアの思考プロセス 💭

各マイグレーションの設計判断を、Chapter 00のテーブル定義書と対応させて理解しましょう。

| 設計書の判断 | マイグレーションでの表現 | 背景 |
|:---|:---|:---|
| ユーザー退会時にデータ連動削除 | `onDelete('cascade')` | Phase 4で決定した「Cascade」ルール |
| ISBN は nullable + UNIQUE | `->nullable()->unique()` | 応用版ではISBNは任意入力 |
| 出版日は任意入力 | `->nullable()` | Bladeのフォームで任意とされている |
| 中間テーブルにidとtimestamps | `$table->id()` + `$table->timestamps()` | Eloquent標準に合わせた設計 |

---

## 4. 実装 🚀

### 2.2.1. `create_books_table`

`database/migrations/YYYY_MM_DD_XXXXXX_create_books_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 13)->nullable()->unique();
            $table->date('published_date')->nullable();
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

`database/migrations/YYYY_MM_DD_XXXXXX_create_genres_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table): void {
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

`database/migrations/YYYY_MM_DD_XXXXXX_create_book_genre_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_genre', function (Blueprint $table): void {
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

`database/migrations/YYYY_MM_DD_XXXXXX_create_reviews_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
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

`database/migrations/YYYY_MM_DD_XXXXXX_create_favorites_table.php`

> **設計方針:** `favorites` テーブルは「お気に入り」という**独立したエンティティ**として扱うため、`book_genre`（純粋な中間テーブル）と異なり**サロゲートキー（`id`）を持たせ、`(user_id, book_id)` には複合 unique 制約**で「1ユーザー1書籍1件」を担保する。Eloquent は標準で複合主キーをサポートしないため、サロゲートキーを採用することで `belongsToMany` 経由の操作も整合する。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
```

### 2.2.6. `create_review_likes_table`

`database/migrations/YYYY_MM_DD_XXXXXX_create_review_likes_table.php`

> **設計方針:** `review_likes` テーブルも `favorites` と同様に「いいね」という独立エンティティとして扱うため、サロゲートキー（`id`）+ `(user_id, review_id)` の複合 unique 制約で実装する。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('review_id')->constrained()->onDelete('cascade');
            $table->unique(['user_id', 'review_id']);
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

## 5. コードの詳細解説 🔍

### 設計書からコードへの変換対応表

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->id()` | `id` (BIGINT, PK, AUTO_INCREMENT) | Laravelの標準的な主キー定義。`unsignedBigInteger`で`auto_increment`な`id`カラムを作成します。 |
| `$table->foreignId('user_id')->constrained()` | FK(`users.id`) | Laravelの命名規則（`テーブル名_id`）に従っているため、`constrained()` だけで`users`テーブルの`id`カラムへの参照を自動設定��ます。 |
| `->onDelete('cascade')` | **CASCADE DELETE** | Chapter 00で「ユーザー退会時は全て消えて良い」と決めた仕様。参照先レコードが削除された時、このレコードも自動削除されます。 |
| `$table->string('isbn', 13)->nullable()->unique()` | `isbn` (VARCHAR(13), NULLABLE, UNIQUE) | Chapter 00のヒアリングで決めた仕様をコードに反映。応用版ではISBNは任意入力のため `nullable()` を付けています。 |
| `$table->date('published_date')->nullable()` | `published_date` (DATE, NULLABLE) | 出版日も任意入力です。 |
| `$table->text('description')->nullable()` | `description` (TEXT, NULLABLE) | 「任意」と決めた仕様を実現。 |
| `$table->tinyInteger('rating')` | `rating` (TINYINT) | 1~5の評価値。`tinyInteger` は -128~127 の範囲を持つ最小の整数型です。 |
| `$table->timestamps()` | `created_at`, `updated_at` | Laravelの標準機能で、レコードの作成日時と更新日時を自動管理します。 |
| `function (Blueprint $table): void` | 型定義 | クロージャの戻り値が何もないこと(`void`)を明示。コードの可読性が向上します。 |

### make:migration コマンド

| 部分 | 説明 | ポイント |
|:---|:---|:---|
| `sail artisan make:migration` | 新しいマイグレーションファイルを作成するArtisanコマンド | `database/migrations`ディレクトリにファイルが生成されます。 |
| `create_genres_table` | ファイル名 | `create_..._table`という命名規則に従うと、Laravelがテーブル作成用の定型コードを自動生成します。 |

---

## 6. この実装にたどり着くための調べ方 🧐

分からないことがあった時、AIに聞くプロンプト例を紹介します。

| 疑問 | プロンプト例 |
|:---|:---|
| 外部キーの書き方 | 「Laravel のマイグレーションで foreignId と constrained を使って外部キーを定義する方法を教えてください。onDelete との違いも含めて。」 |
| 複合ユニーク制約 | 「Laravel のマイグレーションで2つのカラムの組み合わせに unique 制約をかける方法を教えてください。primary との違いも教えてください。」 |
| nullable の判断 | 「Laravel のマイグレーションで nullable() を付けるべきカラムの判断基準を教えてください。」 |
| マイグレーションの実行順序 | 「Laravel のマイグレーションの実行順序はどう決まりますか？外部キーの依存関係がある場合の注意点を教えてください。」 |
| tinyInteger vs unsignedTinyInteger | 「Laravel の tinyInteger と unsignedTinyInteger の違いを教えてください。評価(1-5)を格納するにはどちらが適切ですか？」 |

---

## 7. 動作確認 ✅

マイグレーションが正しく実行されたか確認しましょう。

| 確認項目 | 確認方法 |
|:---|:---|
| マイグレーション成功 | `sail artisan migrate` 実行時にエラーが出ないこと |
| テーブルの存在 | phpMyAdmin (`http://localhost:8080`) で `bookshelf` データベースに7テーブル + Laravel標準テーブルが存在すること |
| カラムの確認 | phpMyAdmin で各テーブルの構造タブを開き、カラム名・型・NULL許容が設計書と一致すること |

> **トラブルシューティング:**
> `sail up -d` の後すぐに `sail artisan migrate` を実行すると「Connection refused」エラーが発生する場合があります。MySQLコンテナの起動完了まで30秒ほど待ってから再実行してください。
>
> 「Table already exists」エラーが発生した場合は、以下のコマンドでボリュームをクリアしてください:
> ```bash
> sail down -v
> sail up -d
> sail artisan migrate
> ```

---

## 8. まとめ ✨

このChapterでは、**Chapter 00で策定したデータベース基本設計書**を、Laravelのマイグレーションコードに変換するプロセスを学びました。

| 設計書の項目 | Laravelのコード | 解説 |
|:---|:---|:---|
| BIGINT, PK, AUTO_INCREMENT | `$table->id()` | 主キーの定義 |
| FK(`users.id`) | `$table->foreignId('user_id')->constrained()` | 外部キーの定義 |
| CASCADE DELETE | `->onDelete('cascade')` | 親レコード削除時の連動削除 |
| VARCHAR(13), NULLABLE, UNIQUE | `$table->string('isbn', 13)->nullable()->unique()` | 桁数指定 + 任意 + 重複禁止 |
| TINYINT | `$table->tinyInteger('rating')` | 最小の整数型 |
| 複合主キー | `$table->primary(['col1', 'col2'])` | 2カラムの組み合わせで主キーを定義 |

これで、**Chapter 00で設計した通りの構造**で、アプリケーションのデータを保存するための器（テーブル）が用意できました。次のChapterでは、これらのテーブルを操作するための「モデル」を作成していきます。
