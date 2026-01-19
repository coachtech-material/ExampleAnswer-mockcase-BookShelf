# Chapter 6: 書籍管理機能 (CRUD) の実装

## 🎯 このセクションで学ぶこと

このセクションでは、Webアプリケーション開発の心臓部とも言える**CRUD（Create, Read, Update, Delete）**操作を、書籍管理機能を通じて実装します。単に機能を実装するだけでなく、エラーを未然に防ぐための開発手順や、コードの責務を分離するための設計思想についても深く学んでいきます。

- **トップダウンな開発アプローチ**: なぜ機能の詳細を実装する前に、まず全体の「骨格」となるルートとコントローラーを準備するのか、その重要性を理解します。
- **フォームリクエスト**: バリデーションロジックをコントローラーから分離し、再利用可能にする方法を学びます。
- **ポリシー（認可）**: 「誰が」「何を」できるかを制御する認可の仕組みを学びます。
- **段階的な実装**: まずは権限チェックなしで動作確認を行い、その後で権限チェックを追加するという、実践的な開発フローを体験します。

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

今回使用する配布済みのBladeファイル（ビュー）には、ヘッダーやボタンなどに、`route(\'books.create\')`のような形でリンク先のルート名が多数記述されています。もし、これらのルートが`routes/web.php`に定義されていない状態でページを表示しようとすると、Laravelは「`books.create`という名前のルートは見つかりません」という`RouteNotDefinedException`エラーを発生させます。

これを防ぐため、機能の中身が空であっても、先に**すべてのURL（ルート）と、その交通整理役であるコントローラーを定義**しておくのです。

### 6.1.2. 全コントローラーの作成

まずは、このアプリケーションで必要となるすべてのコントローラーを`artisan`コマンドで一括作成します。

```bash
# 書籍・ジャンル用 (Resourceコントローラー)
sail artisan make:controller BookController --resource
sail artisan make:controller GenreController --resource

# その他機能用
sail artisan make:controller ReviewController
sail artisan make:controller FavoriteController
sail artisan make:controller ReviewLikeController
sail artisan make:controller RankingController
```

### 6.1.3. ルート定義の作成 (`web.php`)

次に、`routes/web.php`に、アプリケーションで必要となるすべてのルートを記述します。

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

---

## 6.2. 書籍管理機能の実装 (CRUD)

ここからは、`BookController`に具体的な処理を実装していきます。完全手順書に従い、まずは**権限チェック（ポリシー）を実装せずに**一通りのCRUD機能が動作することを確認し、その後に権限チェックを追加するという、実践的な手順で進めます。

### 6.2.1. フォームリクエストの作成

まずは、書籍の登録・更新時のバリデーションルールを定義する「フォームリクエスト」を作成します。

```bash
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
```

### 6.2.2. フォームリクエストの実装

#### StoreBookRequest

`app/Http/Requests/StoreBookRequest.php`

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

#### 📖 コードリーディング：StoreBookRequest

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function authorize(): bool` | このリクエストの実行を許可するかどうかを決定します。 | `FormRequest`の機能の一つ。`true`を返すと、誰でもこのリクエストを送信できます。特定のユーザー（例：管理者）のみに許可したい場合は、ここにロジックを記述します。今回は認証済みユーザーなら誰でも書籍登録できるので`true`でOKです。 |
| `return true;` | 常にリクエストを許可します。 | 認可（権限チェック）は後ほど`Policy`で行うため、ここでは単純に`true`を返します。 |
| `public function rules(): array` | バリデーションルールを定義するメソッドです。 | ここに定義されたルールに違反したリクエストは、コントローラーのメソッドが実行される前に自動的に弾かれ、エラーメッセージと共に直前のページにリダイレクトされます。 |
| `'title' => ['required', 'string', 'max:255']` | `title`（書籍名）フィールドに対するルール。 | `required`（必須）、`string`（文字列）、`max:255`（最大255文字）を要求します。 |
| `'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn']` | `isbn`フィールドに対するルール。 | `unique:books,isbn`は`books`テーブルの`isbn`カラムに同じ値が存在しないことを保証します。これにより、同じ本が二重に登録されるのを防ぎます。 |
| `'genres' => ['required', 'array']` | `genres`（ジャンル）フィールドに対するルール。 | `required`（必須）、`array`（配列）であることを要求します。 |
| `'genres.*' => ['exists:genres,id']` | `genres`配列の各要素に対するルール。 | `genres`テーブルの`id`カラムに存在する値のみを受け付けます。これにより、存在しないジャンルIDが送信されるのを防ぎます。 `*`はワイルドカードで「配列のすべての要素」を意味します。 |

#### UpdateBookRequest

`app/Http/Requests/UpdateBookRequest.php`

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

#### 📖 コードリーディング：UpdateBookRequest

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `Rule::unique('books')->ignore($this->book)` | `books`テーブル内で一意である必要がありますが、**現在更新中の書籍自身のISBNはチェック対象から除外**します。 | `Rule::unique()`は高度な一意性ルールを定義するための機能です。`ignore($this->book)`がないと、自分自身のISBNを「重複している」と誤判定してしまい、ISBN以外の情報だけを更新することができなくなってしまいます。`$this->book`でルートから渡された`Book`モデルインスタンスにアクセスできます。 |

### 6.2.3. BookController.php の実装 (権限チェックなし)

`app/Http/Controllers/BookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with("genres")->latest()->paginate(10);
        return view("books.index", compact("books"));
    }

    public function search(Request $request): View
    {
        $query = $request->input("query");
        $books = Book::where("title", "like", "%{$query}%")
            ->orWhere("author", "like", "%{$query}%")
            ->with("genres")
            ->latest()
            ->paginate(10);

        return view("books.index", compact("books", "query"));
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
        // $this->authorize("update", $book);
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // $this->authorize("update", $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);

        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    public function destroy(Book $book): RedirectResponse
    {
        // $this->authorize("delete", $book);
        $book->delete();

        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }
}
```

#### 📖 コードリーディング：BookController

| メソッド | コード解説 |
|:---|:---|
| `index()` | `Book::with("genres")->latest()->paginate(10);` → `with("genres")`でN+1問題を回避しつつ、`latest()`で新しい順に並べ替え、`paginate(10)`で10件ずつページ分割して取得します。`compact("books")`でビューに`$books`変数を渡します。 |
| `search()` | `$request->input("query")`で検索クエリを取得し、`where("title", "like", "%{$query}%")`で書籍名と著者名の部分一致検索を行います。 |
| `create()` | `Genre::all()`で全てのジャンルを取得し、登録フォームのチェックボックスを表示するためにビューに渡します。 |
| `store()` | `collect($validated)->except('genres')`でバリデーション済みデータから`genres`キーを除外し、`$request->user()->books()->create(...)`でログインユーザーに紐付いた書籍を作成します。`$book->genres()->attach(...)`で中間テーブルにジャンル情報を保存します。 |
| `show()` | `$book->load(["reviews.user", "genres"]);`で、書籍情報に加えて関連するレビュー（とその投稿者）とジャンルの情報を後から読み込みます（遅延Eagerローディング）。 |
| `edit()` | `// $this->authorize("update", $book);` → **今はコメントアウト**。書籍の編集ページを表示します。`create()`と同様に全ジャンル情報もビューに渡します。 |
| `update()` | `// $this->authorize("update", $book);` → **今はコメントアウト**。`$book->update(...)`で書籍情報を更新し、`$book->genres()->sync(...)`で中間テーブルのジャンル情報を更新します（`sync`は一旦全て削除してから新しく登録し直す）。 |
| `destroy()` | `// $this->authorize("delete", $book);` → **今はコメントアウト**。`$book->delete()`で書籍を削除します。 |

