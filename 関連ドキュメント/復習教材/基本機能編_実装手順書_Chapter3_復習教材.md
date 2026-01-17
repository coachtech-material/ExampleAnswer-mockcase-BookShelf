# Chapter 3: モデルとリレーションシップ

## 🎯 このセクションで学ぶこと

Chapter 2で作成したデータベースの「テーブル」を、LaravelのEloquent ORMを通じて操作するための「モデル」を作成します。モデルは、テーブルの1行1行のデータを表現するオブジェクトです。

- **モデルの役割**: なぜ直接SQLを書かずに、PHPのオブジェクトを通じてデータベースを操作するのかを理解します。
- **リレーションシップの定義**: モデル間の関連（`hasMany`, `belongsTo`, `belongsToMany`）を定義し、関連するデータを簡単に取得できるようにします。
- **マスアサインメント**: `$fillable`プロパティを設定し、意図しないデータがデータベースに保存されるのを防ぐ方法を学びます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜマイグレーションの次にモデルなのか？

データベースの「構造（テーブル）」が決まったら、次はその構造をPHPの「オブジェクト」として扱えるようにします。これがモデルの役割です。SQLを直接書く代わりに、`Book::find(1)`のように直感的なコードでデータを操作できるようになり、開発効率とコードの可読性が劇的に向上します。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| SQLを直接書くのは面倒で、間違いやすい | **Eloquentモデル**を使い、PHPのオブジェクトとしてDBを操作する | `User`オブジェクトが`users`テーブル、`Book`オブジェクトが`books`テーブルに対応し、直感的に扱える。 |
| 関連するデータを取得するのが大変（例：ユーザーの投稿一覧） | **リレーションシップ**をモデルに定義する | `$user->books`のように、プロパティにアクセスする感覚で関連データを取得できる。 |
| フォームから送られてきた不要なデータまでDBに保存されてしまう | **マスアサインメント（`$fillable`）**で、保存を許可するカラムを明示的に指定する | セキュリティの基本。意図しないカラム（例：`is_admin`）が勝手に更新されるのを防ぐ。 |

モデルは、**「データベースのテーブルと会話するための通訳者」**です。この通訳者がいるおかげで、私たちはSQLの細かい方言を気にすることなく、PHPという共通言語でデータベースと対話できるのです。

---

## 3.1. モデルの作成

Artisanコマンドを使って、`Book`, `Review`, `Genre`モデルを作成します。

```bash
sail artisan make:model Book
sail artisan make:model Review
sail artisan make:model Genre
```

`app/Models`ディレクトリに3つのPHPファイルが作成されます。

---

## 3.2. リレーションシップとマスアサインメントの定義

モデル間の関連性を定義し、安全にデータを保存するための設定を行います。

### 3.2.1. `User`モデル

`app/Models/User.php`を以下のように編集します。

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

    public function reviewLikes()
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

### 3.2.2. `Book`モデル

`app/Models/Book.php`を以下のように編集します。

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
        'description',
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
        return $this->belongsToMany(Genre::class, 'book_genre');
    }

    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
```

### 3.2.3. `Review`モデル

`app/Models/Review.php`を以下のように編集します。

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

### 3.2.4. `Genre`モデル

`app/Models/Genre.php`を以下のように編集します。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_genre');
    }
}
```

> **🧠 先輩エンジニアの思考プロセス**
> `hasMany`と`belongsTo`は常にペアで使われます。
> - `User`が`Book`をたくさん持つ (`hasMany`) → `Book`は`User`に属する (`belongsTo`)
> - `Book`が`Review`をたくさん持つ (`hasMany`) → `Review`は`Book`に属する (`belongsTo`)
> このペアを意識すると、リレーションの定義がスムーズになります。

これで、モデルの基本的な設定は完了です。次のChapterでは、アプリケーションの「入り口」となる認証機能と、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
