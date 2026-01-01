# Chapter 4: 書籍管理機能の実装

このChapterでは、書籍のCRUD（Create, Read, Update, Delete）機能を実装します。LaravelのMVC（Model-View-Controller）パターンに沿って、コントローラ、FormRequest、Policyを作成していきます。

## 4-1. 要件定義書の確認とPMへのヒアリング

### 要件定義書から読み取れる情報

| 機能 | 概要 |
|---|---|
| 書籍一覧表示 | 登録されている書籍を一覧表示する |
| 書籍詳細表示 | 書籍の詳細情報を表示する |
| 書籍登録 | 新しい書籍を登録する |
| 書籍編集 | 登録済みの書籍情報を編集する |
| 書籍削除 | 登録済みの書籍を削除する |

### PMへのヒアリングシート

1. **書籍一覧のページネーション**: 1ページに何件表示しますか？
   - **回答例**: 10件
2. **書籍の編集・削除権限**: 誰が編集・削除できますか？
   - **回答例**: 登録したユーザーのみ
3. **バリデーションルール**: 各項目の制約を教えてください。
   - **回答例**: タイトル（必須、255文字以内）、著者（必須、255文字以内）、ISBN（必須、13桁、一意）、出版日（任意）、説明（任意）
4. **フラッシュメッセージ**: 登録・更新・削除時のメッセージを教えてください。
   - **回答例**: 「書籍を登録しました。」「書籍情報を更新しました。」「書籍を削除しました。」

## 4-2. Bladeテンプレートの読み解き

PMから提供されたBladeテンプレートを確認し、コントローラに何が必要かを逆算します。

### `books/index.blade.php` の分析

```blade
@foreach ($books as $book)
    <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
    <p>{{ $book->author }}</p>
    <p>平均評価: {{ number_format($book->reviews_avg_rating, 1) }}</p>
@endforeach

{{ $books->links() }}
```

**読み取れる情報:**
- `$books`: 書籍のコレクション（ページネーション付き）
- `$book->title`, `$book->author`: Bookモデルのプロパティ
- `$book->reviews_avg_rating`: レビューの平均評価（Eager Loadingで取得）
- `$books->links()`: ページネーションリンク

**コントローラに必要な実装:**
```php
$books = Book::withAvg('reviews', 'rating')->paginate(10);
return view('books.index', compact('books'));
```

### `books/show.blade.php` の分析

```blade
<h1>{{ $book->title }}</h1>
<p>著者: {{ $book->author }}</p>
<p>ISBN: {{ $book->isbn }}</p>
<p>出版日: {{ $book->published_date?->format('Y年m月d日') }}</p>
<p>説明: {{ $book->description }}</p>
<p>登録者: {{ $book->user->name }}</p>

@foreach ($book->genres as $genre)
    <span>{{ $genre->name }}</span>
@endforeach

@can('update', $book)
    <a href="{{ route('books.edit', $book) }}">編集</a>
@endcan

@can('delete', $book)
    <form action="{{ route('books.destroy', $book) }}" method="POST">
        @csrf
        @method('DELETE')
        <button type="submit">削除</button>
    </form>
@endcan
```

**読み取れる情報:**
- `$book`: 単一の書籍モデル
- `$book->user`: 登録者（リレーション）
- `$book->genres`: 紐付くジャンル（多対多リレーション）
- `@can('update', $book)`, `@can('delete', $book)`: 認可チェック（Policyが必要）

### `books/create.blade.php` の分析

```blade
<form method="POST" action="{{ route('books.store') }}">
    @csrf
    <input type="text" name="title" value="{{ old('title') }}">
    <input type="text" name="author" value="{{ old('author') }}">
    <input type="text" name="isbn" value="{{ old('isbn') }}">
    <input type="date" name="published_date" value="{{ old('published_date') }}">
    <textarea name="description">{{ old('description') }}</textarea>

    @foreach ($genres as $genre)
        <label>
            <input type="checkbox" name="genres[]" value="{{ $genre->id }}"
                {{ in_array($genre->id, old('genres', [])) ? 'checked' : '' }}>
            {{ $genre->name }}
        </label>
    @endforeach

    <button type="submit">登録</button>
</form>
```

