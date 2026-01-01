# Chapter 3: Eloquentモデルとリレーションシップ

このChapterでは、前のChapterで作成したデータベーステーブルを操作するためのEloquent（エロクエント）モデルを作成し、モデル間の関連性（リレーションシップ）を定義します。

## 3-1. 先輩エンジニアの思考プロセス：なぜ設計の後にモデルを定義するのか？

Chapter 2でデータベースの「設計図」であるテーブル定義書とER図を完成させました。では、なぜその直後にモデルとリレーションシップを定義するのでしょうか？

> **思考プロセス：モデルは「データベースとの対話役」**
> 
> 1.  **役割分担の明確化**: データベース（MySQL）は**データを保管・整理する専門家**です。一方、アプリケーション（Laravel）は**ビジネスロジックを実行する専門家**です。この二者がスムーズに連携するための「通訳」兼「対話役」が**Eloquentモデル**です。
> 
> 2.  **先に「何を・どう保管するか」を決める**: 家を建てる時、まず「どんな部屋がいくつ必要か」「部屋と部屋をどう繋ぐか」という**設計図（＝テーブル定義・ER図）**を決めますよね。データベース設計も同じで、まずデータの保管場所とその構造を確定させることが最優先です。設計図なしに家を建て始めると、後から「柱が邪魔でドアが開かない！」といった手戻りが発生します。
> 
> 3.  **設計図を元に「対話役」を用意する**: データベースの構造が決まったら、次はその構造をLaravelアプリケーションが理解できる形に変換します。`users`テーブルには`User`モデル、`books`テーブルには`Book`モデルというように、テーブル一つひとつに対応する「対話役」を用意します。これがモデル作成のプロセスです。
> 
> 4.  **「対話役」同士の関係性を教える**: さらに、「書籍はユーザーによって登録される（`Book`は`User`に属する）」、「書籍は複数のレビューを持つ（`Book`は多数の`Review`を持つ）」といった、ER図で定義した関係性をモデルに教え込みます。これがリレーションシップの定義です。これにより、`$book->user`や`$book->reviews`のように、直感的なコードで関連データにアクセスできるようになります。

この「**データベース設計 → モデル作成 → リレーション定義**」という流れは、**データの構造とアプリケーションのロジックを綺麗に分離**し、見通しが良く、メンテナンス性の高いコードを書くための基本戦略なのです。

## 3-2. Eloquent ORMとは？

Eloquentは、Laravelに標準で搭載されている**ORM (Object-Relational Mapping)** です。ORMは、データベースのテーブルと、PHPの「オブジェクト（クラスのインスタンス）」を対応付け（マッピング）してくれる技術です。

Eloquentを使うと、`Book::find(1)` のように、まるでPHPのオブジェクトを操作するかのように直感的にデータベースを扱え、`$book->user->name` のように、テーブル間の関連をオブジェクトのプロパティのように簡単にたどることができます。

## 3-3. モデルの作成

Artisanコマンドを使って、`Book`, `Genre`, `Review`の3つのモデルを生成します。

```bash
sail artisan make:model Book
sail artisan make:model Genre
sail artisan make:model Review
```

## 3-4. マスアサインメントの設定

モデルを編集して、`$fillable`プロパティを設定します。これは、リクエストのデータを一括でモデルに流し込んで登録・更新（マスアサインメント）する際に、どのカラムの変更を許可するかを指定する、セキュリティのための重要な設定です。

> **思考プロセス：なぜ`$fillable`が必要か？**
> もしこの設定がないと、悪意のあるユーザーがHTTPリクエストに `is_admin=1` のような、本来変更されるべきでないデータを紛れ込ませて送信してきた場合、意図せず管理者権限を付与してしまう、といった脆弱性が生まれる可能性があります。`$fillable`は、開発者が意図したカラムだけが一括操作の対象となることを保証する「ホワイトリスト」の役割を果たします。

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

## 3-5. リレーションシップの定義

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
