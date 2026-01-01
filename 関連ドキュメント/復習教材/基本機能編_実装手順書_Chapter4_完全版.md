# Chapter 4: 書籍のCRUD機能 (ルーティング・コントローラ編)

このChapterでは、アプリケーションの中核機能である書籍のCRUD（作成、読み取り、更新、削除）のバックエンド部分を実装します。具体的には、ルーティング、バリデーション、そしてコントローラのロジックを構築します。

## 4-1. 先輩エンジニアの思考プロセス：なぜリレーションの次にCRUDを実装するのか？

Chapter 2でデータベースの「骨格」を作り、Chapter 3でモデルとリレーションという「神経」を通しました。では、なぜ次がCRUD（Create, Read, Update, Delete）なのでしょうか？

> **思考プロセス：土台から着実に積み上げる**
> 
> アプリケーション開発は、ビルを建てるプロセスに似ています。
> 
> 1.  **基礎工事（Chapter 2: DB設計）**: どんなデータをどこに置くか、土地（DB）の区画整理をしました。
> 2.  **骨組みの構築（Chapter 3: モデルとリレーション）**: 区画の上に柱（モデル）を立て、柱同士を梁（リレーション）で繋ぎ、建物の構造を固めました。
> 3.  **内装工事（Chapter 4: CRUD）**: いよいよ、部屋（機能）の中身を作っていきます。データを「表示」したり、「登録」したり、「更新」したりする、アプリケーションの具体的な機能そのものです。

> **なぜ他のものから実装してはいけないのか？**
> 
> 例えば、いきなりUI（見た目）から作り始めるとどうなるでしょう？「このボタンを押したら、どのデータをどう処理すればいいんだ？」と、結局はデータの構造に立ち返ることになります。また、データの取得方法（リレーション）が決まっていなければ、効率的な表示処理も書けません。
> 
> **「データ構造 → 関連付け → データ操作」**
> 
> この順番は、アプリケーションの土台から着実に機能を積み上げていくための、最も合理的で手戻りの少ない進め方なのです。CRUDは、ユーザーが直接触れる機能の根幹であり、これまでの設計が正しかったかを最初に検証する場でもあります。

## 4-2. ルーティングの設計 (Routing)

まず、書籍CRUD機能に関するURLと、それに対応する処理（コントローラのアクション）を紐付けます。

**`routes/web.php`**
```php
<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

// ... (他のルート)

Route::resource("books", BookController::class)->middleware("auth");
```

> **思考プロセス：なぜ `Route::resource` を使うのか？**
> 書籍のCRUDには、一覧表示(index)、登録画面(create)、登録処理(store)、詳細表示(show)、編集画面(edit)、更新処理(update)、削除処理(destroy) の計7つのアクションが必要です。これらを一つずつ `Route::get(...)` や `Route::post(...)` で定義するのは冗長です。
> 
> `Route::resource("books", BookController::class)` は、この7つのルートを**規約（Convention）**に基づいて自動的に生成してくれる便利なメソッドです。これにより、コードの記述量を大幅に削減し、誰が見ても分かりやすい標準的なURL設計を実現できます。
> 
> また、`->middleware("auth")` をチェーンすることで、これら7つのルートすべてに認証ミドルウェアを適用し、「ログインしているユーザーのみがアクセスできる」という制限を簡単に設けることができます。

## 4-3. バリデーションの設計 (Form Request)

ユーザーからの入力値を検証（バリデーション）するロジックを、専用のForm Requestクラスに分離して実装します。

```bash
# 登録用と更新用のForm Requestをそれぞれ作成
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
```

> **思考プロセス：なぜバリデーションロジックを分離するのか？**
> コントローラの主な責務は、リクエストを受け取り、ビジネスロジック（モデル）を呼び出し、レスポンスを返すことです。ここにバリデーションのような定型的なロジックが混在すると、コントローラが肥大化し、可読性や保守性が低下します。
> 
> Form Requestクラスを作成することで、バリデーションルールをカプセル化できます。これにより、コントローラは `StoreBookRequest $request` のようにタイプヒントするだけで、バリデーション済みで安全なデータを受け取れるようになり、本来の責務に集中できます。

### 登録処理のバリデーション (`StoreBookRequest`)

**`app/Http/Requests/StoreBookRequest.php`**
```php
public function rules(): array
{
    return [
        "title" => "required|string|max:255",
        "author" => "required|string|max:255",
        "isbn" => "required|string|size:13|unique:books,isbn",
        "published_date" => "required|date",
        "description" => "nullable|string",
        "image_url" => "nullable|url",
        "genres" => "required|array",
        "genres.*" => "exists:genres,id",
    ];
}
```

