# Chapter 06: アプリケーションの中核 - 書籍管理機能CRUDを実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、BookShelfアプリケーションの中核となる**書籍管理機能（CRUD）**を実装します。具体的には、書籍の一覧表示・登録・詳細表示・編集・削除という一連の操作を、コントローラー・FormRequest・Policyという3つの部品を組み合わせて構築します。「誰がリクエストを処理するのか（コントローラー）」「入力データは正しいか（バリデーション）」「この操作は許可されているか（認可）」という3つの関心事を明確に分離するLaravelの設計パターンを体得し、実務で通用するCRUD実装の基礎を身につけます。

## 1. はじめに 📖

### CRUDとは？Webアプリケーションの基本4動作

CRUD（クラッド）は、データの **Create（作成）・Read（読み取り）・Update（更新）・Delete（削除）** の頭文字を取ったものです。ほぼすべてのWebアプリケーションは、何らかのデータに対するCRUD操作で構成されています。SNSなら投稿のCRUD、ECサイトなら商品のCRUD——BookShelfでは「書籍」のCRUDが中核機能です。

このチャプターでは、単にCRUDを動かすだけでなく、Laravelが提供する仕組みを活用して**責務を分離**する方法を学びます。

- **コントローラー**: リクエストの受付とレスポンスの返却
- **FormRequest**: バリデーションロジックの分離
- **Policy**: 認可ロジック（「この人はこの操作をしてよいか？」）の分離

これらを組み合わせることで、コントローラーのコードはスリムに保たれ、各部品が独立してテスト可能になります。これは実務で非常に重要な設計原則です。

### このチャプターで扱う範囲

このチャプターでは書籍CRUDの**基本機能**を実装します。書籍一覧ページの検索・フィルタ・ソート機能はChapter 15で、ISBN検索機能はChapter 16で追加します。

## 2. 要件の確認 📋

書籍管理機能で実装する操作と、対応するURL・コントローラーメソッドを整理します。

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 | 認可 |
|:---|:---|:---|:---|:---|:---|
| 書籍一覧画面表示 | GET | `/` または `/books` | BookController@index | 不要 | — |
| 書籍登録画面表示 | GET | `/books/create` | BookController@create | 必要 | — |
| 書籍登録処理 | POST | `/books` | BookController@store | 必要 | — |
| 書籍詳細画面表示 | GET | `/books/{book}` | BookController@show | 不要 | — |
| 書籍編集画面表示 | GET | `/books/{book}/edit` | BookController@edit | 必要 | BookPolicy@update（作成者のみ） |
| 書籍更新処理 | PUT | `/books/{book}` | BookController@update | 必要 | BookPolicy@update（作成者のみ） |
| 書籍削除処理 | DELETE | `/books/{book}` | BookController@destroy | 必要 | BookPolicy@delete（作成者のみ） |

### バリデーションルール

| 項目 | ルール | エラーメッセージ |
|:---|:---|:---|
| title | required / string / max:255 | タイトルは必須です。 |
| author | required / string / max:255 | 著者名は必須です。 |
| isbn | required / string / size:13 / unique | ISBNは必須です。 / ISBNは13桁で入力してください。 |
| published_date | nullable / date | 出版日は有効な日付形式で入力してください。 |
| description | nullable / string | — |
| image_url | nullable / url / max:255 | 画像URLは有効なURL形式で入力してください。 / 画像URLは255文字以内で入力してください。 |
| genres | required / array | ジャンルは1つ以上選択してください。 |
| genres.* | exists:genres,id | 選択されたジャンルは存在しません。 |

### 認可ルール（BookPolicy）

| 操作 | 条件 | 違反時 |
|:---|:---|:---|
| 書籍編集（update） | `$user->id === $book->user_id`（作成者本人のみ） | 403 Forbidden |
| 書籍削除（delete） | `$user->id === $book->user_id`（作成者本人のみ） | 403 Forbidden |

## 3. 先輩エンジニアの思考プロセス 💭

