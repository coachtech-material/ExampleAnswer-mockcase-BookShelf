# Chapter 5: 書籍管理機能 (CRUD)

このChapterでは、アプリケーションの中核となる書籍管理機能（CRUD: Create, Read, Update, Delete）を実装します。認証済みのユーザーが、自身で書籍を登録・編集・削除できる一連の機能を、Laravelの作法に則って構築します。

---

## 5-1. 先輩エンジニアの思考プロセス：要件をCRUDに分解し、実装に落とし込む

CRUDは「Create（生成）、Read（読み取り）、Update（更新）、Delete（削除）」の頭文字を取ったもので、ほとんどのWebアプリケーションの基本機能です。要件定義書に出てくる「〇〇管理機能」は、ほぼこのCRUDに分解できます。

### Step 1: 要件定義書からCRUD操作をマッピングする

まず、`基本機能編_要件定義書_基本設計書_詳細度100%.md`の「書籍管理機能」の項目を、CRUDと具体的なアクション（メソッド）にマッピングします。

| 要件 | CRUD | アクション (メソッド) | 役割 |
|:---|:---|:---|:---|
| 書籍一覧表示 | **R**ead | `index` | 登録されている全書籍の一覧を表示する |
| 書籍詳細表示 | **R**ead | `show` | 特定の1冊の書籍の詳細情報を表示する |
| 書籍登録 | **C**reate | `create` / `store` | `create`: 登録フォーム画面を表示 / `store`: フォームから送られたデータをDBに保存 |
| 書籍編集 | **U**pdate | `edit` / `update` | `edit`: 編集フォーム画面を表示 / `update`: フォームから送られたデータでDBを更新 |
| 書籍削除 | **D**elete | `destroy` | 特定の書籍をDBから削除する |

> **【学習のポイント】**
> Laravelの`--resource`オプションでコントローラーを作成すると、これらのCRUDに対応する7つのメソッド（`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`）が自動的に生成されます。これは、Webアプリケーションの基本がCRUDであることをフレームワークレベルで示唆しています。

### Step 2: 各アクションの「責務」を明確にする

次に、各アクションを実装するために必要な「部品」は何かを考えます。Laravelでは、役割ごとにクラスを分離する「関心の分離」が推奨されています。

- **ルーティング (`routes/web.php`)**: どのURLがどのアクションを呼び出すかを定義する「交通整理」
- **フォームリクエスト (`StoreBookRequest`, `UpdateBookRequest`)**: 「このデータは正しい形式か？」を検証する「門番」
- **ポリシー (`BookPolicy`)**: 「この操作を実行する権限があるか？」を検証する「警備員」
- **コントローラー (`BookController`)**: ルーティング、リクエスト、ポリシーからの情報を受け取り、モデルを使ってビジネスロジックを実行し、ビューに応答を返す「司令塔」
- **ビュー (`*.blade.php`)**: ユーザーに見せる画面を生成する「デザイナー」

この後の実装パートでは、これらの部品を一つずつ組み立てていきます。

---

## 5.2. 部品の作成 (Artisanコマンド)

まず、`artisan`コマンドを使って、書籍管理機能に必要なコントローラー、フォームリクエスト、ポリシーの雛形を一括で作成します。

```bash
# CRUDの7メソッドを持つコントローラーを作成
sail artisan make:controller BookController --resource

# 書籍登録用のバリデーションルールを定義するクラスを作成
sail artisan make:request StoreBookRequest

# 書籍更新用のバリデーションルールを定義するクラスを作成
sail artisan make:request UpdateBookRequest

# 書籍の認可ロジックを定義するクラスを作成
sail artisan make:policy BookPolicy --model=Book
```

---

## 5.3. 認可ルールの実装 (Policy)

**要件**: 「自分が登録した書籍の情報は編集・削除できるが、他人が登録した書籍は編集・削除できない」

この「操作の許可」を司るのがポリシーです。

1.  **ポリシーの登録 (`app/Providers/AuthServiceProvider.php`)**
    `Book`モデルに対する操作は`BookPolicy`で認可チェックを行う、という関連付けを定義します。

    ```php
    protected $policies = [
        Book::class => BookPolicy::class, // この行を追加
    ];
    ```

