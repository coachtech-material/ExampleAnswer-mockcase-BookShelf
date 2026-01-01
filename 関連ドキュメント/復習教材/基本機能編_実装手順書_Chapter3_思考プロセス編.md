# 実装手順書 Chapter 3: 書籍管理機能の要件詰め・設計・実装編

## はじめに

認証機能が整い、いよいよアプリケーションのコアとなる「書籍管理機能」の実装に入ります。この機能は、Webアプリケーション開発の基本である**CRUD**（Create, Read, Update, Delete）のすべてを含んでいます。

このChapterでは、Chapter 1で学んだ「ヒアリングシート」の考え方を元に、PM（コーチ）と仕様を確定させ、LaravelのMVCモデルに沿って実装を進めていくプロセスを学びます。

---

## Section 1: 書籍一覧・詳細表示（Read）の実装

まずは、データを「表示」するRead機能から実装します。なぜなら、登録（Create）や更新（Update）が正しく行われたかを確認するために、表示機能が不可欠だからです。

### 思考プロセス：実装の順序を考える

LaravelのMVCモデルを意識して、実装の順序を考えます。

1.  **Model & Migration（データ構造の定義）**: まずは書籍データを保存するための「器」であるデータベーステーブルと、それに対応するEloquentモデルが必要です。`books`テーブルの設計から始めます。
2.  **Controller（処理の記述）**: 次に、データベースから書籍情報を取得し、ビューに渡すためのロジックを記述するコントローラを作成します。
3.  **Routing（URLと処理の紐付け）**: 作成したコントローラのアクションと、特定のURL（例: `/` や `/books/{id}`）を紐付けます。
4.  **View（画面の表示）**: 最後に、コントローラから渡されたデータを表示するためのBladeテンプレートを準備します。

この「**M → C → R → V**」という流れは、Laravel開発の基本的なリズムです。

### 1. ModelとMigrationの作成

```bash
# Bookモデルと、それに対応するマイグレーションファイル、ファクトリ、シーダー、コントローラをまとめて作成
sail artisan make:model Book -mfs -c -r
```

> **【エンジニアの思考】**
> `-mfs -c -r` は魔法の呪文のように見えますが、それぞれ以下の意味があります。
> - `-m`: `--migration` マイグレーションファイルを作成
> - `-f`: `--factory` ファクトリを作成（テストデータ生成用）
> - `-s`: `--seeder` シーダーを作成（初期データ投入用）
> - `-c`: `--controller` コントローラを作成
> - `-r`: `--resource` コントローラにCRUDの基本アクションを自動生成
> 
> これらを使いこなすことで、手作業によるファイル作成の手間を大幅に削減できます。

次に、生成されたマイグレーションファイル (`database/migrations/xxxx_create_books_table.php`) を編集し、ヒアリングシートで固めた仕様を元にカラムを定義します。

```php
// database/migrations/xxxx_create_books_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 登録者
            $table->string('title'); // タイトル
            $table->string('author'); // 著者
            $table->string('isbn', 13)->unique(); // ISBN-13（ユニーク）
            $table->date('published_at'); // 出版日
            $table->text('overview')->nullable(); // 概要（任意）
            $table->string('image_url')->nullable(); // 画像URL（任意）
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
```

> **【コードリーディング】**
> - `$table->id()`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`型の`id`カラムを作成します。
> - `$table->foreignId('user_id')->constrained()->onDelete('cascade')`: `user_id`カラムを作成し、`users`テーブルの`id`への外部キー制約を設定します。`onDelete('cascade')`は、ユーザーが削除された場合、そのユーザーが登録した書籍も自動的に削除されることを意味します。
> - `$table->string('isbn', 13)->unique()`: 最大13文字の`isbn`カラムを作成し、ユニーク制約を設定します。
> - `$table->text('overview')->nullable()`: `TEXT`型の`overview`カラムを作成し、NULL許可にします。
> - `$table->timestamps()`: `created_at`と`updated_at`カラムを自動的に作成します。

### 2. Controllerの編集とRoutingの設定

`BookController`の`index`（一覧）と`show`（詳細）メソッドを編集します。

```php
// app/Http/Controllers/BookController.php

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * 書籍一覧を表示
     */
    public function index()
    {
        // booksテーブルの全レコードを取得し、10件ずつのページネーションを適用
        $books = Book::paginate(10);
        // books.indexビューに$booksを渡して表示
        return view('books.index', compact('books'));
    }

    /**
     * 書籍詳細を表示
     */
    public function show(Book $book)
    {
        // ルートモデルバインディングにより、IDに一致するBookインスタンスが自動的に渡される
        return view('books.show', compact('book'));
    }
    
    // ... 他のアクションは一旦そのまま ...
}
```

> **【コードリーディング】**
> - `Book::paginate(10)`: Eloquentの`paginate`メソッドは、データを指定件数（ここでは10件）ごとに分割し、ページネーション用のオブジェクトを返します。ビューで`{{ $books->links() }}`と書くだけで、ページ送りのリンクが自動生成されます。
> - `public function show(Book $book)`: 引数に`Book $book`と型宣言することで、**ルートモデルバインディング**が有効になります。LaravelはURLの`{book}`部分（例: `/books/5`の`5`）を見て、自動的に`Book::find(5)`を実行し、結果を`$book`変数に代入してくれます。

`routes/web.php`で、`BookController`に対するリソースルートを定義します。

```php
// routes/web.php

