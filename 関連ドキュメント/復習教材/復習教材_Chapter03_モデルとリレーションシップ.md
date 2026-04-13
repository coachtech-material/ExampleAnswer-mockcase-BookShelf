# Chapter 03: 「データベースの通訳者」 - モデルとリレーションシップ

## 🎯 このChapterの目標

Chapter 02で作成したデータベースの「テーブル」を、LaravelのEloquent ORMを通じて操作するための「モデル」を作成します。モデルは、**「データベースのテーブルと会話するための通訳者」**です。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| モデルの役割 | なぜ直接SQLを書かずに、PHPのオブジェクトを通じてデータベースを操作するのか |
| リレーションシップの定義 | モデル間の関連（`hasMany`, `belongsTo`, `belongsToMany`）を定義し、関連データを簡単に取得 |
| マスアサインメント | `$fillable` プロパティを設定し、意図しないデータがDBに保存されるのを防ぐ |

---

## 📖 背景知識：なぜマイグレーションの次にモデルなのか？

データベースの「構造（テーブル）」が決まったら、次はその構造をPHPの「オブジェクト」として扱えるようにします。SQLを直接書く代わりに、`Book::find(1)`のように直感的なコードでデータを操作できるようになり、開発効率とコードの可読性が劇的に向上します。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| SQLを直接書くのは面倒で、間違いやすい | **Eloquentモデル**を使い、PHPのオブジェクトとしてDBを操作する | `User`が`users`テーブル、`Book`が`books`テーブルに対応し、直感的に扱える。 |
| 関連するデータを取得するのが大変 | **リレーションシップ**をモデルに定義する | `$user->books`のように、プロパティにアクセスする感覚で関連データを取得できる。 |
| フォームから送られてきた不要なデータまでDBに保存されてしまう | **マスアサインメント（`$fillable`）**で、保存を許可するカラムを明示的に指定する | セキュリティの基本。意図しないカラムが勝手に更新されるのを防ぐ。 |

---

## 📋 実装の手順

### 3.1. モデルの作成

```bash
sail artisan make:model Book
sail artisan make:model Review
sail artisan make:model Genre
```

`app/Models`ディレクトリにPHPファイルが作成されます。

> **注意:** 基本版では `Favorite` モデルと `ReviewLike` モデルは作成しません。中間テーブルの操作は `User` モデルに定義した `belongsToMany` リレーションを通じて行います。

---

## 💭 なぜこう作るのか？

### リレーションのペア関係

`hasMany` と `belongsTo` は常にペアで使われます。
- `User` が `Book` をたくさん持つ (`hasMany`) <-> `Book` は `User` に属する (`belongsTo`)
- `Book` が `Review` をたくさん持つ (`hasMany`) <-> `Review` は `Book` に属する (`belongsTo`)

`belongsToMany` も常にペアで使われます。
- `User` が `Book` をたくさんお気に入りする (`belongsToMany`) <-> `Book` はたくさんの `User` にお気に入りされる (`belongsToMany`)

このペアを意識すると、リレーションの定義がスムーズになります。

---

## 🚀 コードの実装

### 3.2.1. `User` モデル

`app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function books()
    {
        return $this->hasMany(Book::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favoriteBooks()
    {
        return $this->belongsToMany(Book::class, 'favorites');
    }

    public function likedReviews()
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

### 3.2.2. `Book` モデル

`app/Models/Book.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'image_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class);
    }

    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
```

### 3.2.3. `Review` モデル

`app/Models/Review.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'review_likes');
    }
}
```

### 3.2.4. `Genre` モデル

`app/Models/Genre.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
```

---

## 🔍 コードリーディング

### User モデル

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use HasFactory, Notifiable;` | ファクトリ、通知機能を有効化するトレイトです。 | テスト用のダミーデータ生成や、メール通知機能で使用します。 |
| `protected $fillable = [...]` | `name`, `email`, `password` への一括代入を許可します。 | `$fillable` はマスアサインメントの「ホワイトリスト」です。 |
| `protected $hidden = [...]` | `password` と `remember_token` をJSON出力時に隠します。 | `$hidden` は情報漏洩を防ぐ「ブラックリスト」です。 |
| `protected function casts(): array` | `email_verified_at` をCarbon日付オブジェクトに、`password` を自動ハッシュ化します。 | Laravel の属性キャスト機能です。 |
| `public function books()` | ユーザーが登録した書籍の一覧を取得するリレーションです。 | 戻り値の型は明示していませんが、`HasMany` が返されます。 |

### Book モデル

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function user()` | この書籍を登録したユーザーを取得します。 | `belongsTo` は「多対1」で、`hasMany` の逆の関係です。 |
| `public function genres()` | 書籍が属するジャンルを取得します。 | 中間テーブル名は命名規則（`book_genre`）に従っているため省略可能です。 |
| `public function favoritedByUsers()` | 書籍をお気に入りしているユーザーを取得します。 | 命名規則と異なるテーブル名（`favorites`）を第二引数で明示しています。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| リレーションの種類が分からない | 「Laravel の hasMany, belongsTo, belongsToMany の違いを、具体的なテーブル例で教えてください。」 |
| $fillable と $guarded の違い | 「Laravel のマスアサインメント保護で、$fillable と $guarded のどちらを使うべきですか？」 |
| casts の使い方 | 「Laravel モデルの casts() メソッドの使い方を教えてください。'password' => 'hashed' は何をしますか？」 |

---

## ✨ このChapterのまとめ

| モデル | テーブル | リレーション | 特記事項 |
|:---|:---|:---|:---|
| `User` | users | hasMany(Book, Review), belongsToMany(Book via favorites, Review via review_likes) | casts() メソッド |
| `Book` | books | belongsTo(User), hasMany(Review), belongsToMany(Genre, User via favorites) | -- |
| `Review` | reviews | belongsTo(User, Book), belongsToMany(User via review_likes) | -- |
| `Genre` | genres | belongsToMany(Book) | $fillable は `['name']` のみ |

次のChapterでは、アプリケーションの「入り口」となる認証機能を実装していきます。
