# Chapter 2: データベース設計とマイグレーション

## 🎯 このセクションで学ぶこと

このセクションでは、**Chapter 0で策定した「データベース基本設計書」を、実際のコード（マイグレーション）に落とし込む**プロセスを学びます。具体的には以下の点を学びます。

- **マイグレーションとは何か**: なぜ直接データベースを操作せず、PHPのコードでテーブル定義を管理するのかを理解します。
- **設計書からコードへ**: Chapter 0のテーブル定義書に書かれた「VARCHAR(13)」や「UNIQUE制約」を、Laravelのマイグレーションでどう表現するかを学びます。
- **リレーションシップと外部キー**: ER図に描かれたテーブル間の「線」を、`foreignId()`と`constrained()`を使ってコードで表現する方法を学びます。
- **マイグレーションの実行**: 作成した設計図を元に、実際にデータベースにテーブルを作成します。

---

## 🧠 先輩エンジニアの思考プロセス：なぜマイグレーションから始めるのか？

アプリケーション開発は、多くの場合「データ」を中心に進みます。**Chapter 0でPMと合意形成した「データの形」**を最初にコードで確定させることで、その後の機能開発（モデル、コントローラー、ビュー）の手戻りが格段に少なくなります。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 開発者ごとにDBのテーブル定義が微妙に違う | **マイグレーション**でテーブル定義をコードとしてバージョン管理する | 全員が同じコマンド（`sail artisan migrate`）を叩くだけで、同じ構造のデータベースを再現できる。 |
| Chapter 0で決めた「ISBNは重複不可」が守られない | **UNIQUE制約**をマイグレーションで定義し、データベースレベルで不正なデータ入力を防ぐ | アプリケーションのバグで不正なデータが作られるのを防ぐ最後の砦。最初に定義するのが最も安全。 |
| 後からカラムを追加・変更するのが大変 | マイグレーションファイルを追加・編集し、変更履歴をコードで管理する | 誰が、いつ、どのような理由でDB構造を変更したかが一目瞭然になり、チーム開発がスムーズに進む。 |

マイグレーションは、**「データベースの世界のGit」**のようなものです。変更履歴を追いかけられ、いつでも過去の状態に戻したり、他の人と共有したりできます。最初にこの仕組みを整えることが、堅牢なアプリケーション開発の第一歩です。

---

## 2.1. マイグレーションファイルの作成

まずは、各テーブルの設計図となるマイグレーションファイルを作成します。

### 2.1.1. コマンドの実行

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

> **🧠 先輩エンジニアの思考プロセス**
> なぜ`sleep 1`を入れるのか？
> マイグレーションファイルは、ファイル名の先頭にあるタイムスタンプ順に実行されます。しかし、コマンドを連続で実行すると、タイムスタンプが同じになり、実行順序が保証されなくなる可能性があります。`sleep 1`で1秒待つことで、タイムスタンプを確実にずらし、**Chapter 0のER図で定義した依存関係（例：`books`テーブルは`users`テーブルに依存）**を担保しています。

> **重要：マイグレーションの実行順序について**
> マイグレーションファイルはタイムスタンプ順に実行されます。`book_genre` テーブルは `genres` テーブルと `books` テーブルに外部キーで依存しているため、必ず **`genres` と `books` のマイグレーションが先に実行される必要があります**。
> コマンドをそのまま実行すれば問題ないとは思われますが、問題が起きた場合はファイル名を修正してマイグレーションが順番に行われるようにしてください。

### 2.1.2. コードリーディング：`make:migration`コマンド

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `sail artisan` | Sailコンテナ内でLaravelのArtisanコマンドを実行するためのコマンド | (コマンドの実行結果) | `sail`は`./vendor/bin/sail`のエイリアスです。 |
| `make:migration` | 新しいマイグレーションファイルを作成するArtisanコマンド | (ファイルパス) | `database/migrations`ディレクトリにファイルが生成されます。 |
| `create_genres_table` | 作成するマイグレーションファイルの名前 | なし | `create_..._table`という命名規則に従うと、Laravelがテーブル作成用の定型コードを自動で生成してくれます。 |

---

## 2.2. マイグレーションファイルへの記述

作成された各マイグレーションファイルに、**Chapter 0のテーブル定義書**の内容をコードで記述していきます。

---

### 2.2.1. `create_books_table`

**📖 Chapter 0 テーブル定義書 (books) との対応**

Chapter 0の「3.2. テーブル定義書」では、`books`テーブルは以下のように定義されていました。