書籍CRUDの実装にあたって、経験豊富なエンジニアがどのような設計判断をするのかを見ていきましょう。

### Point 1: 準備フェーズで全体の骨組みを先に作る

実務では、いきなりコードを書き始めるのではなく、まずコントローラー・FormRequest・Policy・ルート定義を**空の状態で全て作成**してから、中身を実装していきます。これにより `RouteNotDefinedException` のようなエラーを防ぎ、全体の構造を見通しながら開発を進められます。

### Point 2: バリデーションはコントローラーに書かない

```php
// ❌ コントローラーにバリデーションを直接書く
public function store(Request $request) {
    $request->validate(['title' => 'required|max:255', ...]);
}

// ✅ FormRequestクラスに分離する
public function store(StoreBookRequest $request) {
    // バリデーションは自動実行済み
}
```

`FormRequest` をメソッドの引数に指定するだけで、Laravelが自動でバリデーションを実行します。バリデーションに失敗すれば、メソッド本体は実行されずにフォームにリダイレクトされます。これにより、コントローラーには「バリデーション通過後の処理」だけを書けばよくなります。

### Point 3: 認可はPolicyクラスで一元管理する

「この書籍を編集/削除できるのは作成者だけ」という認可ルールを、コントローラー内で `if ($user->id !== $book->user_id)` のように書くこともできますが、Policyクラスに分離することで以下のメリットがあります:

- コントローラーでは `$this->authorize('update', $book)` の一行で済む
- 同じ認可ルールをBlade内（`@can('update', $book)`）でも再利用できる
- 認可ロジックの変更がPolicyの1箇所で完結する

### Point 4: ジャンル紐付けは `attach()` / `sync()` を使い分ける

書籍とジャンルは多対多リレーションです。中間テーブル（`book_genre`）を直接操作する代わりに:

- **新規登録時**: `$book->genres()->attach($genreIds)` — 指定したジャンルを追加
- **更新時**: `$book->genres()->sync($genreIds)` — 既存の紐付けを完全に置き換え

`sync()` は「指定されたIDだけが紐付いている状態」にしてくれるため、更新時のジャンル変更に最適です。

### Point 5: `collect()` でバリデーション済みデータを柔軟に加工する

バリデーション済みデータから `genres` を分離する際、Collectionの `except()` メソッドを使うと、配列操作よりも宣言的で読みやすいコードになります:

```php
$bookData = collect($validated)->except('genres')->toArray();
```

## 4. 実装 🚀

### 4.1. ルート定義とコントローラーの一括準備

機能の詳細な実装に入る前に、まずアプリケーション全体の「骨格」となるルートとコントローラーを準備します。

#### なぜ先にルートを定義するのか？

配布済みのBladeファイル（ビュー）には、ヘッダーやボタンに `route('books.create')` や `route('favorites.toggle')` のような形でルート名が多数記述されています。これらのルートが `routes/web.php` に定義されていない状態でページを表示すると、`RouteNotDefinedException` エラーが発生します。

これを防ぐため、機能の中身が空であっても、先に**すべてのURL（ルート）と、対応するコントローラーを定義**しておきます。

#### 全コントローラーの作成

このアプリケーションで必要となるすべてのコントローラーを一括作成します。

```bash
sail artisan make:controller BookController
sail artisan make:controller ReviewController
sail artisan make:controller FavoriteController
sail artisan make:controller ReviewLikeController
sail artisan make:controller GenreController
sail artisan make:controller RankingController
```

#### ルート定義の作成 (`routes/web.php`)

`routes/web.php` に、アプリケーションで必要となるすべてのルートを記述します。この時点ではコントローラーのメソッドは空ですが、ルート名が定義されるためBladeのエラーが解消されます。

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

// トップページ（書籍一覧）
Route::get('/', [BookController::class, 'index'])->name('home');

// 書籍関連（認証不要）
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// ランキング（認証不要）
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// 認証が必要なルート
Route::middleware('auth')->group(function () {
    // ジャンル管理
    Route::resource('genres', GenreController::class);

    // 書籍管理（createは{book}より先に定義）
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // レビュー管理
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // いいね
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
});

