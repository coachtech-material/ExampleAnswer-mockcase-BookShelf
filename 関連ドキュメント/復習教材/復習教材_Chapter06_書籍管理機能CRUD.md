# Chapter 6: 書籍管理機能 (CRUD) の実装

## 🎯 このセクションで学ぶこと

このセクションでは、Webアプリケーション開発の心臓部とも言える**CRUD（Create, Read, Update, Delete）**操作を、書籍管理機能を通じて実装します。単に機能を実装するだけでなく、エラーを未然に防ぐための開発手順や、コードの責務を分離するための設計思想についても深く学んでいきます。

- **トップダウンな開発アプローチ**: なぜ機能の詳細を実装する前に、まず全体の「骨格」となるルートとコントローラーを準備するのか、その重要性を理解します。
- **フォームリクエスト**: バリデーションロジックをコントローラーから分離し、再利用可能にする方法を学びます。
- **ポリシー（認可）**: 「誰が」「何を」できるかを制御する認可の仕組みを学びます。
- **段階的な実装**: まずは権限チェックなしで動作確認を行い、その後で権限チェックを追加するという、実践的な開発フローを体験します。
- **型定義の活用**: コントローラーのメソッドやフォームリクエストに引数と戻り値の型を追加し、コードの可読性と堅牢性を向上させます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜ最初に「骨格」を作るのか？

多くの初学者は、一つの機能（例えば「書籍の登録」）を完成させてから次の機能に取り掛かろうとします。しかし、経験豊富なエンジニアは、まずアプリケーション全体の「骨格」となる部分から組み立て始めます。これは、家を建てる際に、内装工事の前にまず土台と柱を組み上げるのと同じです。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 配布されたビューには`route()`ヘルパーが多数あり、ルートが未定義だとエラーになる | **最初にすべてのルートとコントローラーを準備**する | どのページにアクセスしても`RouteNotDefined`エラーが発生しない状態を先に作ることで、ビューの表示確認をしながらスムーズに開発を進められる。 |
| 機能ごとに行き当たりばったりで実装すると、全体像が見えなくなる | **トップダウン**で実装を進める | まず全体のURL設計（ルート）と司令塔（コントローラー）を決め、その後に各機能の詳細（ロジック）を肉付けしていくことで、一貫性のある設計を維持できる。 |
| どこに何を書くべきか迷う | **責務の分離**を意識する | 「URLの交通整理はルート」「リクエストの検証はフォームリクエスト」「権限の確認はポリシー」「具体的な処理はコントローラー」というように、役割分担を明確にすることで、見通しが良くメンテナンスしやすいコードになる。 |
| メソッドが何を返し、何を受け取るか不明確 | **引数と戻り値に型定義**を追加する | `: View`や`: RedirectResponse`といった型を明記することで、各アクションの役割（ページを表示するのか、リダイレクトするのか）が一目瞭然になる。 |

このChapterでは、まずアプリケーション全体の「骨格」を固め、その上で書籍管理機能という「最初の部屋」の内装を仕上げていく、という流れで開発を進めます。

---

## 6.1. ルート定義とコントローラーの準備

機能ごとの詳細な実装に入る前に、まずアプリケーション全体の「骨格」となるルートとコントローラーを準備します。これにより、開発中の`RouteNotDefined`エラーを防ぎ、スムーズに開発を進めることができます。

### 6.1.1. なぜ先にルートを定義するのか？

今回使用する配布済みのBladeファイル（ビュー）には、ヘッダーやボタンなどに、`route('books.create')`のような形でリンク先のルート名が多数記述されています。もし、これらのルートが`routes/web.php`に定義されていない状態でページを表示しようとすると、Laravelは「`books.create`という名前のルートは見つかりません」という`RouteNotDefinedException`エラーを発生させます。

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
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

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
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    // CSVエクスポート機能
    Route::get('/books/export/csv', [BookController::class, 'exportCsv'])->name('books.export');
    // ISBN検索機能
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.searchByIsbn');
    // マイ読書レポート機能
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    // レビューいいね機能
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});

// --- 3. 汎用的な公開ルート (最後) ---
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');

// --- 認証関連ルート ---
require __DIR__.'/auth.php';
```

> **✅ 動作確認**
> この時点で、アプリケーションのどのページにアクセスしても、`RouteNotDefinedException`エラーではなく、空のページまたはコントローラーで定義した内容が表示されることを確認しましょう。

---

## 6.2. 書籍管理機能の実装

全体の骨格ができたので、いよいよ書籍管理機能（CRUD）を実装していきます。

### 6.2.1. フォームリクエストの作成

コントローラーに処理を書く前に、まずバリデーション（入力値の検証）ルールを定義する`FormRequest`クラスを作成します。これにより、バリデーションロジックをコントローラーから分離し、コードの見通しを良くします。

```bash
sail artisan make:request StoreBookRequest
sail artisan make:request UpdateBookRequest
```

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
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'description' => 'required|string',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }
}
```

`app/Http/Requests/UpdateBookRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'description' => 'required|string',
            'genres' => 'required|array',
            'genres.*' => 'exists:genres,id',
        ];
    }
}
```

#### 📖 コードリーディング：FormRequest

