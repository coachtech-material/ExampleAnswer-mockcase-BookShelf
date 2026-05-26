# Chapter 14: 高度な検索機能の実装

## 🎯 このセクションで学ぶこと

（このセクションで学ぶ内容の要約をここに追記予定）

---


## 1. はじめに 📖

基本機能編では、書籍のタイトルと著者名での単純な検索機能を実装しました。応用機能編では、これをさらに拡張し、実務で求められるような「絞り込み」や「並び替え」を含む**高度な検索機能**を実装します。

## 2. 要件の確認 📋

まずは、今回実装する高度な検索機能の要件を再確認しましょう。

| 機能 | 詳細仕様 |
|:---|:---|
| **キーワード検索** | 書籍のタイトルまたは著者名で部分一致検索ができる。 |
| **ジャンル絞り込み** | 特定のジャンルに属する書籍のみを絞り込んで表示できる。 |
| **並び替え** | 「登録日の新しい順」「登録日の古い順」「タイトル順」「評価の高い順」で結果を並び替えられる。 |
| **状態の維持** | 検索条件や並び順を維持したまま、ページネーションが機能する。 |

## 3. 先輩エンジニアの思考プロセス 💭

これから`BookController`の`index`メソッドを改修していきますが、その前に、どのような考え方で実装を進めていくのか、設計段階の思考プロセスを覗いてみましょう。これは、単にコードを書き写すのではなく、「なぜこのコードになるのか」を理解するための重要なステップです。

### 思考1：どうやって複数の検索条件を組み合わせるか？

> 「まず考えるべきは、ユーザーが入力する検索条件（キーワード、ジャンル、並び順）は、常に全てが指定されるわけではない、ということ。キーワードだけで検索することもあれば、ジャンルと並び順だけを指定することもある。つまり、**条件に応じてクエリを動的に組み立てる**必要があるな。」
> 
> 「Laravelのクエリビルダには `when()` メソッドがある。これは『第1引数がtruthyなときだけクロージャを実行する』という条件付きクエリ構築メソッドだ。`if`文で分岐するよりもメソッドチェーンの流れを崩さずに書けるので、コードがすっきりする。この `when()` を使ったアプローチで実装しよう。」

### 思考2：「タイトル or 著者」の検索はどう実現する？

> 「キーワード検索は『タイトルまたは著者』での部分一致。SQLで言えば `WHERE (title LIKE '%keyword%') OR author LIKE '%keyword%')` という形にしたい。Laravelのクエリビルダでこれを実現するには、`where()`メソッドにクロージャ（無名関数）を渡すテクニックが使える。`$query->where(function($q) { ... })` のように書くことで、`()`で囲まれた論理グループを作れるんだ。これをしないと、他の`where`句との組み合わせで意図しないSQLになってしまう可能性があるから注意が必要だ。」

### 思考3：関連テーブル（ジャンル）での絞り込みは？

> 「ジャンルでの絞り込みは、`books`テーブルに直接ジャンル名があるわけではなく、中間テーブルを介して`genres`テーブルとリレーションしている。こういう**リレーション先のテーブルの条件で絞り込みたい**場合は、`whereHas()`メソッドがまさにうってつけ。`whereHas('genres', function($q) { ... })`と書けば、『指定した条件に合致する`genres`リレーションを持つ`Book`』を簡単に絞り込める。」

### 思考4：評価の高い順ってどうやって並び替える？

> 「『評価の高い順』での並び替えは少し工夫が必要だ。各書籍の平均評価を計算し、その結果でソートしないといけない。これもリレーションの集計機能が使える。`withAvg('reviews', 'rating')`を使うと、`reviews`リレーションの`rating`カラムの平均値を`reviews_avg_rating`という名前で取得できる。あとは、このエイリアスカラムで`orderByDesc()`すればいい。他の並び替え条件（新着順、タイトル順など）は`switch`文でシンプルに分岐させよう。」

### 思考5：検索条件を維持したままページ移動させたい

> 「最後に忘れてはいけないのが、ユーザー体験。検索結果の2ページ目に移動したときに、検索条件がリセットされて全件表示に戻ってしまったら最悪だ。Laravelのページネーションには `appends()` メソッドがあり、`paginate(10)->appends(request()->query())` と書くことで、現在のURLのクエリパラメータ（`?keyword=...`など）をページネーションリンクに引き継げる。これは絶対に使うべき機能だね。」

