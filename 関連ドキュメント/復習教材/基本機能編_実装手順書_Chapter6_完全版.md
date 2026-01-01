
# Chapter 6: レビュー機能の実装

このChapterでは、書籍に対するレビューの投稿・編集・削除機能を実装します。書籍とユーザーに紐付く、リレーションを意識した実装がポイントです。

## 6-1. ControllerとRequestの作成

レビュー機能のロジックを担う`ReviewController`と、バリデーションを行う`StoreReviewRequest`, `UpdateReviewRequest`を作成します。

```bash
sail artisan make:controller ReviewController
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
```

> **思考プロセス:**
> なぜ`ReviewController`には`--resource`オプションを付けないのでしょうか？ 今回実装するレビュー機能は、投稿（`store`）、更新（`update`）、削除（`destroy`）が主であり、レビューの一覧（`index`）は書籍詳細ページの一部として表示され、独立したページは持ちません。また、投稿フォーム（`create`）も書籍詳細ページに埋め込まれます。このように、RESTfulなリソースの7つのアクション全てを必要としない場合は、`--resource`を使わずにコントローラを作成し、必要なメソッドだけを個別に定義する方が、不要なコードが生成されず、プロジェクトをクリーンに保てます。

## 6-2. ルーティングの設定

レビューに関するルートを`routes/web.php`に追加します。

**`routes/web.php`**
```php
use App\Http\Controllers\ReviewController;

Route::middleware('auth')->group(function () {
    // ... 他の認証済みルート

    // レビュー
    Route::post('books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});
```

> **思考プロセス:**
> - **ネストされたルート (`books/{book}/reviews`)**: レビューは必ず特定の書籍に紐付く「子」リソースです。そのため、`store`アクションのURLを`/books/{book}/reviews`のようにネストさせることで、「どの書籍に対するレビューなのか」という親子関係をURL構造で明確に表現できます。これはRESTfulなAPI設計のベストプラクティスです。
> - **ネストしないルート (`reviews/{review}`):** 一方で、編集・更新・削除のアクションは、対象となるレビューのID (`{review}`) さえ分かれば操作可能です。そのため、こちらはトップレベルのURL `/reviews/{review}` とし、シンプルに保ちます。

## 6-3. バリデーションと認可の実装

### Step 1: Form Requestの編集

**`app/Http/Requests/StoreReviewRequest.php`**
```php
public function rules(): array
{
    return [
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:1000',
    ];
}
```

**`app/Http/Requests/UpdateReviewRequest.php`**
```php
public function rules(): array
{
    return [
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:1000',
    ];
}
```

### Step 2: ReviewPolicyの作成と登録

レビューを編集・削除できるのは投稿した本人のみ、というルールを`ReviewPolicy`で定義します。

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

`AuthServiceProvider`に`ReviewPolicy`を登録します。

**`app/Providers/AuthServiceProvider.php`**
```php
protected $policies = [
    Book::class => BookPolicy::class,
    Review::class => ReviewPolicy::class, // この行を追加
];
```

### Step 3: ReviewPolicyへのロジック実装

**`app/Policies/ReviewPolicy.php`**
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

## 6-4. Controllerの実装

`ReviewController`に、レビューの保存・編集・更新・削除のロジックを実装します。

**`app/Http/Controllers/ReviewController.php`**
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
        $review = new Review($request->validated());
        $review->user_id = Auth::id();
        $book->reviews()->save($review);

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
        $review->update($request->validated());

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

> **コード解説:**
> - `store()`: `Book`モデルと`Review`モデルのリレーション（`hasMany`）を利用して、`$book->reviews()->save($review)` とすることで、`book_id`を自動的に設定しながらレビューを保存しています。これにより、モデル間の関連性をコード上で明確に表現できます。
> - `update()`: 更新後、`$review->book` を使って関連する書籍モデルを取得し、その書籍の詳細ページにリダイレクトしています。リレーションシップを定義したことで、このようにオブジェクトのプロパティにアクセスする感覚で関連モデルをたどることができます。
> - `destroy()`: 削除処理の前にリダイレクト先の書籍情報を `$book = $review->book;` として変数に保持しています。なぜなら、`$review->delete()` を実行するとリレーションが切れてしまい、`$review->book` にアクセスできなくなるためです。

---

これでレビュー機能のバックエンドが完成しました。次のChapterでは、これらの機能に対応するBladeテンプレートを作成し、フロントエンドを構築します。
