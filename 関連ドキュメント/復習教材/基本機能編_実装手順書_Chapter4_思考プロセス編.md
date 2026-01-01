# 実装手順書 Chapter 4: レビュー機能の要件詰め・設計・実装編

## はじめに

書籍のCRUDが完成したところで、次はこのアプリケーションのもう一つの主役である「レビュー機能」を実装します。

レビュー機能は、書籍管理機能と似ていますが、重要な違いがあります。それは、**「特定の書籍に従属する」**という点です。つまり、レビューは必ず「どの書籍に対するレビューなのか」という情報を持っていなければなりません。このような「親（Book）と子（Review）」の関係性をどう設計し、実装するかがこのChapterの最大のテーマです。

---

## Section 1: レビュー機能の要件詰めと設計

まずは詳細度50%の要件定義書を確認します。

> **【機能一覧】**
> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | **レビュー管理** | レビュー投稿 | 書籍詳細ページから、5段階評価とコメントでレビューを投稿できる。 |
> | | レビュー編集 | 自分が投稿したレビューを編集できる。 |
> | | レビュー削除 | 自分が投稿したレビューを削除できる。 |

### 思考プロセス：親子関係をどう実装に落とし込むか？

1.  **データ構造（DB設計）**:
    - `reviews`テーブルが必要。
    - 「どの書籍に対するレビューか」を示すために、`book_id`カラムが必須。
    - 「誰が投稿したレビューか」を示すために、`user_id`カラムも必須。
    - `book_id`と`user_id`には、それぞれ`books`テーブルと`users`テーブルの`id`を参照する**外部キー制約**を設定すべき。これにより、存在しない書籍やユーザーに紐付くレビューが作られるのを防ぎ、データの整合性を保つ。
    - レビューの評価（rating）とコメント（comment）のカラムも必要。

2.  **ルーティング（URI設計）**:
    - レビューは書籍に従属するので、URIもそれを表現するのがRESTfulな設計の基本。
    - レビュー投稿（Create）のURIは、`/reviews`ではなく、`/books/{book}/reviews`とすべき。これにより、「どの書籍に」レビューを投稿するのかがURIだけで明確になる。
    - 編集や削除は、`/reviews/{review}/edit`のように、レビューIDで直接指定する形で問題ない。

3.  **バリデーションと認可**:
    - **投稿時**: 評価（rating）は1〜5の整数、コメントは必須か？最大文字数は？これらはPMへのヒアリング事項。
    - **ユニーク制約**: 1人のユーザーは、1つの書籍に対して1回しかレビューを投稿できないようにすべきでは？これも重要なヒアリング項目。
    - **認可（Policy）**: レビューの編集・削除は、投稿した本人しかできないようにする。これは書籍管理機能で実装した`BookPolicy`と考え方は同じ。

### PMへのヒアリングシート（レビュー機能編）

--- 

**To: PM（コーチ）**

お疲れ様です。レビュー機能の仕様についてご相談です。

**1. バリデーションについて**

| 項目名 | ルール案 | 質問・確認事項 |
|---|---|---|
| 評価 (rating) | `required`, `integer`, `between:1,5` | 1〜5の整数必須、でよろしいでしょうか？ |
| コメント (comment) | `required`, `string`, `max:1000` | コメントも必須とし、最大1000文字でいかがでしょうか？ |

**2. 投稿制限について**

- 1ユーザーにつき、1書籍あたり1レビューのみ投稿可能、という仕様にしたいと考えています。同じ書籍に何度もレビューできてしまうのは不自然かと思うのですが、いかがでしょうか？
  - （実装案: `reviews`テーブルの`book_id`と`user_id`の組み合わせにユニーク制約をかける）

--- 

## Section 2: レビュー投稿（Create）の実装

PMとの仕様が固まったら、実装を開始します。ここでも「M → C → R → V」の流れを意識します。

### 1. ModelとMigrationの作成

```bash
# Reviewモデルと関連ファイルを一式作成
sail artisan make:model Review -mfs -c -r
```

生成されたマイグレーションファイルを編集します。

```php
// database/migrations/xxxx_create_reviews_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // 親であるBookとUserへの外部キー
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating'); // 1-5の評価
            $table->text('comment'); // コメント
            $table->timestamps();

            // book_idとuser_idの組み合わせでユニーク制約
            $table->unique(['book_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
```

> **【コードリーディング】**
> - `->constrained()`: カラム名から自動的にテーブル名（`books`）とカラム名（`id`）を推測して外部キー制約を設定してくれる便利なメソッドです。
> - `->onDelete('cascade')`: 「親が削除されたら子も一緒に削除する」という設定です。つまり、ある書籍が削除されたら、その書籍に紐付くレビューも自動的にDBから削除されます。データの整合性を保つために非常に重要です。
> - `$table->unique(['book_id', 'user_id'])`: 複合ユニーク制約。この組み合わせが重複するレコードは作成できなくなります。

### 2. リレーションの定義

`Book`, `User`, `Review`の各モデルに、お互いの関係性を定義します。

```php
// app/Models/Book.php

public function reviews()
{
    return $this->hasMany(Review::class);
}

public function user()
{
    return $this->belongsTo(User::class);
}
```

