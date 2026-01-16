# Chapter 5: 書籍管理機能 (CRUD)

## 🎯 このセクションで学ぶこと

このセクションでは、Webアプリケーションの基本である**CRUD（Create, Read, Update, Delete）**操作を、書籍管理機能を通じて実装します。

- **リソースコントローラー**: Laravelの規約に沿った、RESTfulなコントローラーの作成方法を学びます。
- **フォームリクエスト**: バリデーションロジックをコントローラーから分離し、再利用可能にする方法を学びます。
- **ポリシー（認可）**: 「誰が」「何を」できるかを制御する認可の仕組みを学びます。

---

## 🧠 先輩エンジニアの思考プロセス：CRUD実装の順序

CRUD機能を実装する際、経験豊富なエンジニアは以下の順序で考えます。

| 順序 | 作業 | 理由 |
|:---|:---|:---|
| 1 | コントローラー作成 | アプリケーションの「司令塔」となるクラスを用意する。 |
| 2 | フォームリクエスト作成 | バリデーションルールを定義し、不正なデータの侵入を防ぐ。 |
| 3 | ポリシー作成 | 認可ルールを定義し、「自分の投稿だけ編集・削除できる」などの制御を行う。 |
| 4 | ルート定義 | URLとコントローラーのメソッドを紐づける。 |
| 5 | ビュー作成 | ユーザーが実際に操作する画面を作成する。 |

この順序で実装することで、**「データの流れ」**を意識しながら開発を進められます。

---

## 5.1. コントローラーとリクエスト、ポリシーの作成

```bash
# リソースコントローラーを作成（--resourceオプションでCRUDメソッドが自動生成される）
sail artisan make:controller BookController --resource

# フォームリクエストを作成
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest

# ポリシーを作成
sail artisan make:policy BookPolicy --model=Book
```

| コマンド | 生成されるファイル | 💡 ポイント |
|:---|:---|:---|
| `make:controller BookController --resource` | `app/Http/Controllers/BookController.php` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`の7メソッドが自動生成されます。 |
| `make:request StoreBookRequest` | `app/Http/Requests/StoreBookRequest.php` | 新規作成時のバリデーションルールを定義します。 |
| `make:request UpdateBookRequest` | `app/Http/Requests/UpdateBookRequest.php` | 更新時のバリデーションルールを定義します。 |
| `make:policy BookPolicy --model=Book` | `app/Policies/BookPolicy.php` | `Book`モデルに対する認可ルールを定義します。 |

---

## 5.2. ポリシーの登録と実装

### 5.2.1. ポリシーの登録

`app/Providers/AuthServiceProvider.php`にポリシーを登録します。

```php
// app/Providers/AuthServiceProvider.php

protected $policies = [
    Book::class => BookPolicy::class,
];
```

### 5.2.2. ポリシーの実装

```php
// app/Policies/BookPolicy.php

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

| メソッド | 説明 | 💡 ポイント |
|:---|:---|:---|
| `update(User $user, Book $book)` | 書籍を更新できるかどうかを判定します。 | 書籍を登録したユーザー（`$book->user_id`）と、現在ログインしているユーザー（`$user->id`）が一致する場合のみ`true`を返します。 |
| `delete(User $user, Book $book)` | 書籍を削除できるかどうかを判定します。 | 同上。 |

> **🧠 先輩エンジニアの思考プロセス**
> ポリシーは「認可（Authorization）」を担当します。認証（Authentication）が「あなたは誰？」を確認するのに対し、認可は「あなたはこれをする権限がある？」を確認します。

---

## 5.3. フォームリクエストの実装

### 5.3.1. StoreBookRequest（新規作成用）

```php
// app/Http/Requests/StoreBookRequest.php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認証済みユーザーなら誰でも書籍を登録できる
    }

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
}
```

| ルール | 説明 | 💡 ポイント |
|:---|:---|:---|
| `'required'` | 必須項目です。 | 空の値は許可されません。 |
| `'string'` | 文字列である必要があります。 | - |
| `'max:255'` | 最大255文字です。 | データベースのカラム定義と合わせます。 |
| `'size:13'` | 正確に13文字である必要があります。 | ISBNは13桁の固定長です。 |
| `'unique:books'` | `books`テーブルで一意である必要があります。 | 同じISBNの書籍は登録できません。 |
| `'genres.*'` | 配列の各要素に対するルールです。 | `genres`配列の各IDが`genres`テーブルに存在することを確認します。 |

### 5.3.2. UpdateBookRequest（更新用）

```php
// app/Http/Requests/UpdateBookRequest.php

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
            'isbn' => [
                'required',
                'string',
                'size:13',
                Rule::unique('books')->ignore($this->book),
            ],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['exists:genres,id'],
        ];
    }
}
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `Rule::unique('books')->ignore($this->book)` | `books`テーブルで一意であることを確認しますが、現在編集中の書籍は除外します。 | 更新時に「自分自身のISBN」と重複チェックされないようにします。 |
| `$this->book` | ルートパラメータから取得した`Book`モデルのインスタンスです。 | ルートモデルバインディングにより、自動的に注入されます。 |

---

## 5.4. BookControllerの実装

```php
// app/Http/Controllers/BookController.php

<?php

namespace App\Http\Controllers;

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

### 5.4.1. コードリーディング：`store`メソッド

```php
public function store(StoreBookRequest $request)
{
    $book = Auth::user()->books()->create($request->validated());
    $book->genres()->attach($request->genres);

    return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
}
```

| 部分 | 説明 | 戻り値 |
|:---|:---|:---|
| `Auth::user()` | 現在ログインしているユーザーを取得します。 | `User` |
| `->books()` | `User`モデルの`books`リレーションを取得します。 | `HasMany` |
| `->create($request->validated())` | バリデーション済みのデータで新しい`Book`を作成します。 | `Book` |
| `$book->genres()` | `Book`モデルの`genres`リレーションを取得します。 | `BelongsToMany` |
| `->attach($request->genres)` | 中間テーブル（`book_genre`）にレコードを追加します。 | `void` |

> **💡 ポイント: `attach` vs `sync`**
> - **`attach`**: 既存のリレーションを保持したまま、新しいリレーションを追加します。
> - **`sync`**: 既存のリレーションを全て削除し、指定したリレーションで置き換えます。
> 新規作成時は`attach`、更新時は`sync`を使うのが一般的です。

---

## 5.5. ルートの定義

```php
// routes/web.php

// 認証が必要なルート
Route::middleware('auth')->group(function () {
    // Book management
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});

// 認証不要のルート
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
```

---

## 5.6. ビューの作成

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/books
touch resources/views/books/index.blade.php
touch resources/views/books/create.blade.php
touch resources/views/books/show.blade.php
touch resources/views/books/edit.blade.php
touch resources/views/books/_form.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

これで、書籍管理機能（CRUD）の実装が完了しました。次のChapterでは、レビュー機能を実装していきます。
