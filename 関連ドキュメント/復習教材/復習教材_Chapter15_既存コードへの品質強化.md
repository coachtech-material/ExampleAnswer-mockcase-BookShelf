# Chapter 15: 既存コードへの品質強化（型宣言・PHPDoc・Collection）

## 🎯 このセクションで学ぶこと

このChapterから「応用機能編」に入ります。応用機能編の最初の Chapter として、Chapter 14 までで実装した Basic コードに対して**品質強化を一括で施します**。

- **クロージャの戻り値型宣言**: マイグレーションファイルの `Schema::create()` クロージャに `: void` を追加
- **モデルリレーションへの戻り値型宣言**: `HasMany`、`BelongsTo`、`BelongsToMany` 等の具体的な型を付与
- **PHPDoc コメントの整備**: 各クラス・プロパティ・メソッドに目的を 1 行で説明する PHPDoc
- **Book モデルの `$casts` 活用**: `'published_date' => 'date'` を追加して Carbon オブジェクトとして扱う
- **HasApiTokens トレイトは Chapter 16 で追加**: 本 Chapter では追加しない（Sanctum 認証層追加と同時）
- **Collection リファクタはスキップ**: Basic 段階の `RankingController` は既に Eloquent クエリビルダ（`withAvg` / `withCount` / `has` / `orderByDesc`）を使った Collection 友好的実装になっているため、本 Chapter では変更不要

これらを Chapter 16 以降の応用機能実装に入る前に済ませることで、追加機能を一貫した品質基準のもと実装できます。

---

## 前提

- Chapter 14 までを完了している（Basic 模範解答コードと同一の状態）

## 応用機能編開始の準備: 提供 Blade ファイル（Advanced 用）の再取得

Chapter 04 で配置した **Basic ブランチ** の Blade を、応用機能用の **Advanced ブランチ** の Blade に上書きします。これにより応用機能用の Blade（マイレポート画面 / 読書計画画面 / 通知画面 / 高度な検索フォーム / ISBN 検索ボタン等）が利用可能になります。

```bash
# 提供 Blade リポジトリの Advanced ブランチを取得
git clone -b Advanced https://github.com/coachtech-prepared-file/Preparedblade-mockcase-BookShelf.git /tmp/prepared-blade-advanced

# resources/views/ 配下をすべて上書き
cp -r /tmp/prepared-blade-advanced/resources/views/. resources/views/
```

> **注:** Basic ブランチで先に動かしていた認証・書籍・ジャンル等の Blade も Advanced 版に置き換わりますが、Advanced 用 Blade は Basic 機能と互換のため動作が壊れることはありません。

---

## 1. はじめに 📖

基本機能編では「動くものを作る」ことを優先し、型宣言や PHPDoc を最小限に抑えてきました。応用機能編に入る今、それらを一括で追加することで、**コードの可読性・IDE 補完・静的解析・チーム開発時の安全性**を一段引き上げます。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| メソッドが何を返すか分かりにくい | **戻り値型を全メソッドに付ける** | `public function index(): View` のように戻り値が明示されると、呼び出し側で安心して使える。IDE 補完も効く |
| Eloquent リレーションメソッドの戻り値が曖昧 | `: HasMany` 等の具体的な型を返す | `$user->books` の型が `HasMany<Book>` と推論され、`->paginate()` 等のメソッドが補完される |
| Book::$casts が無いと `$book->published_date` が文字列 | `$casts = ['published_date' => 'date']` を追加 | Carbon オブジェクトとして `->format('Y/m/d')` 等が呼べるようになる |
| クロージャ `function (Blueprint $table) {}` の戻り値型が曖昧 | `: void` を付与 | 「何も返さない」ことが明示され、静的解析ツールも安心 |

---

## 2. 要件の確認 📋

要件シート シート2 で型宣言は「**★ 応用**」要件として明示されています:

> ★ 型宣言: すべてのコントローラ、モデル、FormRequest、Policy のメソッドに、引数と戻り値の型を明示的に宣言する（例: `public function index(): View`）。モデルのリレーションメソッドには `HasMany`, `BelongsTo`, `BelongsToMany` などの具体的な戻り値型を宣言する。基本機能では型宣言は不要だが、応用機能の開発時に全メソッドに追加する。

つまり、**Basic 段階ではあえて省略**し、Advance 段階で一括追加する設計です。

---

## 3. 先輩エンジニアの思考プロセス 💭

### なぜ型宣言を「後から一括追加」するのか？

学習効率の観点では、最初は「動く最小コード」だけに集中し、応用フェーズで品質要件を追加するほうが理解が深まります。実務でも、まず動くプロトタイプを作り、品質要件を別フェーズで満たしていく開発フローはよくあります（リファクタリング・品質強化の Sprint）。

