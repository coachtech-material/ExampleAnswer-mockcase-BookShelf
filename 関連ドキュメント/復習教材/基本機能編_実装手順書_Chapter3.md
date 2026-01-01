## Chapter 3: アプリケーションの心臓部 - 書籍管理(CRUD)機能の実装

### はじめに

認証機能が整い、誰がアプリケーションを使っているのかを識別できるようになりました。いよいよ、このアプリの核となる**書籍管理機能**を実装していきます。CRUDという言葉を聞いたことがありますか？これは、ほとんどのアプリケーションに共通する基本的なデータ操作の頭文字をとったものです。

- **C**reate: 生成（新しいデータを作る）
- **R**ead: 読み取り（データを表示する）
- **U**pdate: 更新（既存のデータを変更する）
- **D**elete: 削除（データを消す）

このChapterでは、書籍データを題材に、このCRUD機能一式をLaravelの流儀に沿って実装していきます。エンジニアの思考プロセスを追いながら、実践的な開発の流れを掴んでいきましょう。

---

### Section 1: データベースの準備 - データの「入れ物」を作る

機能を作る前に、まずは書籍やジャンルの情報を保存するための「入れ物」、つまりデータベースのテーブルを設計し、作成します。

#### 1.1. 設計図（マイグレーション）の作成

要件定義書のER図を思い出してください。書籍管理には`books`, `genres`, そして両者を繋ぐ中間テーブル`book_genre`が必要です。Laravelでは、`make:migration`コマンドでこれらのテーブルの設計図となる**マイグレーションファイル**を作成します。

```bash
# genresテーブルのマイグレーションを作成
sail artisan make:migration create_genres_table

# booksテーブルのマイグレーションを作成
sail artisan make:migration create_books_table

# book_genre中間テーブルのマイグレーションを作成
sail artisan make:migration create_book_genre_table
```

これらのコマンドを実行すると、`database/migrations/`ディレクトリにタイムスタンプ付きのPHPファイルが3つ作成されます。

#### 1.2. 設計図（マイグレーション）の記述

作成されたマイグレーションファイルの`up`メソッドに、テーブルの具体的な構造を記述していきます。

**1. `..._create_genres_table.php`**

```php
// ...
public function up(): void
{
    Schema::create('genres', function (Blueprint $table) {
        $table->id(); // 自動増分の主キー(id)
        $table->string('name')->unique(); // ジャンル名、ユニーク制約付き
        $table->timestamps(); // created_atとupdated_atカラム
    });
}
// ...
```

**2. `..._create_books_table.php`**

```php
// ...
public function up(): void
{
    Schema::create('books', function (Blueprint $table) {
        $table->id();
        // 外部キー制約。usersテーブルのidを参照する。
        // onDelete('cascade')は、ユーザーが削除されたら、そのユーザーの書籍も一緒に削除する設定。
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->string('author');
        $table->string('isbn', 13)->unique(); // 13文字固定、ユニーク制約
        $table->date('published_date');
        $table->text('description')->nullable(); // nullを許容
        $table->string('image_url')->nullable(); // nullを許容
        $table->timestamps();
    });
}
// ...
```

**3. `..._create_book_genre_table.php`**

```php
// ...
public function up(): void
{
    Schema::create('book_genre', function (Blueprint $table) {
        // 中間テーブルには通常idは不要。複合主キーを設定する。
        $table->foreignId('book_id')->constrained()->onDelete('cascade');
        $table->foreignId('genre_id')->constrained()->onDelete('cascade');
        // 同一の組み合わせが複数登録されないように、複合主キーを設定
        $table->primary(['book_id', 'genre_id']);
    });
}
// ...
```

> **【エンジニアの思考】**
> なぜ`id`や`timestamps`を記述するのか？これらはLaravelのORM「Eloquent」がモデルを操作する際に暗黙的に利用する「お作法」だからです。このお作法に従うことで、後のコードが非常にシンプルになります。また、`onDelete('cascade')`のようなデータベースレベルでの制約をしっかり設定しておくことで、データの不整合が起こるのを防ぎ、アプリケーションの堅牢性を高めています。

#### 1.3. データベースへの反映

