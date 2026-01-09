# Chapter 6: レビュー機能

このChapterでは、書籍に対してレビュー（評価とコメント）を投稿・編集・削除できる機能を実装します。書籍という「親」のデータに紐付く「子」のデータ（レビュー）をどう扱うかがポイントです。

---

## 6-1. 先輩エンジニアの思考プロセス：親子関係を持つデータのCRUD

### Step 1: 要件と画面遷移から実装の全体像を掴む

まず、要件定義書と画面遷移を確認し、レビュー機能がどのような流れで操作されるかを把握します。

**要件**: 「書籍詳細ページから、5段階評価とコメントでレビューを投稿できる」

**画面遷移**:
1.  ユーザーが**書籍詳細ページ**でフォームに入力し、「レビューを投稿」ボタンをクリックする。
2.  `reviews.store`ルートにリクエストが飛ぶ。
3.  `ReviewController@store`が実行され、DBにレビューが保存される。
4.  処理後、**同じ書籍詳細ページ**にリダイレクトされ、「レビューを投稿しました」と表示される。

> **先輩エンジニアの思考:**
> 「レビューは、必ず特定の書籍（Book）に紐づく。独立して存在することはない。つまり、レビューのCRUD操作の起点は、常に親である『書籍詳細ページ』になる。これはルーティング設計に大きく影響する。例えば、レビュー投稿のURLは`/reviews/create`ではなく、`/books/{book}/reviews`のように、どの書籍に対する操作なのかがURL自体から分かるように設計すべきだ。」

この「親子関係」を意識することが、リソースフルな設計の鍵となります。

### Step 2: 各アクションの「責務」を明確にする

書籍管理機能（Chapter 5）と同様に、レビュー機能に必要な「部品」を洗い出します。

- **ルーティング (`routes/web.php`)**: レビューのCRUD操作に対応するURLを定義する。
- **フォームリクエスト (`StoreReviewRequest`, `UpdateReviewRequest`)**: 評価（1〜5の整数）やコメントの文字数といったバリデーションを定義する。
- **ポリシー (`ReviewPolicy`)**: 「レビューを編集・削除できるのは投稿者本人のみ」という認可ルールを定義する。
- **コントローラー (`ReviewController`)**: 各部品を協調させ、レビューの保存・更新・削除処理を実行する。
- **ビュー (`reviews/edit.blade.php`, `books/show.blade.php`の一部)**: レビュー編集フォームや、書籍詳細ページ内のレビュー投稿フォーム・一覧部分を作成する。

---

## 6.2. 部品の作成 (Artisanコマンド)

レビュー機能に必要なコントローラー、フォームリクエスト、ポリシーの雛形を作成します。

```bash
# ReviewControllerには--resourceは不要。indexやshowは使わないため
sail artisan make:controller ReviewController

sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
sail artisan make:policy ReviewPolicy --model=Review
```

---

## 6.3. 認可ルールの実装 (Policy)

**要件**: 「自分が投稿したレビューを編集・削除できる」

1.  **ポリシーの登録 (`app/Providers/AuthServiceProvider.php`)**

    ```php
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class, // この行を追加
    ];
    ```

2.  **ポリシーの実装 (`app/Policies/ReviewPolicy.php`)**
    ロジックは`BookPolicy`と全く同じです。操作ユーザーとデータの所有者が一致するかを確認します。

    ```php
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
    ```

---

## 6.4. バリデーションルールの実装 (FormRequest)

**要件**: 「評価は1〜5の整数で必須」「コメントは1000文字以内」

### `app/Http/Requests/StoreReviewRequest.php`

```php
public function rules(): array
{
    return [
        'rating' => ['required', 'integer', 'min:1', 'max:5'],
        'comment' => ['nullable', 'string', 'max:1000'],
    ];
}
```

### `app/Http/Requests/UpdateReviewRequest.php`

`StoreReviewRequest`と全く同じルールを定義します。

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

---

## 6.5. ルーティングの定義 (`routes/web.php`)

レビュー操作のルートを`middleware('auth')`グループ内に追加します。

```php
// Review management
// POST /books/{book}/reviews -> reviews.store
Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

// GET /reviews/{review}/edit -> reviews.edit
Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');

// PUT /reviews/{review} -> reviews.update
Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');

// DELETE /reviews/{review} -> reviews.destroy
Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
```

> **【学習のポイント】**
> `reviews.store`のURLに注目してください。`/books/{book}/reviews`となっており、「どの書籍(book)にレビューを投稿(store)するのか」が明確に表現されています。このように、リソースの親子関係をURLで表現するのがRESTfulな設計の基本です。

---

## 6.6. コントローラーの実装 (`ReviewController.php`)

`Book`モデルと`Review`モデルのリレーションシップを活用して、簡潔なコードで実装していきます。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;

