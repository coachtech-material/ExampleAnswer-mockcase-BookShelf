# Chapter 6: 書籍管理機能 (CRUD) の実装

## 🎯 このセクションで学ぶこと

このセクションでは、Webアプリケーション開発の心臓部とも言える**CRUD（Create, Read, Update, Delete）**操作を、書籍管理機能を通じて実装します。単に機能を実装するだけでなく、エラーを未然に防ぐための開発手順や、コードの責務を分離するための設計思想についても深く学んでいきます。

- **トップダウンな開発アプローチ**: なぜ機能の詳細を実装する前に、まず全体の「骨格」となるルートとコントローラーを準備するのか、その重要性を理解します。
- **フォームリクエスト**: バリデーションロジックをコントローラーから分離し、再利用可能にする方法を学びます。
- **ポリシー（認可）**: 「誰が」「何を」できるかを制御する認可の仕組みを学びます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜ最初に「骨格」を作るのか？

多くの初学者は、一つの機能（例えば「書籍の登録」）を完成させてから次の機能に取り掛かろうとします。しかし、経験豊富なエンジニアは、まずアプリケーション全体の「骨格」となる部分から組み立て始めます。これは、家を建てる際に、内装工事の前にまず土台と柱を組み上げるのと同じです。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 配布されたビューには`route()`ヘルパーが多数あり、ルートが未定義だとエラーになる | **最初にすべてのルートとコントローラーを準備**する | どのページにアクセスしても`RouteNotDefined`エラーが発生しない状態を先に作ることで、ビューの表示確認をしながらスムーズに開発を進められる。 |
| 機能ごとに行き当たりばったりで実装すると、全体像が見えなくなる | **トップダウン**で実装を進める | まず全体のURL設計（ルート）と司令塔（コントローラー）を決め、その後に各機能の詳細（ロジック）を肉付けしていくことで、一貫性のある設計を維持できる。 |
| どこに何を書くべきか迷う | **責務の分離**を意識する | 「URLの交通整理はルート」「リクエストの検証はフォームリクエスト」「権限の確認はポリシー」「具体的な処理はコントローラー」というように、役割分担を明確にすることで、見通しが良くメンテナンスしやすいコードになる。 |

このChapterでは、まずアプリケーション全体の「骨格」を固め、その上で書籍管理機能という「最初の部屋」の内装を仕上げていく、という流れで開発を進めます。

---

## 6.1. ルート定義とコントローラーの準備

機能ごとの詳細な実装に入る前に、まずアプリケーション全体の「骨格」となるルートとコントローラーを準備します。これにより、開発中の`RouteNotDefined`エラーを防ぎ、スムーズに開発を進めることができます。

### 6.1.1. なぜ先にルートを定義するのか？

今回使用する配布済みのBladeファイル（ビュー）には、ヘッダーやボタンなどに、`route('books.create')`のような形でリンク先のルート名が多数記述されています。もし、これらのルートが`routes/web.php`に定義されていない状態でページを表示しようとすると、Laravelは「`books.create`という名前のルートは見つかりません」という`RouteNotDefinedException`エラーを発生させます。

これを防ぐため、機能の中身が空であっても、先に**すべてのURL（ルート）と、その交通整理役であるコントローラーを定義**しておくのです。

### 6.1.2. 全コントローラーの作成

まずは、このアプリケーションで必要となるすべてのコントローラーを`artisan`コマンドで一括作成します。この時点では、コントローラーの中身は空のままで問題ありません。

```bash
# 書籍・ジャンル管理用のリソースコントローラーを作成
sail artisan make:controller BookController --resource
sail artisan make:controller GenreController --resource

# その他の機能用のコントローラーを作成
sail artisan make:controller ReviewController
sail artisan make:controller FavoriteController
sail artisan make:controller ReviewLikeController
sail artisan make:controller RankingController
```

> **💡 ポイント: `--resource` オプション**
> `--resource`オプションを付けてコントローラーを作成すると、CRUD操作に対応する7つのメソッド（`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`）が自動的に生成されます。これにより、RESTfulな設計に沿ったコントローラーを効率的に作成できます。

