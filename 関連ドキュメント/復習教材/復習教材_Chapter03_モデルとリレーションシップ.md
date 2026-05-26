# Chapter 03: 「データベースの通訳者」 - モデルとリレーションシップ

## 🎯 このセクションで学ぶこと

Chapter 02で作成したデータベースの「テーブル」を、LaravelのEloquent ORMを通じて操作するための「モデル」を作成します。モデルは、**「データベースのテーブルと会話するための通訳者」**です。この通訳者がいるおかげで、SQLの細かい方言を気にすることなく、PHPという共通言語でデータベースと対話できるのです。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| モデルの役割 | なぜ直接 SQL を書くのではなく、Eloquent ORM（PHP のオブジェクト）を通じてデータベースを操作するのか |
| リレーションシップの定義 | モデル間の関連（`hasMany`, `belongsTo`, `belongsToMany`）を定義し、関連データを取得・操作できるようにする |
| マスアサインメント | `$fillable` プロパティを設定し、意図しないデータがDBに保存されるのを防ぐ |
| PHPDoc と型定義 | `@var array<int, string>` やメソッドの戻り値型でコードの意図を明確化 |

---

## 1. はじめに 📖

データベースの「構造（テーブル）」が決まったら、次はその構造をPHPの「オブジェクト」として扱えるようにします。SQLを直接書く代わりに、`Book::find(1)`のように直感的なコードでデータを操作できるようになり、開発効率とコードの可読性が劇的に向上します。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| SQLを直接書くのは面倒で、間違いやすい | **Eloquentモデル**を使い、PHPのオブジェクトとしてDBを操作する | `User`が`users`テーブル、`Book`が`books`テーブルに対応し、直感的に扱える。 |
| 関連するデータを取得するのが大変 | **リレーションシップ**をモデルに定義する | `$user->books`のように、プロパティにアクセスする感覚で関連データを取得できる。 |
| フォームから送られてきた不要なデータまでDBに保存されてしまう | **マスアサインメント（`$fillable`）**で、保存を許可するカラムを明示的に指定する | セキュリティの基本。意図しないカラム（例：`is_admin`）が勝手に更新されるのを防ぐ。 |
| メソッドが何を返すか分かりにくい | **戻り値の型定義**と**PHPDoc**を追加する | `public function books(): HasMany` のように型を明記し、IDE補完も効くようになる。 |

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

> **注意:** `Favorite` モデルと `ReviewLike` モデルは作成しない。`favorites` / `review_likes` テーブルは独立エンティティとしてサロゲートキー（`id`）を持つが、CRUD ロジックは User / Book / Review に定義した `belongsToMany` リレーション経由で行う（`favoriteBooks` / `favoritedByUsers` / `likedReviews` / `likedByUsers`）。Eloquent の `belongsToMany` は `attach`/`detach`/`toggle`/`sync` を提供するため、独立した Eloquent モデルがなくても十分に CRUD 操作できる。応用機能で必要な `ReadingPlan` モデルは Chapter 21（読書計画機能 + リマインダー通知）で作成する。

---

## 3. 先輩エンジニアの思考プロセス 💭

### リレーションのペア関係

`hasMany` と `belongsTo` は常にペアで使われます。
- `User` が `Book` をたくさん持つ (`hasMany`) <-> `Book` は `User` に属する (`belongsTo`)
- `Book` が `Review` をたくさん持つ (`hasMany`) <-> `Review` は `Book` に属する (`belongsTo`)

`belongsToMany` も常にペアで使われます。
- `User` が `Book` をたくさんお気に入りする (`belongsToMany`) <-> `Book` はたくさんの `User` にお気に入りされる (`belongsToMany`)

このペアを意識すると、リレーションの定義がスムーズになります。

### HasApiTokens トレイトの先行追加

`User` モデルに `HasApiTokens` トレイトを追加していますが、これは Step 18（Sanctum認証API）で使用するための先行準備です。

---

## 4. 実装 🚀

### 3.2.1. `User` モデル

`app/Models/User.php` を以下のように編集します。

```php
<?php

namespace App\Models;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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
     * ユーザーがお気に入りに登録した書籍
     */
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites');
    }

    /**
     * ユーザーがいいねしたレビュー
     */
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

### 3.2.2. `Book` モデル

`app/Models/Book.php` を以下のように編集します。

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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
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
     * 書籍に対するレビュー
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * 書籍のジャンル
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * 書籍をお気に入りに登録したユーザー
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
```