### 更新処理のバリデーション (`UpdateBookRequest`)

**`app/Http/Requests/UpdateBookRequest.php`**
```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        "title" => "required|string|max:255",
        "author" => "required|string|max:255",
        "isbn" => ["required", "string", "size:13", Rule::unique("books")->ignore($this->book->id)],
        "published_date" => "required|date",
        "description" => "nullable|string",
        "image_url" => "nullable|url",
        "genres" => "required|array",
        "genres.*" => "exists:genres,id",
    ];
}
```
> **コード解説:**
> - `Rule::unique("books")->ignore($this->book->id)`: 更新時のISBNユニークチェックでは、自分自身のISBNを除外しないと、「そのISBNは既に使用されています」というエラーが出てしまいます。`ignore`メソッドで更新対象の書籍IDを指定することで、この問題を解決します。

## 4-4. コントローラの作成と実装

`Route::resource`に対応する`BookController`を作成し、各メソッドに処理を実装します。

```bash
sail artisan make:controller BookController --resource --model=Book
```

### `index()` メソッド：書籍一覧表示

> **要件 → 実装の思考フロー**
> 1.  **要件**: 「登録されている書籍を**10件ずつ**のページネーションで一覧表示する」
> 2.  **思考**: 
>     - まず、`Book`モデルを使って全書籍を取得する必要があるな。`Book::all()` かな？
>     - いや、待てよ。一覧画面では各書籍のジャンルも表示するはずだ（UIテンプレートを確認）。普通に取得すると、書籍の数だけジャンル取得クエリが発行される「**N+1問題**」が発生してしまう。
>     - パフォーマンスを考慮し、`with("genres")` を使って**Eager Loading（事前読み込み）**しよう。
>     - 「10件ずつ」とあるから、`get()`ではなく`paginate(10)`を使う必要がある。
>     - 新しく登録されたものが上に表示されるのが一般的だから、`latest()`で登録日時の降順に並び替えよう。
> 3.  **実装**: `Book::with("genres")->latest()->paginate(10);`
> 4.  **思考**: 取得したデータをビューに渡す必要がある。`compact("books")` を使って、`books`という変数名でビューに渡そう。
> 5.  **実装**: `return view("books.index", compact("books"));`

```php
// app/Http/Controllers/BookController.php

public function index()
{
    $books = Book::with("genres")->latest()->paginate(10);
    return view("books.index", compact("books"));
}
```

### `create()` / `store()` メソッド：書籍登録

> **要件 → 実装の思考フロー**
> 1.  **要件**: 「書籍のタイトル、著者、...、**ジャンル**を登録できる」
> 2.  **思考 (`create`)**: 登録フォームにはジャンルを選択するチェックボックスがある。ということは、`genres`テーブルから全ジャンルを取得してビューに渡す必要があるな。
> 3.  **実装 (`create`)**: `$genres = Genre::all(); return view("books.create", compact("genres"));`
> 4.  **思考 (`store`)**: 
>     - リクエストデータは`StoreBookRequest`でバリデーション済みだ。
>     - `books`テーブルに保存するデータと、中間テーブル`book_genre`に保存するデータ（`genres`）を分離する必要がある。
>     - `Auth::user()->books()->create(...)` を使えば、`user_id`を自動的にセットして書籍を登録できる。これは便利だ。
>     - 書籍を登録した後、その書籍のIDとリクエストで送られてきたジャンルIDの配列を使って、`attach()`メソッドで中間テーブルにレコードを追加しよう。
> 5.  **実装 (`store`)**: 
    ```php
    $validated = $request->validated();
    $bookData = collect($validated)->except(["genres"])->toArray();
    $book = Auth::user()->books()->create($bookData);
    $book->genres()->attach($validated["genres"]);
    ```
> 6.  **思考**: 登録後は一覧ページに戻り、「登録しました」というメッセージを表示するのが親切だ。
> 7.  **実装**: `return redirect()->route("books.index")->with("success", "書籍を登録しました。");`

```php
// app/Http/Controllers/BookController.php

public function create()
{
    $genres = Genre::all();
    return view("books.create", compact("genres"));
}

public function store(StoreBookRequest $request)
{
    $validated = $request->validated();
    $bookData = collect($validated)->except(["genres"])->toArray();

    $book = Auth::user()->books()->create($bookData);
    $book->genres()->attach($validated["genres"]);

    return redirect()->route("books.index")->with("success", "書籍を登録しました。");
}
```

### `show()` メソッド：書籍詳細表示

