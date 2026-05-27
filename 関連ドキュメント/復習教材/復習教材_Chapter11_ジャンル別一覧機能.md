# Chapter 11: 分類の窓口 - ジャンル別一覧機能を実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、特定のジャンルに属する書籍を一覧表示する機能を実装します。ジャンルから書籍へのリレーションを起点としたデータ取得と、N+1問題を防ぐEager Loadingの実践を学びます。

- **リレーションを活用した絞り込み**: ジャンルから関連する書籍を取得する方法を学びます
- **Eager Loading**: N+1問題を防ぐための `with()` メソッドの使い方を復習します
- **ページネーション**: 大量のデータを分割して表示する方法を学びます

## 1. はじめに 📖

### なぜジャンル別一覧が必要なのか

書籍管理アプリケーションにおいて、ユーザーは「プログラミング」「ビジネス」「小説」といったジャンルで書籍を探したいというニーズがあります。全書籍一覧から目的の書籍を探すのは大変なので、ジャンルで絞り込めると利便性が大幅に向上します。

### Genre起点のデータ取得

Chapter 03で定義した `Genre` モデルの `books()` リレーション（`belongsToMany`）を活用します。「このジャンルに属する書籍」という要件に対して、Genre起点でデータを取得するのが最も自然です。

```
Genre (ジャンル)
  ↓ belongsToMany (多対多) - $genre->books()
Book (書籍)
  ↓ belongsToMany (多対多) - Eagerロード with('genres')
Genre (ジャンル) ※各書籍に紐づくジャンル情報
```

## 2. 要件の確認 📋

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| ジャンル別書籍一覧 | GET | `/genres/{genre}` | GenreController@show | 必要 |

### 表示仕様

| 項目 | 仕様 |
|:---|:---|
| データ取得の起点 | Genre モデル |
| Eager Loading | 各書籍に紐づくジャンル情報（`with('genres')`） |
| ページネーション | 10件ずつ |

## 3. 先輩エンジニアの思考プロセス 💭

### Point 1: Genre起点 vs Book起点

「ジャンル別書籍一覧」を実装する方法は2つあります:

| 方法 | コード例 | 採用 |
|:---|:---|:---|
| Genre起点 | `$genre->books()->paginate(10)` | **採用** |
| Book起点 | `Book::whereHas('genres', fn($q) => $q->where('id', $genre->id))->paginate(10)` | - |

Genre起点の方が、「このジャンルに属する書籍」という要件を直接的に表現でき、コードが簡潔です。

### Point 2: なぜ書籍のジャンル情報もEager Loadingするのか

書籍一覧を表示する際に「この書籍は○○、△△というジャンルに属しています」というバッジを表示したいため、各書籍に紐づくジャンル情報も取得します。`with('genres')` でEager Loadingしないと、書籍ごとにジャンル取得のクエリが発行され、N+1問題が発生します。

## 4. 実装 🚀

`GenreController` を新規作成し、`show` メソッドを実装します。Chapter 12 で残りのCRUDメソッド（index / create / store / edit / update / destroy）を追加します。

```bash
sail artisan make:controller GenreController
```

### `app/Http/Controllers/GenreController.php`（showメソッドのみ）

```php
public function show(Genre $genre)
{
    $books = $genre->books()->with('genres')->paginate(10);

    return view('genres.show', compact('genre', 'books'));
}
```

> **注意:** この時点では `show` メソッドのみを実装します。GenreControllerの完全な実装（index / create / store / edit / update / destroy）は Chapter 12 で行います。

## 5. コードの詳細解説 🔍

### メソッドチェーンの処理フロー

| ステップ | コード | 処理内容 | 戻り値 |
|:---|:---|:---|:---|
| 1 | `$genre->books()` | 指定ジャンルに属する書籍を取得するクエリビルダを生成 | `BelongsToMany`（クエリビルダ） |
| 2 | `->with('genres')` | 各書籍に紐づくジャンル情報をEager Loadingするよう指定 | `BelongsToMany`（クエリビルダ） |
| 3 | `->paginate(10)` | クエリを実行し、10件ずつページネーションされた結果を取得 | `LengthAwarePaginator` |

### 各構文の解説

| コード / 構文 | 解説 |
|:---|:---|
| `show(Genre $genre): View` | ルートモデルバインディングで `Genre` モデルを受け取り、`View` オブジェクトを返します。`/genres/{genre}` のURLに対応します。 |
| `$genre->books()` | `Genre` モデルに定義した `books` リレーション（`belongsToMany`）を呼び出します。中間テーブル `book_genre` を経由して書籍を取得するクエリビルダが返されます。 |
| `->with('genres')` | 各書籍に紐づくジャンル情報をEager Loadingします。N+1問題を防ぎ、書籍のジャンルバッジ表示時に追加クエリが発生しません。 |
| `->paginate(10)` | 10件ずつページネーションして取得します。大量の書籍があっても適切な件数で分割表示できます。 |
| `compact('genre', 'books')` | `$genre` と `$books` をビューに渡します。`['genre' => $genre, 'books' => $books]` と同じ意味です。 |

## 6. この実装にたどり着くための調べ方 🧐

| 疑問 | プロンプト例 |
|:---|:---|
| Route Model Binding で `Genre $genre` を受け取る仕組み | 「Laravel で `Route::get('/genres/{genre}', ...)` のように URL に ID を埋め込み、コントローラで `function show(Genre $genre)` のようにモデルインスタンスとして受け取れる仕組み（Route Model Binding）を解説してください。」 |
| `with('genres')` の N+1 対策 | 「ジャンル詳細画面でそのジャンルに属する書籍を表示するとき、各書籍が複数ジャンルを持つので Eager Loading が必要です。`Genre::with('books')` と `Book::with('genres')` のどちらを使うべきか、目的別に説明してください。」 |
| 認証不要ルートの設計 | 「`/genres/{genre}` のように『参照だけは認証不要』なルートを設計する場合、`Route::middleware('auth')->group(...)` から外す方針で良いですか？セキュリティ観点で気をつけることはありますか？」 |

---

## 7. 動作確認 ✅

| 確認項目 | 確認方法 |
|:---|:---|
| ジャンル詳細画面の表示 | `/genres/{id}` （例: `/genres/1`）にアクセスすると、ジャンル名とそのジャンルに属する書籍一覧が表示される |
| 認証不要 | ログアウト状態でも `/genres/{id}` にアクセス可能 |
| 紐付く書籍が無いジャンル | レビュー / 書籍が無いジャンルでも画面が正常に表示される（空メッセージや 0 件表示） |
| 不正な ID | `/genres/9999` のように存在しない ID にアクセスすると 404 が返ること |

---

## 8. まとめ ✨

このチャプターでは、ジャンル別一覧機能を実装しました。

- **Genre起点のデータ取得**: `$genre->books()` でジャンルから書籍へのリレーションを起点に、簡潔にデータを取得しました
- **Eager Loadingの活用**: `with('genres')` で各書籍のジャンル情報を事前に読み込み、N+1問題を防止しました
- **ページネーション**: `paginate(10)` で大量データに対応しました

次の Chapter 12 では、ジャンルの登録・編集・削除を行う**ジャンル管理機能（CRUD）**を実装します。Chapter 06 の書籍CRUDと同じパターンを、別のリソースで再実践します。