設計図が完成したので、`migrate`コマンドで実際にデータベースにテーブルを作成します。

```bash
sail artisan migrate
```

phpMyAdminで確認してみてください。`books`, `genres`, `book_genre`テーブルが作成されているはずです。

---

### Section 2: モデルとリレーション - データ操作の要

テーブルができたので、次はそのテーブルをPHPのコード上からエレガントに操作するための**モデル**を作成し、モデル間の**リレーション（関連付け）**を定義します。

#### 2.1. モデルの作成

`make:model`コマンドで`Book`と`Genre`モデルを作成します。

```bash
sail artisan make:model Book
sail artisan make:model Genre
```

#### 2.2. マスアサインメントの設定

`create`メソッドなどで一度に複数のカラムを更新する「マスアサインメント」を許可するために、各モデルの`$fillable`プロパティに、代入を許可するカラム名を配列で指定します。

**`app/Models/Book.php`**
```php
class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];
}
```

**`app/Models/Genre.php`**
```php
class Genre extends Model
{
    use HasFactory;

    protected $fillable = ['name'];
}
```

#### 2.3. リレーションの定義

ここがEloquentの強力な部分です。モデル間にリレーションを定義することで、`$book->user->name`のように、オブジェクトのプロパティにアクセスする感覚で関連データを取得できるようになります。

**`app/Models/Book.php`**
```php
// ... (fillableの下に追記)

// Bookは1人のUserに属する (1対多の「多」側)
public function user()
{
    return $this->belongsTo(User::class);
}

// Bookは複数のReviewを持つ (1対多の「1」側)
public function reviews()
{
    return $this->hasMany(Review::class);
}

// Bookは複数のGenreに属する (多対多)
public function genres()
{
    return $this->belongsToMany(Genre::class)->withTimestamps();
}
```

**`app/Models/Genre.php`**
```php
// ... (fillableの下に追記)

// Genreは複数のBookを持つ (多対多)
public function books()
{
    return $this->belongsToMany(Book::class);
}
```

**`app/Models/User.php`**

Breezeが作成した`User`モデルにも、関連するリレーションを追記しておきましょう。

```php
// ... (castsメソッドの下に追記)

// Userは複数のBookを持つ
public function books()
{
    return $this->hasMany(Book::class);
}
```

> **【リレーションの解説】**
> - **`belongsTo` (〜に属する)**: 「従」となる側（外部キーを持つ側）に定義します。`books`テーブルは`user_id`を持っているので、`Book`モデルから`User`モデルへの関係は`belongsTo`です。
> - **`hasMany` (たくさん持つ)**: 「主」となる側に定義します。1人の`User`はたくさんの`Book`を持つので、`User`モデルから`Book`モデルへの関係は`hasMany`です。
> - **`belongsToMany` (多対多)**: 中間テーブルを介した関係です。`Book`と`Genre`の関係がこれにあたります。

---

### Section 3: 書籍登録機能 (Create) - 最初のデータを作る

準備が整いました。いよいよCRUDの「C」、書籍登録機能から実装します。

#### 3.1. ルーティングとコントローラの準備

まず、書籍管理機能の司令塔となる`BookController`を`--resource`オプション付きで作成します。これにより、CRUDに必要な7つのメソッド（`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`）の雛形が自動で生成されます。

```bash
sail artisan make:controller BookController --resource
```

次に、`routes/web.php`に書籍関連のルートを定義します。`Route::resource`を使うと、7つのルートを1行で定義できて非常に便利です。

```php
// routes/web.php

use App\Http\Controllers\BookController;
// ...

Route::middleware('auth')->group(function () {
    // ... (profileルートなど)

    // この1行が7つのルートを生成する
    Route::resource('books', BookController::class);
});
```

#### 3.2. 登録フォームの表示 (create)

1.  **コントローラ (`BookController@create`)**
    登録フォーム画面では、ユーザーにジャンルを選択してもらう必要があります。そのため、`create`メソッドで全てのジャンル情報を取得し、ビューに渡します。

    ```php
    // app/Http/Controllers/BookController.php

    use App\Models\Genre;
    // ...

    public function create()
    {
        $genres = Genre::all(); // 全てのジャンルを取得
        return view('books.create', compact('genres')); // ビューに渡す
    }
    ```