> **✅ 動作確認**
> この時点で、書籍の登録、表示、編集、削除が一通り動作することを確認しましょう。ただし、まだ権限チェックがないため、**他のユーザーが登録した書籍も編集・削除できてしまう**状態です。

### 6.2.4. ポリシーの作成と実装

CRUDの基本的な動作が確認できたら、次に「認可」、つまり権限管理の仕組みを導入します。

```bash
sail artisan make:policy BookPolicy --model=Book
```

`app/Policies/BookPolicy.php`

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

#### 📖 コードリーディング：BookPolicy

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function update(User $user, Book $book): bool` | `update`アクション（更新）の権限をチェックするメソッド。 | Laravelは`$this->authorize('update', $book)`が呼ばれると、`BookPolicy`の`update`メソッドを自動的に探し出して実行します。第一引数には現在のログインユーザー、第二引数には対象のモデルが渡されます。 |
| `return $user->id === $book->user_id;` | **ログインしているユーザーのID**と、**その書籍を登録したユーザーのID**が一致する場合にのみ`true`（許可）を返します。 | これが認可の核心部分です。これにより、「自分の投稿は自分で編集・削除できるが、他人の投稿はできない」というルールを実現します。 |

### 6.2.5. ポリシーの登録

作成したポリシーを`AuthServiceProvider`に登録して、Laravelに認識させます。

`app/Providers/AuthServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Policies\BookPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

### 6.2.6. コントローラーへの権限チェック追加

最後に、`BookController`の`edit`, `update`, `destroy`メソッドのコメントアウトを解除し、権限チェックを有効にします。

#### 🧠 なぜ段階的に実装するのか？ - コメントアウト解除の意味

実務では、「まず機能を動かすこと」と「セキュリティを確保すること」を分けて考えることがよくあります。最初から全ての権限チェックを完璧に実装しようとすると、ロジックが複雑になり、どこでエラーが出ているのか分かりにくくなるからです。

1.  **ステップ1：機能実装（コメントアウト状態）**
    - まずは「書籍を編集・削除できるか」という基本機能そのものが正しく動作することに集中します。
    - この段階では、誰でも編集・削除できてしまう「穴」がある状態ですが、意図的にそうしています。

2.  **ステップ2：認可実装（コメントアウト解除）**
    - 基本機能が動くことを確認した後で、`$this->authorize()`のコメントアウトを外します。
    - これにより、コントローラーのメソッドが実行される**直前**に、先ほど定義した`BookPolicy`が呼び出され、「この操作、許可していいんだっけ？」というチェックが入ります。
    - ポリシーが`false`を返すと、Laravelは即座に処理を中断し、「403 This action is unauthorized.（この操作は許可されていません）」というエラーページを表示します。

このように段階を踏むことで、問題の切り分けが容易になり、効率的かつ安全に開発を進めることができるのです。

`app/Http/Controllers/BookController.php` (修正箇所のみ)

```php
    public function edit(Book $book): View
    {
        // 権限チェックを有効化！
        $this->authorize("update", $book);
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // 権限チェックを有効化！
        $this->authorize("update", $book);
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book->update($bookData);
        $book->genres()->sync($validated['genres']);
        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    public function destroy(Book $book): RedirectResponse
    {
        // 権限チェックを有効化！
        $this->authorize("delete", $book);
        $book->delete();
        return redirect()->route("home")->with("success", "書籍を削除しました。");
    }
```

> **✅ 動作確認**
> 再度、動作確認を行いましょう。今度は、自分が登録した書籍は編集・削除できますが、他のユーザーが登録した書籍の編集・削除ページにアクセスしようとすると「403 This action is unauthorized.」エラーが表示されるはずです。

これで、書籍管理機能（CRUD）の実装が完了しました。次のChapterでは、レビュー機能を実装していきます。