### 3.2.3. `Review` モデル

`app/Models/Review.php` を以下のように編集します。

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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
     * レビューにいいねしたユーザー
     */
    public function likedByUsers(): BelongsToMany
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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * ジャンルに属する書籍
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
```

### 3.2.5. `Favorite` モデル

`app/Models/Favorite.php` を以下のように編集します。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
    ];

    /**
     * お気に入りを登録したユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * お気に入りに登録された書籍
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
```

### 3.2.6. `ReviewLike` モデル

`app/Models/ReviewLike.php` を以下のように編集します。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewLike extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'review_id',
    ];

    /**
     * いいねしたユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * いいねされたレビュー
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
```

---

## 5. コードの詳細解説 🔍

### User モデル

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use HasApiTokens, HasFactory, Notifiable;` | Sanctum API認証、ファクトリ、通知機能を有効化するトレイトです。 | `HasApiTokens` は Step 18 で使用します。 |
| `protected $fillable = [...]` | `name`, `email`, `password` への一括代入を許可します。 | `$fillable` はマスアサインメントの「ホワイトリスト」です。 |
| `protected $hidden = [...]` | `password` と `remember_token` をJSON出力時に隠します。 | `$hidden` は情報漏洩を防ぐ「ブラックリスト」です。 |
| `protected function casts(): array` | `email_verified_at` を Carbon 日付オブジェクトに、`password` をハッシュ化して保存するためのキャストを設定します。 | Laravel の属性キャスト機能です。 |
| `public function books(): HasMany` | ユーザーが登録した書籍の一覧を取得するリレーションです。 | `: HasMany` が戻り値の型定義。IDE補完が効きます。 |

### Book モデル

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $casts = ['published_date' => 'date']` | `published_date` を Carbon 日付オブジェクトとして扱えるようにします。 | `$book->published_date->format('Y/m/d')` のように日付操作が可能になります。 |
| `public function user(): BelongsTo` | この書籍を登録したユーザーを取得します。 | `belongsTo` は「多対1」で、`hasMany` の逆の関係です。 |
| `public function genres(): BelongsToMany` | 書籍が属するジャンルを取得します。 | 中間テーブル名が Laravel の命名規則（`book_genre`）に従っているため、テーブル名の指定は省略可能です。 |
| `public function favoritedByUsers(): BelongsToMany` | 書籍をお気に入りしているユーザーを取得します。 | 中間テーブル名が命名規則と異なるテーブル名（`favorites`）のため、第二引数で明示しています。 |

---

## 6. この実装にたどり着くための調べ方 🧐

分からないことがあった時、AIに聞くプロンプト例を紹介します。

| 疑問 | プロンプト例 |
|:---|:---|
| リレーションの種類が分からない | 「Laravel の hasMany, belongsTo, belongsToMany の違いを、具体的なテーブル例で教えてください。」 |
| $fillable と $guarded の違い | 「Laravel のマスアサインメント保護で、$fillable と $guarded のどちらを使うべきですか？それぞれのメリット・デメリットを教えてください。」 |
| PHPDoc の書き方 | 「Laravel モデルの PHPDoc で @var array<int, string> と書く意味を教えてください。」 |
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

このChapterでは、Chapter 02で作成したテーブルに対応するモデルを6つ作成し、リレーションシップとマスアサインメントを定義しました。

| モデル | テーブル | リレーション | 特記事項 |
|:---|:---|:---|:---|
| `User` | users | hasMany(Book, Review), belongsToMany(Book via favorites, Review via review_likes) | HasApiTokens, casts() メソッド |
| `Book` | books | belongsTo(User), hasMany(Review), belongsToMany(Genre, User via favorites) | $casts で published_date を date に |
| `Review` | reviews | belongsTo(User, Book), belongsToMany(User via review_likes) | -- |
| `Genre` | genres | belongsToMany(Book) | $fillable は `['name']` のみ |

※ `Favorite` / `ReviewLike` の独立モデルは作成しない（前述の通り `belongsToMany` 経由でアクセスする）。

次のChapterでは、アプリケーションの「入り口」となる認証機能を実装していきます。
