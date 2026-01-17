# Chapter 12: ジャンル別一覧機能

## 🎯 このセクションで学ぶこと

このセクションでは、特定のジャンルに属する書籍の一覧を表示する機能を実装します。

- **リレーションを活用した絞り込み**: `belongsToMany`リレーションを使って、特定のジャンルに紐づく書籍を取得する方法を学びます。
- **ルートモデルバインディング**: URLパラメータから自動的にモデルインスタンスを取得する仕組みを学びます。

---

## 🧠 先輩エンジニアの思考プロセス：ジャンル別一覧の設計

ジャンル別一覧機能を実装する際、以下の点を考慮します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| URLの設計 | `/genres/{genre}` | RESTfulな設計で、「ジャンルの詳細」を表示するイメージ。 |
| データの取得方法 | `$genre->books()` | `Genre`モデルに定義した`books`リレーションを活用する。 |
| 既存のビューを再利用するか | 専用のビューを作成 | ジャンル名を表示するなど、ジャンル固有の情報を含めたい。 |

---

## 11.1. GenreControllerにshowメソッドを追加

ジャンル管理用の`GenreController`に、ジャンル別一覧を表示する`show`メソッドを追加します。

```php
// app/Http/Controllers/GenreController.php

// ... (既存のコード)

use App\Models\Genre;

// ... (既存のコード)

public function show(Genre $genre)
{
    $books = $genre->books()->paginate(10);
    return view(\'genres.show\', compact(\'genre\', \'books\'));
}
```

### 11.1.1. コードリーディング：メソッドチェーンの分解

```php
$books = $genre->books()->paginate(10);
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `$genre` | ルートモデルバインディングにより、URLの`{genre}`から自動的に取得された`Genre`モデルのインスタンスです。 | `Genre` | URLが`/genres/1`の場合、ID=1のジャンルが自動的に取得されます。 |
| `->books()` | `Genre`モデルの`books`リレーション（`belongsToMany`）を取得します。 | `BelongsToMany` | Chapter 3で定義したリレーションを活用します。 |
| `->paginate(10)` | 10件ずつページネーションします。 | `LengthAwarePaginator` | - |

> **💡 ポイント: ルートモデルバインディング**
> Laravelは、ルートパラメータ名（`{genre}`）とメソッド引数の型ヒント（`Genre $genre`）を照合し、自動的にデータベースからモデルを取得します。
> 
> ```php
> // ルート定義
> Route::get(\'/genres/{genre}\', [GenreController::class, \'show\']);
> 
> // コントローラー
> public function show(Genre $genre) // 自動的にGenre::findOrFail($id)が実行される
> ```

---

## 11.2. ルートの追加

```php
// routes/web.php

// ... (他のルート)

Route::get(\'/genres/{genre}\', [GenreController::class, \'show\'])->name(\'genres.show\');
```

---

## 11.3. ビューの作成

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/genres
touch resources/views/genres/show.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 11.4. Bladeテンプレートでのジャンル別一覧表示例

```blade
{{-- ジャンル別一覧 --}}
<h1>ジャンル: {{ $genre->name }}</h1>

<ul>
    @foreach ($books as $book)
        <li>
            <a href="{{ route(\'books.show\', $book) }}">{{ $book->title }}</a>
        </li>
    @endforeach
</ul>

{{ $books->links() }}
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `$genre->name` | 現在表示しているジャンルの名前です。 | - |
| `$books->links()` | ページネーションリンクを表示します。 | Tailwind CSSに対応したスタイルが自動的に適用されます。 |

---

## 11.5. ジャンル一覧からのリンク

ヘッダーやサイドバーにジャンル一覧を表示し、各ジャンルへのリンクを設置する例です。

```blade
{{-- ジャンル一覧 --}}
<ul>
    @foreach (\App\Models\Genre::all() as $genre)
        <li>
            <a href="{{ route(\'genres.show\', $genre) }}">{{ $genre->name }}</a>
        </li>
    @endforeach
</ul>
```

> **⚠️ 注意: N+1問題**
> 上記のコードは、ビューで直接`Genre::all()`を呼び出しています。これは簡易的な実装ですが、パフォーマンスを考慮する場合は、コントローラーでジャンル一覧を取得し、ビューに渡す方が良いでしょう。

これで、ジャンル別一覧機能の実装が完了しました。次のChapterでは、ジャンル管理機能（CRUD）を実装していきます。