```php
// app/Models/User.php

public function reviews()
{
    return $this->hasMany(Review::class);
}

public function books()
{
    return $this->hasMany(Book::class);
}
```

```php
// app/Models/Review.php

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'rating',
        'comment',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### 3. FormRequestの作成

```bash
sail artisan make:request StoreReviewRequest
```

```php
// app/Http/Requests/StoreReviewRequest.php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

### 4. ControllerとRouting

`ReviewController`の`store`メソッドを編集します。

```php
// app/Http/Controllers/ReviewController.php

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * レビューを投稿
     */
    public function store(StoreReviewRequest $request, Book $book)
    {
        // バリデーション済みのデータを取得
        $validated = $request->validated();

        // 取得したデータに、現在認証しているユーザーのIDを追加
        $validated['user_id'] = auth()->id();

        // Bookモデルのリレーション（reviews()）を利用して、新しいレビューを作成・保存
        $book->reviews()->create($validated);

        // 投稿後は、元の書籍詳細ページに戻る
        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }
    
    // ...
}
```

> **【コードリーディング】**
> - `store(StoreReviewRequest $request, Book $book)`: 引数に`Book $book`を追加することで、URIの`{book}`部分に対応する`Book`インスタンスを自動的に受け取れます（ルートモデルバインディング）。
> - `$book->reviews()->create($validated)`: リレーションメソッド経由で`create`すると、`book_id`を自動的にセットしてくれるため、コードがよりシンプルになります。

`routes/web.php`にルートを追加します。

```php
// routes/web.php

use App\Http\Controllers\ReviewController;

// ...

Route::resource('books', BookController::class)->middleware('auth');

// レビュー関連のルート
Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
    ->name('reviews.store')
    ->middleware('auth');

Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])
    ->name('reviews.edit')
    ->middleware('auth');

Route::put('/reviews/{review}', [ReviewController::class, 'update'])
    ->name('reviews.update')
    ->middleware('auth');

Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
    ->name('reviews.destroy')
    ->middleware('auth');

require __DIR__.'/auth.php';
```

---

## Section 3: レビュー編集・削除（Update, Delete）の実装

ここでも主役は`Policy`です。

### 1. Policyの作成とControllerへの適用

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

`ReviewPolicy`を作成し、`update`と`delete`メソッドを定義します。

```php
// app/Policies/ReviewPolicy.php

<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新する権限があるかを判定
     */
    public function update(User $user, Review $review): bool
    {
        // ログインユーザーのIDと、レビューのuser_idが一致するかをチェック
        return $user->id === $review->user_id;
    }

    /**
     * レビューを削除する権限があるかを判定
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

`ReviewController`の`edit`, `update`, `destroy`メソッドに認可チェックを追加します。

```php
// app/Http/Controllers/ReviewController.php

use App\Http\Requests\UpdateReviewRequest;

class ReviewController extends Controller
{
    // ...

    /**
     * レビュー編集フォームを表示
     */
    public function edit(Review $review)
    {
        $this->authorize('update', $review);
        return view('reviews.edit', compact('review'));
    }

    /**
     * レビューを更新
     */
    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);

        $validated = $request->validated();
        $review->update($validated);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューを削除
     */
    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $book = $review->book; // リダイレクト先で使うため、削除前に取得
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

### 2. Viewでの認可チェック

他人のレビューの「編集」「削除」ボタンは、そもそも画面に表示させないのが親切です。Bladeの`@can`ディレクティブを使います。

```blade
{{-- resources/views/books/show.blade.php のレビュー表示部分 --}}

@foreach ($book->reviews as $review)
    <div class="review">
        <p>評価: {{ $review->rating }}</p>
        <p>{{ $review->comment }}</p>
        <p>投稿者: {{ $review->user->name }}</p>

        {{-- このレビューを更新する権限が、現在のユーザーにあるかチェック --}}
        @can('update', $review)
            <a href="{{ route('reviews.edit', $review) }}">編集</a>
        @endcan

        @can('delete', $review)
            <form action="{{ route('reviews.destroy', $review) }}" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" onclick="return confirm('本当に削除しますか？')">削除</button>
            </form>
        @endcan
    </div>
@endforeach
```

> **【コードリーディング】**
> - `@can('update', $review)`: `ReviewPolicy`の`update`メソッドを呼び出し、`true`を返す場合のみ、`@can`〜`@endcan`の間のHTMLがレンダリングされます。
> - `@method('DELETE')`: HTMLフォームは`GET`と`POST`しかサポートしていないため、Laravelでは`@method`ディレクティブを使って、`PUT`, `PATCH`, `DELETE`などのHTTPメソッドを擬似的に送信します。

---

## まとめ

このChapterでは、親子関係を持つ機能の実装を通じて、

-   **ネストされたルーティング**の設計
-   **外部キー制約**と`onDelete('cascade')`によるデータ整合性の確保
-   モデル間の**リレーション定義**と、それを利用したデータ操作
-   `@can`ディレクティブによる、ビュー層での認可チェック

といった、より実践的なテクニックを学びました。これらの知識は、今後さらに複雑な機能を実装する上での強固な土台となります。
