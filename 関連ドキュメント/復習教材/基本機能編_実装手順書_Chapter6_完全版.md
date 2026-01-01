# Chapter 6: レビュー機能

このChapterでは、書籍に対してレビュー（評価とコメント）を投稿・編集・削除できる機能を実装します。

---

## 6.1. コントローラーとリクエストの作成

```bash
sail artisan make:controller ReviewController
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
sail artisan make:policy ReviewPolicy --model=Review
```

---

## 6.2. ポリシーの登録

`app/Providers/AuthServiceProvider.php` に `ReviewPolicy` を追加します。

```php
protected $policies = [
    Book::class => BookPolicy::class,
    Review::class => ReviewPolicy::class,
];
```

---

## 6.3. ReviewControllerの実装

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        $book->reviews()->create([
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review)
    {
        $this->authorize('update', $review);
        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);
        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);
        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

---

## 6.4. フォームリクエストの実装

### `app/Http/Requests/StoreReviewRequest.php`

```php
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

---

## 6.5. ルートの追加

`routes/web.php` の認証必須ルートに以下を追加します。

```php
// Review management
Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
```

---

## 6.6. ReviewPolicyの実装

```php
<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

---

## 6.7. 動作確認

1. 書籍詳細ページでレビューを投稿できることを確認
2. 自分が投稿したレビューのみ編集・削除ボタンが表示されることを確認
3. 他のユーザーが投稿したレビューは編集・削除できないことを確認

これで、レビュー機能の実装が完了しました。
