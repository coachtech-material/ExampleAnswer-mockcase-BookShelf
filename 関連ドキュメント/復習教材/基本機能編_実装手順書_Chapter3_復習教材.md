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

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

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

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $fillable = [...]` | `name`（ユーザー名）、`email`（メールアドレス）、`password`（パスワード）の3つのカラムに対して、`create()`メソッドなどによる一括代入を許可します。これにより、ユーザー登録フォームから送られてきたデータを一度にモデルにセットして保存できます。 | `protected`は、このプロパティ（変数）がこのクラス内と、このクラスを継承したクラス内からのみアクセスできることを示すPHPのアクセス修飾子です。`$fillable`はEloquentモデルの特別なプロパティで、マスアサインメントの脆弱性を防ぐための「ホワイトリスト」として機能します。 |
| `protected $hidden = [...]` | `password`（パスワード）と`remember_token`（ログイン維持用のトークン）を、モデルがJSONや配列形式で出力される際に自動的に隠します。これにより、APIなどでユーザー情報を返す際に、これらの機密情報が外部に漏れるのを防ぎます。 | `$hidden`もEloquentの特別なプロパティです。こちらはマスアサインメントとは逆で、意図しない情報漏洩を防ぐための「ブラックリスト」として機能します。 |
|` protected $casts = [...] `| `email_verified_at` カラムの値を日付操作オブジェクト（Carbon）として扱えるように自動変換します。また、 `password` カラムに値がセットされる際に自動的にハッシュ化します。 | Laravel 10の属性キャストは `$casts` プロパティで定義します。`'password' => 'hashed'` を指定すると、モデルにパスワードを代入して保存する時に自動でハッシュ化されます。 |
| `public function books()` | 1人のユーザーが投稿した複数の書籍（`Book`モデル）を取得するためのリレーションを定義します。このメソッドを定義することで、`$user->books`という形で簡単に関連データを取得できるようになります。 | `public`は、このメソッドがクラスの外部からでも呼び出せることを示すアクセス修飾子です。`function books()`で`books`という名前のメソッドを定義しています。`$this->hasMany(Book::class)`は、「このUserモデルは、Bookモデルを多数持っている（1対多）」という関係性をEloquentに伝えています。 |
| `public function reviews()` | 1人のユーザーが投稿した複数のレビュー（`Review`モデル）を取得するためのリレーションを定義します。`$user->reviews`で取得できます。 | `hasMany`は「1対多」のリレーションを定義するメソッドです。`User`が「1」で、`Review`が「多」の関係になります。 |
| `public function favoriteBooks()` | ユーザーがお気に入り登録した書籍（`Book`モデル）の一覧を取得するためのリレーションを定義します。`$user->favoriteBooks`で取得できます。 | `belongsToMany` は「多対多」のリレーションを定義します。中間テーブル `favorites` を介してデータを取得します。Laravelの命名規則（`book_user`）とは異なるテーブル名を使用しているため、第二引数でテーブル名を指定しています。 |
| `public function likedReviews()` | ユーザーがいいねしたレビュー（`Review`モデル）の一覧を取得するためのリレーションを定義します。`$user->likedReviews`で取得できます。 | こちらも「多対多」です。中間テーブル `review_likes` を使用します。「誰がどのレビューをいいねしたか」という情報を管理する専用のテーブルを介して、関連する情報を結びつけています。 |

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

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $fillable = [...]` | `user_id`（登録したユーザーのID）、`title`（書籍名）、`author`（著者名）、`isbn`（ISBNコード）など、書籍の登録・更新時にフォームから一括で代入・保存を許可するカラムを指定しています。 | `user_id`も`$fillable`に含めることで、コントローラー側で`$request->user()->books()->create(...)`のように、認証済みユーザーに紐づけて書籍を簡単に作成できるようになります。 |
| `public function user()` | この書籍を登録したユーザー（`User`モデル）の情報を取得するためのリレーションを定義します。`$book->user`で取得できます。 | `belongsTo`は「多対1」のリレーションを定義し、`hasMany`の逆の関係を示します。`books`テーブルが持つ`user_id`カラムを元に、`users`テーブルの対応するレコードを探しに行きます。 |
| `public function reviews()` | この書籍に投稿された複数のレビュー（`Review`モデル）を取得するためのリレーションを定義します。`$book->reviews`で取得できます。 | `Book`が「1」で、`Review`が「多」の「1対多」の関係です。 |
| `public function genres()` | この書籍が属する複数のジャンル（`Genre`モデル）を取得するためのリレーションを定義します。`$book->genres`で取得できます。 | `belongsToMany`による「多対多」リレーションです。中間テーブル名は`book_genre`となり、Laravelの命名規則（モデル名をアルファベット順に並べてアンダースコアで繋ぐ）に従っているため、第二引数のテーブル名指定は省略できます。 |
| `public function favoritedByUsers()` | この書籍をお気に入り登録している複数のユーザー（`User`モデル）を取得するためのリレーションを定義します。`$book->favoritedByUsers`で取得できます。 | こちらも`belongsToMany`による「多対多」リレーションです。命名規則（`book_user`）とは異なるテーブル名（`favorites`）を使用するため、第二引数で明示的に指定しています。 |

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

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $fillable = [...]` | `user_id`（投稿者ID）、`book_id`（対象書籍ID）、`rating`（評価点）、`comment`（コメント内容）の4つのカラムへの一括代入を許可します。 | これにより、レビュー投稿フォームからのリクエストデータを使って、`Review::create($request->all())`のようなシンプルなコードでレコードを作成できます。 |
| `public function user()` | このレビューを投稿したユーザー（`User`モデル）の情報を取得します。`$review->user`で取得できます。 | `belongsTo`による「多対1」のリレーションです。`reviews`テーブルの`user_id`カラムを外部キーとして使用します。 |
| `public function book()` | このレビューがどの書籍（`Book`モデル）に対するものかを取得します。`$review->book`で取得できます。 | こちらも`belongsTo`による「多対1」のリレーションです。`reviews`テーブルの`book_id`カラムを外部キーとして使用します。 |
| `public function likedByUsers()` | このレビューに「いいね」した複数のユーザー（`User`モデル）を取得します。`$review->likedByUsers`で取得できます。 | `belongsToMany`による「多対多」のリレーションです。命名規則（`review_user`）とは異なるテーブル名（`review_likes`）を使用するため、第二引数で明示的に指定しています。|

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

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $fillable = ['name']` | ジャンルを登録・更新する際に、`name`（ジャンル名）カラムへの一括代入を許可します。 | 配列の要素が1つだけなので、`['name']`という短い書き方になっています。 |
| `public function books()` | このジャンルに属する複数の書籍（`Book`モデル）を取得します。`$genre->books`で取得できます。 | `belongsToMany`による「多対多」のリレーションです。中間テーブル名は命名規則に従った`book_genre`が自動的に使用されます。 |

---

> **🧠 先輩エンジニアの思考プロセス**
> `hasMany`と`belongsTo`は常にペアで使われます。
> - `User`が`Book`をたくさん持つ (`hasMany`) → `Book`は`User`に属する (`belongsTo`)
> - `Book`が`Review`をたくさん持つ (`hasMany`) → `Review`は`Book`に属する (`belongsTo`)
> 
> `belongsToMany`も常にペアで使われます。
> - `User`が`Book`をたくさんお気に入りする (`belongsToMany`) → `Book`はたくさんの`User`にお気に入りされる (`belongsToMany`)
> 
> このペアを意識すると、リレーションの定義がスムーズになります。

これで、モデルの基本的な設定は完了です。次のChapterでは、アプリケーションの「入り口」となる認証機能と、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