2.  **ポリシーの実装 (`app/Policies/BookPolicy.php`)**
    `update`と`delete`メソッドに、具体的な認可ロジックを記述します。

    ```php
    public function update(User $user, Book $book): bool
    {
        // 操作しようとしているユーザー($user)のIDと、
        // その書籍が元々持っているユーザーID($book->user_id)が一致するかを判定
        return $user->id === $book->user_id;
    }

    public function delete(User $user, Book $book): bool
    {
        // ロジックはupdateと全く同じ
        return $user->id === $book->user_id;
    }
    ```

---

## 5.4. バリデーションルールの実装 (FormRequest)
**要件**: 「タイトルと著者は必須」「ISBNは13桁で、重複してはならない」など、要件定義書に定められた入力データに関するルールを実装します。

### `app/Http/Requests/StoreBookRequest.php` (登録用)

```php
public function rules(): array
{
    return [
        // ルールは要件定義書通りに記述
        'title'           => ['required', 'string', 'max:255'],
        'author'          => ['required', 'string', 'max:255'],
        'isbn'            => ['required', 'string', 'size:13', 'unique:books,isbn'], // booksテーブル内でユニーク
        'published_date'  => ['required', 'date'],
        'description'     => ['nullable', 'string'],
        'image_url'       => ['nullable', 'url'],
        'genres'          => ['required', 'array'], // ジャンルは必須
        'genres.*'        => ['exists:genres,id'], // 配列内の各IDがgenresテーブルに存在するか
    ];
}
```

### `app/Http/Requests/UpdateBookRequest.php` (更新用)

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
            // 更新時は、自分自身のISBNはユニークチェックの対象外にする必要がある
            Rule::unique('books')->ignore($this->book),
        ],
        // ...
    ];
}
```

---

## 5.5. ルーティングの定義 (`routes/web.php`)

書籍管理機能に関するURLとコントローラーのアクションを紐付けます。

```php
<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

// --- Public routes (誰でもアクセス可能) ---
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// --- Authenticated routes (ログイン必須) ---
Route::middleware('auth')->group(function () {
    // 書籍登録
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');

    // 書籍編集
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');

    // 書籍削除
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});

// 認証関連のルートを読み込む
require __DIR__.'/auth.php';
```

---

## 5.6. コントローラーの実装 (`BookController.php`)
いよいよ司令塔であるコントローラーを実装します。各メソッドが「要件」からどのように「実装」に落とし込まれるかを意識して読み進めてください。

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
    /**
     * Read (Index): 書籍一覧表示
     */
    public function index(): View
    {
        // 要件：登録されている書籍を10件ずつのページネーションで一覧表示する。
        // 思考：
        // 1. N+1問題を避けるため、`with("genres")`でジャンル情報をEager Loadする。
        // 2. 新しい投稿が上にくるように`latest()`でソートする。
        // 3. 10件ごとにページを区切るため`paginate(10)`を使う。
        $books = Book::with("genres")->latest()->paginate(10);
        return view("books.index", compact("books"));
    }

    /**
     * Create (Form): 書籍登録フォーム表示
     */
    public function create(): View
    {
        // 要件：書籍登録フォームに、選択可能なジャンルの一覧を表示する。
        // 思考：`Genre`モデルから全てのジャンルを取得してビューに渡すだけで良い。
        $genres = Genre::all();
        return view("books.create", compact("genres"));
    }

    /**
     * Create (Store): 書籍登録処理
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        // 要件：ログインユーザーが書籍を登録でき、ジャンルを紐付けられる。
        // 思考：
        // 1. バリデーションは`StoreBookRequest`が自動で行う。
        // 2. `user_id`を自動で付与するため、`$request->user()->books()->create()`を使う。
        // 3. 多対多のリレーションを保存するため、`attach()`メソッドでジャンルIDを中間テーブルに保存する。
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);

        return redirect()->route("books.show", $book)->with("success", "書籍を登録しました。");
    }

    /**
     * Read (Show): 書籍詳細表示
     */
    public function show(Book $book): View
    {
        // 要件：書籍の詳細情報と、その書籍に紐付くレビューの一覧を表示する。
        // 思考：
        // 1. レビューと、各レビューを書いたユーザー情報を表示したい → `reviews.user`をEager Load。
        // 2. 書籍のジャンルも表示したい → `genres`をEager Load。
        // 3. `load()`は、既に取得済みのモデルインスタンスに対して後からEager Loadを行うメソッド。
        $book->load(["reviews.user", "genres"]);
        return view("books.show", compact("book"));
    }

    /**
     * Update (Form): 書籍編集フォーム表示
     */
    public function edit(Book $book): View
    {
        // 要件：自分が登録した書籍の情報を編集できる。
        // 思考：
        // 1. まず、この操作が許可されているか`BookPolicy`でチェックする → `$this->authorize("update", $book)`。
        // 2. 認可されれば、登録フォームと同様に全ジャンル情報を取得してビューに渡す。
        $this->authorize("update", $book);
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    /**
     * Update (Store): 書籍更新処理
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // 要件：書籍情報を更新し、ジャンルの紐付けも更新する。
        // 思考：
        // 1. まず`BookPolicy`で認可チェック。
        // 2. バリデーションは`UpdateBookRequest`が行う。
        // 3. `update()`メソッドで書籍情報を一括更新。
        // 4. ジャンルは一度全て解除してから再度設定し直す`sync()`メソッドが便利。
        $this->authorize("update", $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);

        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    /**
     * Delete: 書籍削除処理
     */
    public function destroy(Book $book): RedirectResponse
    {
        // 要件：自分が登録した書籍を削除できる。
        // 思考：
        // 1. まず`BookPolicy`で認可チェック。
        // 2. `delete()`メソッドで書籍を削除する。
        // 3. 削除後は一覧ページにリダイレクトする。
        $this->authorize("delete", $book);
        $book->delete();

        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }
}
```