class ReviewController extends Controller
{
    /**
     * Create (Store): レビュー投稿処理
     */
    public function store(StoreReviewRequest $request, Book $book)
    {
        // 要件：書籍詳細ページからレビューを投稿できる。
        // 思考：
        // 1. 親である`$book`モデルのリレーション(`reviews()`)経由で`create()`を呼ぶ。
        // 2. これにより、`book_id`が自動的に設定され、コードが簡潔になる。
        // 3. `user_id`も`$request->user()->id()` (または`Auth::id()`)で設定する。
        $review = new Review($request->validated());
        $review->user_id = $request->user()->id;
        $book->reviews()->save($review);

     return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');  }

    /**
     * Update (Form): レビュー編集フォーム表示
     */
    public function edit(Review $review)
    {
        // 要件：自分が投稿したレビューを編集できる。
        // 思考：
        // 1. まず`ReviewPolicy`で認可チェックを行う。
        // 2. 許可されれば、編集対象の`$review`オブジェクトをビューに渡す。
        $this->authorize('update', $review);      return view('reviews.edit', compact('review'));  }

    /**
     * Update (Store): レビュー更新処理
     */
    public function update(UpdateReviewRequest $request, Review $review)
    {
        // 要件：レビュー情報を更新する。
        // 思考：
        // 1. まず`ReviewPolicy`で認可チェック。
        // 2. `update()`メソッドで一括更新。
        // 3. 更新後は、そのレビューが属していた書籍の詳細ページに戻る必要がある。
        //    `$review->book`で親のBookモデルを取得できるのがリレーションの強み。
        $this->authorize('update', $review);
        $review->update($request->validated());

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');

    /**
     * Delete: レビュー削除処理
     */
    public function destroy(Review $review)
    {
        // 要件：自分が投稿したレビューを削除できる。
        // 思考：
        // 1. まず`ReviewPolicy`で認可チェック。
        // 2. 削除後リダイレクトするために、削除前に親のBookモデルを`$book`変数に保持しておく。
        // 3. `delete()`メソッドで削除を実行。
       $this->authorize('delete', $review);        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
}
```

---

## 6.7. ビューの実装

### レビュー編集画面 (`resources/views/reviews/edit.blade.php`)

まず、必要なディレクトリと空のファイルを作成します。

```bash
# ディレクトリを作成
mkdir -p resources/views/reviews

# 空のファイルを作成
touch resources/views/reviews/edit.blade.php
```


```html
<x-app-layout>
    <x-slot name="header">レビューの編集</x-slot>
   <form action="{{ route('reviews.update', $review) }}" method="POST">       @method('PUT')        @csrf
        <div>
            <label for="rating">評価</label>
            <select name="rating" id="rating" required>
                @for ($i = 1; $i <= 5; $i++)
               <option value="{{ $i }}" {{ old('rating', $review->rating) == $i ? 'selected' : '' }}>                        {{ $i }}
                    </option>
                @endfor
            </select>
        </div>
        <div>
            <label for="comment">コメント</label>
           <textarea name="comment" id="comment">{{ old('comment', $review->comment) }}</textarea>        </div>
        <button type="submit">更新する</button>
    </form>
</x-app-layout>
```

### 書籍詳細画面へのフォーム追加 (`resources/views/books/show.blade.php`)

書籍詳細ページに、レビュー投稿フォームとレビュー一覧表示を追加します。（このビューの全体像は後のChapterで完成させます）

```html
<!-- レビュー投稿フォーム -->
@auth<form action="{{ route('reviews.store', $book) }}" method="POST">    @csrf
    <!-- 評価とコメントの入力欄 -->
    <button type="submit">レビューを投稿</button>
</form>
@endauth

<!-- レビュー一覧 -->
@foreach ($book->reviews as $review)
    <p>{{ $review->user->name }}</p>
    <p>評価: {{ $review->rating }}</p>
    <p>{{ $review->comment }}</p>
    @can('update', $review)
      <a href="{{ route('reviews.edit', $review) }}">編集</a>
    @endcan
   @can('delete', $review)        <form action="{{ route('reviews.destroy', $review) }}" method="POST">           @csrf
            @method('DELETE')           <button type="submit">削除</button>
        </form>
    @endcan
@endforeach
```

> **【学習のポイント】**
> `@can('update', $review)いうBladeディレクティブに注目してください。これは`ReviewPolicy`の`update`メソッドを呼び出し、認可がある場合のみ内部のHTML（編集ボタン）を表示します。これにより、コントローラーだけでなくビュー層でも認可チェックが簡単に行えます。

---

## 6.8. 動作確認

1.  書籍詳細ページでレビューを投稿できることを確認します。
2.  投稿後、同じページにリダイレクトされ、自分のレビューが表示されることを確認します。
3.  自分が投稿したレビューにのみ「編集」「削除」ボタンが表示されることを確認します。
4.  編集ページにアクセスし、レビューを更新できることを確認します。
5.  削除ボタンを押し、レビューが一覧から消えることを確認します。

これで、レビュー機能の実装が完了しました。
'''
