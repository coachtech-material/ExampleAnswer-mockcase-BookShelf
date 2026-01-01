# Chapter 5: レビュー機能の実装

このChapterでは、書籍に対するレビュー機能を実装します。レビューは書籍に紐付く子リソースであり、ネストされたルーティングを使用します。

## 5-1. 要件定義書の確認とPMへのヒアリング

### 要件定義書から読み取れる情報

| 機能 | 概要 |
|---|---|
| レビュー投稿 | 書籍に対してレビューを投稿する |
| レビュー編集 | 投稿したレビューを編集する |
| レビュー削除 | 投稿したレビューを削除する |

### PMへのヒアリングシート

1. **レビューの制約**: 同じユーザーが同じ書籍に複数のレビューを投稿できますか？
   - **回答例**: いいえ、1ユーザー1書籍につき1レビューのみ
2. **評価の範囲**: 評価は何点満点ですか？
   - **回答例**: 1〜5の整数
3. **レビューの編集・削除権限**: 誰が編集・削除できますか？
   - **回答例**: 投稿したユーザーのみ
4. **フラッシュメッセージ**: 投稿・更新・削除時のメッセージを教えてください。
   - **回答例**: 「レビューを投稿しました。」「レビューを更新しました。」「レビューを削除しました。」

## 5-2. Bladeテンプレートの読み解き

### `books/show.blade.php` のレビューセクション分析

```blade
{{-- レビュー投稿フォーム --}}
@auth
    @unless ($book->reviews->contains('user_id', Auth::id()))
        <form method="POST" action="{{ route('books.reviews.store', $book) }}">
            @csrf
            <select name="rating">
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
            <textarea name="comment"></textarea>
            <button type="submit">投稿</button>
        </form>
    @endunless
@endauth

{{-- レビュー一覧 --}}
@foreach ($book->reviews as $review)
    <div>
        <p>{{ $review->user->name }}</p>
        <p>評価: {{ $review->rating }}</p>
        <p>{{ $review->comment }}</p>

        @can('update', $review)
            <a href="{{ route('books.reviews.edit', [$book, $review]) }}">編集</a>
        @endcan

        @can('delete', $review)
            <form action="{{ route('books.reviews.destroy', [$book, $review]) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit">削除</button>
            </form>
        @endcan
    </div>
@endforeach
```

**読み取れる情報:**
- `route('books.reviews.store', $book)`: ネストされたルーティング（`/books/{book}/reviews`）
- `$book->reviews->contains('user_id', Auth::id())`: 既にレビュー投稿済みかどうかをチェック
- `route('books.reviews.edit', [$book, $review])`: 編集ルートには書籍とレビューの両方が必要
- `@can('update', $review)`, `@can('delete', $review)`: ReviewPolicyが必要

## 5-3. コントローラの作成

### Step 1: コントローラの生成

```bash
sail artisan make:controller ReviewController
```

### Step 2: コントローラの実装

**`app/Http/Controllers/ReviewController.php`:**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * レビューを投稿
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        // 既にレビュー投稿済みかチェック
        $existingReview = $book->reviews()->where('user_id', Auth::id())->first();
        if ($existingReview) {
            return redirect()
                ->route('books.show', $book)
                ->with('error', 'この書籍には既にレビューを投稿しています。');
        }

        $book->reviews()->create([
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * レビュー編集フォームを表示
     */
    public function edit(Book $book, Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('book', 'review'));
    }

    /**
     * レビューを更新
     */
    public function update(UpdateReviewRequest $request, Book $book, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューを削除
     */
    public function destroy(Book $book, Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()
            ->route('books.show', $book)
            ->with('success', 'レビューを削除しました。');
    }
}
```

**コードリーディング:**

**store メソッド:**
```php
$existingReview = $book->reviews()->where('user_id', Auth::id())->first();
if ($existingReview) {
    return redirect()
        ->route('books.show', $book)
        ->with('error', 'この書籍には既にレビューを投稿しています。');
}
```
- データベースの一意制約に加えて、コントローラでも重複チェックを行います。これにより、ユーザーフレンドリーなエラーメッセージを表示できます。

```php
$book->reviews()->create([
    'user_id' => Auth::id(),
    'rating' => $request->rating,
    'comment' => $request->comment,
]);
```
- `$book->reviews()->create(...)`: リレーション経由でレビューを作成します。`book_id` は自動的に設定されます。

**edit, update, destroy メソッド:**
```php
public function edit(Book $book, Review $review): View
```
- ネストされたルーティングでは、親リソース（Book）と子リソース（Review）の両方がメソッドの引数として渡されます。

## 5-4. FormRequestの作成

### Step 1: FormRequestの生成

```bash
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
```

### Step 2: StoreReviewRequestの実装

**`app/Http/Requests/StoreReviewRequest.php`:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => '評価',
            'comment' => 'コメント',
        ];
    }
}
```

**コードリーディング:**
- `'rating' => ['required', 'integer', 'min:1', 'max:5']`: 評価は必須、整数、1〜5の範囲。

### Step 3: UpdateReviewRequestの実装

**`app/Http/Requests/UpdateReviewRequest.php`:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => '評価',
            'comment' => 'コメント',
        ];
    }
}
```

## 5-5. Policyの作成

### Step 1: Policyの生成

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

### Step 2: Policyの実装

**`app/Policies/ReviewPolicy.php`:**

```php
<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Review $review): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

### Step 3: Policyの登録

**`app/Providers/AuthServiceProvider.php`:**

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Review;
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
```

## 5-6. ルーティングの設定

**`routes/web.php`:**

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::resource('books', BookController::class);

// ネストされたリソースルート
Route::resource('books.reviews', ReviewController::class)
    ->only(['store', 'edit', 'update', 'destroy']);
```