### Collection リファクタを今回スキップする理由

要件シートでは Collection メソッドの活用が応用要件として明記されています。しかし `RankingController` は Basic 段階で既に **Eloquent クエリビルダ + Collection** ベースの実装になっており、`DB::raw` のような生 SQL は使っていません。応用機能の `ReportController`（Chapter 19）や、新規追加の `ReadingPlanController`（Chapter 20）で Collection を本格活用する設計です。

---

## 4. 実装 🚀

### 15.1. マイグレーションのクロージャに `: void` 型宣言を追加

Chapter 02 で作成した 6 つのマイグレーションファイルすべてに対し、`Schema::create()` に渡すクロージャに `: void` 型宣言を追加します。

対象ファイル:
- `database/migrations/xxxx_create_books_table.php`
- `database/migrations/xxxx_create_genres_table.php`
- `database/migrations/xxxx_create_book_genre_table.php`
- `database/migrations/xxxx_create_reviews_table.php`
- `database/migrations/xxxx_create_favorites_table.php`
- `database/migrations/xxxx_create_review_likes_table.php`

> **注:** Chapter 02 で同時に作成した `reading_plans` / `notifications` の migration も同様に `: void` を付けてください。

変更例（books migration）:

```diff
-        Schema::create('books', function (Blueprint $table) {
+        Schema::create('books', function (Blueprint $table): void {
```

### 15.2. モデルへのリレーション戻り値型宣言・PHPDoc・$casts を追加

#### `app/Models/User.php`

リレーションメソッドの戻り値型宣言と PHPDoc を追加します。`HasApiTokens` トレイトは Chapter 16（Sanctum 認証層）で追加するためここでは入れません。

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

#### `app/Models/Book.php`

`$fillable` / `$casts` 上に PHPDoc を追加。`$casts` に `published_date` を追加（Carbon 日付オブジェクト化）。リレーションメソッドの戻り値型宣言と PHPDoc を追加。

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

#### `app/Models/Review.php`

`$fillable` 上に PHPDoc を追加。リレーションメソッドの戻り値型宣言と PHPDoc を追加。

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

#### `app/Models/Genre.php`