// 書籍詳細（認証不要、{book}パラメータを含むため最後に定義）
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
```

> **注意:** この時点では ISBN 検索ルート（Step 16）やマイ読書レポートルート（Step 17）は未定義です。これらは該当チャプターで追加します。ただし、提供 Blade（navigation.blade.php）がこれらのルートを参照している場合は、Step 15〜17 を実装するまで一部のページでエラーが出る可能性があります。

### 4.2. Policyの作成

次に、認可ロジックを担うPolicyクラスを作成します。

```bash
sail artisan make:policy BookPolicy --model=Book
sail artisan make:policy ReviewPolicy --model=Review
```

#### `app/Policies/BookPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function update(User $user, Book $book)
    {
        return $user->id === $book->user_id;
    }

    public function delete(User $user, Book $book)
    {
        return $user->id === $book->user_id;
    }
}
```

#### `app/Policies/ReviewPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $review)
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review)
    {
        return $user->id === $review->user_id;
    }
}
```

#### `app/Providers/AuthServiceProvider.php`

作成したPolicyをLaravelに登録します:

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Review;
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    public function boot()
    {
        //
    }
}
```

### 4.3. FormRequestの作成

#### `app/Http/Requests/StoreBookRequest.php`

```bash
sail artisan make:request StoreBookRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array'],
            'genres.*' => ['exists:genres,id'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは配列で入力してください。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
```

#### `app/Http/Requests/UpdateBookRequest.php`

```bash
sail artisan make:request UpdateBookRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->book)],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array'],
            'genres.*' => ['exists:genres,id'],
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは配列で入力してください。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
```

> **ポイント:** `UpdateBookRequest` の `isbn` ルールでは `Rule::unique('books')->ignore($this->book)` を使い、自分自身のISBNを除外しています。これにより、ISBNを変更せずに他のフィールドだけを更新できます。

### 4.4. BookControllerの実装

`BookController` は Step 4.1 で既に作成済みです。以下のように中身を実装します。