**読み取れる情報:**
- `$genres`: ジャンル一覧（チェックボックス用）
- `name="genres[]"`: 複数選択可能（配列として送信）
- フォーム項目: `title`, `author`, `isbn`, `published_date`, `description`, `genres[]`

## 4-3. コントローラの作成

### Step 1: コントローラの生成

```bash
sail artisan make:controller BookController --resource
```

`--resource` オプションにより、CRUD操作に必要なメソッドが自動生成されます。

### Step 2: コントローラの実装

**`app/Http/Controllers/BookController.php`:**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * コンストラクタ
     * 認証が必要なアクションを指定
     */
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * 書籍一覧を表示
     */
    public function index(): View
    {
        $books = Book::with(['user', 'genres'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 書籍登録フォームを表示
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍を登録
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = Auth::user()->books()->create($request->validated());

        // ジャンルの紐付け
        if ($request->has('genres')) {
            $book->genres()->attach($request->genres);
        }

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細を表示
     */
    public function show(Book $book): View
    {
        $book->load(['user', 'genres', 'reviews.user']);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集フォームを表示
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $genres = Genre::all();
        $book->load('genres');

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍情報を更新
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $book->update($request->validated());

        // ジャンルの紐付けを更新
        $book->genres()->sync($request->genres ?? []);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
```

**コードリーディング:**

**コンストラクタ:**
```php
$this->middleware('auth')->except(['index', 'show']);
```
- `index` と `show` 以外のアクションには認証が必要です。未ログインユーザーは書籍の一覧・詳細は見れますが、登録・編集・削除はできません。

**index メソッド:**
```php
$books = Book::with(['user', 'genres'])
    ->withAvg('reviews', 'rating')
    ->latest()
    ->paginate(10);
```
- `with(['user', 'genres'])`: Eager Loadingで関連データを事前に取得します。これにより、N+1問題を防ぎます。
- `withAvg('reviews', 'rating')`: レビューの平均評価を計算して `reviews_avg_rating` として取得します。
- `latest()`: 作成日時の降順でソートします（新しい順）。
- `paginate(10)`: 10件ずつページネーションします。

**store メソッド:**
```php
$book = Auth::user()->books()->create($request->validated());
```
- `Auth::user()->books()->create(...)`: 現在ログイン中のユーザーに紐付けて書籍を作成します。`user_id` は自動的に設定されます。
- `$request->validated()`: FormRequestでバリデーション済みのデータのみを取得します。

```php
$book->genres()->attach($request->genres);
```
- `attach()`: 多対多リレーションで中間テーブルにレコードを追加します。

**update メソッド:**
```php
$book->genres()->sync($request->genres ?? []);
```
- `sync()`: 中間テーブルを指定した配列の状態に同期します。既存の紐付けを削除し、新しい紐付けを追加します。

**authorize メソッド:**
```php
$this->authorize('update', $book);
```
- Policyを使用して認可チェックを行います。認可されない場合は403エラーが発生します。

## 4-4. FormRequestの作成

### Step 1: FormRequestの生成

```bash
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
```

### Step 2: StoreBookRequestの実装

**`app/Http/Requests/StoreBookRequest.php`:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['exists:genres,id'],
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
            'title' => 'タイトル',
            'author' => '著者',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'genres' => 'ジャンル',
        ];
    }
}
```

**コードリーディング:**
- `authorize()`: このリクエストが許可されるかどうかを返します。`true` を返すと常に許可されます（認可はコントローラで行います）。
- `'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn']`: ISBNは必須、文字列、13文字固定、`books` テーブルの `isbn` カラムで一意。
- `'genres.*' => ['exists:genres,id']`: `genres` 配列の各要素が `genres` テーブルに存在することを確認します。
- `attributes()`: エラーメッセージで使用される属性名を日本語に変換します。

### Step 3: UpdateBookRequestの実装

**`app/Http/Requests/UpdateBookRequest.php`:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'size:13',
                Rule::unique('books', 'isbn')->ignore($this->book),
            ],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['exists:genres,id'],
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
            'title' => 'タイトル',
            'author' => '著者',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'genres' => 'ジャンル',
        ];
    }
}
```

**コードリーディング:**
```php
Rule::unique('books', 'isbn')->ignore($this->book),
```
- 更新時は、自分自身のISBNは重複チェックから除外する必要があります。`ignore($this->book)` により、現在編集中の書籍を除外します。

## 4-5. Policyの作成

### Step 1: Policyの生成

```bash
sail artisan make:policy BookPolicy --model=Book
```

### Step 2: Policyの実装

**`app/Policies/BookPolicy.php`:**

```php
<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
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
    public function view(?User $user, Book $book): bool
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
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
```

**コードリーディング:**
- `viewAny(?User $user)`, `view(?User $user, Book $book)`: `?User` は nullable を意味し、未ログインユーザーでも閲覧可能です。
- `update(User $user, Book $book)`: ログインユーザーのIDと書籍の登録者IDが一致する場合のみ `true` を返します。

### Step 3: Policyの登録

**`app/Providers/AuthServiceProvider.php`:**

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Policies\BookPolicy;
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

## 4-6. ルーティングの設定

**`routes/web.php`:**

```php
<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::resource('books', BookController::class);
```

**コードリーディング:**
- `Route::resource('books', BookController::class)`: RESTfulなルートを一括で定義します。

生成されるルート:

| メソッド | URI | アクション | ルート名 |
|---|---|---|---|
| GET | /books | index | books.index |
| GET | /books/create | create | books.create |
| POST | /books | store | books.store |
| GET | /books/{book} | show | books.show |
| GET | /books/{book}/edit | edit | books.edit |
| PUT/PATCH | /books/{book} | update | books.update |
| DELETE | /books/{book} | destroy | books.destroy |

## 4-7. Bladeテンプレートの作成

### `books/index.blade.php`

```blade
@extends('layouts.app')

