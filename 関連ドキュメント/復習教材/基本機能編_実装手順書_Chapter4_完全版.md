'''# Chapter 4: 書籍のCRUD機能 (ルーティング・コントローラ編)

このChapterでは、アプリケーションの中核機能である書籍のCRUD（作成、読み取り、更新、削除）のバックエンド部分を実装します。具体的には、ルーティング、バリデーション、そしてコントローラのロジックを構築します。

## 4-1. ルーティングの設計 (Routing)

まず、書籍CRUD機能に関するURLと、それに対応する処理（コントローラのアクション）を紐付けます。

**`routes/web.php`**
```php
<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

// ... (他のルート)

Route::resource('books', BookController::class)->middleware('auth');
```

> **思考プロセス:**
> なぜ `Route::resource` を使うのでしょうか？ 書籍のCRUDには、一覧表示(GET /books)、登録画面(GET /books/create)、登録処理(POST /books)、詳細表示(GET /books/{book})、編集画面(GET /books/{book}/edit)、更新処理(PUT /books/{book})、削除処理(DELETE /books/{book}) の計7つのアクションが必要です。これらを `Route::get(...)`, `Route::post(...)` のように一つずつ定義するのは非常に冗長です。
> 
> `Route::resource('books', BookController::class)` は、この7つのルートを**規約（Convention）**に基づいて自動的に生成してくれる便利なメソッドです。これにより、コードの記述量を大幅に削減し、誰が見ても分かりやすい標準的なURL設計を実現できます。
> 
> また、`->middleware('auth')` をチェーンすることで、これら7つのルートすべてに認証ミドルウェアを適用し、「ログインしているユーザーのみがアクセスできる」という制限を簡単に設けることができます。

## 4-2. バリデーションの設計 (Form Request)

ユーザーからの入力値を検証（バリデーション）するロジックを、専用のForm Requestクラスに分離して実装します。

```bash
# 登録用と更新用のForm Requestをそれぞれ作成
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
```

> **思考プロセス:**
> なぜバリデーションロジックをコントローラから分離するのでしょうか？ コントローラの主な責務は、リクエストを受け取り、ビジネスロジック（モデル）を呼び出し、レスポンスを返すことです。ここにバリデーションのような定型的なロジックが混在すると、コントローラが肥大化し、可読性や保守性が低下します。
> 
> Form Requestクラスを作成することで、バリデーションルールと認可ロジック（後述）をカプセル化できます。これにより、コントローラは `StoreBookRequest $request` のようにタイプヒントするだけで、バリデーション済みで安全なデータを受け取れるようになり、本来の責務に集中できます。同じバリデーションルールを複数の箇所で再利用する際にも便利です。

### 登録処理のバリデーション (`StoreBookRequest`)

**`app/Http/Requests/StoreBookRequest.php`**
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ログインしていれば誰でも登録リクエストは可能
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'isbn' => 'required|string|size:13|unique:books,isbn',
            'published_date' => 'required|date',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }
}
```

### 更新処理のバリデーション (`UpdateBookRequest`)

**`app/Http/Requests/UpdateBookRequest.php`**
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認可はポリシーに任せるため、ここではtrue
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->book->id)],
            'published_date' => 'required|date',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }
}
```
> **コード解説:**
> - `Rule::unique('books')->ignore($this->book->id)`: 更新時のISBNユニークチェックは、登録時と少し異なります。自分自身のISBNはユニークチェックの対象から除外しないと、「そのISBNは既に使用されています」というエラーが出てしまい、ISBNを変更しない限り更新できなくなってしまいます。`ignore`メソッドで更新対象の書籍IDを指定することで、この問題を解決します。`$this->book` は、ルートモデルバインディングによって注入された`Book`インスタンスを指します。

## 4-3. コントローラの作成と実装

`Route::resource`に対応する`BookController`を作成し、各メソッドに処理を実装します。

```bash
sail artisan make:controller BookController --resource --model=Book
```
> **思考プロセス:**
> `--resource` に加えて `--model=Book` オプションを付けることで、各メソッドの引数に `Book` モデルがタイプヒントされ、ルートモデルバインディングが自動で設定された状態のコントローラが生成されます。細かい部分ですが、こうしたArtisanのオプション活用が開発効率を高めます。

**`app/Http/Controllers/BookController.php`**
```php
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
        $book->genres()->sync($request->genres);

        return redirect()->route('books.index')->with('success', '書籍を登録しました。');
    }

    public function show(Book $book)
    {
        $book->load(['reviews.user', 'genres']);
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

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    public function destroy(Book $book)
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
```
> **コード解説:**
> - `index()`: `with('genres')` でジャンル情報をEager Loading（事前読み込み）しています。これがないと、一覧表示で各書籍のジャンルを表示するたびにクエリが発行される「N+1問題」が発生し、パフォーマンスが著しく低下します。
> - `store()`: `Auth::user()->books()->create(...)` とすることで、現在ログインしているユーザーのIDを`user_id`として自動的に設定し、書籍を登録します。`$request->validated()` は、Form Requestでバリデーション済みのデータを配列として取得するメソッドです。
> - `sync($request->genres)`: 多対多リレーションを更新する便利なメソッドです。中間テーブル（`book_genre`）のレコードを、引数で渡された`genres`のID配列と完全に同期させます。古い関連付けは削除され、新しい関連付けが追加されます。
> - `show(Book $book)`: これは「ルートモデルバインディング」という機能です。URLの `{book}` 部分のIDをもとに、Laravelが自動で`Book`モデルのインスタンスを検索し、メソッドに注入（DI）してくれます。`Book::findOrFail($id)` を書く必要がありません。
> - `edit()`, `update()`, `destroy()`: `$this->authorize(...)` は、次のChapterで作成する「ポリシー（Policy）」を使った認可処理です。「このユーザーがこの書籍を更新/削除する権限があるか？」をチェックします。

---

これで書籍CRUDの心臓部が完成しました。次のChapterでは、このバックエンドロジックを保護するための「認可」の仕組みを実装します。'''
