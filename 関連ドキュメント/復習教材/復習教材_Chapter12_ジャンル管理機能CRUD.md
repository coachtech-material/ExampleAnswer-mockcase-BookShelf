# Chapter 12: もう一つのCRUD - ジャンル管理機能を実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、ジャンルの一覧表示・登録・編集・削除機能を実装します。Chapter 06 で学んだ書籍CRUDと同じパターンを別のリソースで再実践し、CRUDパターンの理解を定着させます。さらに、ジャンル管理特有の「削除時の制約チェック」という実践的なパターンも学びます。

- **CRUDパターンの再実践**: 書籍管理で学んだパターンを、ジャンル管理に適用します
- **削除時の制約**: 書籍が紐付いているジャンルは削除できないようにする実装を学びます
- **`unique` バリデーションの応用**: 更新時に自分自身を除外する `Rule::unique()->ignore()` の使い方を学びます
- **`withCount` の活用**: 各ジャンルに紐づく書籍数を効率的に取得する方法を学びます

## 1. はじめに 📖

### 書籍CRUDとの共通パターン

書籍管理とジャンル管理は、技術的にはほぼ同じCRUDパターンです。

| 機能 | 書籍管理（Chapter 06） | ジャンル管理（Chapter 12） |
|:---|:---|:---|
| 一覧表示 | `BookController@index` | `GenreController@index` |
| 新規作成 | `BookController@create/store` | `GenreController@create/store` |
| 編集 | `BookController@edit/update` | `GenreController@edit/update` |
| 削除 | `BookController@destroy` | `GenreController@destroy` |

### ジャンル管理特有のポイント

ジャンルは書籍と多対多で紐付いているため、書籍が紐付いているジャンルを安易に削除するとデータの整合性が壊れます。`destroy` メソッドでは、削除前に紐付きチェックを行い、書籍が存在する場合は削除を禁止します。

## 2. 要件の確認 📋

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| ジャンル一覧 | GET | `/genres` | GenreController@index | 必要 |
| ジャンル登録フォーム | GET | `/genres/create` | GenreController@create | 必要 |
| ジャンル登録処理 | POST | `/genres` | GenreController@store | 必要 |
| ジャンル別書籍一覧 | GET | `/genres/{genre}` | GenreController@show | 不要 |
| ジャンル編集フォーム | GET | `/genres/{genre}/edit` | GenreController@edit | 必要 |
| ジャンル更新処理 | PUT | `/genres/{genre}` | GenreController@update | 必要 |
| ジャンル削除処理 | DELETE | `/genres/{genre}` | GenreController@destroy | 必要 |

### バリデーションルール

| 項目 | ルール（Store） | ルール（Update） | エラーメッセージ |
|:---|:---|:---|:---|
| name | required / string / max:255 / unique:genres,name | required / string / max:255 / unique:genres（自分自身を除外） | ジャンル名は必須です。/ そのジャンル名は既に使用されています。 |

### 削除の制約

| 条件 | 挙動 |
|:---|:---|
| 書籍が紐付いていない | 削除成功。「ジャンルを削除しました。」 |
| 書籍が1件以上紐付いている | 削除拒否。「このジャンルには書籍が紐付いているため削除できません。」 |

## 3. 先輩エンジニアの思考プロセス 💭

### Point 1: `unique` バリデーションの更新時の落とし穴

ジャンル名にはユニーク制約があります。新規登録時は単純な `unique:genres,name` で問題ありませんが、更新時には「自分自身のレコードは除外」しなければなりません。名前を変更せずに保存しただけで「既に使用されています」というエラーになってしまうためです。

```php
// ❌ 更新時も同じルール → 自分自身と重複してエラー
'name' => ['required', 'string', 'max:255', 'unique:genres,name'],

// ✅ Rule::unique()->ignore() で自分自身を除外
'name' => ['required', 'string', 'max:255', Rule::unique('genres')->ignore($this->genre)],
```