@section('title', '書籍一覧')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">書籍一覧</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse ($books as $book)
        <div class="bg-white rounded-lg shadow-md p-6">
            <a href="{{ route('books.show', $book) }}" class="text-xl font-bold text-blue-600 hover:underline">
                {{ $book->title }}
            </a>
            <p class="text-gray-600 mt-2">{{ $book->author }}</p>
            <div class="flex flex-wrap gap-2 mt-2">
                @foreach ($book->genres as $genre)
                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-sm">{{ $genre->name }}</span>
                @endforeach
            </div>
            <p class="text-gray-500 mt-2">
                平均評価:
                @if ($book->reviews_avg_rating)
                    {{ number_format($book->reviews_avg_rating, 1) }}
                @else
                    -
                @endif
            </p>
            <p class="text-gray-400 text-sm mt-2">登録者: {{ $book->user->name }}</p>
        </div>
    @empty
        <p class="text-gray-500">書籍が登録されていません。</p>
    @endforelse
</div>

<div class="mt-6">
    {{ $books->links() }}
</div>
@endsection
```

### `books/show.blade.php`

```blade
@extends('layouts.app')

@section('title', $book->title)

@section('content')
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $book->title }}</h1>
            <p class="text-gray-600 mt-2">著者: {{ $book->author }}</p>
        </div>
        <div class="flex space-x-2">
            @can('update', $book)
                <a href="{{ route('books.edit', $book) }}" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                    編集
                </a>
            @endcan
            @can('delete', $book)
                <form action="{{ route('books.destroy', $book) }}" method="POST" onsubmit="return confirm('本当に削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        削除
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <p class="text-gray-500">ISBN</p>
            <p>{{ $book->isbn }}</p>
        </div>
        <div>
            <p class="text-gray-500">出版日</p>
            <p>{{ $book->published_date?->format('Y年m月d日') ?? '未設定' }}</p>
        </div>
        <div>
            <p class="text-gray-500">登録者</p>
            <p>{{ $book->user->name }}</p>
        </div>
        <div>
            <p class="text-gray-500">ジャンル</p>
            <div class="flex flex-wrap gap-2">
                @forelse ($book->genres as $genre)
                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-sm">{{ $genre->name }}</span>
                @empty
                    <span class="text-gray-400">未設定</span>
                @endforelse
            </div>
        </div>
    </div>

    @if ($book->description)
        <div class="mb-6">
            <p class="text-gray-500">説明</p>
            <p class="whitespace-pre-wrap">{{ $book->description }}</p>
        </div>
    @endif
</div>

{{-- レビューセクション（Chapter 5で実装） --}}
@endsection
```

### `books/create.blade.php`

```blade
@extends('layouts.app')

