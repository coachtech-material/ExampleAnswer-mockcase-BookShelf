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

    public function likedReviews()
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

**🔬 コードリーディング**

| コード | 解説 |
|:---|:---|
| `protected $fillable = [...]` | **マスアサインメント**の設定です。`create`や`update`メソッドで一括して代入を許可するカラムを配列で指定します。これにより、意図しないカラム（例えば、管理者権限を示す`is_admin`など）が不正に更新されるのを防ぎます。 |
| `protected $hidden = [...]` | モデルをJSONや配列に変換する際に、**自動的に隠される**カラムを指定します。`password`のような機密情報をAPIレスポンスなどに含めないようにするためのセキュリティ設定です。 |
| `protected function casts(): array` | 属性（カラム）のデータ型を変換（キャスト）する設定です。`password`は自動的にハッシュ化され、`email_verified_at`は`Carbon`オブジェクトとして扱われるようになります。 |
| `public function books()` | **1対多**のリレーションを定義します。1人のユーザーは多数の書籍（`Book`）を登録できる、という関係を表現します。`$user->books`でユーザーが登録した書籍一覧を取得できます。 |
| `public function reviews()` | **1対多**のリレーションです。1人のユーザーは多数のレビュー（`Review`）を投稿できます。`$user->reviews`でユーザーが投稿したレビュー一覧を取得できます。 |
| `public function favoriteBooks()` | **多対多**のリレーションです。1人のユーザーは多数の書籍をお気に入り登録でき、1冊の書籍は多数のユーザーからお気に入り登録されます。第二引数の`'favorites'`は、Chapter 2で作成した**中間テーブル**の名前です。 |
| `public function likedReviews()` | **多対多**のリレーションです。1人のユーザーは多数のレビューに「いいね」でき、1つのレビューは多数のユーザーから「いいね」されます。第二引数の`'review_likes'`は、**中間テーブル**の名前です。 |

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

**🔬 コードリーディング**

| コード | 解説 |
|:---|:---|
| `protected $fillable = [...]` | 書籍を登録・更新する際に、フォームから一括で代入・保存を許可するカラムを指定しています。`user_id`も含まれている点に注目してください。 |
| `public function user()` | **多対1**のリレーションです。`belongsTo`は`hasMany`の逆の関係を定義します。この書籍がどのユーザー（`User`）に属しているかを示します。`$book->user`で書籍を登録したユーザー情報を取得できます。 |
| `public function reviews()` | **1対多**のリレーションです。1冊の書籍には多数のレビュー（`Review`）が投稿されます。`$book->reviews`でその書籍に投稿されたレビュー一覧を取得できます。 |
| `public function genres()` | **多対多**のリレーションです。1冊の書籍は多数のジャンル（`Genre`）に属することができます。`$book->genres`でその書籍が持つジャンル一覧を取得できます。中間テーブル名はLaravelの命名規則（`book_genre`）に従っているため、省略可能です。 |
| `public function favoritedByUsers()` | **多対多**のリレーションです。この書籍をお気に入り登録しているユーザー（`User`）の一覧を取得します。`$book->favoritedByUsers`で取得できます。 |

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

**🔬 コードリーディング**

| コード | 解説 |
|:---|:---|
| `protected $fillable = [...]` | レビューを投稿・更新する際に、一括代入を許可するカラムを指定しています。 |
| `public function user()` | **多対1**のリレーションです。このレビューがどのユーザー（`User`）によって投稿されたかを示します。`$review->user`で投稿者情報を取得できます。 |
| `public function book()` | **多対1**のリレーションです。このレビューがどの書籍（`Book`）に対するものかを示します。`$review->book`で対象の書籍情報を取得できます。 |
| `public function likedByUsers()` | **多対多**のリレーションです。このレビューに「いいね」したユーザー（`User`）の一覧を取得します。`$review->likedByUsers`で取得できます。 |

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

    protected $fillable = ['name'];

    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
```

**🔬 コードリーディング**

| コード | 解説 |
|:---|:---|
| `protected $fillable = ['name']` | ジャンルを登録・更新する際に、`name`カラムへの一括代入を許可しています。 |
| `public function books()` | **多対多**のリレーションです。このジャンルに属する書籍（`Book`）の一覧を取得します。`$genre->books`で取得できます。中間テーブル名はLaravelの命名規則（`book_genre`）に従っているため、省略可能です。 |

---

> **🧠 先輩エンジニアの思考プロセス**
> `hasMany`と`belongsTo`は常にペアで使われます。
> - `User`が`Book`をたくさん持つ (`hasMany`) → `Book`は`User`に属する (`belongsTo`)
> - `Book`が`Review`をたくさん持つ (`hasmany`) → `Review`は`Book`に属する (`belongsTo`)
> 
> `belongsToMany`も常にペアで使われます。
> - `User`が`Book`をたくさんお気に入りする (`belongsToMany`) → `Book`はたくさんの`User`にお気に入りされる (`belongsToMany`)
> 
> このペアを意識すると、リレーションの定義がスムーズになります。

これで、モデルの基本的な設定は完了です。次のChapterでは、アプリケーションの「入り口」となる認証機能と、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