**コードリーディング:**
- `Route::resource('books.reviews', ReviewController::class)`: ネストされたリソースルートを定義します。
- `->only(['store', 'edit', 'update', 'destroy'])`: 必要なアクションのみを定義します。`index`, `create`, `show` は不要です（レビュー一覧は書籍詳細ページに表示、投稿フォームも書籍詳細ページに配置）。

生成されるルート:

| メソッド | URI | アクション | ルート名 |
|---|---|---|---|
| POST | /books/{book}/reviews | store | books.reviews.store |
| GET | /books/{book}/reviews/{review}/edit | edit | books.reviews.edit |
| PUT/PATCH | /books/{book}/reviews/{review} | update | books.reviews.update |
| DELETE | /books/{book}/reviews/{review} | destroy | books.reviews.destroy |

## 5-7. Bladeテンプレートの作成・更新

### `books/show.blade.php` にレビューセクションを追加

**`resources/views/books/show.blade.php`:**（レビューセクション部分）

```blade
{{-- 既存の書籍詳細部分の後に追加 --}}

<div class="mt-8 bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-bold mb-4">レビュー</h2>

    {{-- レビュー投稿フォーム --}}
    @auth
        @unless ($book->reviews->contains('user_id', Auth::id()))
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="font-bold mb-2">レビューを投稿</h3>
                <form method="POST" action="{{ route('books.reviews.store', $book) }}">
                    @csrf

                    <div class="mb-4">
                        <label for="rating" class="block text-gray-700 font-medium mb-2">評価 <span class="text-red-500">*</span></label>
                        <select name="rating" id="rating"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('rating') border-red-500 @enderror">
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" {{ old('rating') == $i ? 'selected' : '' }}>
                                    {{ $i }} {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }}
                                </option>
                            @endfor
                        </select>
                        @error('rating')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="comment" class="block text-gray-700 font-medium mb-2">コメント <span class="text-red-500">*</span></label>
                        <textarea name="comment" id="comment" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('comment') border-red-500 @enderror">{{ old('comment') }}</textarea>
                        @error('comment')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        投稿
                    </button>
                </form>
            </div>
        @else
            <p class="mb-6 text-gray-500">この書籍には既にレビューを投稿しています。</p>
        @endunless
    @else
        <p class="mb-6 text-gray-500">レビューを投稿するには<a href="{{ route('login') }}" class="text-blue-500 hover:underline">ログイン</a>してください。</p>
    @endauth

    {{-- レビュー一覧 --}}
    <div class="space-y-4">
        @forelse ($book->reviews as $review)
            <div class="border-b border-gray-200 pb-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-bold">{{ $review->user->name }}</p>
                        <p class="text-yellow-500">
                            {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        @can('update', $review)
                            <a href="{{ route('books.reviews.edit', [$book, $review]) }}" class="text-blue-500 hover:underline text-sm">
                                編集
                            </a>
                        @endcan
                        @can('delete', $review)
                            <form action="{{ route('books.reviews.destroy', [$book, $review]) }}" method="POST" class="inline" onsubmit="return confirm('本当に削除しますか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline text-sm">
                                    削除
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
                <p class="mt-2 whitespace-pre-wrap">{{ $review->comment }}</p>
                <p class="text-gray-400 text-sm mt-2">{{ $review->created_at->format('Y年m月d日 H:i') }}</p>
            </div>
        @empty
            <p class="text-gray-500">まだレビューがありません。</p>
        @endforelse
    </div>
</div>
```

**コードリーディング:**
- `@unless ($book->reviews->contains('user_id', Auth::id()))`: 現在のユーザーがまだレビューを投稿していない場合のみフォームを表示します。
- `str_repeat('★', $review->rating)`: 評価に応じた数の★を表示します。
- `route('books.reviews.edit', [$book, $review])`: ネストされたルートには、親リソースと子リソースの両方を渡します。

### `reviews/edit.blade.php` の作成

**`resources/views/reviews/edit.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'レビュー編集')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-6">レビュー編集</h1>
    <p class="text-gray-600 mb-4">書籍: {{ $book->title }}</p>

    <form method="POST" action="{{ route('books.reviews.update', [$book, $review]) }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label for="rating" class="block text-gray-700 font-medium mb-2">評価 <span class="text-red-500">*</span></label>
            <select name="rating" id="rating"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('rating') border-red-500 @enderror">
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" {{ old('rating', $review->rating) == $i ? 'selected' : '' }}>
                        {{ $i }} {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }}
                    </option>
                @endfor
            </select>
            @error('rating')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="comment" class="block text-gray-700 font-medium mb-2">コメント <span class="text-red-500">*</span></label>
            <textarea name="comment" id="comment" rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('comment') border-red-500 @enderror">{{ old('comment', $review->comment) }}</textarea>
            @error('comment')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('books.show', $book) }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                キャンセル
            </a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                更新
            </button>
        </div>
    </form>
</div>
@endsection
```

## 5-8. BookControllerの修正

書籍詳細ページでレビューを表示するため、`show` メソッドでレビューをEager Loadingします。

**`app/Http/Controllers/BookController.php`:**（show メソッドの修正）

```php
/**
 * 書籍詳細を表示
 */
public function show(Book $book): View
{
    $book->load(['user', 'genres', 'reviews.user']);

    return view('books.show', compact('book'));
}
```

**コードリーディング:**
- `'reviews.user'`: レビューとその投稿者を一緒にEager Loadingします。これにより、N+1問題を防ぎます。

---

これでレビュー機能の実装が完了しました。次のChapterでは、お気に入り、いいね、ジャンル管理、ランキングなどのその他機能を実装していきます。
