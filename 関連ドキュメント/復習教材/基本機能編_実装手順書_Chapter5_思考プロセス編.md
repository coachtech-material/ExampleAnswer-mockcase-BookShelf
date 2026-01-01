# 実装手順書 Chapter 5: その他機能の要件詰め・設計・実装編

## はじめに

アプリケーションのコアである書籍とレビューのCRUDが完成しました。この最後のChapterでは、アプリケーションをより豊かにするための「お気に入り」「いいね」「ジャンル管理」「ランキング」といった周辺機能を実装していきます。

これらの機能は、これまでのCRUDとは少し毛色が異なり、**多対多リレーション**や**集計クエリ**といった、より高度なデータベース操作の知識が求められます。

---

## Section 1: お気に入り・いいね機能（多対多リレーション）

まずは「お気に入り」と「いいね」です。この2つの機能は、実は技術的にはほとんど同じ構造をしています。

### 思考プロセス：なぜ「多対多」なのか？

- **お気に入り**: 1人の**ユーザー**は、**複数**の**書籍**をお気に入りに登録できる。そして、1冊の**書籍**は、**複数**の**ユーザー**からお気に入りに登録される。
- **いいね**: 1人の**ユーザー**は、**複数**の**レビュー**にいいねできる。そして、1つの**レビュー**は、**複数**の**ユーザー**からいいねされる。

このように、両方のモデルがお互いに「複数」の関係を持つ場合を**多対多（Many-to-Many）リレーション**と呼びます。

これをデータベースで表現するには、2つのモデル（例: `users`と`books`）を直接つなぐことはできません。間に**中間テーブル**（例: `favorites`）を置く必要があります。

- **`favorites`テーブルの構造**: `id`, `user_id`, `book_id`, `timestamps`
- **`review_likes`テーブルの構造**: `id`, `user_id`, `review_id`, `timestamps`

この中間テーブルにレコードを作成・削除することで、お気に入りやいいねの状態を管理します。

### 1. Model、Migration、リレーションの作成

中間テーブル用のマイグレーションファイルを作成します。

```bash
sail artisan make:migration create_favorites_table
sail artisan make:migration create_review_likes_table
```

`favorites`テーブルのマイグレーションファイルを編集します。

```php
// database/migrations/xxxx_create_favorites_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            // user_idとbook_idの組み合わせでユニーク
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
```

`review_likes`テーブルも同様に作成します。

```php
// database/migrations/xxxx_create_review_likes_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('review_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['user_id', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_likes');
    }
};
```

次に、`User`モデルと`Book`モデルに多対多リレーションを定義します。`belongsToMany`メソッドを使います。

```php
// app/Models/User.php

/**
 * このユーザーがお気に入りに登録している書籍
 */
public function favorite_books()
{
    return $this->belongsToMany(Book::class, 'favorites')->withTimestamps();
}

/**
 * このユーザーがいいねしたレビュー
 */
public function liked_reviews()
{
    return $this->belongsToMany(Review::class, 'review_likes')->withTimestamps();
}
```

```php
// app/Models/Book.php

/**
 * この書籍をお気に入りに登録しているユーザー
 */
public function favorited_by_users()
{
    return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
}
```

```php
// app/Models/Review.php

/**
 * このレビューにいいねしたユーザー
 */
public function liked_by_users()
{
    return $this->belongsToMany(User::class, 'review_likes')->withTimestamps();
}
```

> **【コードリーディング】**
> - `belongsToMany(Book::class, 'favorites')`: 第2引数に中間テーブル名を指定します。
> - `->withTimestamps()`: 中間テーブルの`created_at`と`updated_at`を自動的に更新するようにします。

### 2. Controllerの実装（toggleメソッド）

お気に入りやいいねの登録・解除は、同じボタンで行われる「トグル」動作です。コントローラには、このトグル処理を実装します。

