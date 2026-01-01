'''# Chapter 3: Eloquentモデルとリレーションシップ

このChapterでは、前のChapterで作成したデータベーステーブルを操作するためのEloquent（エロクエント）モデルを作成し、モデル間の関連性（リレーションシップ）を定義します。

## 3-1. Eloquent ORMとは？

> **思考プロセス:**
> なぜモデルが必要なのでしょうか？ データベースを操作するには、通常「SQL」という言語を使います。例えば、`SELECT * FROM books WHERE id = 1` のようなコードです。しかし、アプリケーションの機能が複雑になるにつれて、書くべきSQLも長大で複雑になり、管理が大変になります。
> 
> ここで登場するのが**ORM (Object-Relational Mapping)** です。ORMは、データベースのテーブルと、PHPの「オブジェクト（クラスのインスタンス）」を対応付け（マッピング）してくれる技術です。Laravelに搭載されているORMが**Eloquent**です。
> 
> Eloquentを使うと、以下のようなメリットがあります。
> 
> - **直感的な操作**: `Book::find(1)` のように、まるでPHPのオブジェクトを操作するかのように直感的にデータベースを扱えます。SQLを直接書く必要はほとんどありません。
> - **安全性の向上**: Eloquentは、SQLインジェクションなどの一般的な脆弱性からアプリケーションを保護する仕組みを内蔵しています。
> - **コードの可読性・保守性の向上**: `->where(...)`, `->orderBy(...)` のようにメソッドチェーンでクエリを組み立てられるため、何をしているかが分かりやすく、後からの修正も容易です。
> - **リレーションシップ**: `book->user->name` のように、テーブル間の関連をオブジェクトのプロパティのように簡単にたどることができます。これがEloquentの最も強力な機能の一つです。

## 3-2. モデルの作成

Artisanコマンドを使って、`Book`, `Genre`, `Review`の3つのモデルを生成します。

```bash
sail artisan make:model Book
sail artisan make:model Genre
sail artisan make:model Review
```

これにより、`app/Models`ディレクトリに3つのファイルが生成されます。

## 3-3. マスアサインメントの設定

モデルを編集して、`$fillable`プロパティを設定します。これは、`Book::create($request->all())` のように、リクエストのデータを一括でモデルに流し込んで登録・更新（マスアサインメント）する際に、どのカラムの変更を許可するかを指定する、セキュリティのための重要な設定です。

> **思考プロセス:**
> なぜ`$fillable`が必要なのでしょうか？ もしこの設定がないと、悪意のあるユーザーがHTTPリクエストに `is_admin=1` のような、本来変更されるべきでないデータを紛れ込ませて送信してきた場合、意図せず管理者権限を付与してしまう、といった脆弱性が生まれる可能性があります。`$fillable`は、開発者が意図したカラムだけが一括操作の対象となることを保証する「ホワイトリスト」の役割を果たします。

**`app/Models/Book.php`**
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
}
```

**`app/Models/Genre.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = ['name'];
}
```

**`app/Models/Review.php`**
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
}
```

## 3-4. リレーションシップの定義

モデル間にER図で設計した通りのリレーションシップを定義します。これにより、`$book->reviews` のように関連するモデルのデータを簡単に取得できるようになります。

### Userモデルのリレーション

`User`モデルはデフォルトで存在するので、ファイルを開いて追記します。

**`app/Models/User.php`**
```php
// ... (use文など)

class User extends Authenticatable
{
    // ... (既存のコード)

    /**
     * このユーザーが登録した書籍を取得
     */
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    /**
     * このユーザーが投稿したレビューを取得
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
```
> **リレーション解説:**
> - `hasMany` (1対多): 1人のユーザーが**多数の**書籍を登録し、**多数の**レビューを投稿できます。`$user->books`のようにアクセスすると、そのユーザーに紐付く`Book`モデルのコレクション（配列のようなもの）が返されます。

### Bookモデルのリレーション

**`app/Models/Book.php`**
```php
// ... (use文など)

class Book extends Model
{
    // ... ($fillableなど)

    /**
     * この書籍を登録したユーザーを取得
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この書籍に紐付くレビューを取得
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍が属するジャンルを取得
     */
    public function genres()
    {
        return $this->belongsToMany(Genre::class);
    }
}
```
> **リレーション解説:**
> - `belongsTo` (多対1): 多数の書籍は、それぞれ**1人の**ユーザーに所属します。`$book->user`のようにアクセスすると、その書籍に紐付く`User`モデルが1つ返されます。`hasMany`とは逆の関係です。
> - `belongsToMany` (多対多): 1冊の書籍は**多数の**ジャンルに属することができ、1つのジャンルも**多数の**書籍を持つことができます。Eloquentは、モデル名（`book`と`genre`）から中間テーブル名（`book_genre`）を自動で推測し、そこを経由して関連データを取得します。`$book->genres`で、その書籍に紐付く`Genre`モデルのコレクションが返されます。

### Reviewモデルのリレーション

**`app/Models/Review.php`**
```php
// ... (use文など)

class Review extends Model
{
    // ... ($fillableなど)

    /**
     * このレビューを投稿したユーザーを取得
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * このレビューが紐付く書籍を取得
     */
    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
```

### Genreモデルのリレーション

**`app/Models/Genre.php`**
```php
// ... (use文など)

class Genre extends Model
{
    // ... ($fillableなど)

    /**
     * このジャンルに属する書籍を取得
     */
    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
```

---

これで、データベースのテーブルとアプリケーションのモデルが完全に関連付けられました。SQLを意識することなく、オブジェクト指向の考え方でデータを自在に扱える準備が整いました。次のChapterでは、これらのモデルを使って、最初の機能である書籍の一覧表示を実装します。
'''