このような思考プロセスを経て、これから見ていく具体的な実装コードが出来上がっていきます。一つ一つのコードが、どの思考に基づいて書かれているのかを意識しながら読み進めてみてください。

## 4. 実装 🚀

それでは、上記の思考プロセスを元に、`app/Http/Controllers/BookController.php`の`index`メソッドを以下のように修正します。コード全体を掲載した後に、各ブロックの詳細な解説を追記します。

```php
// app/Http/Controllers/BookController.php

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookController extends Controller
{
    /**
     * 書籍一覧を表示（検索機能付き - 応用機能）
     */
    public function index(Request $request): View
    {
        $query = Book::with('genres');

        // キーワード検索（応用機能）
        $query->when($request->input('keyword'), function ($q, $keyword): void {
            $q->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        });

        // ジャンル絞り込み（応用機能）
        $query->when($request->input('genre'), function ($q, $genreId): void {
            $q->whereHas('genres', function ($q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        });

        // 並び順（応用機能）
        switch ($request->input('sort')) {
            case 'oldest':
                $query->oldest();
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'rating':
                $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating');
                break;
            default:
                $query->latest();
                break;
        }

        $books = $query->paginate(10)->appends(request()->query());
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    // ... 他のメソッドは省略 ...
}
```

### コードの詳細解説

ここからは、上記のコードをブロックごとに分解し、何をしているのかを詳しく見ていきましょう。

#### 1. クエリの初期化

```php
public function index(Request $request): View
{
    $query = Book::with('genres');
```

- **`public function index(Request $request): View`**: `index`メソッドの定義です。引数の`Request $request`は、ユーザーからのHTTPリクエスト（検索キーワードや選択したジャンルなど）を受け取るためのオブジェクトです。`: View`は、このメソッドが最終的に`View`オブジェクト（Bladeテンプレートをレンダリングしたもの）を返すことを示しています（戻り値の型宣言）。
- **`$query = Book::with('genres');`**: これが検索クエリの土台となります。`Book`モデルに対するクエリビルダを`$query`変数に格納しています。`with('genres')`は**Eager Loading（イーガーローディング）**と呼ばれる機能で、書籍情報を取得する際に、関連するジャンル情報も一緒に取得するように指示しています。これにより、後から書籍ごとにジャンルを取得する（N+1問題）のを防ぎ、パフォーマンスを向上させます。

#### 2. キーワード検索

```php
// キーワード検索（応用機能）
$query->when($request->input('keyword'), function ($q, $keyword): void {
    $q->where(function ($q) use ($keyword): void {
        $q->where('title', 'like', "%{$keyword}%")
          ->orWhere('author', 'like', "%{$keyword}%");
    });
});
```

- **`$query->when($request->input('keyword'), function ($q, $keyword): void { ... })`**: `when()` メソッドは、第1引数がtruthyな場合にのみ第2引数のクロージャを実行します。キーワードが入力されていない場合はクエリに影響を与えません。クロージャの第2引数 `$keyword` には第1引数の値が渡されます。
- **`$q->where(function ($q) use ($keyword): void { ... })`**: ここがキーワード検索の核です。`where`メソッドに**クロージャ（無名関数）**を渡すことで、SQLの`WHERE`句をグループ化（`()`で囲む）できます。これにより、`WHERE (title LIKE ... OR author LIKE ...)`というSQLが生成され、他の検索条件と正しく組み合わせることができます。
- **`$q->where('title', 'like', "%{$keyword}%")`**: 書籍の`title`カラムを`$keyword`で**部分一致検索**します。`like`演算子とワイルドカード`%`を使っています。
- **`->orWhere('author', 'like', "%{$keyword}%")`**: `orWhere`を使うことで、「または」の条件を追加します。つまり、「タイトル`OR`著者」での検索が実現します。

#### 3. ジャンル絞り込み

```php
// ジャンル絞り込み（応用機能）
$query->when($request->input('genre'), function ($q, $genreId): void {
    $q->whereHas('genres', function ($q) use ($genreId): void {
        $q->where('genres.id', $genreId);
    });
});
```

