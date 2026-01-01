
# Chapter 3: 書籍管理機能 - アプリケーションの中核を実装する

認証機能が整ったので、いよいよこのアプリケーションのメイン機能である「書籍管理機能」を実装していきます。ここでは、Laravelの基本的な開発スタイルである**MVC（Model-View-Controller）**モデルに従い、CRUD（Create, Read, Update, Delete）操作を一つずつ丁寧に実装していきます。

## 3-1. 要件の再確認とBladeからの情報抽出

ここでも、まずは「詳細度50%」の要件定義書と、与えられたBladeテンプレートから情報を読み解くことから始めます。

> **【要件定義書（詳細度50%）より抜粋】**
> 
> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | 書籍管理 | 書籍一覧表示 | 登録されている書籍を一覧で表示する |
> | | 書籍詳細表示 | 特定の書籍の詳細情報を表示する |
> | | 書籍登録 | 新しい書籍を登録する |
> | | 書籍編集 | 登録した書籍の情報を編集する |
> | | 書籍削除 | 登録した書籍を削除する |

### Bladeテンプレートの読み解き (`index.blade.php` と `show.blade.php`)

-   `resources/views/books/index.blade.php`（一覧画面）を見ると…
    -   `@foreach($books as $book)`: コントローラから`$books`という名前の変数が渡される必要がある。
    -   `{{ $book->title }}`, `{{ $book->author }}`: `$book`オブジェクトは`title`と`author`プロパティを持つ。
    -   `@foreach($book->genres as $genre)`: `$book`は`genres`というリレーションを持つ。
    -   `{{ $books->links() }}`: ページネーションが実装されている。
    -   `<a href="{{ route(\'books.show\', $book) }}">`: 詳細ページへのリンクがある。

-   `resources/views/books/show.blade.php`（詳細画面）を見ると…
    -   `{{ $book->title }}`, `{{ $book->author }}`, `{{ $book->isbn }}`, `{{ $book->published_date }}`: 書籍のさらに詳細な情報が表示されている。
    -   `@can(\'update\', $book)`: 書籍の編集・削除ボタンが表示される条件として、`update`という名前の認可（Policy）が使われている。
    -   `@foreach($book->reviews as $review)`: 書籍はレビュー（`reviews`）リレーションを持つ。

この分析から、コントローラで何を用意し、モデルがどんなリレーションを持つべきか、具体的な実装の道筋が見えてきます。

## 3-2. 実装戦略：M-C-R-Vの順番で進める

現場のエンジニアは、闇雲にコードを書き始めることはありません。効率的な実装順序として、**M-C-R-V**という考え方があります。

1.  **M (Model)**: まずはアプリケーションのデータの核となるモデルと、DBの構造（マイグレーション）を定義する。
2.  **R (Route)**: 次に、どのURLがどのコントローラのアクションを呼び出すかを定義する（`routes/web.php`）。
3.  **C (Controller)**: モデルを使ってDBからデータを取得・加工し、ビューに渡すロジックを記述する。
4.  **V (View)**: 最後に、コントローラから渡されたデータを表示するBladeテンプレートを仕上げる。（今回は既に与えられているので、主に動作確認と微調整）

この順番で進めることで、データの流れが明確になり、手戻りが少なくスムーズに開発を進めることができます。

## 3-3. モデルとリレーションの定義 (M)

Chapter 1でマイグレーションは作成済みなので、次はEloquentモデルに必要なリレーションを定義します。

### 1. `Book`モデル (`app/Models/Book.php`)

```php
// app/Models/Book.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    // マスアサインメント可能な属性
    protected $fillable = [
        "user_id",
        "title",
        "author",
        "isbn",
        "published_date",
        "description",
        "image_url",
    ];

    // Userモデルとのリレーション (1対多の逆)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Reviewモデルとのリレーション (1対多)
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Genreモデルとのリレーション (多対多)
    public function genres()
    {
        return $this->belongsToMany(Genre::class);
    }

    // Userモデルとのリレーション (お気に入り - 多対多)
    public function favoritedByUsers()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
```

-   `$fillable`: `create`や`update`メソッドで一括代入を許可するカラムを指定します。セキュリティ上、非常に重要です。
-   `belongsTo`, `hasMany`, `belongsToMany`: Eloquentの強力な機能であるリレーションを定義します。これにより、`$book->user`や`$book->reviews`のように、関連するモデルのデータに簡単にアクセスできます。

### 2. `Genre`モデル (`app/Models/Genre.php`)

```php
// app/Models/Genre.php

// ... (namespaceなど)

class Genre extends Model
{
    use HasFactory;

    protected $fillable = ["name"];

    // Bookモデルとのリレーション (多対多)
    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
```

### 3. `User`モデル (`app/Models/User.php`)

`User`モデルにも、逆側のリレーションを定義しておきましょう。