| メソッド / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function authorize(): bool` | このリクエストを送信する権限があるかどうかを判定します。`: bool`は戻り値が真偽値であることを示します。ここでは一旦`true`を返し、誰でもリクエストを送信できるようにしておきます。権限の制御は後ほどポリシーで行います。 |
| `public function rules(): array` | バリデーションルールを配列形式で返します。`: array`は戻り値が配列であることを示します。 |
| `'genres' => 'required|array'` | `genres`という名前の入力が必須であり、かつ配列でなければならないことを示します。 |
| `'genres.*' => 'exists:genres,id'` | `genres`配列の**各要素**（`*`はワイルドカード）が、`genres`テーブルの`id`カラムに実際に存在するかどうかをチェックします。これにより、存在しないジャンルIDが送信されるのを防ぎます。 |

### 6.2.2. BookControllerの実装

次に、`BookController`にCRUDの各処理を実装します。この時点では、**権限チェック（認可）のロジックはコメントアウト**しておきます。これは、まず機能そのものが正しく動作するかを確認し、その後に権限の問題を切り分けて考えるためです。

`app/Http/Controllers/BookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(): View
    {
        $books = Book::with('user')->latest()->paginate(10);
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
        // $this->authorize('update', $book);
        $genres = Genre::all();
        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // $this->authorize('update', $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);
        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    public function destroy(Book $book): RedirectResponse
    {
        // $this->authorize('delete', $book);
        $book->delete();
        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
```

#### 📖 コードリーディング：BookController

| メソッド | コード解説 |
|:---|:---|
| `public function index(): View` | `: View`は、このメソッドが`View`オブジェクト（レンダリングされたHTMLページ）を返すことを示します。書籍一覧ページを表示する役割が一目でわかります。 |
| `public function create(): View` | 書籍登録フォームを表示するための`View`オブジェクトを返します。 |
| `public function store(StoreBookRequest $request): RedirectResponse` | 引数の`StoreBookRequest`でバリデーション済みのリクエストを受け取ります。`: RedirectResponse`は、処理後に別のページへリダイレクトすることを示します。 |
| `public function show(Book $book): View` | 引数の`Book $book`は、URLの`{book}`部分から自動的に該当する`Book`モデルを見つけて注入する「ルートモデルバインディング」機能です。`: View`で書籍詳細ページを返すことがわかります。 |
| `public function edit(Book $book): View` | `show`メソッドと同様に`Book`モデルを受け取り、編集フォームの`View`を返します。 |
| `public function update(UpdateBookRequest $request, Book $book): RedirectResponse` | `UpdateBookRequest`でバリデーションを行い、対象の`Book`モデルを更新した後、詳細ページへリダイレクトします。 |
| `public function destroy(Book $book): RedirectResponse` | 対象の`Book`モデルを削除した後、書籍一覧ページへリダイレクトします。 |

> **✅ 動作確認**
> この時点で、書籍の登録、表示、編集、削除が一通り動作することを確認しましょう。ただし、まだ権限チェックがないため、**他のユーザーが登録した書籍も編集・削除できてしまう**状態です。

### 6.2.3. ポリシーの作成と実装

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
| `public function update(User $user, Book $book): bool` | `update`アクションに対する権限をチェックします。戻り値の型`: bool`は、このメソッドが必ず真偽値を返すことを保証します。 | Laravelは`$this->authorize('update', $book)`が呼ばれると、自動的にこのメソッドを探し出して実行します。第一引数には現在ログインしている`User`モデルが、第二引数には対象となる`Book`モデルが渡されます。 |
| `return $user->id === $book->user_id;` | **ログインしているユーザーのID**と、 **書籍が持つ`user_id`** が一致するかどうかを判定します。 | `true`を返せば許可、`false`を返せば拒否（403 Forbiddenエラー）となります。これにより、「自分の投稿は自分で編集・削除できるが、他人の投稿はできない」という認可ロジックを実現しています。 |

### 6.2.4. ポリシーの登録

作成したポリシーをLaravelに認識させるため、`app/Providers/AuthServiceProvider.php`に登録します。

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
        //
    }
}
```

### 6.2.5. コントローラーへの権限チェック追加

すべての準備が整ったので、`BookController`の`edit`, `update`, `destroy`メソッドのコメントアウトを解除して、ポリシーによる権限チェックを有効にします。

`app/Http/Controllers/BookController.php` (変更箇所のみ)

```php
    public function edit(Book $book): View
    {
        $this->authorize("update", $book);
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize("update", $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);
        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize("delete", $book);
        $book->delete();
        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }
```

#### 📖 コードリーディング：段階的実装の意図

なぜ一度コメントアウトしてから、再度有効にするという一見面倒な手順を踏んだのでしょうか？これは、**問題の切り分け**を容易にし、開発を効率的に進めるための実践的なテクニックです。

- **ステップ1：機能実装（コメントアウト状態）**
  - **目的**: まずは「書籍の更新」という**基本機能が正しく動作するか**に集中します。
  - **状態**: この段階では、誰でも編集・削除ができてしまう「穴」がある状態です。しかし、バリデーションエラーやデータベースエラーなど、機能そのものの問題を発見しやすくなります。

- **ステップ2：認可実装（コメントアウト解除）**
  - **目的**: 基本機能が動作することを確認した後で、「**権限を持つユーザーだけが操作できるか**」という認可ロジックを追加します。
  - **状態**: `$this->authorize()`のコメントアウトを外すと、`BookPolicy`が有効になります。ポリシーが`false`を返した場合、Laravelは自動的に403 Forbiddenエラーページを表示します。もしここで問題が発生すれば、原因はポリシーの設定にあるとすぐに特定できます。

もし最初から全てを実装してしまうと、エラーが発生した際に「機能自体のバグ」なのか「権限設定のミス」なのかを判断するのが難しくなります。このように段階を踏むことで、一つ一つの要素を確実にクリアしながら、安全に開発を進めることができるのです。

> **✅ 最終動作確認**
> - 自分が登録した書籍の編集・削除ができること。
> - 他人が登録した書籍の編集・削除ページにアクセスしようとすると「403 | THIS ACTION IS UNAUTHORIZED.」と表示されること。
> 
> 上記を確認できれば、書籍管理機能の実装は完了です。