<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::resource('books', BookController::class)->middleware('auth');

require __DIR__.'/auth.php';
```

> **【コードリーディング】**
> - `Route::resource('books', BookController::class)`: この1行で、以下の7つのルートが自動的に定義されます。
>   | HTTPメソッド | URI | アクション | ルート名 |
>   |---|---|---|---|
>   | GET | `/books` | index | books.index |
>   | GET | `/books/create` | create | books.create |
>   | POST | `/books` | store | books.store |
>   | GET | `/books/{book}` | show | books.show |
>   | GET | `/books/{book}/edit` | edit | books.edit |
>   | PUT/PATCH | `/books/{book}` | update | books.update |
>   | DELETE | `/books/{book}` | destroy | books.destroy |
> - `->middleware('auth')`: このルートグループに`auth`ミドルウェアを適用し、ログインしていないユーザーはアクセスできないようにします。

### 3. テストデータの投入とマイグレーション

表示を確認するために、テストデータを作成します。`database/factories/BookFactory.php`と`database/seeders/DatabaseSeeder.php`を編集します。

```php
// database/factories/BookFactory.php

<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->realText(20),
            'author' => $this->faker->name(),
            'isbn' => $this->faker->unique()->numerify('#############'), // 13桁の数字
            'published_at' => $this->faker->date(),
            'overview' => $this->faker->realText(100),
            'image_url' => 'https://placehold.co/300x400',
        ];
    }
}
```

```php
// database/seeders/DatabaseSeeder.php