```php
// app/Models/User.php

// ...

class User extends Authenticatable
{
    // ... (既存のコード)

    // Bookモデルとのリレーション (1対多)
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    // Reviewモデルとのリレーション (1対多)
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Bookモデルとのリレーション (お気に入り - 多対多)
    public function favoriteBooks()
    {
        return $this->belongsToMany(Book::class, 'favorites');
    }

    // Reviewモデルとのリレーション (いいね - 多対多)
    public function likedReviews()
    {
        return $this->belongsToMany(Review::class, 'review_likes');
    }
}
```

これでモデルの準備は完了です。

## 3-4. ルーティングの定義 (R)

次に、書籍管理機能に関するWebページ（URI）と、それに対応するコントローラのアクションを紐付けます。`routes/web.php`に以下のコードを追加・編集します。

```php
// routes/web.php

use App\Http\Controllers\BookController;

// ... (他のuse文)

// 誰でもアクセスできるルート
Route::get("/"), [BookController::class, "index"])->name("home");
Route::get("/books"), [BookController::class, "index"])->name("books.index");
Route::get("/books/{book}"), [BookController::class, "show"])->name("books.show");

// ログインしているユーザーのみアクセスできるルート
Route::middleware("auth")->group(function () {
    // ... (他のルート)

    // 書籍管理のルート
    Route::get("/books/create"), [BookController::class, "create"])->name("books.create");
    Route::post("/books"), [BookController::class, "store"])->name("books.store");
    Route::get("/books/{book}/edit"), [BookController::class, "edit"])->name("books.edit");
    Route::put("/books/{book}"), [BookController::class, "update"])->name("books.update");
    Route::delete("/books/{book}"), [BookController::class, "destroy"])->name("books.destroy");
});
```

**思考プロセス**: なぜ`resource`を使わないのか？
`Route::resource("books", BookController::class);`と書けば、これらのCRUDルートを一行で定義できます。しかし、今回は`show`ルートを認証ミドルウェアの外に出すなど、少し変則的な構成になっています。`resource`を使いつつ`except`や`only`で調整することも可能ですが、このように個別に定義した方が、どのルートがどのミドルウェアに属しているかが一目瞭然で、可読性が高いと判断しました。

-   **ルートモデルバインディング**: `{book}`のように波括弧で囲まれた部分は、ルートパラメータを表します。Laravelは、`Book`モデルのType-hintと組み合わせることで、URLに含まれるIDに一致する`Book`モデルのインスタンスを自動的に検索し、コントローラメソッドに注入してくれます。これを**暗黙のルートモデルバインディング**と呼び、コードを大幅に簡潔にしてくれます。

## 3-5. コントローラの作成 (C)

いよいよロジックの中心であるコントローラを作成します。

```bash
# --resourceオプションを付けると、CRUD用のメソッドが予め定義されたコントローラが作成される
sail artisan make:controller BookController --resource
```

生成された`app/Http/Controllers/BookController.php`を編集していきます。

### 1. `index`メソッド (一覧表示)

```php
// app/Http/Controllers/BookController.php

// ... (use文)

class BookController extends Controller
{
    public function index(): View
    {
        // with("genres"): N+1問題を回避するためのEager Loading
        // latest(): 作成日時の降順でソート
        // paginate(10): 10件ずつのページネーション
        $books = Book::with("genres")->latest()->paginate(10);

        // compact("books")は ["books" => $books] と同じ意味
        return view("books.index", compact("books"));
    }
```

-   **N+1問題とEager Loading**: `with("genres")`は非常に重要です。これがないと、一覧表示で各書籍のジャンルを表示するたびに、書籍の数だけ追加のDBクエリが発行されてしまいます（これがN+1問題です）。`with`を使うことで、最初に1回の追加クエリで全書籍の全ジャンルを取得（Eager Loading）し、パフォーマンスを大幅に改善できます。

### 2. `create`メソッド (登録画面表示)

```php
// ...
    public function create(): View
    {
        // 書籍登録フォームでジャンルを選択できるように、全ジャンルを取得してビューに渡す
        $genres = Genre::all();
        return view("books.create", compact("genres"));
    }
```

### 3. `store`メソッド (登録処理)

登録処理では、ユーザーからの入力を検証する必要があります。そのために**FormRequest**を使います。

```bash
# 書籍登録用のFormRequestを作成
sail artisan make:request StoreBookRequest
```

`app/Http/Requests/StoreBookRequest.php`を編集します。

```php
// app/Http/Requests/StoreBookRequest.php

public function authorize(): bool
{
    // 誰でも登録できるようにtrueを返す
    return true;
}

public function rules(): array
{
    return [
        "title" => ["required", "string", "max:255"],
        "author" => ["required", "string", "max:255"],
        "isbn" => ["required", "string", "size:13", "unique:books,isbn"],
        "published_date" => ["required", "date"],
        "description" => ["nullable", "string"],
        "image_url" => ["nullable", "url"],
        "genres" => ["required", "array"], // genresは配列であること
        "genres.*" => ["exists:genres,id"], // 配列の各要素がgenresテーブルに存在すること
    ];
}
```