### Point 2: 削除前の紐付きチェック

ジャンルに書籍が紐付いている場合、削除するとその書籍のジャンル情報が失われます。データの整合性を保つために、削除前に `$genre->books()->count()` で紐付きをチェックします。

### Point 3: `$request->validated()` でデータを一括取得

Chapter 06 では `collect($validated)->except('genres')->toArray()` で genres を分離する必要がありましたが、ジャンルのフォームは `name` フィールドのみなので、`$request->validated()` をそのまま `create()` / `update()` に渡せます。

### Point 4: `withCount()` で書籍数を効率的に取得

一覧ページで各ジャンルの書籍数を表示するために、`Genre::withCount('books')` を使います。これにより `$genre->books_count` で書籍数にアクセスでき、N+1問題も発生しません。

## 4. 実装 🚀

### 4.1. FormRequestの作成

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

#### `app/Http/Requests/StoreGenreRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGenreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
            'name.string' => 'ジャンル名は文字列で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名は既に使用されています。',
        ];
    }
}
```

#### `app/Http/Requests/UpdateGenreRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('genres')->ignore($this->genre)],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
            'name.string' => 'ジャンル名は文字列で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名は既に使用されています。',
        ];
    }
}
```

### 4.2. GenreControllerの完全実装

Chapter 11 で `show` メソッドのみを実装していた `GenreController` に、残りのCRUDメソッドを追加します。

#### `app/Http/Controllers/GenreController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::withCount('books')->get();

        return view('genres.index', compact('genres'));
    }

    public function create()
    {
        return view('genres.create');
    }

    public function store(StoreGenreRequest $request)
    {
        Genre::create($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを作成しました。');
    }

    public function show(Genre $genre)
    {
        $books = $genre->books()->with('genres')->paginate(10);

        return view('genres.show', compact('genre', 'books'));
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
        if ($genre->books()->count() > 0) {
            return redirect()->route('genres.index')->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();

        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
```

## 5. コードの詳細解説 🔍

### FormRequest の解説

| コード / 構文 | 解説 |
|:---|:---|
| `'unique:genres,name'`（Store） | `genres` テーブルの `name` カラムで重複がないかチェックします。同じ名前のジャンルは登録できません。 |
| `Rule::unique('genres')->ignore($this->genre)`（Update） | `genres` テーブルでユニークチェックを行いますが、現在編集中のレコード（`$this->genre`）は除外します。名前を変更しない場合でもエラーにならないようにするためです。 |
| `$this->genre` | FormRequest内では、ルートパラメータに `$this->パラメータ名` でアクセスできます。ルートモデルバインディングにより、`Genre` モデルのインスタンスが取得されます。 |

### GenreController の解説

#### 基本CRUDメソッド（Chapter 06 と同じパターン）

| メソッド | 処理内容 | ポイント |
|:---|:---|:---|
| `index(): View` | ジャンル一覧を表示。`withCount('books')` で各ジャンルの書籍数も取得。 | `$genre->books_count` で書籍数にアクセス可能。 |
| `create(): View` | ジャンル作成フォームを表示。 | シンプルにビューを返すだけ。 |
| `store(StoreGenreRequest $request): RedirectResponse` | バリデーション済みデータでジャンルを作成。 | `$request->validated()` をそのまま `create()` に渡せる。 |
| `show(Genre $genre): View` | Chapter 11 で実装済み。ジャンル別書籍一覧を表示。 | Eager Loading + ページネーション。 |
| `edit(Genre $genre): View` | ジャンル編集フォームを表示。 | 編集対象のジャンルをビューに渡す。 |
| `update(UpdateGenreRequest $request, Genre $genre): RedirectResponse` | バリデーション済みデータでジャンルを更新。 | `$genre->update()` でモデルを更新。 |

#### `destroy` メソッドの詳細

`destroy` メソッドには「書籍が紐付いている場合は削除を禁止する」という特別なロジックが含まれています。

| コード / 構文 | 解説 |
|:---|:---|
| `$genre->books()->count()` | このジャンルに紐付いている書籍の数を取得します。`belongsToMany` リレーションの `count()` メソッドを使用します。 |
| `> 0` | 書籍が1冊以上紐付いているかをチェックします。 |
| `->with('error', '...')` | セッションにエラーメッセージを保存します。ビューで `session('error')` として取得・表示できます。 |
| `$genre->delete()` | 書籍が紐付いていない場合のみ、ジャンルをデータベースから削除します。 |

## 6. この実装にたどり着くための調べ方 🧐

### Step 1: 公式ドキュメントを読みやすくまとめる

**プロンプト例**
```
以下はLaravelのバリデーションに関する公式ドキュメントの一部です。
特に「uniqueルール」と「更新時の自己除外（ignore）」に焦点を当てて
まとめてください。

出力してほしい内容：
- Rule::unique() の基本的な使い方
- ignore() メソッドの使い方と注意点
- FormRequest内でルートパラメータにアクセスする方法（$this->パラメータ名）

--- ここから ---
（ここにLaravelのValidationに関する公式ドキュメントを貼り付ける）
--- ここまで ---
```

### Step 2: 「なぜそうなる？」をはっきりさせる

**プロンプト例**
```
LaravelでジャンルのCRUD機能を実装しようとしています。
ジャンル名にはユニーク制約があり、書籍が紐付いているジャンルは
削除できないようにしたいです。

お願い：
1) Rule::unique()->ignore($this->genre) で $this->genre が
   どのように解決されるか教えてください
2) 削除前の紐付きチェックを「データベース制約」で行う方法との比較を
   教えてください（外部キー制約 vs アプリケーション側チェック）
3) withCount('books') の内部で発行されるSQLを教えてください
```

## 7. 動作確認 ✅

以下の項目を確認してください。

1. **ジャンル一覧** (`/genres`)
   - ジャンル一覧が表示され、各ジャンルの書籍数が表示される
   - 新規作成・編集・削除のリンク/ボタンが表示される

2. **ジャンル登録** (`/genres/create`)
   - ジャンル名を入力して登録できる
   - 登録後「ジャンルを作成しました。」が表示される
   - 既存のジャンル名を入力すると「そのジャンル名は既に使用されています。」が表示される

3. **ジャンル編集** (`/genres/{id}/edit`)
   - 既存の名前がフォームにセットされている
   - 名前を変更せずに更新してもエラーにならない
   - 他の既存ジャンル名に変更するとユニークエラーが表示される

4. **ジャンル削除**
   - 書籍が紐付いていないジャンルを削除すると「ジャンルを削除しました。」が表示される
   - 書籍が紐付いているジャンルを削除しようとすると「このジャンルには書籍が紐付いているため削除できません。」が表示される

5. **ジャンル別書籍一覧** (`/genres/{id}`)
   - 指定ジャンルに属する書籍が表示される
   - 各書籍にジャンルバッジが表示される
   - ページネーションが動作する（10件を超える場合）

## 8. まとめ ✨

このチャプターでは、ジャンル管理機能（CRUD）を実装しました。

- **CRUDパターンの再実践**: Chapter 06 の書籍CRUDと同じパターンを、ジャンルという別のリソースに適用し、パターンの理解を定着させました
- **`Rule::unique()->ignore()` の活用**: 更新時に自分自身を除外するユニークバリデーションを実装しました
- **削除時の制約チェック**: `$genre->books()->count()` で紐付きをチェックし、データの整合性を保つ実装を行いました
- **`withCount()` の活用**: 各ジャンルの書籍数を効率的に取得し、一覧ページに表示しました

これで基本機能の実装が完了しました。次の Chapter 14 では、ここまで実装した機能に対する**テスト**を作成します。