`$fillable` 上に PHPDoc を追加。リレーションメソッドの戻り値型宣言と PHPDoc を追加。

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
    protected $fillable = ['name'];

    /**
     * ジャンルに属する書籍
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
```

### 15.3. FormRequest への型宣言・PHPDoc を追加

Part 2 段階では型宣言を付与していないため、`StoreReviewRequest` / `UpdateReviewRequest` 等の各 FormRequest クラスのメソッドに、戻り値型（`: bool`、`: array`）と PHPDoc を追加します。

例（`app/Http/Requests/StoreReviewRequest.php`）:

```php
class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        // 既存と同じ
    }
}
```

他の FormRequest（`StoreBookRequest`, `UpdateBookRequest`, `StoreGenreRequest`, `UpdateGenreRequest`, `Api/V1/IndexBookRequest`, `Api/V1/StoreBookRequest`, `Api/V1/UpdateBookRequest` 等）にも同様の戻り値型と PHPDoc を追加してください。

### 15.4. コントローラへの型宣言・PHPDoc 追加

Part 2 で作成したコントローラ群（`BookController`, `ReviewController`, `FavoriteController`, `ReviewLikeController`, `GenreController`, `RankingController`, `Api\V1\BookController` 等）の各アクションメソッドに、戻り値型（`: View`、`: RedirectResponse`、`: JsonResponse` 等）と、目的を 1 行で説明する PHPDoc を追加します。`Api\V1\BookResource` 等の Resource クラスの `toArray()` メソッドにも `: array` 戻り値型を追加します。Policy / Exception Handler も同様。

例（`app/Http/Controllers/BookController.php`）:

```php
class BookController extends Controller
{
    /**
     * 書籍一覧を表示
     */
    public function index(): View
    {
        // ...
    }

    /**
     * 書籍登録フォームを表示
     */
    public function create(): View
    {
        // ...
    }
}
```

Policy（`app/Policies/BookPolicy.php`）の例:

```php
class BookPolicy
{
    /**
     * 書籍を更新できるか
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるか
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
```

### 15.5. Collection メソッドへのリファクタリング（ランキング機能）

> **注:** 本プロジェクトの advance 模範解答コードでは `RankingController` は Basic 段階で既に Eloquent クエリビルダ（`withAvg`, `withCount`, `has`, `orderByDesc`, `take`）を活用した実装になっており、追加の Collection リファクタは行わない。`RankingController` に対する変更は不要なのでスキップして Chapter 16 へ進む。

Collection メソッドの本格活用は次の Chapter 以降で行われます:
- **Chapter 19 マイ読書レポート**: `groupBy` / `map` / `flatMap` / `filter` / `sortByDesc` を駆使した 4 統計の集計
- **Chapter 20 読書計画**: Eloquent scope（`active` / `completed` / `expired`）と Collection の組合せ

---

## 5. コードの詳細解説 🔍

### 戻り値型を「リレーションクラス」にする意味

`public function books(): HasMany` のように戻り値型を `HasMany` クラスにすると、IDE や静的解析ツールが「`$user->books()` は `HasMany` インスタンスを返す」と認識し、`->paginate()` / `->latest()` / `->with()` 等の続きのメソッドが補完されます。

一方、リレーションプロパティアクセス `$user->books` （`()` なし）は Eloquent が魔法的に Collection を返すため、戻り値型は適用されません。

### `$casts = ['published_date' => 'date']` の効果

DB から取得した `published_date` カラム（文字列「2024-01-01」）が、Eloquent によって自動で `Carbon\Carbon` インスタンスに変換されます。Blade で `{{ $book->published_date->format('Y年m月d日') }}` のような日付操作がそのまま書けるようになります。

### PHPDoc `@var array<int, string>` の読み方

`@var array<int, string>` は「キーが int、値が string の配列」という意味の Generics 風表記。`$fillable = ['user_id', 'title', ...]` のような数値キー配列に適合します。一方 `casts()` メソッドの戻り値は `@return array<string, string>`（キーが string、値が string）。

---

## 6. この実装にたどり着くための調べ方 🧐

| 疑問 | プロンプト例 |
|:---|:---|
| PHP の型宣言の効果と限界 | 「PHP 8.x のスカラ型宣言と nullable 型（`?int` 等）について、戻り値型として `void` / `mixed` / 具体型を使い分ける指針を教えてください。」 |
| Eloquent リレーション型 | 「Laravel の `hasMany` / `belongsTo` / `belongsToMany` メソッドの戻り値型として `HasMany` / `BelongsTo` / `BelongsToMany` のどれを使うべきか、サンプルコードと合わせて教えてください。」 |
| PHPDoc の書き方 | 「Laravel モデルで `@var array<int, string>` と書く意味と、PHPStan / Larastan が認識する PHPDoc 記法を教えてください。」 |
| Carbon casts の使い方 | 「Laravel の `$casts` に `'published_date' => 'date'` と `'datetime'` の違いを教えてください。タイムゾーン取り扱いも含めて。」 |

---

## 7. 動作確認 ✅

### Laravel Pint（コードフォーマッタ）の紹介

本 Chapter で多数のファイルに型宣言・PHPDoc を追加したため、スペースや改行などのスタイル違反が混入していないかを **Laravel Pint** で確認します。Pint は Laravel デフォルトで同梱されているコードフォーマッタ（PHP-CS-Fixer ベース）で、追加インストール不要です。

```bash
# 違反を検出するだけ（修正しない・CI 用）
sail bin pint --test

# 違反を自動修正
sail bin pint
```

`sail bin pint --test` で `No fixable issues were found` と出れば OK。違反があれば該当行が表示されるので、`sail bin pint`（test 無し）で自動修正します。

### 確認項目

| 確認項目 | 確認方法 |
|:---|:---|
| 構文エラー / スタイル違反がないか | `sail bin pint --test` で `No fixable issues were found` が出ること。違反があれば `sail bin pint` で自動修正後に再実行 |
| Laravel 全体の構成が壊れていないか | `sail artisan about` がエラーなく実行できること |
| マイグレーション再実行 | `sail artisan migrate:fresh --seed` でテーブルが正常に作り直されること |
| Carbon キャスト動作 | `sail artisan tinker` で `Book::first()->published_date->format('Y/m/d')` が動作すること |
| リレーションが従来通り動く | `sail artisan tinker` で `User::first()->books`、`Book::first()->genres` 等のリレーション取得が正常に動作すること |
| 既存テスト（Chapter 14）への影響 | （Chapter 21 完了後に実行）`sail artisan test` で Basic 段階の test が全 PASS のままであること |

---

## 8. まとめ ✨

このChapterでは、Basic 段階で省略していた品質要件を一括で追加しました。

- **戻り値型**: メソッドの「契約」を明示し、IDE / 静的解析の支援を得る
- **PHPDoc**: 「何のためのメソッド／プロパティか」を 1 行で説明し、可読性を上げる
- **`HasMany` 等のリレーション型**: Eloquent クエリビルダのメソッド補完を効かせる
- **`$casts`**: 文字列ではなく Carbon オブジェクトとしてデータを扱う

これらの品質要件は、応用機能編で新規追加する Chapter 16 以降のコードでも一貫して適用していきます。

次の Chapter 16 では、**Sanctum 認証層** を公開 API に追加し、書き込み系（POST / PUT / DELETE）を認証必須に切り替えます。
