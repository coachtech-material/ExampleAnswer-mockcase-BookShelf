# Chapter 5: レビュー機能

このChapterでは、書籍に対するレビューの投稿・編集・削除機能を実装します。書籍とユーザーに紐付く、リレーションを意識した実装がポイントです。

## 5-1. ControllerとRequestの作成

レビュー機能のロジックを担う`ReviewController`と、バリデーションを行う`StoreReviewRequest`を作成します。

### Step 1: ControllerとRequestの生成

```bash
sail artisan make:controller ReviewController
sail artisan make:request StoreReviewRequest
```

**思考プロセス:**
今回は`--resource`オプションを付けずにControllerを作成しました。なぜなら、レビュー機能は書籍詳細ページ（`books.show`）に統合されており、`index`（一覧）や`create`（作成ページ）が不要なためです。必要なメソッドだけを個別に定義する方が、コードがスッキリします。

## 5-2. バリデーションルールの定義

`StoreReviewRequest`に、レビュー投稿時のバリデーションルールを定義します。

**`app/Http/Requests/StoreReviewRequest.php`**
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
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ];
    }
}
```

**コードリーディング:**
- `'rating' => 'required|integer|min:1|max:5'`: 評価は必須、整数、そして1から5の範囲でなければなりません。
- `'comment' => 'required|string|max:1000'`: コメントは必須、文字列、そして最大1000文字までです。

## 5-3. ルーティングの設定

レビューに関するルートを`routes/web.php`に追加します。レビューは常に特定の書籍に紐付くため、ルートもネスト（入れ子）させます。

**`routes/web.php`**
```php
use App\Http\Controllers\ReviewController;

// ... 他のルート

Route::middleware('auth')->group(function () {
    // ... 他の認証済みルート

    // レビューの保存
    Route::post('books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    // レビューの削除
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});
```

**思考プロセス:**
- `books/{book}/reviews`: このようにURLを設計することで、「どの書籍(`{book}`)に対するレビューなのか」が明確になります。RESTfulな設計思想に基づいています。
- `middleware('auth')`: レビューの投稿や削除はログインしているユーザーしか行えないため、認証ミドルウェアでルートを保護します。

## 5-4. Controllerの実装

`ReviewController`に、レビューの保存（`store`）と削除（`destroy`）のロジックを実装します。

**`app/Http/Controllers/ReviewController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreReviewRequest;
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

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);
        $review->delete();

        return back()->with('success', 'レビューを削除しました。');
    }
}
```

**コードリーディング:**
- `store(StoreReviewRequest $request, Book $book)`: ルートモデルバインディングにより、URLの`{book}`に対応する`Book`インスタンスが自動的に注入されます。
- `$book->reviews()->save($review)`: `Book`モデルと`Review`モデルのリレーション（`hasMany`）を利用して、書籍に紐付いたレビューとして保存します。`book_id`が自動的に設定されます。
- `destroy(Review $review)`: こちらもルートモデルバインディングです。削除対象の`Review`インスタンスが注入されます。
- `$this->authorize('delete', $review)`: `ReviewPolicy`（後ほど作成）を使って、このレビューを削除する権限があるかチェックします。
- `return back()`: 直前のページ（この場合は書籍詳細ページ）にリダイレクトします。

## 5-5. 認可機能 (ReviewPolicy) の実装

レビューを削除できるのは投稿した本人のみ、というルールを`ReviewPolicy`で定義します。

### Step 1: Policyの生成と登録

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

**`app/Providers/AuthServiceProvider.php`**
```php
protected $policies = [
    Book::class => BookPolicy::class,
    Review::class => ReviewPolicy::class, // この行を追加
];
```

### Step 2: Policyへのロジック実装

**`app/Policies/ReviewPolicy.php`**
```php
<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

## 5-6. Bladeテンプレートの編集

書籍詳細ページに、レビュー投稿フォームとレビュー一覧表示を追加します。

**`resources/views/books/show.blade.php`**
```blade
{{-- ... 書籍情報 ... --}}

{{-- レビュー投稿フォーム --}}
@auth
<div class="mt-8">
    <h2 class="text-xl font-bold">レビューを投稿する</h2>
    <form action="{{ route('reviews.store', $book) }}" method="POST">
        @csrf
        <div>
            <label for="rating">評価</label>
            <select name="rating" id="rating">
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select>
        </div>
        <div>
            <label for="comment">コメント</label>
            <textarea name="comment" id="comment" rows="4">{{ old('comment') }}</textarea>
        </div>
        <button type="submit">投稿</button>
    </form>
</div>
@endauth

{{-- レビュー一覧 --}}
<div class="mt-8">
    <h2 class="text-xl font-bold">レビュー一覧</h2>
    @forelse ($book->reviews as $review)
        <div class="border-t py-4">
            <p><strong>{{ $review->user->name }}</strong> (評価: {{ $review->rating }})</p>
            <p>{{ $review->comment }}</p>
            @can('delete', $review)
                <form action="{{ route('reviews.destroy', $review) }}" method="POST" onsubmit="return confirm(\'本当に削除しますか？\');">
                    @csrf
                    @method('DELETE')
                    <button type="submit">削除</button>
                </form>
            @endcan
        </div>
    @empty
        <p>まだレビューはありません。</p>
    @endforelse
</div>
```

**コードリーディング:**
- `@auth ... @endauth`: ログインしているユーザーにのみレビュー投稿フォームを表示します。
- `@forelse ($book->reviews as $review) ... @empty ... @endforelse`: 書籍に紐付くレビューをループで表示します。レビューが1件もない場合は`@empty`の中身が表示されます。
- `@can('delete', $review)`: `ReviewPolicy`を使い、削除ボタンを投稿者本人にのみ表示します。

## 5-7. 動作確認

1.  書籍詳細ページにレビュー投稿フォームが表示されていることを確認します（要ログイン）。
2.  評価とコメントを入力して投稿し、「レビューを投稿しました。」というメッセージと共にレビューが一覧に表示されることを確認します。
3.  投稿したレビューに削除ボタンが表示されていることを確認します。
4.  他のユーザーでログインし、同じ書籍詳細ページを開きます。そのレビューに削除ボタンが表示されていないことを確認します。
5.  削除ボタンを押し、確認ダイアログでOKを押すとレビューが削除されることを確認します。

---

これでレビュー機能が完成しました。次のChapterでは、お気に入り機能といいね機能を実装します。