<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // テスト用ユーザーを1人作成
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // そのユーザーに紐付く書籍を15件作成
        Book::factory(15)->create(['user_id' => $user->id]);
    }
}
```

データベースを再構築し、シーダーを実行します。

```bash
# データベースをリフレッシュし、シーダーを実行
sail artisan migrate:fresh --seed
```

これで、Read機能の実装は完了です。`/books`にアクセスすれば一覧が、`/books/1`などにアクセスすれば詳細が表示されるはずです。

---

## Section 2: 書籍登録（Create）の実装

次に、データを「作成」するCreate機能です。ここでの主役は**FormRequest**によるバリデーションです。

### 1. FormRequestの作成

コントローラにバリデーションロジックを書くと、コントローラが肥大化します。Laravelでは、バリデーションルールを専用の`FormRequest`クラスに分離するのがベストプラクティスです。

```bash
# 書籍登録用のFormRequestを作成
sail artisan make:request StoreBookRequest
```

生成された`app/Http/Requests/StoreBookRequest.php`を編集します。

```php
// app/Http/Requests/StoreBookRequest.php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * このリクエストを実行する権限があるかを判定
     */
    public function authorize(): bool
    {
        // ログインしているユーザーなら誰でも登録できる
        return true;
    }

    /**
     * バリデーションルールを定義
     */
    public function rules(): array
    {
        // ヒアリングシートで確定したバリデーションルールを定義
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],
            'published_at' => ['required', 'date'],
            'overview' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
        ];
    }
}
```

> **【コードリーディング】**
> - `authorize()`: このメソッドが`false`を返すと、403 Forbiddenエラーが発生します。今回は認証済みユーザーなら誰でも登録できるので`true`を返しています。
> - `rules()`: バリデーションルールを連想配列で返します。キーがフォームの`name`属性、値がルールの配列です。
> - `'unique:books,isbn'`: `books`テーブルの`isbn`カラムで一意性をチェックします。

### 2. ControllerとViewの編集

`BookController`の`create`（登録フォーム表示）と`store`（登録処理）メソッドを編集します。

```php
// app/Http/Controllers/BookController.php

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // ... index, show ...

    /**
     * 書籍登録フォームを表示
     */
    public function create()
    {
        return view('books.create');
    }

    /**
     * 書籍を登録
     */
    public function store(StoreBookRequest $request)
    {
        // バリデーション済みのデータを取得
        $validated = $request->validated();

        // ログインユーザーのIDを追加
        $validated['user_id'] = auth()->id();

        // データベースに保存
        $book = Book::create($validated);

        // 登録した書籍の詳細ページにリダイレクトし、フラッシュメッセージを表示
        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }
    
    // ...
}
```

> **【コードリーディング】**
> - `store(StoreBookRequest $request)`: 引数に`StoreBookRequest`を指定するだけで、Laravelが自動的にバリデーションを実行してくれます。バリデーションが失敗すれば、自動的に前のページ（登録フォーム）にリダイレクトされ、エラーメッセージもセッションに保存されます。
> - `$request->validated()`: バリデーションを通過したデータのみを取得します。悪意のあるユーザーがフォームに存在しないフィールドを追加して送信しても、`rules()`で定義されていないフィールドは無視されます。
> - `->with('success', '書籍を登録しました。')`: セッションに`success`というキーでフラッシュメッセージを保存します。リダイレクト先のビューで`session('success')`として取得できます。

### 3. Modelの編集（Mass Assignment対策）

`Book::create($validated)`のように、配列を渡して一括でデータを保存する機能を**Mass Assignment**と呼びます。これはセキュリティ上のリスクがあるため、Laravelはデフォルトで保護しています。

`Book`モデルに、Mass Assignmentを許可するカラムを明示的に指定します。

```php
// app/Models/Book.php

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    /**
     * Mass Assignmentを許可するカラム
     */
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_at',
        'overview',
        'image_url',
    ];
}
```

---

## Section 3: 書籍更新・削除（Update, Delete）の実装

最後に、更新と削除です。ここでの主役は**Policy**による認可（Authorization）です。

### 思考プロセス：なぜ認可が必要か？

- **要件**: 「自分が登録した書籍」の情報のみ編集・削除できる。
- **リスク**: もし認可がなければ、悪意のあるユーザーがURLを直接叩く（例: `/books/5/edit`）ことで、他人の書籍を勝手に編集・削除できてしまいます。
- **対策**: 「この操作を行う権限が、このユーザーにあるか？」をチェックする仕組みが必要です。それがLaravelのPolicyです。

### 1. Policyの作成

```bash
# Bookモデルに対するPolicyを作成
sail artisan make:policy BookPolicy --model=Book
```

生成された`app/Policies/BookPolicy.php`を編集します。

```php
// app/Policies/BookPolicy.php

<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を更新する権限があるかを判定
     */
    public function update(User $user, Book $book): bool
    {
        // ログインユーザーのIDと、書籍のuser_idが一致するかをチェック
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除する権限があるかを判定
     */
    public function delete(User $user, Book $book): bool
    {
        // updateと同じロジック
        return $user->id === $book->user_id;
    }
}
```

### 2. Controllerの編集

`BookController`の`edit`, `update`, `destroy`メソッドを編集します。

```php
// app/Http/Controllers/BookController.php

use App\Http\Requests\UpdateBookRequest;

class BookController extends Controller
{
    // ...

    /**
     * 書籍編集フォームを表示
     */
    public function edit(Book $book)
    {
        // 認可チェック
        $this->authorize('update', $book);
        return view('books.edit', compact('book'));
    }

    /**
     * 書籍を更新
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        // 認可チェック
        $this->authorize('update', $book);

        $validated = $request->validated();
        $book->update($validated);

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除
     */
    public function destroy(Book $book)
    {
        // 認可チェック
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
```

> **【コードリーディング】**
> - `$this->authorize('update', $book)`: `BookPolicy`の`update`メソッドを呼び出し、権限がなければ自動的に403 Forbiddenエラーを返します。
> - `edit`メソッドにも認可チェックを入れるのが重要です。そもそも編集画面を他人に見せてはいけません。

---

## まとめ

このChapterでは、書籍管理機能のCRUDを実装する過程で、

-   **MVC**のリズムに沿った開発フロー
-   **FormRequest**によるバリデーションロジックの分離
-   **Policy**による認可ロジックの分離

という、Laravelにおけるモダンで堅牢なアプリケーション開発の核心部分を学びました。これらのテクニックは、今後のすべての機能開発で活用していくことになります。