-   **FormRequestのメリット**: バリデーションロジックをコントローラから分離することで、コントローラがスッキリし、再利用性も高まります。コントローラのメソッドで`StoreBookRequest`をType-hintするだけで、Laravelが自動でバリデーションを実行してくれます。失敗した場合は、自動的に直前のページにリダイレクトされます。

そして、`BookController`の`store`メソッドを実装します。

```php
// BookController.php

// use App\Http\Requests\StoreBookRequest; を追記

// ...
    public function store(StoreBookRequest $request): RedirectResponse
    {
        // バリデーション済みのデータを取得
        $validated = $request->validated();

        // genresを除いた書籍データを準備
        $bookData = collect($validated)->except("genres")->toArray();

        // ログインユーザーと関連付けて書籍を作成
        $book = $request->user()->books()->create($bookData);

        // 中間テーブルにジャンルを登録
        $book->genres()->attach($validated["genres"]);

        // 詳細ページにリダイレクトし、フラッシュメッセージを添える
        return redirect()->route("books.show", $book)->with("success", "書籍を登録しました。");
    }
```

### 4. `show`メソッド (詳細表示)

```php
// ...
    public function show(Book $book): View
    {
        // レビューとその投稿者、いいねしたユーザー、ジャンルをEager Loading
        $book->load(["reviews.user", "reviews.likedByUsers", "genres"]);
        return view("books.show", compact("book"));
    }
```

-   `load()`: 既に取得済みのモデルインスタンスに対して、追加でリレーションをEager Loadingするためのメソッドです。


### 5. `edit` / `update` メソッド (編集処理)

編集・更新処理では、「自分の登録した書籍しか編集・更新できない」という**認可**の仕組みが必要です。そのために**Policy**を使います。

```bash
# Bookモデルに対するPolicyを作成
sail artisan make:policy BookPolicy --model=Book
```

`app/Policies/BookPolicy.php`を編集します。

```php
// app/Policies/BookPolicy.php

// ...
class BookPolicy
{
    // ...

    // 書籍を更新できるか
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    // 書籍を削除できるか
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
```

次に、このPolicyをLaravelに登録します。`app/Providers/AuthServiceProvider.php`を編集します。

```php
// app/Providers/AuthServiceProvider.php

protected $policies = [
    Book::class => BookPolicy::class, // この行を追記
];
```

これで準備が整いました。`BookController`の`edit`と`update`メソッドを実装します。

```php
// BookController.php

// use App\Http\Requests\UpdateBookRequest; を追記

// ...
    public function edit(Book $book): View
    {
        // 認可チェック。許可されない場合は403エラーが自動で返される。
        $this->authorize("update", $book);

        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize("update", $book);

        // UpdateBookRequestでバリデーションを行う（StoreBookRequestとほぼ同じだが、ISBNのuniqueチェックが異なる）
        $book->update($request->validated());

        // ジャンルのリレーションを更新。syncは既存の関連を全て解除し、新しいものだけを登録する。
        $book->genres()->sync($request->genres);

        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }
```

`UpdateBookRequest`も同様に作成し、ISBNのユニークチェックで自分自身のISBNを除外するように設定します。

```php
// app/Http/Requests/UpdateBookRequest.php
use Illuminate\Validation\Rule;

// ...
public function rules(): array
{
    return [
        // ... 他はStoreBookRequestと同じ
        "isbn" => ["required", "string", "size:13", Rule::unique("books")->ignore($this->book)],
    ];
}
```

### 6. `destroy`メソッド (削除処理)

```php
// BookController.php

// ...
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize("delete", $book);

        $book->delete();

        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }
```

## まとめ

このChapterでは、書籍管理機能のCRUDをM-C-R-Vの順で実装しました。重要なポイントを振り返りましょう。

-   **実装戦略**: M-C-R-Vの順で実装することで、手戻りを減らし効率的に開発を進める。
-   **Bladeの読解**: UIから必要なデータやルート、認可のヒントを読み取る。
-   **Eager Loading**: `with()`を使い、N+1問題を未然に防ぐ。
-   **FormRequest**: バリデーションロジックをコントローラから分離し、コードをクリーンに保つ。
-   **Policy**: `authorize()`メソッドを使い、ビジネスロジック（誰が何をできるか）をコントローラから分離する。
-   **ルートモデルバインディング**: Laravelの便利な機能で、コードを簡潔にする。

これでアプリケーションの中核機能が完成しました。次のChapterでは、この書籍に紐づく「レビュー機能」を実装していきます。
