# Chapter 12: ジャンル管理機能（CRUD）

## 🎯 このセクションで学ぶこと

このセクションでは、ジャンルの登録・編集・削除機能を実装します。Chapter 6で学んだ書籍管理機能（CRUD）と同様のパターンを、別のリソースで再度実践します。

- **CRUDパターンの再実践**: 書籍管理で学んだパターンを、ジャンル管理に適用します。
- **学習の定着**: 同じパターンを繰り返し実装することで、理解を深めます。
- **削除時の制約**: 書籍が紐付いているジャンルは削除できないようにする実装を学びます。
- **型定義の活用**: コントローラーのメソッドやフォームリクエストに引数と戻り値の型を追加し、コードの可読性と堅牢性を向上させます。

---

## 🧠 先輩エンジニアの思考プロセス：ジャンル管理機能の設計

ジャンル管理機能を設計する際、先輩エンジニアは以下のような思考プロセスを経ています。

### 書籍管理との共通パターン

書籍管理とジャンル管理は、技術的にはほぼ同じCRUDパターンです。

| 機能 | 書籍管理 | ジャンル管理 |
|:---|:---|:---|
| 一覧表示 | `BookController@index` | `GenreController@index` |
| 新規作成 | `BookController@create/store` | `GenreController@create/store` |
| 編集 | `BookController@edit/update` | `GenreController@edit/update` |
| 削除 | `BookController@destroy` | `GenreController@destroy` |

### ジャンル管理特有の考慮点

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| ジャンル名の一意性 | `unique`バリデーション | 同じ名前のジャンルが複数存在すると混乱を招く。 |
| 書籍が紐付いているジャンルの削除 | 削除を禁止 | 書籍のジャンル情報が失われるのを防ぐ。 |
| 一覧での書籍数表示 | `withCount`で取得 | 各ジャンルに何冊の書籍があるかを表示できる。 |

---

## 12.1. フォームリクエストの作成

まず、バリデーションを行うフォームリクエストを作成します。

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

---

## 12.2. フォームリクエストの実装

### StoreGenreRequest

`app/Http/Requests/StoreGenreRequest.php`を開き、以下の内容を記述してください。

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

### 📖 コードリーディング：StoreGenreRequest

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function authorize(): bool` | このリクエストの実行を許可するかどうかを定義します。 | `: bool`は、このメソッドが必ず真偽値（`true`か`false`）を返すことを示します。今回はシンプルに`true`を返し、認証状態に関わらず誰でもリクエストを実行できるようにしています。 |
| `public function rules(): array` | バリデーションルールを配列で定義します。 | `: array`は、このメソッドが必ず配列を返すことを示します。これにより、意図しない型の値が返されるのを防ぎ、コードの堅牢性を高めます。 |
| `'name' => ['required', 'string', 'max:255', 'unique:genres,name']` | `name`フィールドのバリデーションルールを定義。 | 必須、文字列、最大255文字、`genres`テーブルの`name`カラムで一意であること。 |
| `'unique:genres,name'` | `genres`テーブルの`name`カラムで重複がないかチェック。 | 同じ名前のジャンルは登録できない。 |

### UpdateGenreRequest

`app/Http/Requests/UpdateGenreRequest.php`を開き、以下の内容を記述してください。

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

### 📖 コードリーディング：UpdateGenreRequest

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use Illuminate\Validation\Rule;` | `Rule`クラスをインポート。 | 複雑なバリデーションルールを構築するために必要。 |
| `Rule::unique('genres')->ignore($this->genre)` | `genres`テーブルで一意性をチェックするが、現在編集中のレコードは除外する。 | 名前を変更しない場合でも、自分自身と重複しているとエラーになるのを防ぐ。 |

---

## 12.3. GenreController.php の完全実装

Chapter 11で`show`メソッドを実装した`GenreController`に、CRUD機能を追加します。

`app/Http/Controllers/GenreController.php`を開き、以下の内容に更新してください。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function index(): View
    {
        $genres = Genre::withCount('books')->get();
        return view('genres.index', compact('genres'));
    }

    public function create(): View
    {
        return view('genres.create');
    }

    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを作成しました。');
    }

    public function show(Genre $genre): View
    {
        $books = $genre->books()->with('genres')->paginate(10);
        return view('genres.show', compact('genre', 'books'));
    }

    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->count() > 0) {
            return redirect()->route('genres.index')->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }
        $genre->delete();
        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
```

### 📖 コードリーディング：各メソッドの解説

| メソッド | 処理内容 | ポイント |
|:---|:---|:---|
| `index(): View` | ジャンル一覧を表示。`withCount('books')`で各ジャンルの書籍数も取得。 | `: View`は、このメソッドが`View`オブジェクト（HTMLページ）を返すことを明示します。 |
| `create(): View` | ジャンル作成フォームを表示。 | シンプルにビューを返すだけ。戻り値の型を`View`と明示。 |
| `store(StoreGenreRequest $request): RedirectResponse` | フォームから送信されたデータでジャンルを作成。 | `: RedirectResponse`は、このメソッドがリダイレクトレスポンスを返すことを明示します。 |
| `show(Genre $genre): View` | 特定のジャンルに属する書籍一覧を表示。 | Chapter 11で実装済み。戻り値の型を`View`と明示。 |
| `edit(Genre $genre): View` | ジャンル編集フォームを表示。 | 編集対象のジャンルをビューに渡す。戻り値の型を`View`と明示。 |
| `update(UpdateGenreRequest $request, Genre $genre): RedirectResponse` | フォームから送信されたデータでジャンルを更新。 | `$genre->update()`でモデルを更新。戻り値の型を`RedirectResponse`と明示。 |
| `destroy(Genre $genre): RedirectResponse` | ジャンルを削除。ただし書籍が紐付いている場合は削除不可。 | データの整合性を保つための重要なチェック。戻り値の型を`RedirectResponse`と明示。 |

### 📖 コードリーディング：`destroy`メソッドの詳細

> **💡 なぜ`destroy`メソッドだけ詳細な解説があるのか？**
>
> 他のCRUDメソッド（`index`, `create`, `store`, `edit`, `update`）は、書籍管理機能（Chapter 6）で学んだパターンとほぼ同じです。しかし、`destroy`メソッドには**「書籍が紐付いている場合は削除を禁止する」という特別なロジック**が含まれています。
>
> これは、データの整合性を保つための重要な実装パターンです。もし書籍が紐付いているジャンルを削除してしまうと、その書籍のジャンル情報が失われてしまいます。そのため、このメソッドだけは詳細な解説を追加しています。

```php
public function destroy(Genre $genre): RedirectResponse
{
    if ($genre->books()->count() > 0) {
        return redirect()->route('genres.index')->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
    }
    $genre->delete();
    return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
}
```

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `$genre->books()->count()` | このジャンルに紐付いている書籍の数を取得。 | `belongsToMany`リレーションの`count()`メソッドを使用。 |
| `> 0` | 書籍が1冊以上紐付いているかをチェック。 | 紐付いている場合は削除を禁止する。 |
| `->with('error', ...)` | セッションにエラーメッセージを保存。 | ビューで`session('error')`として取得できる。 |
| `$genre->delete()` | ジャンルを削除。 | Eloquentの`delete()`メソッドでレコードを削除。 |

> **📝 ルート定義について**
> ジャンル管理機能のルート定義も、Chapter 6で既に定義済みです。そのため、`routes/web.php`を修正する必要はありません。

これで、ジャンル管理機能（CRUD）の実装が完了しました。次のChapterでは、最終確認を行います。