#### `app/Http/Controllers/BookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::with('genres')->latest()->paginate(10);
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    public function create()
    {
        $genres = Genre::orderBy('name')->get();

        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    public function show(Book $book)
    {
        $book->load(['reviews.user', 'reviews.likedByUsers', 'genres']);

        return view('books.show', compact('book'));
    }

    public function edit(Book $book)
    {
        $this->authorize('update', $book);
        $genres = Genre::orderBy('name')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorize('update', $book);
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book->update($bookData);
        $book->genres()->sync($validated['genres']);

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

## 5. コードの詳細解説 🔍

### BookController — 各メソッドの解説

#### `index()` — 書籍一覧表示

```php
$books = Book::with('genres')->latest()->paginate(10);
```

- **`Book::with('genres')`**: Eager Loading で書籍取得時にジャンルも同時取得。これがないと、Blade内で `$book->genres` にアクセスするたびにSQLが発行される**N+1問題**が発生します
- **`latest()`**: `orderBy('created_at', 'desc')` のショートカット。最新の書籍が先頭に
- **`paginate(10)`**: 10件ごとにページ分割。Bladeで `{{ $books->links() }}` と書くだけでページネーションリンクが表示されます

#### `store()` — 書籍登録

```php
$validated = $request->validated();
$bookData = collect($validated)->except('genres')->toArray();
$book = $request->user()->books()->create($bookData);
$book->genres()->attach($validated['genres']);
```

1. **`$request->validated()`**: バリデーション済みデータだけを取得。未定義のフィールドは含まれない
2. **`collect($validated)->except('genres')->toArray()`**: `genres` は中間テーブルに保存するため、書籍データから分離
3. **`$request->user()->books()->create($bookData)`**: ログインユーザーのリレーション経由で作成。`user_id` が自動設定される
4. **`$book->genres()->attach($validated['genres'])`**: 中間テーブル `book_genre` にジャンル紐付けを作成
5. **`->with('success', '書籍を登録しました。')`**: セッションにフラッシュメッセージを保存。Bladeで表示可能

#### `edit()` / `update()` — 書籍編集

```php
$this->authorize('update', $book);
```

- **`$this->authorize()`**: BookPolicyの `update` メソッドを呼び出し、認可チェックを実行。作成者以外がアクセスすると **403 Forbidden** が返されます
- `edit()` と `update()` の両方で認可チェックを行うのは、フォーム表示時と送信時の2段階で防御するためです

```php
$book->genres()->sync($validated['genres']);
```

- **`sync()`**: `attach()` と違い、既存の紐付けを完全に置き換えます。例えば、元々「小説・ビジネス」だったジャンルを「技術書」だけに変更すると、`sync()` が「小説・ビジネスを削除 → 技術書を追加」を一度に処理します

#### `destroy()` — 書籍削除

```php
$book->delete();
```

- マイグレーションで `cascadeOnDelete()` を設定しているため、書籍を削除すると関連するレビュー・お気に入り・ジャンル紐付けも自動的に削除されます

### StoreBookRequest / UpdateBookRequest — バリデーション

#### `authorize()` メソッド

```php
public function authorize()
{
    return true;
}
```

- `true` を返すことで、認証済みユーザーなら誰でもリクエスト可能。認可の判断はPolicyに委ねています
- もし `false` を返すと、403 Forbidden が返されます

#### `rules()` メソッド — isbn の unique ルール

```php
// StoreBookRequest: 新規登録は全レコードを対象にチェック
'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],

// UpdateBookRequest: 自分自身を除外してチェック
'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->book)],
```

`$this->book` はルートモデルバインディングにより自動的に解決されます。URLの `{book}` パラメータから `Book` モデルインスタンスが取得され、`ignore()` でそのIDを除外します。

#### `messages()` メソッド

FormRequest に `messages()` を定義すると、Laravelのデフォルトメッセージの代わりにカスタムメッセージが使われます。キーは `フィールド名.ルール名` の形式です。

### BookPolicy — 認可ロジック

```php
public function update(User $user, Book $book)
{
    return $user->id === $book->user_id;
}
```

- 第一引数 `$user` には**現在ログイン中のユーザー**が自動的に渡されます
- 第二引数 `$book` には、コントローラーで `$this->authorize('update', $book)` と呼んだ際の `$book` が渡されます
- `true` を返せば操作許可、`false` を返せば 403 Forbidden

## 6. この実装にたどり着くための調べ方 🧐

### Step 1: 公式ドキュメントを読みやすくまとめる（全体像の把握）

**プロンプト例**
```
以下はLaravelのコントローラーに関する公式ドキュメントの一部です。
これを「実装できるように」分かりやすくまとめてください。

出力してほしい内容：
- 重要ポイント（10行以内）
- 用語の説明（重要なものだけ）
- できること / できないこと（境界をはっきり）
- よくある落とし穴（回避策つき）
- 最小で動かすための手順（コードはまだ不要）

--- ここから ---
（ここにLaravelのControllerに関する公式ドキュメントを貼り付ける）
--- ここまで ---
```

### Step 2: 「なぜそうなる？」をはっきりさせる（理解を固める）

**プロンプト例**
```
LaravelのCRUD実装について、私の理解はこうです：
「FormRequestでバリデーションを分離し、Policyで認可を分離し、
コントローラーはリクエストの受付とレスポンスの返却に専念する。
多対多リレーションの紐付けは attach/sync で行う。」

お願い：
1) 正しいかチェックして、間違いがあれば「反例」で教えてください
2) 仕組みを「入力→中で起きること→出力」で説明してください
3) attach() と sync() の違いを具体例で説明してください
4) よくある勘違いを3つ教えてください
5) 理解チェック問題を3問ください（答えつき）
```

### Step 3: 実装に落とす

**プロンプト例**
```
目的は、書籍のCRUD機能を実装することです。
書籍にはジャンル（多対多）が紐付き、編集・削除は作成者のみ可能です。

前提知識はLaravelの基本的なモデルとルーティングの知識がある程度です。
バリデーションはFormRequest、認可はPolicyで実装したいです。

次の順番で出力してください：
A. 実装の手順・方針（なぜそのやり方か + 各手順の完了条件）
B. 関連技術の解説（FormRequest/Policy/attach/syncの使い分け）
C. 実装例（最小で動くCRUDから、Policy認可を追加した拡張例へ）
D. コードの解説（重要な部分の「何をしてるか」「なぜそう書くか」）
```

### Step 4: 設計レビュー

**プロンプト例**
```
以下のBookControllerの設計をレビューしてください。

- 目的：書籍CRUD（ジャンル多対多紐付け + 作成者のみ編集削除）
- 制約：Laravel 10, PHP 8.2, SSR（Blade使用）
- 設計案：
（ここにBookController.phpのコードを貼り付ける）
- 不安な点：genres の attach/sync は正しく使い分けられているか

見てほしい観点：
- 正しく動くか（抜け漏れ）
- 変更しやすいか（拡張/分離）
- パフォーマンス（N+1問題など）
- セキュリティ（認可の漏れ）

出力：
- 指摘を「重要度：高/中/低」で出す
- 各指摘に「理由」「影響」「直し方」をつける
```

## 7. 動作確認 ✅

以下の項目を確認してください。

1. **書籍一覧ページ** (`/books`)
   - ページが正常に表示される（200レスポンス）
   - 書籍にジャンルバッジが表示される
   - ページネーションが動作する（10件を超える場合）

2. **書籍登録** (`/books/create`)
   - 未ログインでアクセスするとログイン画面にリダイレクトされる
   - ログイン後、全ジャンルがチェックボックスで表示される
   - 全項目入力して登録すると、書籍詳細画面にリダイレクトされ「書籍を登録しました。」が表示される
   - `book_genre` テーブルにジャンル紐付けが作成されている（phpMyAdminで確認）

3. **バリデーション**
   - タイトル空欄で登録すると「タイトルは必須です。」が表示される
   - ISBNを12桁で入力すると「ISBNは13桁で入力してください。」が表示される
   - ジャンル未選択で登録すると「ジャンルは1つ以上選択してください。」が表示される

4. **書籍編集** (`/books/{id}/edit`)
   - 作成者本人がアクセスすると編集フォームが表示される
   - 他のユーザーがアクセスすると403エラーが表示される
   - ジャンルを変更して更新すると、`book_genre` テーブルの紐付けが正しく更新される

5. **書籍削除**
   - 作成者本人が削除すると書籍一覧にリダイレクトされ「書籍を削除しました。」が表示される
   - 他のユーザーは削除できない（403エラー）
   - 関連するレビュー・お気に入り・ジャンル紐付けもカスケード削除される

## 8. まとめ ✨

このチャプターでは、BookShelfアプリケーションの中核である書籍管理機能CRUDを実装しました。

- **FormRequestによるバリデーション分離**: `StoreBookRequest` / `UpdateBookRequest` にバリデーションルールとエラーメッセージを集約し、コントローラーをスリムに保ちました
- **Policyによる認可の一元管理**: `BookPolicy` で「作成者のみ編集・削除可能」というルールを定義し、コントローラーからは `$this->authorize()` の一行で呼び出せるようにしました
- **多対多リレーションの操作**: `attach()`（新規登録時）と `sync()`（更新時）を使い分け、中間テーブルの操作をEloquentの宣言的なメソッドで実現しました
- **Eager Loadingによるパフォーマンス対策**: `with('genres')` で N+1 問題を防止しました
- **Collection メソッドの活用**: `collect($validated)->except('genres')` でバリデーション済みデータを柔軟に加工しました

次のChapter 07では、書籍に対する**レビュー機能**を実装します。ネストしたリソース（`/books/{book}/reviews`）の設計パターンと、ReviewPolicyによる認可の実装を学びます。