---

## 5.7. ビューの実装 (Blade)
最後に、ユーザーが操作する画面を作成します。登録画面と編集画面はフォームの内容がほぼ同じなため、共通パーツとして切り出すのが定石です。

### 共通フォーム部品 (`resources/views/books/_form.blade.php`)

まず、必要なディレクトリと空のファイルを作成します。

```bash
# ディレクトリを作成
mkdir -p resources/views/books

# 空のファイルを作成
touch resources/views/books/_form.blade.php
touch resources/views/books/create.blade.php
touch resources/views/books/edit.blade.php
```

登録・編集画面で共通して使われるフォーム部分を`@include`で呼び出せるように別ファイルに切り出します。これにより、コードの重複がなくなり、修正が容易になります。

```html
@csrf
<!-- Title, Author, ISBN, etc. fields -->
<div>
    <label for="title">タイトル</label>
    <input type="text" name="title" id="title" value="{{ old('title', $book->title ?? '') }}" required>
    @error('title')<p>{{ $message }}</p>@enderror
</div>
<!-- ... other fields ... -->
<div>
    <label>ジャンル</label>
    @foreach($genres as $genre)
        <input type="checkbox" name="genres[]" value="{{ $genre->id }}" 
               @if(in_array($genre->id, old('genres', $book->genres->pluck('id')->toArray() ?? []))) checked @endif>
        <label>{{ $genre->name }}</label>
    @endforeach
    @error('genres')<p>{{ $message }}</p>@enderror
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

(一覧画面と詳細画面は、後のChapterで他の機能と合わせて実装します)

---

## 5.8. 動作確認

1.  ログイン状態で `/books/create` にアクセスし、書籍を登録できることを確認します。
2.  登録後、その書籍の詳細ページにリダイレクトされ、「書籍を登録しました。」というメッセージが表示されることを確認します。
3.  詳細ページで、自分が登録した書籍にのみ「編集」「削除」ボタンが表示されることを確認します。
4.  編集ページにアクセスし、情報を更新できることを確認します。
5.  削除ボタンを押し、書籍が一覧から消えることを確認します。
6.  （可能であれば）別のユーザーでログインし、他人が登録した書籍の編集・削除ボタンが表示されないこと、またURLを直接入力しても編集・削除ページにアクセスできないこと（403 Forbiddenエラー）を確認します。

これで、書籍管理機能（CRUD）の基本実装が完了しました。