```php
// app/Http/Controllers/FavoriteController.php

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * お気に入りの登録/解除をトグル
     */
    public function toggle(Book $book)
    {
        // 現在認証しているユーザーを取得
        $user = auth()->user();

        // Userモデルのリレーションを利用して、中間テーブルのレコードを操作
        // toggle: レコードがあれば削除、なければ追加する
        $user->favorite_books()->toggle($book);

        // 元のページ（書籍詳細）に戻る
        return back();
    }
}
```

> **【コードリーディング】**
> - `toggle($book)`: 非常に便利なメソッドです。もしこのメソッドがなければ、「既にお気に入り登録されているか？」を`if`文でチェックし、`attach`と`detach`を使い分ける必要があります。`toggle`を使えば、その処理を1行で書くことができます。
>   - `attach`: 中間テーブルにレコードを追加
>   - `detach`: 中間テーブルからレコードを削除
>   - `toggle`: レコードがあれば削除、なければ追加

いいね機能のコントローラも同様に作成します。

```php
// app/Http/Controllers/ReviewLikeController.php

<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * いいねの登録/解除をトグル
     */
    public function toggle(Review $review)
    {
        $user = auth()->user();
        $user->liked_reviews()->toggle($review);

        return back();
    }
}
```

### 3. Routingの設定

```php
// routes/web.php

use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewLikeController;

// ...

// お気に入り
Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])
    ->name('favorites.toggle')
    ->middleware('auth');

// いいね
Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])
    ->name('review_likes.toggle')
    ->middleware('auth');
```

### 4. Viewでの実装

ビューでは、現在の状態（お気に入り済みか否か）によってボタンの表示を切り替える必要があります。

```blade
{{-- resources/views/books/show.blade.php --}}

{{-- この書籍が、現在ログインしているユーザーのお気に入り一覧に含まれているかチェック --}}
@if (auth()->user()->favorite_books->contains($book))
    {{-- 含まれている場合：解除ボタンを表示 --}}
    <form action="{{ route('favorites.toggle', $book) }}" method="POST">
        @csrf
        <button type="submit">お気に入り解除</button>
    </form>
@else
    {{-- 含まれていない場合：登録ボタンを表示 --}}
    <form action="{{ route('favorites.toggle', $book) }}" method="POST">
        @csrf
        <button type="submit">お気に入り登録</button>
    </form>
@endif
```

> **【エンジニアの思考：N+1問題への注意】**
> `auth()->user()->favorite_books`は、現在ログインしているユーザーがお気に入りに登録している`Book`のコレクションを返します。`contains($book)`メソッドで、そのコレクションに今表示している`$book`が含まれているかを判定できます。
> 
> しかし、この書き方には**N+1問題**の罠が潜んでいます。書籍一覧ページで全書籍に対してこの処理を行うと、書籍の数だけクエリが発行されてしまいます。
> 
> **対策**: コントローラで`with('favorited_by_users')`を使ってEager Loadingを行うか、認証ユーザーのお気に入り書籍IDを事前に配列として取得しておき、`in_array`でチェックするなどの方法があります。

---

## Section 2: ジャンル管理機能（CRUD + リレーション）

ジャンル管理機能は、基本的にはCRUDですが、書籍との多対多リレーションを持つ点が特徴です。

### 1. ModelとMigrationの作成

```bash
sail artisan make:model Genre -mfs -c -r
sail artisan make:migration create_book_genre_table
```

```php
// database/migrations/xxxx_create_genres_table.php

public function up(): void
{
    Schema::create('genres', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->timestamps();
    });
}
```

```php
// database/migrations/xxxx_create_book_genre_table.php

public function up(): void
{
    Schema::create('book_genre', function (Blueprint $table) {
        $table->id();
        $table->foreignId('book_id')->constrained()->onDelete('cascade');
        $table->foreignId('genre_id')->constrained()->onDelete('cascade');
        $table->timestamps();
        $table->unique(['book_id', 'genre_id']);
    });
}
```

### 2. リレーションの定義

```php
// app/Models/Book.php

public function genres()
{
    return $this->belongsToMany(Genre::class)->withTimestamps();
}
```