> **要件 → 実装の思考フロー**
> 1.  **要件**: 「特定の書籍の詳細情報と、その書籍に紐付く**レビューの一覧**を表示する」
> 2.  **思考**: 
>     - ルートモデルバインディング（`show(Book $book)`）のおかげで、IDに一致する`$book`インスタンスは自動的に取得できる。
>     - 詳細ページでは、レビュー本文だけでなく、**レビュー投稿者の名前**や、**レビューへの「いいね」**も表示する必要があるはずだ（UIテンプレートを確認）。
>     - これもN+1問題の温床だ。`$book`に紐付くリレーション（`reviews`とその先の`user`、`reviews`と`likedByUsers`、そして書籍自体の`genres`）をまとめてEager Loadingしよう。
>     - 既に取得済みのモデルに後からリレーションを読み込むには`load()`メソッドが使える。
> 3.  **実装**: `$book->load(["reviews.user", "reviews.likedByUsers", "genres"]);`
> 4.  **思考**: 取得した`$book`オブジェクトをビューに渡す。
> 5.  **実装**: `return view("books.show", compact("book"));`

```php
// app/Http/Controllers/BookController.php

public function show(Book $book)
{
    $book->load(["reviews.user", "reviews.likedByUsers", "genres"]);
    return view("books.show", compact("book"));
}
```

### `edit()` / `update()` メソッド：書籍更新

> **要件 → 実装の思考フロー**
> 1.  **要件**: 「**自分が登録した**書籍の情報を編集できる」
> 2.  **思考 (`edit`)**: 
>     - まず、「自分が登録した書籍か？」をチェックする必要がある。これは**認可（Authorization）**だ。次のChapterで実装する`Policy`を使うことを見越して、`$this->authorize("update", $book);` を書いておこう。
>     - 編集フォームには登録時と同様に全ジャンルの一覧が必要だ。
> 3.  **実装 (`edit`)**: `$this->authorize("update", $book); $genres = Genre::all(); return view("books.edit", compact("book", "genres"));`
> 4.  **思考 (`update`)**: 
>     - ここでもまず認可チェックが必要だ。
>     - `store`の時と同様に、`books`テーブルを更新するデータと、中間テーブルを更新する`genres`データを分離する。
>     - 書籍本体の更新は`$book->update()`で行う。
>     - ジャンルの更新は、一度関連を全て削除して新しく登録し直す`sync()`メソッドが最適だ。`attach()`だと重複登録されてしまう。
> 5.  **実装 (`update`)**: 
    ```php
    $this->authorize("update", $book);
    $validated = $request->validated();
    $bookData = collect($validated)->except(["genres"])->toArray();
    $book->update($bookData);
    $book->genres()->sync($validated["genres"]);
    ```
> 6.  **思考**: 更新後は、更新結果が確認できる詳細ページにリダイレクトするのが自然だ。
> 7.  **実装**: `return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");`

```php
// app/Http/Controllers/BookController.php

public function edit(Book $book)
{
    $this->authorize("update", $book);
    $genres = Genre::all();
    return view("books.edit", compact("book", "genres"));
}

public function update(UpdateBookRequest $request, Book $book)
{
    $this->authorize("update", $book);
    
    $validated = $request->validated();
    $bookData = collect($validated)->except(["genres"])->toArray();

    $book->update($bookData);
    $book->genres()->sync($validated["genres"]);

    return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
}
```

### `destroy()` メソッド：書籍削除

> **要件 → 実装の思考フロー**
> 1.  **要件**: 「**自分が登録した**書籍を削除できる」
> 2.  **思考**: 
>     - `edit`/`update`と同様に、まず認可チェックが必要だ。`$this->authorize("delete", $book);`
>     - 削除処理はシンプルに`$book->delete()`を呼び出すだけだ。
>     - DB設計時に`onDelete(\'cascade\')`を設定したので、この書籍に紐づくレビューなども自動的に削除されるはずだ。
> 3.  **実装**: `$this->authorize("delete", $book); $book->delete();`
> 4.  **思考**: 削除後は一覧ページに戻すのが適切だろう。
> 5.  **実装**: `return redirect()->route("books.index")->with("success", "書籍を削除しました。");`

```php
// app/Http/Controllers/BookController.php

public function destroy(Book $book)
{
    $this->authorize("delete", $book);
    $book->delete();

    return redirect()->route("books.index")->with("success", "書籍を削除しました。");
}
```

---

これで書籍CRUDの心臓部が完成しました。次のChapterでは、このバックエンドロジックを保護するための「認可」の仕組みを実装します。