@section('title', '書籍登録')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-6">書籍登録</h1>

    <form method="POST" action="{{ route('books.store') }}">
        @csrf

        <div class="mb-4">
            <label for="title" class="block text-gray-700 font-medium mb-2">タイトル <span class="text-red-500">*</span></label>
            <input type="text" name="title" id="title" value="{{ old('title') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('title') border-red-500 @enderror">
            @error('title')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="author" class="block text-gray-700 font-medium mb-2">著者 <span class="text-red-500">*</span></label>
            <input type="text" name="author" id="author" value="{{ old('author') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('author') border-red-500 @enderror">
            @error('author')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="isbn" class="block text-gray-700 font-medium mb-2">ISBN（13桁） <span class="text-red-500">*</span></label>
            <input type="text" name="isbn" id="isbn" value="{{ old('isbn') }}" maxlength="13"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('isbn') border-red-500 @enderror">
            @error('isbn')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="published_date" class="block text-gray-700 font-medium mb-2">出版日</label>
            <input type="date" name="published_date" id="published_date" value="{{ old('published_date') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('published_date') border-red-500 @enderror">
            @error('published_date')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="description" class="block text-gray-700 font-medium mb-2">説明</label>
            <textarea name="description" id="description" rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
            @error('description')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-medium mb-2">ジャンル</label>
            <div class="flex flex-wrap gap-4">
                @foreach ($genres as $genre)
                    <label class="flex items-center">
                        <input type="checkbox" name="genres[]" value="{{ $genre->id }}"
                            {{ in_array($genre->id, old('genres', [])) ? 'checked' : '' }}
                            class="mr-2">
                        {{ $genre->name }}
                    </label>
                @endforeach
            </div>
            @error('genres')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('books.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                キャンセル
            </a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                登録
            </button>
        </div>
    </form>
</div>
@endsection
```

### `books/edit.blade.php`

```blade
@extends('layouts.app')

@section('title', '書籍編集')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-6">書籍編集</h1>

    <form method="POST" action="{{ route('books.update', $book) }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label for="title" class="block text-gray-700 font-medium mb-2">タイトル <span class="text-red-500">*</span></label>
            <input type="text" name="title" id="title" value="{{ old('title', $book->title) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('title') border-red-500 @enderror">
            @error('title')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="author" class="block text-gray-700 font-medium mb-2">著者 <span class="text-red-500">*</span></label>
            <input type="text" name="author" id="author" value="{{ old('author', $book->author) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('author') border-red-500 @enderror">
            @error('author')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="isbn" class="block text-gray-700 font-medium mb-2">ISBN（13桁） <span class="text-red-500">*</span></label>
            <input type="text" name="isbn" id="isbn" value="{{ old('isbn', $book->isbn) }}" maxlength="13"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('isbn') border-red-500 @enderror">
            @error('isbn')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="published_date" class="block text-gray-700 font-medium mb-2">出版日</label>
            <input type="date" name="published_date" id="published_date" value="{{ old('published_date', $book->published_date?->format('Y-m-d')) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('published_date') border-red-500 @enderror">
            @error('published_date')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="description" class="block text-gray-700 font-medium mb-2">説明</label>
            <textarea name="description" id="description" rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $book->description) }}</textarea>
            @error('description')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-medium mb-2">ジャンル</label>
            <div class="flex flex-wrap gap-4">
                @foreach ($genres as $genre)
                    <label class="flex items-center">
                        <input type="checkbox" name="genres[]" value="{{ $genre->id }}"
                            {{ in_array($genre->id, old('genres', $book->genres->pluck('id')->toArray())) ? 'checked' : '' }}
                            class="mr-2">
                        {{ $genre->name }}
                    </label>
                @endforeach
            </div>
            @error('genres')
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

**コードリーディング:**
- `@method('PUT')`: HTMLフォームはGETとPOSTしかサポートしないため、`_method` hidden フィールドを追加してPUTリクエストをシミュレートします。
- `old('title', $book->title)`: バリデーションエラー時は入力値を、そうでなければ既存の値を表示します。
- `$book->genres->pluck('id')->toArray()`: 既存の紐付けジャンルのIDを配列として取得します。

---

これで書籍管理機能の実装が完了しました。次のChapterでは、レビュー機能を実装していきます。