```php
// app/Models/Genre.php

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
        return $this->belongsToMany(Book::class)->withTimestamps();
    }
}
```

### 3. 削除時の注意点

ジャンルを削除する際、そのジャンルに紐付く書籍が存在する場合はどうすべきでしょうか？これはPMへのヒアリング事項です。

**案1**: 紐付く書籍があっても削除を許可する（中間テーブルのレコードは`onDelete('cascade')`で自動削除される）。
**案2**: 紐付く書籍がある場合は削除を許可せず、エラーメッセージを表示する。

今回は**案2**を採用し、コントローラで以下のようにチェックします。

```php
// app/Http/Controllers/GenreController.php

public function destroy(Genre $genre)
{
    // このジャンルに紐付く書籍があるかチェック
    if ($genre->books()->exists()) {
        return back()->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
    }

    $genre->delete();

    return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
}
```

---

## Section 3: ランキング機能（集計クエリ）

最後にランキング機能です。これは、データベースからデータをただ取得するのではなく、**集計・計算**した結果を取得する必要があります。

### 思考プロセス：どうやってランキングを作るか？

- **要件**: レビューの平均評価が高い順に書籍トップ10を表示する。
- **必要なデータ**: 各書籍の「レビュー評価の平均点」。
- **SQLのイメージ**:
  ```sql
  SELECT
      book_id,
      AVG(rating) AS rating_avg
  FROM
      reviews
  GROUP BY
      book_id
  ORDER BY
      rating_avg DESC
  LIMIT 10;
  ```
- **Laravelでの実装**: 上記のSQLを、Eloquent（LaravelのORM）を使ってどう表現するか？

### Controllerの実装

Eloquentのクエリビルダを駆使して、上記のSQLを組み立てます。

```php
// app/Http/Controllers/RankingController.php

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function index()
    {
        // withAvgを使って、reviewsリレーションのratingカラムの平均を計算
        // 結果は `reviews_avg_rating` というカラム名で取得できる
        $top_books = Book::withAvg('reviews', 'rating')
            ->having('reviews_avg_rating', '>', 0) // レビューがある書籍のみ
            ->orderBy('reviews_avg_rating', 'desc')
            ->take(10)
            ->get();

        return view('ranking.index', compact('top_books'));
    }
}
```

> **【コードリーディング】**
> - `withAvg('reviews', 'rating')`: `reviews`リレーションの`rating`カラムの平均値を計算し、結果を`reviews_avg_rating`という名前の属性として各`Book`モデルに追加します。
> - `having('reviews_avg_rating', '>', 0)`: `WHERE`ではなく`HAVING`を使うのは、集計関数の結果に対してフィルタリングするためです。これにより、レビューが1件もない書籍（平均がNULL）を除外できます。
> - `take(10)`: `LIMIT 10`と同じ。上位10件を取得します。

### Routingの設定

```php
// routes/web.php

use App\Http\Controllers\RankingController;

// ...

Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index')
    ->middleware('auth');
```

### Viewでの表示

```blade
{{-- resources/views/ranking/index.blade.php --}}

<h1>書籍ランキング</h1>

<ol>
    @foreach ($top_books as $book)
        <li>
            <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
            (平均評価: {{ number_format($book->reviews_avg_rating, 1) }})
        </li>
    @endforeach
</ol>
```

---

## まとめ

このChapterでは、応用的な機能を通じて、

-   **多対多リレーション**の概念と、中間テーブルを使った実装方法
-   `toggle`メソッドによる、状態の切り替え処理の簡略化
-   **N+1問題**への意識
-   **集計クエリ**の考え方と、Eloquentを使ったエレガントな実装方法

を学びました。これで、書籍レビューアプリの基本機能はすべて完成です。

この教材で学んだ「要件を読み解き、設計し、実装に落とし込む」という一連の思考プロセスは、どんな開発現場でも通用する普遍的なスキルです。ぜひ今後の学習や開発に活かしてください。
