# Chapter 12: もう一つのCRUD - ジャンル管理機能を実装する

## 🎯 このChapterの目標

このチャプターでは、ジャンルの一覧表示・登録・編集・削除機能を実装します。Chapter 06 で学んだ書籍CRUDと同じパターンを別のリソースで再実践し、さらにジャンル管理特有の「削除時の制約チェック」を学びます。

---

## 📋 要件の確認

### バリデーションルール

| 項目 | ルール（Store） | ルール（Update） |
|:---|:---|:---|
| name | required / string / max:255 / unique:genres,name | required / string / max:255 / unique:genres（自分自身を除外） |

### 削除の制約

| 条件 | 挙動 |
|:---|:---|
| 書籍が紐付いていない | 削除成功 |
| 書籍が1件以上紐付いている | 削除拒否 + エラーメッセージ |

---

## 💭 なぜこう作るのか？

### Point 1: `unique` バリデーションの更新時の落とし穴

更新時は `Rule::unique('genres')->ignore($this->genre)` で自分自身を除外します。

### Point 2: 削除前の紐付きチェック

`$genre->books()->count() > 0` で紐付きをチェックし、書籍が存在する場合は削除を禁止します。

### Point 3: `withCount()` で書籍数を効率的に取得

`Genre::withCount('books')` で `$genre->books_count` にアクセスでき、N+1問題も発生しません。

---

## 🚀 コードの実装

### FormRequest

#### `app/Http/Requests/StoreGenreRequest.php`

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

    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
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

    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名は必須です。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'そのジャンル名は既に使用されています。',
        ];
    }
}
```

### GenreController の完全実装

`app/Http/Controllers/GenreController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;

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

---

## 🔍 コードリーディング

| コード | 解説 |
|:---|:---|
| `Genre::withCount('books')->get()` | 各ジャンルの書籍数を効率的に取得。`$genre->books_count` でアクセス。 |
| `Genre::create($request->validated())` | バリデーション済みデータで一括作成。ジャンルは `name` のみなので `except()` は不要。 |
| `Rule::unique('genres')->ignore($this->genre)` | 更新時に自分自身のレコードを除外してユニークチェック。 |
| `$genre->books()->count() > 0` | 紐付き書籍があるか確認。あれば削除を拒否。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| withCount の使い方 | 「Laravel の withCount() メソッドの使い方を教えてください。」 |
| Rule::unique()->ignore() | 「Laravel の更新時に unique バリデーションで自分自身を除外する方法を教えてください。」 |

---

## ✅ 動作確認

| 確認項目 | 確認方法 |
|:---|:---|
| ジャンル一覧 | ログイン後、ジャンル一覧が表示されること |
| ジャンル登録 | 新しいジャンル名で登録できること |
| 重複チェック | 既存のジャンル名で登録するとエラーが表示されること |
| 削除制限 | 書籍が紐付いたジャンルを削除しようとするとエラーメッセージが表示されること |
| 削除成功 | 書籍が紐付いていないジャンルは削除できること |

---

## ✨ このChapterのまとめ

| 機能 | 書籍管理（Chapter 06） | ジャンル管理（Chapter 12） |
|:---|:---|:---|
| バリデーション | FormRequest（isbn unique） | FormRequest（name unique + ignore） |
| 認可 | BookPolicy（作成者のみ） | なし（認証済みユーザーなら誰でもOK） |
| 削除 | 無条件削除（cascadeで関連データ自動削除） | 条件付き削除（紐付き書籍チェック） |

次の Chapter 13 では、**公開APIの実装**を行います。