2.  **ビュー (`resources/views/books/create.blade.php`)**
    `coachtech-material/Preparedblade-mockcase-BookShelf`リポジトリから提供されている`create.blade.php`を配置します。この中では、コントローラから渡された`$genres`をループして、チェックボックスとして表示しています。

    ```blade
    {{-- ジャンル選択部分の抜粋 --}}
    <div class-="form-group">
        <label>ジャンル</label>
        @foreach ($genres as $genre)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="genres[]" value="{{ $genre->id }}">
                <label class="form-check-label">{{ $genre->name }}</label>
            </div>
        @endforeach
    </div>
    ```

#### 3.3. バリデーションの要 - FormRequest

登録処理（`store`メソッド）に進む前に、非常に重要な**FormRequest**について学びます。これは、フォームから送信された内容の**バリデーション（入力値検証）**ルールを専門に扱うクラスです。

> **【なぜFormRequestを使うのか？】**
> バリデーションロジックをコントローラ内に書くこともできます。しかし、機能が複雑になるにつれてコントローラは肥大化し、見通しが悪くなります。FormRequestにバリデーションを分離することで、「**関心の分離**」という設計原則を守り、コントローラは「リクエストを受け取って、適切な処理に振り分ける」という本来の責務に集中できるのです。

`make:request`コマンドで`StoreBookRequest`を作成します。

```bash
sail artisan make:request StoreBookRequest
```

作成された`app/Http/Requests/StoreBookRequest.php`を編集します。

```php
// app/Http/Requests/StoreBookRequest.php

class StoreBookRequest extends FormRequest
{
    // このリクエストの利用を許可するかどうか。今回はログイン済みなら誰でもOK。
    public function authorize(): bool
    {
        return true;
    }

    // バリデーションルールを定義する。
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
            'genres.*' => ['exists:genres,id'], // genres配列の各要素が存在するか
        ];
    }
}
```

#### 3.4. 登録処理の実装 (store)

準備が整ったので、`store`メソッドを実装します。引数の型を`StoreBookRequest`にすることで、Laravelが**自動的にバリデーションを実行**してくれます。バリデーションが失敗すれば、自動で前のページにエラーメッセージ付きでリダイレクトしてくれるので、コントローラには成功した場合の処理だけを書けばOKです。

```php
// app/Http/Controllers/BookController.php

use App\Http\Requests\StoreBookRequest;
// ...

public function store(StoreBookRequest $request)
{
    // 1. バリデーション済みのデータを取得
    $validated = $request->validated();

    // 2. genresを除いた書籍データを準備
    $bookData = collect($validated)->except('genres')->toArray();

    // 3. ログインユーザーに紐付けて書籍を作成
    $book = $request->user()->books()->create($bookData);

    // 4. 中間テーブルにジャンルを登録
    $book->genres()->attach($validated['genres']);

    // 5. 登録した書籍の詳細ページにリダイレクト
    return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
}
```

> **【コードリーディング】**
> - `(StoreBookRequest $request)`: **依存性の注入**。Laravelが自動で`StoreBookRequest`のインスタンスを作成し、バリデーションを実行してくれます。
> - `$request->validated()`: バリデーションを通過したデータを配列で取得します。
> - `$request->user()`: ログインしている`User`モデルのインスタンスを取得します。
> - `->books()->create($bookData)`: `User`モデルに定義した`books`リレーション（`hasMany`）を利用して、`user_id`が自動的にセットされた状態で`books`テーブルにレコードを作成しています。非常にエレガントな書き方です。
> - `->genres()->attach(...)`: `Book`モデルに定義した`genres`リレーション（`belongsToMany`）を利用して、`book_genre`中間テーブルに複数のレコードを一度に作成しています。
> - `redirect()->route(...)`: 名前付きルートを使ってリダイレクト先を指定しています。
> - `->with('success', ...)`: 次のページに一度だけ表示される**フラッシュメッセージ**をセッションに保存しています。

これで登録機能は完成です！実際にフォームから書籍を登録し、データベースにデータが保存されること、詳細ページにリダイレクトされることを確認してみてください。

---