- **`$query->when($request->input('genre'), function ($q, $genreId): void { ... })`**: ジャンルが選択された場合のみクロージャが実行されます。
- **`$q->whereHas('genres', ...)`**: **リレーション先のテーブルの条件で絞り込む**ためのメソッドです。`whereHas`は「指定した条件に合致する`genres`リレーションを持つ`Book`のみを結果に含める」というクエリを生成します。
- **`function ($q) use ($genreId)`**: ここでもクロージャを使い、絞り込みの具体的な条件を定義します。
- **`$q->where('genres.id', $genreId)`**: `genres`テーブルの`id`カラムが、ユーザーの選択した`$genreId`と一致する、という条件を指定しています。

#### 4. 並び替え

```php
// 並び順（応用機能）
switch ($request->input('sort')) {
    case 'oldest':
        $query->oldest();
        break;
    case 'title':
        $query->orderBy('title');
        break;
    case 'rating':
        $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating');
        break;
    default:
        $query->latest();
        break;
}
```

- **`switch ($request->input('sort'))`**: ユーザーが選択した並び順（`sort`パラメータ）に応じて処理を分岐します。
- **`case 'oldest'` / `case 'title'`**: それぞれ登録日の古い順（`created_at`の昇順）、タイトル順（`title`の昇順）で並び替えます。`oldest()`は`orderBy('created_at', 'asc')`のショートカットです。
- **`case 'rating'`**: 評価の高い順で並び替えます。
    - **`withAvg('reviews', 'rating')`**: `reviews`リレーションの`rating`カラムの平均値を計算し、`reviews_avg_rating`という名前の追加カラムとして取得します。
    - **`->orderByDesc('reviews_avg_rating')`**: 計算した平均評価カラムを使って、降順（高い順）に並び替えます。
- **`default`**: `sort`パラメータが指定されていない場合や、上記`case`のいずれにも一致しない場合のデフォルトの処理です。`latest()`（`created_at`の降順、つまり新しい順）で並び替えます。

#### 5. 結果の取得とビューへの受け渡し

```php
$books = $query->paginate(10)->appends(request()->query());
$genres = Genre::orderBy('name')->get();

return view('books.index', compact('books', 'genres'));
```

- **`$books = $query->paginate(10)->appends(request()->query())`**: ここで最終的なSQLが実行されます。
    - **`paginate(10)`**: それまで組み立ててきたクエリの結果を、1ページあたり10件でページネーションします。
    - **`appends(request()->query())`**: ページネーションのリンク（例：「2」「3」...）に、現在のURLのクエリパラメータ（`?keyword=...&genre=...`など）を引き継ぎます。`request()->query()` で現在のクエリパラメータ全てを取得し、`appends()` でページネーションリンクに付与します。
- **`$genres = Genre::orderBy('name')->get()`**: 検索フォームのジャンル選択プルダウンに表示するため、すべてのジャンル情報を取得しています。
- **`return view('books.index', compact('books', 'genres'))`**: `books.index`ビュー（`resources/views/books/index.blade.php`）をレンダリングして返します。`compact('books', 'genres')`は、`$books`と`$genres`変数をビューに渡すためのPHPの関数で、`['books' => $books, 'genres' => $genres]`と書くのと同じ意味です。

## 5. コードの詳細解説 🔍

（Phase 1B 動作確認時にコード解説を追記予定）

---

## 6. この実装にたどり着くための調べ方 🧐

もし自力でこの実装にたどり着くとしたら、どのように調べれば良いでしょうか？初心者がいきなり「laravel query builder where or」のような具体的なキーワードで検索できることは稀です。ここでは、実際に初心者が辿るであろう検索経路を紹介します。

### 調べ方1：「タイトルか著者で検索したい」場合

**最初の検索（日本語で素朴に）:**
```
Laravel 検索機能 作り方
```

**検索結果から得られる情報:**
- 「Laravelで検索機能を作るには`where`メソッドを使う」という基本がわかる
- 「部分一致検索には`LIKE`を使う」という情報が見つかる

**次の疑問と検索:**
```
Laravel where 複数条件 OR
```

