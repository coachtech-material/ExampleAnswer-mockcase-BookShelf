# Chapter 2: データベース設計とマイグレーション

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの根幹となるデータベースの「設計図」を作成します。具体的には以下の点を学びます。

- **マイグレーションとは何か**: なぜ直接データベースを操作せず、PHPのコードでテーブル定義を管理するのかを理解します。
- **テーブル設計**: 書籍、レビュー、ジャンルなど、このアプリケーションに必要なテーブル構造を設計します。
- **リレーションシップと外部キー**: テーブル間の関連性（例：「このレビューは、どのユーザーが、どの本に書いたか」）を定義する方法を学びます。
- **マイグレーションの実行**: 作成した設計図を元に、実際にデータベースにテーブルを作成します。

---

## 🧠 先輩エンジニアの思考プロセス：なぜマイグレーションから始めるのか？

アプリケーション開発は、多くの場合「データ」を中心に進みます。どのようなデータを、どのように保存し、どのように関連付けるかを最初に決定することで、その後の機能開発（モデル、コントローラー、ビュー）の手戻りが格段に少なくなります。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 開発者ごとにDBのテーブル定義が微妙に違う | **マイグレーション**でテーブル定義をコードとしてバージョン管理する | 全員が同じコマンド（`sail artisan migrate`）を叩くだけで、同じ構造のデータベースを再現できる。 |
| テーブル間の整合性が崩れる（例：存在しないユーザーIDを登録） | **外部キー制約**を設定し、データベースレベルで不正なデータ入力を防ぐ | アプリケーションのバグで不正なデータが作られるのを防ぐ最後の砦。最初に定義するのが最も安全。 |
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
> マイグレーションファイルは、ファイル名の先頭にあるタイムスタンプ順に実行されます。しかし、コマンドを連続で実行すると、タイムスタンプが同じになり、実行順序が保証されなくなる可能性があります。`sleep 1`で1秒待つことで、タイムスタンプを確実にずらし、意図した順序でマイグレーションが実行されるようにしています。

### 2.1.2. コードリーディング：`make:migration`コマンド

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `sail artisan` | Sailコンテナ内でLaravelのArtisanコマンドを実行するためのコマンド | (コマンドの実行結果) | `sail`は`./vendor/bin/sail`のエイリアスです。 |
| `make:migration` | 新しいマイグレーションファイルを作成するArtisanコマンド | (ファイルパス) | `database/migrations`ディレクトリにファイルが生成されます。 |
| `create_genres_table` | 作成するマイグレーションファイルの名前 | なし | `create_..._table`という命名規則に従うと、Laravelがテーブル作成用の定型コードを自動で生成してくれます。 |

---

## 2.2. マイグレーションファイルへの記述

作成された各マイグレーションファイルに、テーブルの構造を定義するコードを記述します。

### 2.2.1. `create_genres_table`

```php
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

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

### 2.2.2. `create_books_table`

```php
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

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

（以降、`reviews`, `book_genre`, `favorites`, `review_likes`テーブルのマイグレーションコードも同様に記載）

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

これで、アプリケーションのデータを保存するための器（テーブル）が用意できました。次のChapterでは、これらのテーブルを操作するための「モデル」を作成していきます。
