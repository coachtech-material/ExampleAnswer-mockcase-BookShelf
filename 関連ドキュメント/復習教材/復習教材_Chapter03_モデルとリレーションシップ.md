# Chapter 03: 「データベースの通訳者」 - モデルとリレーションシップ

## 🎯 このセクションで学ぶこと

Chapter 02で作成したデータベースの「テーブル」を、LaravelのEloquent ORMを通じて操作するための「モデル」を作成します。モデルは、**「データベースのテーブルと会話するための通訳者」**です。この通訳者がいるおかげで、SQLの細かい方言を気にすることなく、PHPという共通言語でデータベースと対話できるのです。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| モデルの役割 | なぜ直接 SQL を書くのではなく、Eloquent ORM（PHP のオブジェクト）を通じてデータベースを操作するのか |
| リレーションシップの定義 | モデル間の関連（`hasMany`, `belongsTo`, `belongsToMany`）を定義し、関連データを取得・操作できるようにする |
| マスアサインメント | `$fillable` プロパティを設定し、意図しないデータがDBに保存されるのを防ぐ |

> **注:** リレーションメソッドへの戻り値型宣言（`: HasMany` 等）・PHPDoc・`$casts` といった応用品質コードは、Chapter 15「既存コードへの品質強化」で一括追加する。本 Chapter では Basic 段階の素直なモデル定義に絞る。

---

## 1. はじめに 📖

データベースの「構造（テーブル）」が決まったら、次はその構造をPHPの「オブジェクト」として扱えるようにします。SQLを直接書く代わりに、`Book::find(1)`のように直感的なコードでデータを操作できるようになり、開発効率とコードの可読性が劇的に向上します。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| SQLを直接書くのは面倒で、間違いやすい | **Eloquentモデル**を使い、PHPのオブジェクトとしてDBを操作する | `User`が`users`テーブル、`Book`が`books`テーブルに対応し、直感的に扱える。 |
| 関連するデータを取得するのが大変 | **リレーションシップ**をモデルに定義する | `$user->books`のように、プロパティにアクセスする感覚で関連データを取得できる。 |
| フォームから送られてきた不要なデータまでDBに保存されてしまう | **マスアサインメント（`$fillable`）**で、保存を許可するカラムを明示的に指定する | セキュリティの基本。意図しないカラム（例：`is_admin`）が勝手に更新されるのを防ぐ。 |

---

## 2. 要件の確認 📋

### 3.1. モデルの作成

Artisanコマンドを使って、モデルファイルを作成します。

```bash
sail artisan make:model Book
sail artisan make:model Review
sail artisan make:model Genre
```

`app/Models` ディレクトリに PHP ファイルが作成されます。

> **注意:** `Favorite` モデルと `ReviewLike` モデルは作成しない。`favorites` / `review_likes` テーブルは独立エンティティとしてサロゲートキー（`id`）を持つが、CRUD ロジックは User / Book / Review に定義した `belongsToMany` リレーション経由で行う（`favoriteBooks` / `favoritedByUsers` / `likedReviews` / `likedByUsers`）。Eloquent の `belongsToMany` は `attach`/`detach`/`toggle`/`sync` を提供するため、独立した Eloquent モデルがなくても十分に CRUD 操作できる。応用機能で必要な `ReadingPlan` モデルは Chapter 20（読書計画機能 + リマインダー通知）で作成する。

---

## 3. 先輩エンジニアの思考プロセス 💭

### リレーションのペア関係

`hasMany` と `belongsTo` は常にペアで使われます。
- `User` が `Book` をたくさん持つ (`hasMany`) <-> `Book` は `User` に属する (`belongsTo`)
- `Book` が `Review` をたくさん持つ (`hasMany`) <-> `Review` は `Book` に属する (`belongsTo`)

`belongsToMany` も常にペアで使われます。
- `User` が `Book` をたくさんお気に入りする (`belongsToMany`) <-> `Book` はたくさんの `User` にお気に入りされる (`belongsToMany`)

このペアを意識すると、リレーションの定義がスムーズになります。

---

## 4. 実装 🚀

### 3.2.1. `User` モデル

`app/Models/User.php` を以下のように編集します。

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

> **応用機能編との関係:** `HasApiTokens` トレイトの追加は Chapter 16（Sanctum 認証層）で行う。Chapter 03 段階では追加しない。リレーションメソッドへの戻り値型宣言（`: HasMany` 等）と PHPDoc は Chapter 15（既存コードへの品質強化）で一括追加する。

