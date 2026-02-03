# Chapter 3: モデルとリレーションシップ

## 🎯 このセクションで学ぶこと

Chapter 2で作成したデータベースの「テーブル」を、LaravelのEloquent ORMを通じて操作するための「モデル」を作成します。モデルは、テーブルの1行1行のデータを表現するオブジェクトです。

- **モデルの役割**: なぜ直接SQLを書かずに、PHPのオブジェクトを通じてデータベースを操作するのかを理解します。
- **リレーションシップの定義**: モデル間の関連（`hasMany`, `belongsTo`, `belongsToMany`）を定義し、関連するデータを簡単に取得できるようにします。
- **マスアサインメント**: `$fillable`プロパティを設定し、意図しないデータがデータベースに保存されるのを防ぐ方法を学びます。
- **型定義の活用**: リレーションメソッドに戻り値の型を追加し、コードの可読性と堅牢性を向上させます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜマイグレーションの次にモデルなのか？

データベースの「構造（テーブル）」が決まったら、次はその構造をPHPの「オブジェクト」として扱えるようにします。これがモデルの役割です。SQLを直接書く代わりに、`Book::find(1)`のように直感的なコードでデータを操作できるようになり、開発効率とコードの可読性が劇的に向上します。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| SQLを直接書くのは面倒で、間違いやすい | **Eloquentモデル**を使い、PHPのオブジェクトとしてDBを操作する | `User`オブジェクトが`users`テーブル、`Book`オブジェクトが`books`テーブルに対応し、直感的に扱える。 |
| 関連するデータを取得するのが大変（例：ユーザーの投稿一覧） | **リレーションシップ**をモデルに定義する | `$user->books`のように、プロパティにアクセスする感覚で関連データを取得できる。 |
| フォームから送られてきた不要なデータまでDBに保存されてしまう | **マスアサインメント（`$fillable`）**で、保存を許可するカラムを明示的に指定する | セキュリティの基本。意図しないカラム（例：`is_admin`）が勝手に更新されるのを防ぐ。 |
| メソッドが何を返すか分かりにくい | **戻り値の型定義**を追加する | `public function books(): HasMany` のように型を明記することで、メソッドの意図が明確になり、エディタの補完も効くようになる。 |

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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites');
    }

    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

**🔬 コードリーディング**

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use Illuminate\...\HasMany;` | `HasMany`リレーション型をインポートします。これにより、メソッドの戻り値の型として`HasMany`を指定できるようになります。 | `use`文は、他の名前空間にあるクラスやトレイトを現在のファイルで短い名前で使えるようにするためのPHPの機能です。 |
| `use Illuminate\...\BelongsToMany;` | `BelongsToMany`リレーション型をインポートします。 | 同様に、`BelongsToMany`を戻り値の型として指定するために必要です。 |
| `protected $fillable = [...]` | `name`（ユーザー名）、`email`（メールアドレス）、`password`（パスワード）の3つのカラムに対して、`create()`メソッドなどによる一括代入を許可します。 | `protected`は、このプロパティがこのクラス内と、このクラスを継承したクラス内からのみアクセスできることを示すPHPのアクセス修飾子です。`$fillable`はEloquentモデルの特別なプロパティで、マスアサインメントの脆弱性を防ぐための「ホワイトリスト」として機能します。 |
| `protected $hidden = [...]` | `password`（パスワード）と`remember_token`（ログイン維持用のトークン）を、モデルがJSONや配列形式で出力される際に自動的に隠します。 | `$hidden`もEloquentの特別なプロパティです。こちらはマスアサインメントとは逆で、意図しない情報漏洩を防ぐための「ブラックリスト」として機能します。 |
|` protected $casts = [...] `| `email_verified_at` カラムの値を日付操作オブジェクト（Carbon）として扱えるように自動変換します。また、 `password` カラムに値がセットされる際に自動的にハッシュ化します。 | Laravel 10の属性キャストは `$casts` プロパティで定義します。`'password' => 'hashed'` を指定すると、モデルにパスワードを代入して保存する時に自動でハッシュ化されます。 |
| `public function books(): HasMany` | 1人のユーザーが投稿した複数の書籍（`Book`モデル）を取得するためのリレーションを定義します。`$user->books`で取得できます。 | **`: HasMany`が戻り値の型定義です。** このメソッドが`HasMany`リレーションオブジェクトを返すことを明示しています。これにより、コードの意図が明確になり、IDE（統合開発環境）によるコード補完や静的解析の恩恵を受けられます。 |
| `public function reviews(): HasMany` | 1人のユーザーが投稿した複数のレビュー（`Review`モデル）を取得するためのリレーションを定義します。 | こちらも戻り値の型として`HasMany`を指定しています。 |
| `public function favoriteBooks(): BelongsToMany` | ユーザーがお気に入り登録した書籍（`Book`モデル）の一覧を取得するためのリレーションを定義します。 | **`: BelongsToMany`が戻り値の型定義です。** このメソッドが`BelongsToMany`リレーションオブジェクトを返すことを示します。 |
| `public function likedReviews(): BelongsToMany` | ユーザーがいいねしたレビュー（`Review`モデル）の一覧を取得するためのリレーションを定義します。 | こちらも戻り値の型として`BelongsToMany`を指定しています。 |

### 3.2.2. `Book`モデル
`app/Models/Book.php`を以下のように編集します。

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

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
```