このChapterは長くなるので、一旦ここで区切ります。次のセクションでは、残りのRead（一覧・詳細）、Update（更新）、Delete（削除）機能を実装していきます。


---

### Section 4: 書籍表示機能 (Read) - データを画面に映し出す

登録したデータをユーザーに見せる機能、Readを実装します。一覧表示と詳細表示の2つです。

#### 4.1. 一覧表示 (index)

**コントローラ (`BookController@index`)**

```php
// app/Http/Controllers/BookController.php

public function index()
{
    // with("genres")でN+1問題を解決
    // latest()で新しい順に並び替え
    // paginate(10)で10件ずつのページネーションを実装
    $books = Book::with("genres")->latest()->paginate(10);
    return view("books.index", compact("books"));
}
```

> **【N+1問題とは？】**
> これはORMを扱う上で非常に重要なパフォーマンス問題です。例えば、10件の書籍を取得し（1クエリ）、その後ループ内で各書籍のジャンルを取得しようとすると、書籍ごとにクエリが発行され、合計1+10=11回のクエリが実行されてしまいます。`with("genres")`（**Eager Loading**といいます）を使うと、「書籍を取得する際に、関連するジャンルもまとめて取得する」という賢いクエリ（この場合は2クエリ）を発行してくれ、この問題を未然に防ぐことができます。

**ビュー (`resources/views/books/index.blade.php`)**

提供されている`index.blade.php`を配置します。コントローラから渡された`$books`をループで表示し、ページネーションのリンクを`{{ $books->links() }}`で表示します。

#### 4.2. 詳細表示 (show)

**コントローラ (`BookController@show`)**

```php
// app/Http/Controllers/BookController.php

public function show(Book $book)
{
    // 書籍に紐づくレビュー、レビューの投稿者、いいねしたユーザー、ジャンルをまとめて読み込む
    $book->load(["reviews.user", "reviews.likedByUsers", "genres"]);
    return view("books.show", compact("book"));
}
```

> **【ルートモデルバインディング】**
> `show(Book $book)`のように、ルートのパラメータ（例: `/books/1`の`1`）に対応するモデルのインスタンスを、Laravelが自動的にデータベースから見つけて注入してくれる機能を**ルートモデルバインディング**と呼びます。これにより、`$book = Book::findOrFail($id);`のような記述が不要になり、コードがスッキリします。

**ビュー (`resources/views/books/show.blade.php`)**

提供されている`show.blade.php`を配置します。`$book`オブジェクトからタイトルや著者、リレーションで読み込んだジャンルなどを表示します。

---

### Section 5: 書籍更新機能 (Update) - 既存のデータを変更する

#### 5.1. 認可の概念 - Policy

更新機能に進む前に、**認可（Authorization）**について学びます。認証（Authentication）が「誰であるか」を確認するのに対し、認可は「**その操作をする権限があるか**」を確認するプロセスです。

> 他のユーザーが登録した書籍を、勝手に編集・削除できてしまったら大問題ですよね？これを防ぐのが認可の役割です。

Laravelでは、この認可ロジックを**Policy**クラスにまとめるのが一般的です。`make:policy`コマンドで`Book`モデルに対するPolicyを作成します。

```bash
sail artisan make:policy BookPolicy --model=Book
```

作成された`app/Policies/BookPolicy.php`に、更新と削除の権限を定義します。

```php
// app/Policies/BookPolicy.php

class BookPolicy
{
    // ...

    // 書籍を更新する権限
    public function update(User $user, Book $book): bool
    {
        // ログインユーザーのIDと、書籍を登録したユーザーのIDが一致すればtrue
        return $user->id === $book->user_id;
    }

    // 書籍を削除する権限
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    // ...
}
```

作成したPolicyを有効にするために、`app/Providers/AuthServiceProvider.php`に登録するのを忘れないでください。

```php
// app/Providers/AuthServiceProvider.php

protected $policies = [
    Book::class => BookPolicy::class, // この行を追記
];
```

#### 5.2. 更新フォームの表示 (edit)

**コントローラ (`BookController@edit`)**

`edit`メソッドで、`update` Policyを使って認可チェックを行います。`$this->authorize("update", $book)`は、`BookPolicy`の`update`メソッドを呼び出し、結果が`false`なら自動的に403 Forbiddenエラーページを表示してくれます。

