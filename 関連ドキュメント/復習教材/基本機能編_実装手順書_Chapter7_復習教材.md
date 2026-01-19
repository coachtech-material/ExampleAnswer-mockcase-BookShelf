# Chapter 7: レビュー機能の実装

## 🎯 このセクションで学ぶこと

このセクションでは、書籍に対するレビュー（評価とコメント）を投稿・編集・削除する機能を実装します。

- **ネストしたリソース**: 書籍（`Book`）に紐づくレビュー（`Review`）という、親子関係のあるリソースの扱い方を学びます。
- **リレーションを活用したデータ作成**: `$book->reviews()->create([...])`のように、リレーションを通じてデータを作成する方法を学びます。
- **ポリシーによる認可**: 自分が投稿したレビューのみ編集・削除できるように制御します。

---

## 7.1. フォームリクエストの作成

まずは、レビューの投稿（`store`）と更新（`update`）で利用するフォームリクエストを作成します。

```bash
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
```

---

## 7.2. フォームリクエストの実装

作成した2つのフォームリクエストファイルに、バリデーションルールを定義します。

### 7.2.1. StoreReviewRequest

`app/Http/Requests/StoreReviewRequest.php`を以下のように編集します。

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

### 7.2.2. UpdateReviewRequest

`app/Http/Requests/UpdateReviewRequest.php`も同様に編集します。今回は`StoreReviewRequest`と全く同じ内容です。

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

## 7.3. ReviewController.php の実装 (権限チェックなし)

次に、`app/Http/Controllers/ReviewController.php`に、レビューの投稿・編集・更新・削除のロジックを実装します。この段階では、まだ権限チェック（ポリシー）は実装しません。

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
        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review)
    {
        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

> **💡 ポイント**
> この段階で一度、レビューの投稿、編集、削除が問題なく動作するか確認しましょう。まだポリシーを適用していないため、**どのユーザーでも**他人のレビューを編集・削除できてしまうはずです。この「穴」がある状態を意図的に作り、次のステップで塞いでいきます。

---

## 7.4. ポリシーの作成と実装

レビュー機能が動作することを確認したら、次にセキュリティ（認可）を実装します。

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

作成された`app/Policies/ReviewPolicy.php`を以下のように編集します。

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

## 7.5. ポリシーの登録

作成したポリシーをLaravelに認識させるため、`app/Providers/AuthServiceProvider.php`に登録します。

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Review; // 追加
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy; // 追加
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class, // 追加
    ];

    public function boot(): void
    {
        //
    }
}
```

---

## 7.6. コントローラーへの権限チェック追加

最後に、`ReviewController`の各メソッドに、ポリシーを使った権限チェックのコードを追加します。

`app/Http/Controllers/ReviewController.php`の`edit`, `update`, `destroy`メソッドを以下のように修正してください。

```php
// app/Http/Controllers/ReviewController.php (修正箇所のみ)

public function edit(Review $review)
{
    // 権限チェックを追加
    $this->authorize('update', $review);
    return view('reviews.edit', compact('review'));
}

public function update(UpdateReviewRequest $request, Review $review)
{
    // 権限チェックを追加
    $this->authorize('update', $review);
    $review->update([
        'rating' => $request->rating,
        'comment' => $request->comment,
    ]);

    return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
}

public function destroy(Review $review)
{
    // 権限チェックを追加
    $this->authorize('delete', $review);
    $book = $review->book;
    $review->delete();

    return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
}
```

これで、レビュー機能の実装は完了です。他人のレビューの編集・削除ボタンが表示されなくなり、直接URLにアクセスしても403エラー（Forbidden）が表示されることを確認してください。
