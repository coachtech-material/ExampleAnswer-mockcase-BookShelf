# Chapter 3: 書籍のCRUD機能

このChapterでは、アプリケーションの中核機能である書籍のCRUD（作成、読み取り、更新、削除）を実装します。

## 3-1. ControllerとRequestの作成

まず、書籍情報の操作を担うControllerと、入力値のバリデーションを行うRequestクラスを作成します。

### Step 1: BookControllerの作成

`--resource` オプションを付けて、CRUDの基本的なメソッドが用意されたControllerを作成します。

```bash
sail artisan make:controller BookController --resource
```

### Step 2: Form Requestの作成

書籍の登録・更新時のバリデーションルールを定義するためのRequestクラスを作成します。

```bash
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
```

**思考プロセス:**
バリデーションロジックをController内に書くこともできますが、Requestクラスに分離することで、Controllerは本来の責務（HTTPリクエストの処理）に集中でき、コードの見通しが良くなります。特に、バリデーションルールが複雑になるほど、この分離のメリットは大きくなります。

## 3-2. バリデーションルールの定義

作成したRequestクラスに、要件に基づいたバリデーションルールを記述します。

### Step 1: `StoreBookRequest` の編集

**`app/Http/Requests/StoreBookRequest.php`**
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 誰でも書籍登録をリクエストできるようにtrue
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'isbn' => 'required|string|size:13|unique:books,isbn',
            'description' => 'nullable|string',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }
}
```

**コードリーディング:**
- `authorize()`: このリクエストが許可される条件を定義します。今回は認証済みのユーザーなら誰でもOKなので `true` を返します。
- `rules()`: バリデーションルールを配列で定義します。
- `'isbn' => 'required|string|size:13|unique:books,isbn'`: ISBNは必須、文字列、13文字固定、そして`books`テーブル内でユニークである必要があります。
- `'genres' => 'required|array'`: ジャンルは必須で、配列である必要があります。
- `'genres.*' => 'exists:genres,id'`: `genres`配列の各値が、`genres`テーブルの`id`カラムに存在するかをチェックします。

### Step 2: `UpdateBookRequest` の編集

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
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->book->id)],
            'description' => 'nullable|string',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }
}
```

**コードリーディング:**
- `Rule::unique('books')->ignore($this->book->id)`: ISBNのユニークチェックですが、更新対象の書籍自身のISBNはチェックから除外します。これがないと、「そのISBNは既に使用されています」というエラーが出てしまい、ISBNを変更しない限り更新できなくなってしまいます。

## 3-3. ルーティングの設定

`routes/web.php` に、`BookController` のCRUDアクションに対応するルートを追加します。

**`routes/web.php`**
```php
use App\Http\Controllers\BookController;

// ... 他のルート

Route::resource('books', BookController::class);
```

**思考プロセス:**
`Route::resource('books', BookController::class)` の一行で、以下の7つのルートが自動的に登録されます。これにより、`index`, `create`, `store`, `show`, `edit`, `update`, `destroy` の各アクションに対応するURLとルート名が規約通りに設定され、記述量を大幅に削減できます。

## 3-4. Controllerの実装

`BookController` の各メソッドに、具体的な処理を実装していきます。

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
        $book = Auth::user()->books()->create($request->only(['title', 'author', 'isbn', 'description']));
        $book->genres()->sync($request->genres);

        return redirect()->route('books.index')->with('success', '書籍を登録しました。');
    }

    public function show(Book $book)
    {
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
        $book->update($request->only(['title', 'author', 'isbn', 'description']));
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

**コードリーディング:**
- `Book::with('genres')`: N+1問題を回避するためのEager Loading（事前読み込み）です。書籍一覧を表示する際に、各書籍のジャンル情報も一度のクエリでまとめて取得します。
- `Auth::user()->books()->create(...)`: 認証済みユーザーに紐付いた書籍としてレコードを作成します。`user_id`が自動的に設定されます。
- `$book->genres()->sync($request->genres)`: 中間テーブルのデータを更新します。引数で渡された配列のIDだけが中間テーブルに残ります。登録・更新処理をシンプルに記述できます。
- `show(Book $book)`: ルートモデルバインディング。URLのIDに対応する`Book`モデルのインスタンスが自動的にDI（依存性注入）されます。
- `$this->authorize('update', $book)`: ポリシーによる認可。`BookPolicy`（後ほど作成）の`update`メソッドを呼び出し、このユーザーがこの書籍を更新する権限があるかチェックします。

---

これで書籍のCRUD機能のバックエンド処理が完成しました。次のChapterでは、これらの機能に対応するビュー（Bladeテンプレート）を作成し、フロントエンドを完成させます。
