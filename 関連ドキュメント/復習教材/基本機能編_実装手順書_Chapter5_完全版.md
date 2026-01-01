# Chapter 5: 書籍管理機能 (CRUD)

このChapterでは、アプリケーションの中核となる書籍管理機能（CRUD: Create, Read, Update, Delete）を実装します。コントローラー、フォームリクエスト、ポリシー、ルート、Bladeテンプレートを作成し、書籍の登録・一覧表示・詳細表示・編集・削除を実現します。

---

## 5-1. 先輩エンジニアの思考プロセス：なぜリレーションの次にCRUDを実装するのか？

### 開発の順序には理由がある

ここまでの流れを振り返ってみましょう。

| Chapter | 内容 | 比喩 |
|:---|:---|:---|
| Chapter 2 | DB設計・マイグレーション | 基礎工事（データの保管場所を決める） |
| Chapter 3 | モデル・リレーション | 骨組みの構築（構造を固める） |
| Chapter 4 | 認証機能 | セキュリティゲートの設置 |
| **Chapter 5** | **CRUD機能** | **内装工事（具体的な機能を作る）** |

### なぜ他のものから実装してはいけないのか？

もし、いきなりUIから作り始めたらどうなるでしょうか？

> 「このボタンを押したらどのデータをどう処理すればいいんだ？」

結局、データの構造に立ち返ることになります。また、データの取得方法（リレーション）が決まっていなければ、効率的な表示処理も書けません。

**「データ構造 → 関連付け → 認証 → データ操作」** の順番が、最も合理的で手戻りの少ない進め方です。

---

## 5.1. コントローラーとリクエスト、ポリシーの作成

```bash
sail artisan make:controller BookController --resource
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
sail artisan make:policy BookPolicy --model=Book
```

---

## 5.2. ポリシーの登録と実装

`app/Providers/AuthServiceProvider.php` に `BookPolicy` を登録します。

```php
protected $policies = [
    Book::class => BookPolicy::class,
];
```

`app/Policies/BookPolicy.php` に、書籍の更新・削除は登録者本人のみが行えるようにロジックを実装します。

```php
<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
```

---

## 5.3. フォームリクエストの実装

バリデーションルールをフォームリクエストクラスに定義します。

### `app/Http/Requests/StoreBookRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'genres' => ['required', 'array'],
            'genres.*' => ['exists:genres,id'],
        ];
    }
}
```

### `app/Http/Requests/UpdateBookRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->book)],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'genres' => ['required', 'array'],
            'genres.*' => ['exists:genres,id'],
        ];
    }
}
```

---

## 5.4. ルートの定義 (`routes/web.php`)

書籍管理と認証に関するルートを定義します。

```php
<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});

// 認証ルート
require __DIR__.'/auth.php';
```

---

## 5.5. `BookController.php` の実装

### 先輩エンジニアの思考：各メソッドの「要件 → 実装」フロー

各メソッドを実装する際、先輩エンジニアは以下のように考えます。

#### `index()` メソッド

1. **要件**: 「登録されている書籍を10件ずつのページネーションで一覧表示する」
2. **思考**: 
   - `Book::all()` かな？ → いや、N+1問題が発生する → `with("genres")` でEager Loading
   - 「10件ずつ」→ `paginate(10)` を使う
   - 新しいものが上に表示されるべき → `latest()` で降順
3. **実装**: `Book::with("genres")->latest()->paginate(10);`

#### `store()` メソッド

1. **要件**: 「ログインユーザーが書籍を登録でき、ジャンルを紐付けられる」
2. **思考**:
   - バリデーション済みデータから `genres` を除外して書籍を作成
   - `$request->user()->books()->create()` で `user_id` を自動設定
   - ジャンルは多対多なので `attach()` で紐付け
3. **実装**: 下記コード参照

### 完全なコントローラー実装

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with("genres")->latest()->paginate(10);
        return view("books.index", compact("books"));
    }

    public function create(): View
    {
        $genres = Genre::all();
        return view("books.create", compact("genres"));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);

        return redirect()->route("books.show", $book)->with("success", "書籍を登録しました。");
    }

    public function show(Book $book): View
    {
        $book->load(["reviews.user", "genres"]);
        return view("books.show", compact("book"));
    }

    public function edit(Book $book): View
    {
        $this->authorize("update", $book);
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize("update", $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);

        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize("delete", $book);
        $book->delete();

        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }
}
```

---

## 5.6. Bladeテンプレートの実装

### 書籍フォーム部品 (`resources/views/books/_form.blade.php`)

```html
@csrf
<div class="space-y-4">
    <div>
        <label for="title">タイトル</label>
        <input type="text" name="title" id="title" value="{{ old('title', $book->title ?? '') }}" required>
        @error('title')<p>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="author">著者</label>
        <input type="text" name="author" id="author" value="{{ old('author', $book->author ?? '') }}" required>
        @error('author')<p>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="isbn">ISBN-13</label>
        <input type="text" name="isbn" id="isbn" value="{{ old('isbn', $book->isbn ?? '') }}" required>
        @error('isbn')<p>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="published_date">出版日</label>
        <input type="date" name="published_date" id="published_date" value="{{ old('published_date', $book->published_date ?? '') }}" required>
        @error('published_date')<p>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="description">概要</label>
        <textarea name="description" id="description">{{ old('description', $book->description ?? '') }}</textarea>
        @error('description')<p>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="image_url">画像URL</label>
        <input type="text" name="image_url" id="image_url" value="{{ old('image_url', $book->image_url ?? '') }}">
        @error('image_url')<p>{{ $message }}</p>@enderror
    </div>
    <div>
        <label>ジャンル</label>
        @foreach($genres as $genre)
            <input type="checkbox" name="genres[]" value="{{ $genre->id }}" @if(in_array($genre->id, old('genres', $book->genres->pluck('id')->toArray() ?? []))) checked @endif>
            <label>{{ $genre->name }}</label>
        @endforeach
        @error('genres')<p>{{ $message }}</p>@enderror
    </div>
</div>
```

### 書籍登録ビュー (`resources/views/books/create.blade.php`)

```html
<x-app-layout>
    <x-slot name="header">書籍の登録</x-slot>
    <form action="{{ route('books.store') }}" method="POST">
        @include('books._form')
        <button type="submit">登録する</button>
    </form>
</x-app-layout>
```

### 書籍編集ビュー (`resources/views/books/edit.blade.php`)

```html
<x-app-layout>
    <x-slot name="header">書籍の編集</x-slot>
    <form action="{{ route('books.update', $book) }}" method="POST">
        @method('PUT')
        @include('books._form')
        <button type="submit">更新する</button>
    </form>
</x-app-layout>
```

### 書籍一覧ビュー (`resources/views/books/index.blade.php`)

*このビューは後のステップで詳細に実装します。*

### 書籍詳細ビュー (`resources/views/books/show.blade.php`)

*このビューは後のステップで詳細に実装します。*

---

## 5.7. 動作確認

1. ログイン状態で `/books/create` にアクセスし、書籍を登録できることを確認
2. 登録した書籍の詳細ページに遷移することを確認
3. 自分が登録した書籍のみ編集・削除ボタンが表示されることを確認
4. 他のユーザーが登録した書籍は編集・削除できないことを確認

これで、書籍管理機能（CRUD）の基本実装が完了しました。