| 論理名 | 物理名 | 型 | NULL | 制約・備考 |
| --- | --- | --- | --- | --- |
| ID | `id` | BIGINT | No | PK, AUTO_INCREMENT |
| ユーザーID | `user_id` | BIGINT | No | FK(`users.id`), **CASCADE DELETE** |
| タイトル | `title` | VARCHAR(255) | No |  |
| 著者 | `author` | VARCHAR(255) | No |  |
| ISBN | `isbn` | VARCHAR(13) | No | **UNIQUE**, 13桁固定 |
| 出版日 | `published_date` | DATE | No |  |
| 概要 | `description` | TEXT | **Yes** |  |
| 画像URL | `image_url` | VARCHAR(255) | **Yes** |  |
| 作成日時 | `created_at` | TIMESTAMP | Yes |  |
| 更新日時 | `updated_at` | TIMESTAMP | Yes |  |

**💻 マイグレーションコード**

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

**🔬 コードリーディング：設計書からコードへの変換**

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->id();` | `id` (BIGINT, PK, AUTO_INCREMENT) | Laravelの標準的な主キー定義です。`unsignedBigInteger`で`auto_increment`な`id`カラムを作成します。 |
| `$table->foreignId('user_id')` | `user_id` (BIGINT, FK) | Chapter 0のER図で「USERS ||--o{ BOOKS」と定義された関係を表現しています。`users`テーブルの`id`を参照する外部キーカラムを作成します。 |
| `->constrained()` | FK(`users.id`) | Laravelの命名規則（`テーブル名_id`）に従っているため、これだけで`users`テーブルの`id`カラムへの参照を自動的に設定します。 |
| `->onDelete('cascade')` | **CASCADE DELETE** | Chapter 0のヒアリングで「ユーザー退会時は全て消えて良い」と決めた仕様です。参照先の`users`レコードが削除された時、この`books`レコードも自動的に削除されます。 |
| `$table->string('isbn', 13)` | `isbn` (VARCHAR(13)) | `string`メソッドの第2引数で桁数を指定できます。**Chapter 0のヒアリング（Q3）**で「13桁固定・ハイフンなし」と決めた仕様をコードに反映しています。 |
| `->unique()` | **UNIQUE** | **Chapter 0のヒアリング（Q3）**で「一意（Unique）制約をかける」と決めた仕様です。同じISBNの書籍が重複登録されるのをデータベースレベルで防ぎます。 |
| `->nullable()` | **Yes** (NULL許容) | **Chapter 0のヒアリング（Q4）**で「任意入力」と決めた仕様を実現しています。`description`と`image_url`は空でも登録可能です。 |
| `$table->timestamps();` | `created_at`, `updated_at` (TIMESTAMP) | Laravelの標準機能で、レコードの作成日時と更新日時を自動管理します。 |

---

### 2.2.2. `create_reviews_table`

**📖 Chapter 0 テーブル定義書 (reviews) との対応**

| 論理名 | 物理名 | 型 | NULL | 制約・備考 |
| --- | --- | --- | --- | --- |
| ID | `id` | BIGINT | No | PK, AUTO_INCREMENT |
| ユーザーID | `user_id` | BIGINT | No | FK(`users.id`), **CASCADE DELETE** |
| 書籍ID | `book_id` | BIGINT | No | FK(`books.id`), **CASCADE DELETE** |
| 評価 | `rating` | TINYINT | No | 1〜5の整数 |
| コメント | `comment` | TEXT | No |  |
| 作成日時 | `created_at` | TIMESTAMP | Yes |  |
| 更新日時 | `updated_at` | TIMESTAMP | Yes |  |

**💻 マイグレーションコード**

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

**🔬 コードリーディング：設計書からコードへの変換**

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->foreignId('user_id')->constrained()->onDelete('cascade')` | FK(`users.id`), CASCADE DELETE | Chapter 0のER図「USERS ||--o{ REVIEWS」を表現。レビューを書いたユーザーが退会したら、そのレビューも削除されます。 |
| `$table->foreignId('book_id')->constrained()->onDelete('cascade')` | FK(`books.id`), CASCADE DELETE | Chapter 0のER図「BOOKS ||--o{ REVIEWS」を表現。書籍が削除されたら、その書籍に対するレビューも削除されます。 |
| `$table->unsignedTinyInteger('rating')` | `rating` (TINYINT) | **Chapter 0のヒアリング（Q5）**で「評価は1〜5の整数のみ」と決めた仕様です。`TINYINT`は0〜255の範囲を持つ最小の整数型で、1〜5の評価には十分です。`unsigned`で負の値を許可しません。 |
| `$table->text('comment')` | `comment` (TEXT) | レビューのコメント本文です。長文を想定して`TEXT`型を使用しています。 |

---

### 2.2.3. `create_genres_table`

**📖 Chapter 0 テーブル定義書 (genres) との対応**

| 論理名 | 物理名 | 型 | NULL | 制約・備考 |
| --- | --- | --- | --- | --- |
| ID | `id` | BIGINT | No | PK, AUTO_INCREMENT |
| ジャンル名 | `name` | VARCHAR(255) | No | **UNIQUE** |
| 作成日時 | `created_at` | TIMESTAMP | Yes |  |
| 更新日時 | `updated_at` | TIMESTAMP | Yes |  |

**💻 マイグレーションコード**

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

**🔬 コードリーディング：設計書からコードへの変換**

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->id();` | `id` (BIGINT, PK, AUTO_INCREMENT) | Laravelの標準的な主キー定義です。 |
| `$table->string('name')` | `name` (VARCHAR(255)) | `string`メソッドは、デフォルトで`VARCHAR(255)`型のカラムを作成します。 |
| `->unique()` | **UNIQUE** 制約 | 同じ名前のジャンルが重複登録されるのを防ぎます。例えば「小説」というジャンルは1つだけ存在できます。 |

---

### 2.2.4. `create_book_genre_table`

**📖 Chapter 0 テーブル定義書 (book_genre) との対応**

Chapter 0のヒアリング（Q2）で「書籍とジャンルは**多対多（N対N）の関係**」と決めたため、中間テーブルが必要です。

| 論理名 | 物理名 | 型 | NULL | 制約・備考 |
| --- | --- | --- | --- | --- |
| 書籍ID | `book_id` | BIGINT | No | FK(`books.id`), **CASCADE DELETE** |
| ジャンルID | `genre_id` | BIGINT | No | FK(`genres.id`), **CASCADE DELETE** |

* **複合主キー**: `(book_id, genre_id)` の組み合わせが主キーとなり、重複不可。

**💻 マイグレーションコード**

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

**🔬 コードリーディング：設計書からコードへの変換**

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->foreignId('book_id')->constrained()->onDelete('cascade')` | FK(`books.id`), CASCADE DELETE | Chapter 0のER図「BOOKS }|--|{ BOOK_GENRE」を表現。書籍が削除されたら、その書籍とジャンルの紐付けも削除されます。 |
| `$table->foreignId('genre_id')->constrained()->onDelete('cascade')` | FK(`genres.id`), CASCADE DELETE | Chapter 0のER図「GENRES }|--|{ BOOK_GENRE」を表現。ジャンルが削除されたら、そのジャンルと書籍の紐付けも削除されます。 |
| `$table->primary(['book_id', 'genre_id'])` | 複合主キー | 2つのカラムの組み合わせを主キーとして設定します。これにより、同じ書籍に同じジャンルを2回紐付けることはできなくなります。 |

> **🧠 先輩エンジニアの思考プロセス**
> なぜ中間テーブルには`id`カラムと`timestamps`がないのか？
> 中間テーブルは「関係性」だけを記録するテーブルです。「いつ紐付けられたか」という情報が不要な場合、`timestamps`は省略できます。また、`book_id`と`genre_id`の組み合わせ自体が一意なので、別途`id`カラムを設ける必要もありません。これにより、テーブルがシンプルになり、パフォーマンスも向上します。

---

### 2.2.5. `create_favorites_table`

**📖 Chapter 0 テーブル定義書 (favorites) との対応**

Chapter 0のヒアリング（Q1）で「ユーザーと書籍の多対多関係（お気に入り）」を管理するテーブルとして定義されました。

| 論理名 | 物理名 | 型 | NULL | 制約・備考 |
| --- | --- | --- | --- | --- |
| ユーザーID | `user_id` | BIGINT | No | FK(`users.id`), **CASCADE DELETE** |
| 書籍ID | `book_id` | BIGINT | No | FK(`books.id`), **CASCADE DELETE** |

* **複合主キー**: `(user_id, book_id)` の組み合わせが主キーとなり、重複不可。

**💻 マイグレーションコード**

`database/migrations/YYYY_MM_DD_XXXXXX_create_favorites_table.php`

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

**🔬 コードリーディング：設計書からコードへの変換**

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->foreignId('user_id')->constrained()->onDelete('cascade')` | FK(`users.id`), CASCADE DELETE | Chapter 0のER図「USERS ||--|{ FAVORITES」を表現。ユーザーが退会したら、そのユーザーのお気に入り情報も削除されます。 |
| `$table->foreignId('book_id')->constrained()->onDelete('cascade')` | FK(`books.id`), CASCADE DELETE | Chapter 0のER図「BOOKS ||--|{ FAVORITES」を表現。書籍が削除されたら、その書籍へのお気に入り情報も削除されます。 |
| `$table->primary(['user_id', 'book_id'])` | 複合主キー | 同じユーザーが同じ書籍を2回お気に入りに追加することはできません。 |

---

### 2.2.6. `create_review_likes_table`

**📖 Chapter 0 テーブル定義書 (review_likes) との対応**

Chapter 0のヒアリング（Q1）で「ユーザーとレビューの多対多関係（いいね）」を管理するテーブルとして定義されました。

| 論理名 | 物理名 | 型 | NULL | 制約・備考 |
| --- | --- | --- | --- | --- |
| ユーザーID | `user_id` | BIGINT | No | FK(`users.id`), **CASCADE DELETE** |
| レビューID | `review_id` | BIGINT | No | FK(`reviews.id`), **CASCADE DELETE** |

* **複合主キー**: `(user_id, review_id)` の組み合わせが主キーとなり、重複不可。

**💻 マイグレーションコード**

`database/migrations/YYYY_MM_DD_XXXXXX_create_review_likes_table.php`

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

**🔬 コードリーディング：設計書からコードへの変換**

| コード | 設計書との対応 | 解説 |
|:---|:---|:---|
| `$table->foreignId('user_id')->constrained()->onDelete('cascade')` | FK(`users.id`), CASCADE DELETE | Chapter 0のER図「USERS ||--|{ REVIEW_LIKES」を表現。ユーザーが退会したら、そのユーザーの「いいね」情報も削除されます。 |
| `$table->foreignId('review_id')->constrained()->onDelete('cascade')` | FK(`reviews.id`), CASCADE DELETE | Chapter 0のER図「REVIEWS ||--|{ REVIEW_LIKES」を表現。レビューが削除されたら、そのレビューへの「いいね」情報も削除されます。 |
| `$table->primary(['user_id', 'review_id'])` | 複合主キー | 同じユーザーが同じレビューに2回「いいね」することはできません。 |

---

## 2.3. マイグレーションの実行

定義された設計図を元に、データベースにテーブルを作成します。

### 2.3.1. コマンドの実行

```bash
# Dockerコンテナをバックグラウンドで起動
sail up -d

# MySQLコンテナが完全に起動するまで30秒ほど待機
sleep 30

# マイグレーションを実行してテーブルを作成
sail artisan migrate
```

### 2.3.2. コードリーディング：`artisan migrate`コマンド

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `sail artisan` | Sailコンテナ内でLaravelのArtisanコマンドを実行するためのコマンド | (コマンドの実行結果) | `sail`は`./vendor/bin/sail`のエイリアスです。 |
| `migrate` | `database/migrations`ディレクトリ内のまだ実行されていないマイグレーションを実行するコマンド | `void` | 実行済みのマイグレーションは`migrations`テーブルに記録され、二重実行はされません。 |

> **🧠 先輩エンジニアの思考プロセス**
> なぜ`sail up -d`の後に`sleep 30`を入れるのか？
> `sail up -d`コマンドはコンテナの「起動開始」を指示するだけで、MySQLデータベースがリクエストを受け付けられる状態になるまでには少し時間がかかります。その前に`migrate`を実行すると「`Connection refused`（接続拒否）」エラーが発生してしまうのです。`sleep`コマンドで意図的に待ち時間を作ることで、この問題を確実かつシンプルに回避できます。実務でもよく使われる堅実なテクニックです。

> **注意：Dockerボリュームについて**
> 以前に同じプロジェクト名やポートでDockerを使用したことがある場合、MySQLのデータボリュームに古いデータが残っている可能性があります。後のステップでマイグレーションを実行した際に「Table already exists」エラーが発生した場合は、以下のコマンドでボリュームをクリアしてください：
> ```bash
> sail down -v
> sail up -d
> sail artisan migrate
> ```

---

## 📝 このChapterのまとめ

このChapterでは、**Chapter 0で策定したデータベース基本設計書**を、Laravelのマイグレーションコードに変換するプロセスを学びました。

| 設計書の項目 | Laravelのコード | 解説 |
|:---|:---|:---|
| BIGINT, PK, AUTO_INCREMENT | `$table->id()` | 主キーの定義 |
| FK(`users.id`) | `$table->foreignId('user_id')->constrained()` | 外部キーの定義 |
| CASCADE DELETE | `->onDelete('cascade')` | 親レコード削除時の連動削除 |
| VARCHAR(13) | `$table->string('isbn', 13)` | 桁数指定の文字列 |
| UNIQUE | `->unique()` | 重複禁止制約 |
| NULL許容 | `->nullable()` | 空値を許可 |
| 複合主キー | `$table->primary(['col1', 'col2'])` | 複数カラムの組み合わせを主キーに |

これで、**Chapter 0で設計した通りの構造**で、アプリケーションのデータを保存するための器（テーブル）が用意できました。次のChapterでは、これらのテーブルを操作するための「モデル」を作成していきます。
