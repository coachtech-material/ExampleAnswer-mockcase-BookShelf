# Chapter 12: ジャンル管理機能 (CRUD)

## 🎯 このセクションで学ぶこと

このセクションでは、ジャンルの登録・編集・削除機能を実装します。Chapter 5で学んだ書籍管理機能（CRUD）と同様のパターンを、別のリソースで再度実践します。

- **CRUDパターンの再実践**: 書籍管理で学んだパターンを、ジャンル管理に適用します。
- **学習の定着**: 同じパターンを繰り返し実装することで、理解を深めます。

---

## 🧠 先輩エンジニアの思考プロセス：パターンの認識

書籍管理とジャンル管理は、技術的にはほぼ同じパターンです。

| 機能 | 書籍管理 | ジャンル管理 |
|:---|:---|:---|
| 一覧表示 | `BookController@index` | `GenreController@index` |
| 詳細表示 | `BookController@show` | `GenreController@show` |
| 新規作成 | `BookController@create/store` | `GenreController@create/store` |
| 編集 | `BookController@edit/update` | `GenreController@edit/update` |
| 削除 | `BookController@destroy` | `GenreController@destroy` |

---

## 12.1. GenreControllerの実装

Chapter 11で`show`メソッドを追加した`GenreController`に、CRUD機能を追加します。

```php
// app/Http/Controllers/GenreController.php

<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::withCount('books')->paginate(10);
        return view('genres.index', compact('genres'));
    }

    public function show(Genre $genre)
    {
        $books = $genre->books()->with('genres')->paginate(10);
        return view('genres.show', compact('genre', 'books'));
    }

    public function create()
    {
        return view('genres.create');
    }

    public function store(StoreGenreRequest $request)
    {
        Genre::create($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました。');
    }

    public function edit(Genre $genre)
    {
        return view('genres.edit', compact('genre'));
    }

    public function update(UpdateGenreRequest $request, Genre $genre)
    {
        $genre->update($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    public function destroy(Genre $genre)
    {
        $genre->delete();
        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
```

### 12.1.1. コードリーディング：`index`メソッド

```php
public function index()
{
    $genres = Genre::withCount('books')->paginate(10);
    return view('genres.index', compact('genres'));
}
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `Genre::withCount('books')` | 各ジャンルに紐づく書籍数を`books_count`として取得します。 | `Builder` | 一覧画面で「このジャンルには〇冊の書籍があります」と表示できます。 |
| `->paginate(10)` | 10件ずつページネーションします。 | `LengthAwarePaginator` | - |

---

## 12.2. フォームリクエストの作成

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

### 12.2.1. StoreGenreRequest

```php
// app/Http/Requests/StoreGenreRequest.php

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
            'name' => ['required', 'string', 'max:255', 'unique:genres'],
        ];
    }
}
```

### 12.2.2. UpdateGenreRequest

```php
// app/Http/Requests/UpdateGenreRequest.php

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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres')->ignore($this->genre),
            ],
        ];
    }
}
```

| ルール | 説明 | 💡 ポイント |
|:---|:---|:---|
| `'unique:genres'` | `genres`テーブルで一意である必要があります。 | 同じ名前のジャンルは登録できません。 |
| `Rule::unique('genres')->ignore($this->genre)` | 更新時は、自分自身を除外してユニークチェックします。 | 名前を変更しない場合でも、バリデーションが通るようにします。 |

---

## 12.3. ルートの追加

```php
// routes/web.php

Route::middleware('auth')->group(function () {
    // ... 既存のルート

    // Genre management (CRUD)
    Route::resource('genres', GenreController::class)->except(['show']);
});

// 認証不要のルート
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
```

| 設定 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `Route::resource('genres', GenreController::class)` | CRUDの7つのルートを一括で定義します。 | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` |
| `->except(['show'])` | `show`ルートを除外します。 | `show`は認証不要のルートとして別途定義しているため。 |

---

## 12.4. ビューの作成

```bash
# ファイルを作成
touch resources/views/genres/index.blade.php
touch resources/views/genres/create.blade.php
touch resources/views/genres/edit.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 12.5. 書籍管理との比較

| 項目 | 書籍管理 | ジャンル管理 |
|:---|:---|:---|
| モデル | `Book` | `Genre` |
| コントローラー | `BookController` | `GenreController` |
| フォームリクエスト | `StoreBookRequest`, `UpdateBookRequest` | `StoreGenreRequest`, `UpdateGenreRequest` |
| ビュー | `resources/views/books/*` | `resources/views/genres/*` |
| バリデーション | `title`, `author`, `isbn`, `description`, `genres` | `name` |

> **🧠 先輩エンジニアの思考プロセス**
> CRUDは多くのWebアプリケーションで共通するパターンです。一度理解すれば、新しいリソース（例: カテゴリ、タグ、ユーザー）を追加する際にも、同じパターンを適用できます。

これで、ジャンル管理機能（CRUD）の実装が完了しました。次のChapterでは、ルート定義の完全版を確認し、全体の設計を振り返ります。
