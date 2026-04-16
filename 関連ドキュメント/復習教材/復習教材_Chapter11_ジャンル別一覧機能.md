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

`GenreController` は Chapter 06 の「ルート定義とコントローラーの準備」で既に作成済みです。ここでは `show` メソッドを実装します。Chapter 12 で残りのCRUDメソッドを追加します。

### `app/Http/Controllers/GenreController.php`（showメソッドのみ）

```php
public function show(Genre $genre): View
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

## 8. まとめ ✨

このチャプターでは、ジャンル別一覧機能を実装しました。

- **Genre起点のデータ取得**: `$genre->books()` でジャンルから書籍へのリレーションを起点に、簡潔にデータを取得しました
- **Eager Loadingの活用**: `with('genres')` で各書籍のジャンル情報を事前に読み込み、N+1問題を防止しました
- **ページネーション**: `paginate(10)` で大量データに対応しました

次の Chapter 12 では、ジャンルの登録・編集・削除を行う**ジャンル管理機能（CRUD）**を実装します。Chapter 06 の書籍CRUDと同じパターンを、別のリソースで再実践します。