### 3.2.2. `Book` モデル

`app/Models/Book.php` を以下のように編集します。

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

> **応用機能編との関係:** `$casts` への `published_date => 'date'` 追加、リレーションメソッドの戻り値型宣言と PHPDoc は Chapter 15（既存コードへの品質強化）で一括追加する。

### 3.2.3. `Review` モデル

`app/Models/Review.php` を以下のように編集します。

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

`app/Models/Genre.php` を以下のように編集します。

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

## 5. コードの詳細解説 🔍

### User モデル

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use HasFactory, Notifiable;` | ファクトリと通知機能を有効化するトレイトです。 | `HasApiTokens` トレイトは Chapter 16（Sanctum 認証層）で追加します。 |
| `protected $fillable = [...]` | `name`, `email`, `password` への一括代入を許可します。 | `$fillable` はマスアサインメントの「ホワイトリスト」です。 |
| `protected $hidden = [...]` | `password` と `remember_token` をJSON出力時に隠します。 | `$hidden` は情報漏洩を防ぐ「ブラックリスト」です。 |
| `protected function casts(): array` | `email_verified_at` を Carbon 日付オブジェクトに、`password` をハッシュ化して保存するためのキャストを設定します。 | Laravel の属性キャスト機能です。 |
| `public function books()` | ユーザーが登録した書籍の一覧を取得するリレーションです。 | `hasMany` は「1対多」のリレーションです。 |

### Book モデル

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function user()` | この書籍を登録したユーザーを取得します。 | `belongsTo` は「多対1」で、`hasMany` の逆の関係です。 |
| `public function genres()` | 書籍が属するジャンルを取得します。 | 中間テーブル名が Laravel の命名規則（`book_genre`）に従っているため、テーブル名の指定は省略可能です。 |
| `public function favoritedByUsers()` | 書籍をお気に入りしているユーザーを取得します。 | 中間テーブル名が命名規則と異なるテーブル名（`favorites`）のため、第二引数で明示しています。 |

> **応用機能編との関係:** `$casts = ['published_date' => 'date']` の追加は Chapter 15（既存コードへの品質強化）で行う。Chapter 03 段階では `$casts` は定義しない。

---

## 6. この実装にたどり着くための調べ方 🧐

分からないことがあった時、AIに聞くプロンプト例を紹介します。

| 疑問 | プロンプト例 |
|:---|:---|
| リレーションの種類が分からない | 「Laravel の hasMany, belongsTo, belongsToMany の違いを、具体的なテーブル例で教えてください。」 |
| $fillable と $guarded の違い | 「Laravel のマスアサインメント保護で、$fillable と $guarded のどちらを使うべきですか？それぞれのメリット・デメリットを教えてください。」 |
| casts の使い方 | 「Laravel モデルの $casts プロパティと casts() メソッドの違いを教えてください。'password' => 'hashed' は何をしますか？」 |

---

## 7. 動作確認 ✅

| 確認項目 | 確認方法 |
|:---|:---|
| モデルとテーブルの紐付け | `sail artisan tinker` で `User::first()` を呼んで `App\\Models\\User` のインスタンスが返ること |
| リレーション動作 | `sail artisan tinker` で `User::first()->books` を呼んで紐付く Book Collection が返ること |
| Mass Assignment | `User::create([...])` で `$fillable` で許可した属性のみ保存できること |

---

## 8. まとめ ✨

このChapterでは、Chapter 02で作成したテーブルに対応するモデルを4つ作成し、リレーションシップとマスアサインメントを定義しました。

| モデル | テーブル | リレーション | 特記事項 |
|:---|:---|:---|:---|
| `User` | users | hasMany(Book, Review), belongsToMany(Book via favorites, Review via review_likes) | casts() メソッド（HasApiTokens は Chapter 16 で追加） |
| `Book` | books | belongsTo(User), hasMany(Review), belongsToMany(Genre, User via favorites) | -- |
| `Review` | reviews | belongsTo(User, Book), belongsToMany(User via review_likes) | -- |
| `Genre` | genres | belongsToMany(Book) | $fillable は `['name']` のみ |

※ `Favorite` / `ReviewLike` の独立モデルは作成しない（前述の通り `belongsToMany` 経由でアクセスする）。

次のChapterでは、アプリケーションの「入り口」となる認証機能を実装していきます。
