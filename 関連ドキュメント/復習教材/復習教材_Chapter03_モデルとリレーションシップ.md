# Chapter 3: モデルとリレーションシップ

このChapterでは、データベースのテーブルと対話するための「Eloquentモデル」を作成し、テーブル間の関連性を定義する「リレーションシップ」を実装します。

---

## 3-1. 先輩エンジニアの思考プロセス：なぜDB設計の次にモデルを定義するのか？

### データベースとアプリケーションを繋ぐ「橋」

Chapter 2でデータベースという「土地」の「基礎工事」が終わりました。次に行うのは、その土地の上に建つ家の「骨組み」を作ることです。これが**Eloquentモデル**と**リレーションシップ**の役割です。

> **先輩エンジニアの思考:**
> 「データベースのテーブル構造が決まっていないのに、アプリケーションのコードを書き始めることはできない。`books`テーブルに`title`カラムがあるのか`book_title`カラムなのか、それが決まらなければデータを取得も保存もできないからだ。だから、必ず**DB設計（マイグレーション）が先、モデル定義が後**という順番になる。」

Eloquentモデルは、データベースの各テーブルを表現するPHPのクラスです。例えば、`books`テーブルの操作は`App\Models\Book`モデルを通じて行います。これにより、SQLを直接書かなくても、オブジェクト指向の直感的なコードでデータベースを操作できるようになります。

### ER図からリレーションシップへ

リレーションシップは、モデル間の「関係性」を定義するものです。これは、Chapter 2で作成した**ER図と完全に一致**します。

| ER図の関係 | Eloquentのリレーション | 意味 |
|:---|:---|:---|
| 1対多 (例: `USERS` --o{ `BOOKS`) | `hasMany` / `belongsTo` | 1人のユーザーは**多数の**本を持つ / 1冊の本は1人のユーザーに**所属する** |
| 多対多 (例: `BOOKS` }|--|{ `GENRES`) | `belongsToMany` | 1冊の本は**多数の**ジャンルに所属する / 1つのジャンルは**多数の**本を持つ |

このように、ER図で定義したデータ構造を、Eloquentリレーションシップという形でコードに落とし込んでいくのです。

---

## 3.2. モデルの作成

まず、`make:model`コマンドで、操作したいテーブルに対応するモデルファイルを作成します。

```bash
sail artisan make:model Book
sail artisan make:model Review
sail artisan make:model Genre
```

> **【学習のポイント】**
> `User`モデルはLaravelの初期状態で既に存在するため、作成する必要はありません。また、`book_genre`のような中間テーブルに対応するモデルは、通常は作成不要です。

---

## 3.3. リレーションシップの定義（完全版）

作成したモデルファイルと既存の`User.php`に、ER図に基づいたリレーションシップを定義していきます。

### `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
     * このユーザーが登録した書籍。（1対多）
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * このユーザーが投稿したレビュー。（1対多）
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * このユーザーがお気に入りに登録した書籍。（多対多）
     */
    public function favoriteBooks(): BelongsToMany
    {
        // 第2引数で中間テーブル名を指定
        return $this->belongsToMany(Book::class, 'favorites');
    }

    /**
     * このユーザーがいいねしたレビュー。（多対多）
     */
    public function likedReviews(): BelongsToMany
    {
        // 第2引数で中間テーブル名を指定
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

### `app/Models/Book.php`

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
     * マスアサインメント可能な属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "user_id",
        "title",
        "author",
        "isbn",
        "published_date",
        "description",
        "image_url",
    ];

    /**
     * この書籍を登録したユーザー。（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この書籍に紐付くレビュー。（1対多）
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍が属するジャンル。（多対多）
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    /**
     * この書籍をお気に入りに登録しているユーザー。（多対多）
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
```

> **【重要】マスアサインメントと`$fillable`**
> `$fillable`プロパティは、`Book::create($request->all())`のように、一度に複数のカラムを更新（マスアサインメント）することを許可するカラムのリストです。これは**セキュリティ対策**であり、意図しないカラム（例：`is_admin`など）が不正に更新されるのを防ぎます。ここに登録されていないカラムは、マスアサインメントでは更新できません。

### `app/Models/Review.php`

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

    protected $fillable = ['user_id', 'book_id', 'rating', 'comment'];

    /**
     * このレビューを投稿したユーザー。（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * このレビューが紐付く書籍。（多対1）
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * このレビューをいいねしたユーザー。（多対多）
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_likes');
    }
}
```

### `app/Models/Genre.php`

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

    /**
     * このジャンルに属する書籍。（多対多）
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
```

---

## 3.4. まとめ

これで、データベースのテーブル構造と、アプリケーションのモデル・リレーションシップが完全に一致しました。アプリケーションは、`$book->reviews`のように、直感的なオブジェクト操作で関連データにアクセスする準備が整いました。

次のChapterでは、いよいよ具体的な機能実装に入っていきます。
