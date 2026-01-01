## Chapter 4: アプリケーションを豊かにする - レビュー機能の実装

### はじめに

書籍の情報を管理するだけでは、単なるデータベースアプリです。このアプリケーションが「レビューアプリ」として真価を発揮するためには、ユーザーが書籍に対して評価やコメントを投稿できる**レビュー機能**が不可欠です。

このChapterでは、書籍（`Book`）という親に対して、レビュー（`Review`）という子が紐づく、典型的な「親子関係」の機能を実装していきます。Chapter 3で学んだCRUDの知識を応用し、リレーションをさらに深く活用する方法を学びましょう。

---

### Section 1: データベースとモデルの準備

まずは、レビューの情報を保存するための`reviews`テーブルと、それを操作するための`Review`モデルを準備します。

#### 1.1. マイグレーションの作成と記述

`make:migration`コマンドで`reviews`テーブルの設計図を作成します。

```bash
sail artisan make:migration create_reviews_table
```

作成されたマイグレーションファイルの`up`メソッドを編集します。

```php
// database/migrations/..._create_reviews_table.php

public function up(): void
{
    Schema::create('reviews', function (Blueprint $table) {
        $table->id();
        // どのユーザーが投稿したか
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        // どの書籍に対するレビューか
        $table->foreignId('book_id')->constrained()->onDelete('cascade');
        $table->unsignedTinyInteger('rating'); // 1-5の評価 (符号なしTINYINT)
        $table->text('comment')->nullable(); // コメント
        $table->timestamps();
    });
}
```

> **【エンジニアの思考】**
> `rating`カラムの型に`unsignedTinyInteger`を選んだのには理由があります。評価は1〜5の小さな正の整数なので、-2,147,483,648〜2,147,483,647の範囲を持つ通常の`integer`では無駄が多すぎます。`unsignedTinyInteger`は0〜255の範囲を持つため、今回の要件にピッタリで、データベースの容量をわずかに節約できます。こうした細部へのこだわりが、大規模なアプリケーションでは大きな差を生むことがあります。

設計図ができたので、データベースに反映させます。

```bash
sail artisan migrate
```

#### 1.2. モデルの作成とリレーション定義

次に`Review`モデルを作成します。

```bash
sail artisan make:model Review
```

作成した`app/Models/Review.php`に、`$fillable`とリレーションを定義します。

```php
// app/Models/Review.php

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'rating',
        'comment',
    ];

    // Reviewは1人のUserに属する
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Reviewは1冊のBookに属する
    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
```

これで、`$review->user->name`や`$review->book->title`のように、レビューから投稿者や書籍の情報を簡単に取得できるようになりました。

---

### Section 2: レビュー投稿機能 (Create)

書籍詳細ページからレビューを投稿する機能を実装します。これは、`Book`と`Review`の親子関係を利用した`Create`処理です。

#### 2.1. コントローラとFormRequestの準備

レビュー関連のロジックをまとめる`ReviewController`と、バリデーションを担当する`StoreReviewRequest`を作成します。

```bash
sail artisan make:controller ReviewController
sail artisan make:request StoreReviewRequest
```

`StoreReviewRequest.php`の`rules`メソッドを編集します。

```php
// app/Http/Requests/StoreReviewRequest.php

public function rules(): array
{
    return [
        'rating' => ['required', 'integer', 'min:1', 'max:5'],
        'comment' => ['nullable', 'string', 'max:1000'],
    ];
}
```

#### 2.2. ルーティングの定義

レビューの投稿は、「どの書籍に対して」投稿するかが重要です。そのため、ルートのURLに書籍のIDを含めるのが一般的です。`routes/web.php`に以下のルートを追加します。

```php
// routes/web.php

use App\Http\Controllers\ReviewController;
// ...

Route::middleware('auth')->group(function () {
    // ...

    // レビュー投稿
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
```

`{book}`の部分がルートモデルバインディングによって`Book`モデルのインスタンスに解決されます。

#### 2.3. 投稿処理の実装

`ReviewController`に`store`メソッドを実装します。

```php
// app/Http/Controllers/ReviewController.php

use App\Models\Book;
use App\Http\Requests\StoreReviewRequest;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        // バリデーション済みのデータを準備
        $validated = $request->validated();

        // ログインユーザーのIDを追加
        $validated['user_id'] = $request->user()->id;

        // 書籍に紐付けてレビューを作成
        $book->reviews()->create($validated);

        // 書籍詳細ページにリダイレクト
        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }
}
```

> **【コードリーディング】**
> `public function store(StoreReviewRequest $request, Book $book)` の引数に注目してください。`$request`でフォームの入力値を、`$book`でURLから特定された書籍モデルを受け取っています。そして、`$book->reviews()->create($validated)`の部分で、`Book`モデルの`reviews`リレーション（`hasMany`）を利用して、`book_id`が自動的にセットされた状態で`reviews`テーブルにレコードを作成しています。これがリレーションを使う最大のメリットの一つです。

#### 2.4. 投稿フォームの設置