```php
// app/Http/Controllers/BookController.php

public function edit(Book $book)
{
    // 認可チェック
    $this->authorize("update", $book);

    $genres = Genre::all();
    return view("books.edit", compact("book", "genres"));
}
```

**ビュー (`resources/views/books/edit.blade.php`)**

提供されている`edit.blade.php`を配置します。`create`ビューと似ていますが、フォームに既存のデータ（`$book->title`など）がセットされている点が異なります。

#### 5.3. 更新処理の実装 (update)

更新時も、バリデーションのために`UpdateBookRequest`を作成します。

```bash
sail artisan make:request UpdateBookRequest
```

`UpdateBookRequest`では、ISBNのユニークチェックで「自分自身のISBNは除外する」という例外ルールが必要です。

```php
// app/Http/Requests/UpdateBookRequest.php

use Illuminate\Validation\Rule;

// ...
public function rules(): array
{
    return [
        // ... (title, authorなどはStoreBookRequestと同じ)
        "isbn" => ["required", "string", "size:13", Rule::unique("books")->ignore($this->book)],
        // ...
    ];
}
```

**コントローラ (`BookController@update`)**

`update`メソッドでも、最初に認可チェックを行い、`UpdateBookRequest`でバリデーションを実行します。

```php
// app/Http/Controllers/BookController.php

use App\Http\Requests\UpdateBookRequest;
// ...

public function update(UpdateBookRequest $request, Book $book)
{
    // 認可チェック
    $this->authorize("update", $book);

    // バリデーション済みのデータを取得して更新
    $book->update($request->validated());

    // ジャンルを再同期（古い関連を削除し、新しいものを登録）
    $book->genres()->sync($request->genres);

    return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
}
```

> **【`attach`と`sync`の違い】**
> - `attach`: 多対多リレーションで、単純に関連を追加します。
> - `sync`: 関連を「同期」します。引数で渡されたIDの関連が存在しない場合は作成し、逆に引数にないIDの関連が既に存在する場合は削除します。更新処理には`sync`が最適です。

---

### Section 6: 書籍削除機能 (Delete) - 最後の仕上げ

CRUDの最後、削除機能を実装します。

**コントローラ (`BookController@destroy`)**

`destroy`メソッドでも、まず`delete` Policyによる認可チェックを行います。

```php
// app/Http/Controllers/BookController.php

public function destroy(Book $book)
{
    // 認可チェック
    $this->authorize("delete", $book);

    $book->delete();

    return redirect()->route("books.index")->with("success", "書籍を削除しました。");
}
```

**ビュー (`resources/views/books/show.blade.php`)**

削除は危険な操作なので、`onclick`属性を使ってJavaScriptで確認ダイアログを表示するのが一般的です。

```blade
<form action="{{ route("books.destroy", $book) }}" method="POST">
    @csrf
    @method("DELETE")
    <button type="submit" class="btn btn-danger" onclick="return confirm("本当に削除しますか？");">削除</button>
</form>
```

> **【`@method("DELETE")`とは？】**
> HTMLの`form`タグは、`method`として`GET`と`POST`しかサポートしていません。`PUT`や`DELETE`といったHTTPメソッドを使いたい場合、Laravelでは`method="POST"`としつつ、`@method("DELETE")`ディレクティブを記述することで、内部的にDELETEリクエストとして扱わせることができます。

### まとめ

お疲れ様でした！このChapterでは、書籍管理のCRUD機能一式を実装しました。学んだことは非常に多いはずです。

- **マイグレーション**によるデータベーススキーマの管理
- **モデル**と**リレーション**によるデータ操作の抽象化
- **リソースコントローラ**と**リソースルート**による効率的なCRUD実装
- **FormRequest**によるバリデーションロジックの分離
- **Policy**による認可ロジックの分離
- **N+1問題**とEager Loading (`with`)
- **ルートモデルバインディング**

これらの概念は、Laravel開発の根幹をなす非常に重要なものです。コードをただ書くだけでなく、「なぜこうなっているのか」を理解することで、応用力が格段に向上します。

次のChapterでは、書籍に紐づくレビュー機能を実装していきます。
