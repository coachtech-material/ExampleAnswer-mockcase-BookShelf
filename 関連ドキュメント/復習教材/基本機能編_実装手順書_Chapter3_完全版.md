# Chapter 3: Eloquentモデルとリレーションシップ

このChapterでは、前のChapterで作成したデータベーステーブルを操作するためのEloquent（エロクエント）モデルを作成し、モデル間の関連性（リレーションシップ）を定義します。

## 3-1. Eloquent ORMとは？

> **思考プロセス:**
> なぜモデルが必要なのでしょうか？ データベースを操作するには、通常「SQL」という言語を使います。しかし、アプリケーションの機能が複雑になるにつれて、書くべきSQLも長大で複雑になり、管理が大変になります。
> 
> ここで登場するのが**ORM (Object-Relational Mapping)** です。ORMは、データベースのテーブルと、PHPの「オブジェクト（クラスのインスタンス）」を対応付け（マッピング）してくれる技術です。Laravelに搭載されているORMが**Eloquent**です。
> 
> Eloquentを使うと、`Book::find(1)` のように、まるでPHPのオブジェクトを操作するかのように直感的にデータベースを扱え、`book->user->name` のように、テーブル間の関連をオブジェクトのプロパティのように簡単にたどることができます。

## 3-2. モデルの作成

Artisanコマンドを使って、`Book`, `Genre`, `Review`の3つのモデルを生成します。

```bash
sail artisan make:model Book
sail artisan make:model Genre
sail artisan make:model Review
```

## 3-3. マスアサインメントの設定

モデルを編集して、`$fillable`プロパティを設定します。これは、リクエストのデータを一括でモデルに流し込んで登録・更新（マスアサインメント）する際に、どのカラムの変更を許可するかを指定する、セキュリティのための重要な設定です。

> **思考プロセス:**
> なぜ`$fillable`が必要なのでしょうか？ もしこの設定がないと、悪意のあるユーザーがHTTPリクエストに `is_admin=1` のような、本来変更されるべきでないデータを紛れ込ませて送信してきた場合、意図せず管理者権限を付与してしまう、といった脆弱性が生まれる可能性があります。`$fillable`は、開発者が意図したカラムだけが一括操作の対象となることを保証する「ホワイトリスト」の役割を果たします。

**`app/Models/Book.php`**
```php
protected $fillable = [
    'user_id',
    'title',
    'author',
    'isbn',
    'published_date',
    'description',
    'image_url',
];
```

**`app/Models/Genre.php`**
```php
protected $fillable = ['name'];
```

**`app/Models/Review.php`**
```php
protected $fillable = [
    'user_id',
    'book_id',
    'rating',
    'comment',
];
```

## 3-4. リレーションシップの定義

モデル間にER図で設計した通りのリレーションシップを定義します。これにより、`$book->reviews` のように関連するモデルのデータを簡単に取得できるようになります。

### Userモデルのリレーション

**`app/Models/User.php`**
```php
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

/**
 * このユーザーがお気に入りに登録した書籍を取得
 */
public function favoriteBooks()
{
    return $this->belongsToMany(Book::class, 'favorites');
}

/**
 * このユーザーがいいねしたレビューを取得
 */
public function likedReviews()
{
    return $this->belongsToMany(Review::class, 'review_likes');
}
```
> **リレーション解説:**
> - `hasMany` (1対多): 1人のユーザーが**多数の**書籍やレビューを持つ関係。
> - `belongsToMany` (多対多): `favorites`テーブルや`review_likes`テーブルを介して、ユーザーと書籍、ユーザーとレビューが多対多の関係にあることを定義します。

### Bookモデルのリレーション

**`app/Models/Book.php`**
```php
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

/**
 * この書籍をお気に入りに登録したユーザーを取得
 */
public function favoritedByUsers()
{
    return $this->belongsToMany(User::class, 'favorites');
}
```
> **リレーション解説:**
> - `belongsTo` (多対1): 多数の書籍が**1人の**ユーザーに所属する関係。
> - `belongsToMany` (多対多): `book_genre`や`favorites`といった中間テーブルを介した関係を定義します。

### Reviewモデルのリレーション

**`app/Models/Review.php`**
```php
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

/**
 * このレビューをいいねしたユーザーを取得
 */
public function likedByUsers()
{
    return $this->belongsToMany(User::class, 'review_likes');
}
```

### Genreモデルのリレーション

**`app/Models/Genre.php`**
```php
// ... ($fillableなど)

/**
 * このジャンルに属する書籍を取得
 */
public function books()
{
    return $this->belongsToMany(Book::class);
}
```

---

これで、データベースのテーブルとアプリケーションのモデルが完全に関連付けられました。SQLを意識することなく、オブジェクト指向の考え方でデータを自在に扱える準備が整いました。次のChapterでは、これらのモデルを使って、アプリケーションの具体的な機能を実装していきます。
