# Chapter 06: アプリケーションの中核 - 書籍管理機能CRUDを実装する

## 🎯 このChapterの目標

このチャプターでは、BookShelfアプリケーションの中核となる**書籍管理機能（CRUD）**を実装します。コントローラー・FormRequest・Policyという3つの部品を組み合わせて構築します。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| CRUDの基本パターン | コントローラーの7つのメソッドとRESTfulなルート設計 |
| FormRequestによるバリデーション分離 | バリデーションロジックをコントローラーから分離する方法 |
| Policyによる認可 | 「この人はこの操作をしてよいか？」を一元管理する方法 |
| ルート定義の全体設計 | アプリケーション全体のURLを先に定義する重要性 |

---

## 📖 背景知識

### このチャプターで扱う範囲

このチャプターでは書籍CRUDの**全機能**を実装します。

---

## 📋 要件の確認

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 | 認可 |
|:---|:---|:---|:---|:---|:---|
| 書籍一覧表示 | GET | `/` または `/books` | BookController@index | 不要 | -- |
| 書籍登録フォーム | GET | `/books/create` | BookController@create | 必要 | -- |
| 書籍登録処理 | POST | `/books` | BookController@store | 必要 | -- |
| 書籍詳細表示 | GET | `/books/{book}` | BookController@show | 不要 | -- |
| 書籍編集フォーム | GET | `/books/{book}/edit` | BookController@edit | 必要 | BookPolicy@update |
| 書籍更新処理 | PUT | `/books/{book}` | BookController@update | 必要 | BookPolicy@update |
| 書籍削除処理 | DELETE | `/books/{book}` | BookController@destroy | 必要 | BookPolicy@delete |

### バリデーションルール

| 項目 | ルール | エラーメッセージ |
|:---|:---|:---|
| title | required / string / max:255 | タイトルは必須です。 |
| author | required / string / max:255 | 著者名は必須です。 |
| isbn | required / string / size:13 / unique | ISBNは必須です。/ ISBNは13桁で入力してください。 |
| published_date | required / date | 出版日は必須です。 |
| description | nullable / string | -- |
| image_url | nullable / url | 画像URLは有効なURL形式で入力してください。 |
| genres | required / array | ジャンルは1つ以上選択してください。 |
| genres.* | exists:genres,id | 選択されたジャンルは存在しません。 |

---

## 💭 なぜこう作るのか？

### Point 1: 全てのルートとコントローラーを先に作成する

配布済みのBladeファイルには `route('books.create')` や `route('favorites.toggle')` のようなルート名が記述されています。これらが未定義だと `RouteNotDefinedException` が発生するため、先に全てのルートを定義します。

### Point 2: バリデーションはFormRequestに分離する

### Point 3: 認可はPolicyクラスで一元管理する

### Point 4: `attach()` / `sync()` を使い分ける

- **新規登録時**: `$book->genres()->attach($genreIds)`
- **更新時**: `$book->genres()->sync($genreIds)`

---

## 🚀 コードの実装

### 全コントローラーの作成

```bash
sail artisan make:controller BookController
sail artisan make:controller ReviewController
sail artisan make:controller FavoriteController
sail artisan make:controller ReviewLikeController
sail artisan make:controller GenreController
sail artisan make:controller RankingController
```

### ルート定義 (`routes/web.php`)

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

// --- 1. 具体的な名前を持つ公開ルート (最優先) ---
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// --- 2. 認証必須ルート ---
Route::middleware('auth')->group(function () {

    // 書籍管理 (Resource)
    Route::resource('books', BookController::class)->except(['index', 'show']);

    // ジャンル管理
    Route::resource('genres', GenreController::class)->except(['show']);

    // レビュー管理
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り機能
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // レビューいいね機能
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});

// --- 3. ワイルドカードを含む公開ルート (最後に定義) ---
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
```

### Policy の作成

```bash
sail artisan make:policy BookPolicy --model=Book
sail artisan make:policy ReviewPolicy --model=Review
```

#### `app/Policies/BookPolicy.php`

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

#### `app/Providers/AuthServiceProvider.php`

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
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
```

### FormRequest の作成

#### `app/Http/Requests/StoreBookRequest.php`

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

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
```

#### `app/Http/Requests/UpdateBookRequest.php`

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

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
```

### BookController の実装

`app/Http/Controllers/BookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with('genres')->latest()->paginate(10);

        return view('books.index', compact('books'));
    }

    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    public function show(Book $book): View
    {
        $book->load(['reviews.user', 'genres']);

        return view('books.show', compact('book'));
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);
        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book->update($bookData);
        $book->genres()->sync($validated['genres']);

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
```

---

## 🔍 コードリーディング

### BookController の主要メソッド

| コード | 解説 |
|:---|:---|
| `Book::with('genres')->latest()->paginate(10)` | Eager Loadingでジャンルも同時取得。N+1問題を防止。 |
| `Genre::all()` | 全ジャンルを取得。基本版では `orderBy('name')` は不要。 |
| `collect($validated)->except('genres')->toArray()` | `genres` を書籍データから分離。中間テーブルに別途保存するため。 |
| `$request->user()->books()->create($bookData)` | リレーション経由で作成。`user_id` が自動設定される。 |
| `$book->genres()->attach(...)` | 新規登録時のジャンル紐付け。 |
| `$book->genres()->sync(...)` | 更新時のジャンル紐付け。既存の紐付けを完全に置き換え。 |
| `$this->authorize('update', $book)` | BookPolicyで認可チェック。作成者以外は403。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| FormRequestの仕組み | 「Laravel の FormRequest でバリデーションを分離する方法を教えてください。」 |
| Policyの使い方 | 「Laravel の Policy で認可を実装する方法を教えてください。」 |
| attach と sync の違い | 「Laravel の belongsToMany で attach と sync の違いを教えてください。」 |

---

## ✅ 動作確認

| 確認項目 | 確認方法 |
|:---|:---|
| 書籍一覧 | `http://localhost` で書籍一覧が表示されること |
| 書籍登録 | ログイン後、新規登録フォームから書籍を登録できること |
| 書籍編集・削除 | 自分が登録した書籍のみ編集・削除できること |
| バリデーション | 空のフォームを送信し、日本語エラーが表示されること |

---

## ✨ このChapterのまとめ

| 構成要素 | ファイル | 役割 |
|:---|:---|:---|
| ルート定義 | `routes/web.php` | アプリ全体のURL設計 |
| コントローラー | `BookController.php` | CRUD処理の実行 |
| バリデーション | `StoreBookRequest.php` / `UpdateBookRequest.php` | 入力チェック |
| 認可 | `BookPolicy.php` | 作成者のみ編集・削除可能 |

次のChapter 07では、書籍に対する**レビュー機能**を実装します。