**🔬 コードリーディング**

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use Illuminate\...\BelongsTo;` | `BelongsTo`リレーション型をインポートします。 | メソッドの戻り値の型として`BelongsTo`を指定するために必要です。 |
| `protected $fillable = [...]` | `user_id`（登録者ID）、`title`（書籍名）など、書籍の登録・更新時にフォームから一括で代入・保存を許可するカラムを指定しています。 | `user_id`も`$fillable`に含めることで、コントローラー側で`$request->user()->books()->create(...)`のように、認証済みユーザーに紐づけて書籍を簡単に作成できるようになります。 |
| `public function user(): BelongsTo` | この書籍を登録したユーザー（`User`モデル）の情報を取得するためのリレーションを定義します。 | **`: BelongsTo`が戻り値の型定義です。** このメソッドが`BelongsTo`リレーションオブジェクトを返すことを明示しています。`belongsTo`は「多対1」のリレーションを定義し、`hasMany`の逆の関係を示します。 |
| `public function reviews(): HasMany` | この書籍に投稿された複数のレビュー（`Review`モデル）を取得するためのリレーションを定義します。 | 戻り値の型として`HasMany`を指定しています。`Book`が「1」で、`Review`が「多」の「1対多」の関係です。 |
| `public function genres(): BelongsToMany` | この書籍が属する複数のジャンル（`Genre`モデル）を取得するためのリレーションを定義します。 | 戻り値の型として`BelongsToMany`を指定しています。中間テーブル名は`book_genre`となり、Laravelの命名規則に従っているため、テーブル名指定は省略できます。 |
| `public function favoritedByUsers(): BelongsToMany` | この書籍をお気に入り登録している複数のユーザー（`User`モデル）を取得するためのリレーションを定義します。 | 戻り値の型として`BelongsToMany`を指定しています。命名規則とは異なるテーブル名（`favorites`）を使用するため、第二引数で明示的に指定しています。 |

### 3.2.3. `Review`モデル

`app/Models/Review.php`を以下のように編集します。

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

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_likes');
    }
}
```

**🔬 コードリーディング**

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $fillable = [...]` | `user_id`（投稿者ID）、`book_id`（対象書籍ID）、`rating`（評価点）、`comment`（コメント内容）の4つのカラムへの一括代入を許可します。 | これにより、レビュー投稿フォームからのリクエストデータを使って、`Review::create($request->all())`のようなシンプルなコードでレコードを作成できます。 |
| `public function user(): BelongsTo` | このレビューを投稿したユーザー（`User`モデル）の情報を取得します。 | **`: BelongsTo`が戻り値の型定義です。** `reviews`テーブルの`user_id`カラムを外部キーとして使用します。 |
| `public function book(): BelongsTo` | このレビューがどの書籍（`Book`モデル）に対するものかを取得します。 | **`: BelongsTo`が戻り値の型定義です。** `reviews`テーブルの`book_id`カラムを外部キーとして使用します。 |
| `public function likedByUsers(): BelongsToMany` | このレビューに「いいね」した複数のユーザー（`User`モデル）を取得します。 | **`: BelongsToMany`が戻り値の型定義です。** 命名規則とは異なるテーブル名（`review_likes`）を使用するため、第二引数で明示的に指定しています。|

### 3.2.4. `Genre`モデル

`app/Models/Genre.php`を以下のように編集します。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
```

**🔬 コードリーディング**

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $fillable = ['name']` | ジャンルを登録・更新する際に、`name`（ジャンル名）カラムへの一括代入を許可します。 | 配列の要素が1つだけなので、`['name']`という短い書き方になっています。 |
| `public function books(): BelongsToMany` | このジャンルに属する複数の書籍（`Book`モデル）を取得します。 | **`: BelongsToMany`が戻り値の型定義です。** 中間テーブル名は命名規則に従った`book_genre`が自動的に使用されます。 |

---

> **🧠 先輩エンジニアの思考プロセス**
> `hasMany`と`belongsTo`は常にペアで使われます。
> - `User`が`Book`をたくさん持つ (`hasMany`) → `Book`は`User`に属する (`belongsTo`)
> - `Book`が`Review`をたくさん持つ (`hasMany`) → `Review`は`Book`に属する (`belongsTo`)
> 
> `belongsToMany`も常にペアで使われます。
> - `User`が`Book`をたくさんお気に入りする (`belongsToMany`) → `Book`はたくさんの`User`にお気に入りされる (`belongsToMany`)
> 
> このペアを意識すると、リレーションの定義がスムーズになります。そして、戻り値の型をしっかり書くことで、そのペア関係がコード上でより明確になります。

これで、モデルの基本的な設定は完了です。次のChapterでは、アプリケーションの「入り口」となる認証機能と、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