**最終的にたどり着く答え:**
- `where`句の中でクロージャ（無名関数）を使うと、`(A OR B)`のようなグループ化ができることがわかる
- 単純に`orWhere`を繋げると意図しない結果になる場合があることも学べる

### 調べ方2：「ジャンル（関連テーブル）で絞り込みたい」場合

**最初の検索（やりたいことをそのまま）:**
```
Laravel 多対多 絞り込み
```
または
```
Laravel リレーション先 条件 検索
```

**検索結果から得られる情報:**
- 「リレーション先のテーブルで絞り込むには`whereHas`を使う」という情報が見つかる
- 公式ドキュメントの「リレーションの存在クエリ」セクションにたどり着く

**最終的にたどり着く答え:**
- `whereHas('リレーション名', function($q) { ... })`という書き方で、リレーション先の条件で絞り込めることがわかる

### 調べ方3：「評価の高い順で並び替えたい」場合

**最初の検索（困っていることをそのまま）:**
```
Laravel 関連テーブル 平均 並び替え
```
または
```
Laravel リレーション 集計 ソート
```

**検索結果から得られる情報:**
- 「リレーション先の集計には`withCount`や`withAvg`が使える」という情報が見つかる
- 集計結果は`{リレーション名}_{集計関数}_{カラム名}`という命名規則でアクセスできることがわかる

**最終的にたどり着く答え:**
- `withAvg('reviews', 'rating')`で平均評価を取得し、`orderByDesc('reviews_avg_rating')`で並び替えられることがわかる

### 調べ方4：「検索条件を維持したままページ移動したい」場合

**最初の検索（実際に困った状況から）:**
```
Laravel ページネーション 検索条件 消える
```
または
```
Laravel paginate パラメータ 引き継ぎ
```

**検索結果から得られる情報:**
- 「ページネーションリンクにクエリパラメータを引き継ぐには`appends()`を使う」という解決策が見つかる

**最終的にたどり着く答え:**
- `paginate(10)->appends(request()->query())`と書くことで、URLのクエリパラメータがページネーションリンクに付与されることがわかる

### 検索のコツ

初心者のうちは、以下のポイントを意識すると効率的に情報にたどり着けます。

1. **まずは日本語で素朴に検索する**: 「Laravel + やりたいこと」で検索すると、概要を掴める記事が見つかりやすい
2. **公式ドキュメントを確認する**: 記事で見つけたメソッド名がわかったら、Laravel公式ドキュメントで正確な使い方を確認する
3. **エラーメッセージで検索する**: 実装中にエラーが出たら、そのエラーメッセージをそのまま検索すると解決策が見つかりやすい
4. **「Laravel + 困っている状況」で検索する**: 例えば「Laravel ページネーション 検索条件 消える」のように、困っている状況をそのまま検索すると、同じ問題に直面した人の解決策が見つかる

### 提供されている Blade ファイルの確認

このプロジェクトでは、書籍一覧画面のBladeファイル（`resources/views/books/index.blade.php`）が事前に提供されています。コントローラーの改修により、提供されているビューの検索フォームが正しく機能するようになります。

### 提供されているBladeファイルのポイント

提供されている`books/index.blade.php`には、以下の要素が含まれています。

- **検索フォーム**: `GET`メソッドで`books.index`ルートにリクエストを送信します。検索条件はURLのクエリパラメータとして渡されます。
- **入力値の復元**: `request('keyword')`や`request('genre') == $genre->id ? 'selected' : ''`のようにして、検索実行後もユーザーが入力・選択した条件がフォームに残るようにしています。これにより、ユーザーは自分がどの条件で検索したかを常に把握できます。
- **ページネーションリンク**: `{{ $books->links() }}`でページネーションリンクを表示します。コントローラーで`appends(request()->query())`を使っているので、このリンクには検索条件が付与されます。

## 7. 動作確認 ✅

| 確認項目 | 確認方法 |
|:---|:---|
| （Phase 1B 動作確認時に追記） | |

---

## 8. まとめ ✨

このChapterでは、`when()`メソッドや`switch`文、`whereHas`、`appends()`といった機能を組み合わせることで、柔軟でユーザーフレンドリーな高度検索機能を実装する方法を学びました。条件に応じてクエリを動的に組み立てるという考え方は、実務の様々な場面で応用できる非常に重要なテクニックです。