書籍詳細ページ（`resources/views/books/show.blade.php`）に、レビュー投稿フォームを設置します。提供されているBladeファイルには既にフォームが含まれています。重要なのは`action`属性の指定です。

```blade
<form action="{{ route('reviews.store', $book) }}" method="POST">
    @csrf
    {{-- 評価(rating)のセレクトボックス --}}
    {{-- コメント(comment)のテキストエリア --}}
    <button type="submit">レビューを投稿</button>
</form>
```

`route('reviews.store', $book)`とすることで、`action`属性は`/books/1/reviews`のような正しいURLに解決されます。

これでレビュー投稿機能は完成です。実際に書籍詳細ページからレビューを投稿し、データが保存されること、フラッシュメッセージが表示されることを確認しましょう。

---

### Section 3: レビュー編集・削除機能 (Update & Delete)

自分が投稿したレビューは、後から編集・削除できるようにすべきです。ここでも`Policy`を使った認可が重要になります。

#### 3.1. Policyの作成と登録

`Review`モデルに対する`ReviewPolicy`を作成します。

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

`app/Policies/ReviewPolicy.php`を編集し、更新と削除の権限を定義します。

```php
// app/Policies/ReviewPolicy.php

class ReviewPolicy
{
    // ...

    public function update(User $user, Review $review): bool
    {
        // 投稿者本人であれば許可
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        // 投稿者本人であれば許可
        return $user->id === $review->user_id;
    }

    // ...
}
```

`AuthServiceProvider`に`ReviewPolicy`を登録します。

```php
// app/Providers/AuthServiceProvider.php

protected $policies = [
    Book::class => BookPolicy::class,
    Review::class => ReviewPolicy::class, // この行を追記
];
```

#### 3.2. ルーティングの追加

編集ページ表示、更新処理、削除処理の3つのルートを`routes/web.php`に追加します。

```php
// routes/web.php

Route::middleware('auth')->group(function () {
    // ...

    // レビュー管理
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});
```

#### 3.3. 編集機能の実装 (Update)

1.  **コントローラ (`ReviewController@edit`)**
    認可チェックを行い、レビュー編集ビューを返します。

    ```php
    // app/Http/Controllers/ReviewController.php

    public function edit(Review $review)
    {
        $this->authorize('update', $review);
        return view('reviews.edit', compact('review'));
    }
    ```

2.  **ビュー (`resources/views/reviews/edit.blade.php`)**
    提供されている`edit.blade.php`を配置します。フォームの`action`には`reviews.update`ルートを指定し、`@method('PUT')`ディレクティブを使います。

3.  **コントローラ (`ReviewController@update`)**
    `UpdateReviewRequest`（`StoreReviewRequest`と同じ内容でOK）でバリデーションし、認可チェックを行った上で更新処理を実行します。

    ```php
    // app/Http/Controllers/ReviewController.php
    use App\Http\Requests\UpdateReviewRequest; // 追加

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        // レビューが紐づく書籍の詳細ページにリダイレクト
        return redirect()->route('books.show', $review->book_id)->with('success', 'レビューを更新しました。');
    }
    ```

#### 3.4. 削除機能の実装 (Delete)

**コントローラ (`ReviewController@destroy`)**

認可チェックを行い、`delete`メソッドでレビューを削除します。

```php
// app/Http/Controllers/ReviewController.php

public function destroy(Review $review)
{
    $this->authorize('delete', $review);

    $review->delete();

    return redirect()->route('books.show', $review->book_id)->with('success', 'レビューを削除しました。');
}
```

#### 3.5. ビューへのボタン設置

書籍詳細ページ（`show.blade.php`）で、レビュー一覧を表示しているループの中に、編集・削除ボタンを設置します。`@can`ディレクティブを使うと、Policyによる認可チェックをビューの中で簡単に行えます。

```blade
{{-- resources/views/books/show.blade.php のレビュー表示ループ内 --}}

@foreach ($book->reviews as $review)
    {{-- ... レビュー内容の表示 ... --}}

    {{-- 認可チェック --}}
    @can('update', $review)
        <a href="{{ route('reviews.edit', $review) }}">編集</a>
    @endcan

    @can('delete', $review)
        <form action="{{ route('reviews.destroy', $review) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" onclick="return confirm('本当に削除しますか？');">削除</button>
        </form>
    @endcan
@endforeach
```

`@can('update', $review)`は、`ReviewPolicy`の`update`メソッドを呼び出し、結果が`true`の場合のみ内部のHTMLを出力します。これにより、自分のレビューにしか編集・削除ボタンが表示されなくなります。

### まとめ

このChapterでは、親子関係にあるレビュー機能のCRUDを実装しました。書籍管理機能で学んだ知識をベースに、より実践的なリレーションの活用方法や、ビューにおける認可（`@can`）について学びました。

- 親モデル（`Book`）のリレーションを経由した子モデル（`Review`）の作成
- `@can`ディレクティブを使ったビューでの表示制御

アプリケーションにユーザー参加型の機能が加わり、ぐっとWebサービスらしくなりましたね。次のChapterでは、お気に入りやいいねといった、さらにインタラクティブな機能を実装していきます。
