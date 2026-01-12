'''
# Chapter 11: ジャンル管理機能 (CRUD)

このChapterでは、管理者（このアプリケーションでは全ログインユーザーが管理者として振る舞います）がジャンルを自由に作成・編集・削除できる、マスタデータ管理機能を実装します。

---

## 11-1. 先輩エンジニアの思考プロセス：マスタデータ管理機能の実装パターン

### Step 1: 要件をCRUDに分解する

**要件**:
- ジャンルの一覧を表示できる（そのジャンルに紐づく書籍数も表示する）
- 新しいジャンルを登録できる
- 既存のジャンル名を編集できる
- ジャンルを削除できる（ただし、そのジャンルに紐づく書籍が存在しない場合に限る）

これは、書籍管理（Chapter 5）で実装したCRUDのパターンと非常によく似ています。このパターン認識が、実装のスピードを上げます。

| 要件 | CRUD | アクション (メソッド) | 役割 |
|:---|:---|:---|:---|
| ジャンル一覧表示 | **R**ead | `index` | 全ジャンルの一覧と、関連書籍数を表示する |
| ジャンル登録 | **C**reate | `create` / `store` | 登録フォーム表示 / DBへの保存 |
| ジャンル編集 | **U**pdate | `edit` / `update` | 編集フォーム表示 / DBの更新 |
| ジャンル削除 | **D**elete | `destroy` | DBからの削除（条件付き） |

### Step 2: 特殊要件への対応方針を考える

今回のCRUDには、書籍管理とは少し違う、2つの特殊な要件があります。

> **先輩エンジニアの思考（書籍数の表示について）:**
> 「`index`で全ジャンルを取得した後に、`foreach`ループの中で`$genre->books->count()`を呼ぶのは典型的なN+1問題だ。ジャンルが100個あれば101回のクエリが走ってしまう。こういう集計処理には、Laravelに便利な機能があるはず... そうだ、`withCount()`だ。`Genre::withCount('books')->get()`とすれば、`books_count`というプロパティに関連書籍数を自動でセットしてくれる。これならクエリは2回で済む。非常に効率的だ。」

> **先輩エンジニアの思考（条件付き削除について）:**
> 「`destroy`メソッドでは、いきなり`$genre->delete()`を実行してはいけない。まず、『このジャンルに紐づく書籍が存在するか？』をチェックする必要がある。`if ($genre->books()->count() > 0)`という条件分岐を入れ、もし書籍が存在するなら、エラーメッセージと共に一覧ページにリダイレクトするのが親切な設計だ。外部キー制約でエラーを出すのではなく、アプリケーション側で事前にチェックしてあげるのが良いUXに繋がる。」

### Step 3: ルーティングを効率化する

> **先輩エンジニアの思考:**
> 「書籍管理の時は`GET /books`, `POST /books`...と一つずつルートを定義したが、CRUDのルート定義は定型的で冗長だ。Laravelには`Route::resource()`という便利な機能がある。`Route::resource('genres', GenreController::class)`と書くだけで、7つのCRUDアクションに対応するルートを自動で生成してくれる。これは使わない手はない。ただし、`genres.show`はChapter 10で既に公開ルートとして定義済みだから、`->except(['show'])`で除外して、ルートの重複を避ける必要があるな。」

---

## 11.2. 部品の作成と実装

### 1. 部品の作成 (Artisanコマンド)

`GenreController`はChapter 10で作成済みなので、フォームリクエストのみ作成します。

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

`GenreController`はChapter 10で作成済みなので、フォームリクエストのみ作成します。

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

### 2. ルーティングの定義 (`routes/web.php`)

`Route::resource`を使い、認証必須ルートグループ内にジャンル管理のルートを定義します。

```php
// `routes/web.php` の `Route::middleware('auth')` グループ内

// Genre management
Route::resource('genres', GenreController::class)->except(['show']);
```

### 3. バリデーションルールの実装 (FormRequest)

`StoreGenreRequest`と`UpdateGenreRequest`に、要件通りのバリデーションルールを実装します。内容は書籍管理のものとほぼ同じです。

- **`app/Http/Requests/StoreGenreRequest.php`**: `name`は必須、文字列、255文字以内、`genres`テーブルでユニーク。
- **`app/Http/Requests/UpdateGenreRequest.php`**: `name`のユニークチェックで、自分自身の名前は対象外にする。

### `app/Http/Requests/StoreGenreRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
        ];
    }
}
```

### `app/Http/Requests/UpdateGenreRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('genres')->ignore($this->genre)],
        ];
    }
}
```

### 4. コントローラーの完全実装 (`GenreController.php`)

Chapter 10で作成した`show`メソッドに、CRUDの各メソッドを追加していきます。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class GenreController extends Controller
{
    /**
     * Read (Index): ジャンル一覧表示
     */
    public function index(): View
    {
        // 思考：N+1問題を避けるため`withCount`を使い、関連書籍数を効率的に取得する。
        $genres = Genre::withCount('books')->get();
        return view('genres.index', compact('genres'));
    }

    /**
     * Create (Form): ジャンル登録フォーム表示
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * Create (Store): ジャンル登録処理
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを作成しました。');
    }

    // showメソッドはChapter 10で実装済み
    public function show(Genre $genre): View { /* ... */ }

    /**
     * Update (Form): ジャンル編集フォーム表示
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * Update (Store): ジャンル更新処理
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    /**
     * Delete: ジャンル削除処理
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        // 思考：削除前に、このジャンルに紐付く書籍が存在しないかチェックする。
        if ($genre->books()->count() > 0) {
            return redirect()->route('genres.index')->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();
        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
```

### 5. ビューの実装

まず、必要なディレクトリと空のファイルを作成します。

```bash
# 空のファイルを作成
touch resources/views/genres/index.blade.php
touch resources/views/genres/create.blade.php
touch resources/views/genres/edit.blade.php
```

- **`resources/views/genres/index.blade.php`**: ジャンル一覧と関連書籍数を表示
- **`resources/views/genres/create.blade.php`**: 新規登録フォーム
- **`resources/views/genres/edit.blade.php`**: 編集フォーム

- **`resources/views/genres/index.blade.php`**: ジャンル一覧と関連書籍数を表示
- **`resources/views/genres/create.blade.php`**: 新規登録フォーム
- **`resources/views/genres/edit.blade.php`**: 編集フォーム

各bladeファイルは「Preparedblade-mockcase-BookShelf」を参照してください。

`index.blade.php`では、`withCount`によって追加された`books_count`プロパティを使います。

```html
<!-- genres/index.blade.php の一部 -->
@foreach ($genres as $genre)
    <tr>
        <td>{{ $genre->name }}</td>
        <td>{{ $genre->books_count }}</td> <!-- ここ！ -->
        <td>
            <a href="{{ route('genres.edit', $genre) }}">編集</a>
            <form action="{{ route('genres.destroy', $genre) }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit">削除</button>
            </form>
        </td>
    </tr>
@endforeach
```

---

## 11.3. 動作確認

1.  ログイン後、`/genres`にアクセスし、ジャンル管理ページが表示されることを確認します。
2.  各ジャンルの横に、紐付いている書籍の数が正しく表示されていることを確認します。
3.  「新規登録」ボタンから新しいジャンルを作成できることを確認します。
4.  「編集」ボタンからジャンル名を変更できることを確認します。
5.  書籍が1冊も紐付いていないジャンルの「削除」ボタンを押し、正常に削除されることを確認します。
6.  書籍が1冊以上紐付いているジャンルの「削除」ボタンを押し、「〜削除できません。」というエラーメッセージが表示され、削除されないことを確認します。

これで、ジャンル管理機能の実装が完了しました。
'''