### 6.1.3. ルート定義の作成 (`web.php`)

次に、`routes/web.php`に、アプリケーションで必要となるすべてのルートを記述します。これにより、どのページにアクセスしても「Route not defined」エラーが出ない状態を作ります。

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- 1. 具体的な名前を持つ公開ルート (最優先) ---
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/search', [BookController::class, 'search'])->name('books.search');
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

    // お気に入り機能 (Bladeに合わせて toggle に変更)
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // レビューいいね機能 (▼ここを修正: Bladeに合わせて reviews.like / toggle に変更)
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});


// --- 3. ワイルドカードを含む公開ルート (最後に定義) ---
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');

// 認証機能用ルート
require __DIR__.'/auth.php';
```

これで、アプリケーションの「骨格」が完成しました。次から、この骨格に肉付けをしていく作業に入ります。

---

## 6.2. 書籍管理機能の実装 (CRUD)

それでは、書籍管理機能の具体的な実装を進めていきましょう。

### 6.2.1. フォームリクエストとポリシーの作成

書籍の登録・更新時のバリデーションルールを定義する「フォームリクエスト」と、操作の権限を管理する「ポリシー」を作成します。

```bash
# フォームリクエストを作成 (新規作成用と更新用)
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest

# ポリシーを作成 (Bookモデルに関連付ける)
sail artisan make:policy BookPolicy --model=Book
```

### 6.2.2. ポリシーの登録と実装

作成したポリシーを`AuthServiceProvider`に登録し、具体的な認可ルールを記述します。

**ポリシーの登録 (`app/Providers/AuthServiceProvider.php`)**
```php
protected $policies = [
    Book::class => BookPolicy::class,
];
```

**ポリシーの実装 (`app/Policies/BookPolicy.php`)**
```php
public function update(User $user, Book $book): bool
{
    return $user->id === $book->user_id;
}

public function delete(User $user, Book $book): bool
{
    return $user->id === $book->user_id;
}
```

### 6.2.3. フォームリクエストの実装

`StoreBookRequest`と`UpdateBookRequest`に、それぞれバリデーションルールを記述します。

**StoreBookRequest (`app/Http/Requests/StoreBookRequest.php`)**
```php
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:255'],
        'author' => ['required', 'string', 'max:255'],
        'isbn' => ['required', 'string', 'size:13', 'unique:books'],
        'published_date' => ['required', 'date'],
        'description' => ['nullable', 'string'],
        'image_url' => ['nullable', 'url'],
        'genres' => ['required', 'array', 'min:1'],
        'genres.*' => ['exists:genres,id'],
    ];
}
```

**UpdateBookRequest (`app/Http/Requests/UpdateBookRequest.php`)**
```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        // ... 他はStoreBookRequestと同じ
        'isbn' => [
            'required',
            'string',
            'size:13',
            Rule::unique('books')->ignore($this->book),
        ],
        // ...
    ];
}
```

### 6.2.4. BookControllerの実装

最後に、`BookController`にCRUDの各処理を実装します。

```php
// app/Http/Controllers/BookController.php

use App\Models\Book;
use App\Models\Genre;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::with('genres')->latest()->paginate(10);
        return view('books.index', compact('books'));
    }

    public function create()
    {
        $genres = Genre::all();
        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request)
    {
        $book = Auth::user()->books()->create($request->validated());
        $book->genres()->attach($request->genres);

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    public function show(Book $book)
    {
        $book->load(['genres', 'reviews.user']);
        return view('books.show', compact('book'));
    }

    public function edit(Book $book)
    {
        $this->authorize('update', $book);
        $genres = Genre::all();
        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorize('update', $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);

        return redirect()->route('books.show', $book)->with('success', '書籍を更新しました。');
    }

    public function destroy(Book $book)
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
```

これで、書籍管理機能（CRUD）の実装が完了しました。次のChapterでは、レビュー機能を実装していきます。
