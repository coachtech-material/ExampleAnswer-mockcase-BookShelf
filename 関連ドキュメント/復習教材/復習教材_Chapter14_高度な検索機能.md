# Chapter 14: 高度な検索機能の実装

## 1. はじめに

基本機能編では、書籍のタイトルと著者名での単純な検索機能を実装しました。応用機能編では、これをさらに拡張し、実務で求められるような「絞り込み」や「並び替え」を含む**高度な検索機能**を実装します。

## 2. 要件の確認

まずは、今回実装する高度な検索機能の要件を再確認しましょう。

| 機能 | 詳細仕様 |
|:---|:---|
| **キーワード検索** | 書籍のタイトルまたは著者名で部分一致検索ができる。 |
| **ジャンル絞り込み** | 特定のジャンルに属する書籍のみを絞り込んで表示できる。 |
| **並び替え** | 「登録日の新しい順」「登録日の古い順」「タイトル順」「評価の高い順」で結果を並び替えられる。 |
| **状態の維持** | 検索条件や並び順を維持したまま、ページネーションが機能する。 |

## 3. 先輩エンジニアの思考プロセス：実装の設計

これから`BookController`の`index`メソッドを改修していきますが、その前に、どのような考え方で実装を進めていくのか、設計段階の思考プロセスを覗いてみましょう。これは、単にコードを書き写すのではなく、「なぜこのコードになるのか」を理解するための重要なステップです。

### 思考1：どうやって複数の検索条件を組み合わせるか？

> 「まず考えるべきは、ユーザーが入力する検索条件（キーワード、ジャンル、並び順）は、常に全てが指定されるわけではない、ということ。キーワードだけで検索することもあれば、ジャンルと並び順だけを指定することもある。つまり、**条件に応じてクエリを動的に組み立てる**必要があるな。」
> 
> 「これは、ベースとなる`Book::query()`に対して、`if`文で条件分岐させながら`where`句や`orderBy`句を繋げていく（メソッドチェーンしていく）のが良さそうだ。この『段階的にクエリを構築する』アプローチは、複雑な検索機能を作る上での基本パターンになる。」

### 思考2：「タイトル or 著者」の検索はどう実現する？

> 「キーワード検索は『タイトルまたは著者』での部分一致。SQLで言えば `WHERE (title LIKE \'%keyword%\') OR author LIKE \'%keyword%\')` という形にしたい。Laravelのクエリビルダでこれを実現するには、`where()`メソッドにクロージャ（無名関数）を渡すテクニックが使える。`$query->where(function($q) { ... })` のように書くことで、`()`で囲まれた論理グループを作れるんだ。これをしないと、他の`where`句との組み合わせで意図しないSQLになってしまう可能性があるから注意が必要だ。」

### 思考3：関連テーブル（ジャンル）での絞り込みは？

> 「ジャンルでの絞り込みは、`books`テーブルに直接ジャンル名があるわけではなく、中間テーブルを介して`genres`テーブルとリレーションしている。こういう**リレーション先のテーブルの条件で絞り込みたい**場合は、`whereHas()`メソッドがまさにうってつけ。`whereHas(\'genres\', function($q) { ... })`と書けば、『指定した条件に合致する`genres`リレーションを持つ`Book`』を簡単に絞り込める。」

### 思考4：評価の高い順ってどうやって並び替える？

> 「『評価の高い順』での並び替えは少し工夫が必要だ。各書籍の平均評価を計算し、その結果でソートしないといけない。これもリレーションの集計機能が使える。`withAvg(\'reviews\', \'rating\')`を使うと、`reviews`リレーションの`rating`カラムの平均値を`reviews_avg_rating`という名前で取得できる。あとは、このエイリアスカラムで`orderByDesc()`すればいい。他の並び替え条件（新着順、タイトル順など）は`switch`文でシンプルに分岐させよう。」

### 思考5：検索条件を維持したままページ移動させたい

> 「最後に忘れてはいけないのが、ユーザー体験。検索結果の2ページ目に移動したときに、検索条件がリセットされて全件表示に戻ってしまったら最悪だ。Laravelのページネーションには、`withQueryString()`という便利なメソッドがある。これを`paginate()`の後ろに付けるだけで、URLのクエリパラメータ（`?keyword=...`など）を自動でページネーションリンクに引き継いでくれる。これは絶対に使うべき機能だね。」

このような思考プロセスを経て、これから見ていく具体的な実装コードが出来上がっていきます。一つ一つのコードが、どの思考に基づいて書かれているのかを意識しながら読み進めてみてください。

## 4. 実装：BookControllerの改修

それでは、上記の思考プロセスを元に、`app/Http/Controllers/BookController.php`の`index`メソッドを以下のように修正します。

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
        $query = Book::with(\'genres\');

        // キーワード検索（応用機能）
        if ($keyword = $request->input(\'keyword\')) {
            $query->where(function ($q) use ($keyword): void {
                $q->where(\'title\', \'like\', "%{$keyword}%")
                  ->orWhere(\'author\', \'like\', "%{$keyword}%");
            });
        }

        // ジャンル絞り込み（応用機能）
        if ($genreId = $request->input(\'genre\')) {
            $query->whereHas(\'genres\', function ($q) use ($genreId): void {
                $q->where(\'genres.id\', $genreId);
            });
        }

        // 並び順（応用機能）
        switch ($request->input(\'sort\')) {
            case \'oldest\':
                $query->oldest();
                break;
            case \'title\':
                $query->orderBy(\'title\');
                break;
            case \'rating\':
                $query->withAvg(\'reviews\', \'rating\')->orderByDesc(\'reviews_avg_rating\');
                break;
            default:
                $query->latest();
                break;
        }

        $books = $query->paginate(10)->withQueryString();
        $genres = Genre::orderBy(\'name\')->get();

        return view(\'books.index\', compact(\'books\', \'genres\'));
    }

    // ... 他のメソッドは省略 ...
}
```

## 5. How to: この実装にたどり着くための調べ方

もし自力でこの実装にたどり着くとしたら、どのようなキーワードで調べれば良いでしょうか？先輩エンジニアが新人の頃にやっていた思考プロセスはこんな感じです。

| やりたいこと | 検索キーワード（例） | たどり着く答え（公式ドキュメントなど） |
|:---|:---|:---|
| **タイトルか著者で検索したい** | `laravel query builder where or` | `where`句のパラメータとしてクロージャを渡すことで、`AND (A OR B)`という条件を作れることがわかる。`orWhere`を単純に繋げると`AND A OR B`になってしまう問題点にも気づける。 |
| **関連テーブルの条件で絞り込みたい** | `laravel eloquent relation where` / `laravel リレーション 絞り込み` | Eloquentの「リレーションの存在クエリ」のセクションに`whereHas`というまさにやりたいこと通りのメソッドが見つかる。 |
| **評価の高い順で並び替えたい** | `laravel order by relation count` / `laravel リレーション 集計 並び替え` | `withCount`や`withAvg`といったメソッドでリレーション先の集計結果をSELECT句に追加し、そのエイリアス（`reviews_avg_rating`）で`orderBy`できることがわかる。 |
| **検索条件をページャーに引き継ぎたい** | `laravel pagination query parameter` / `laravel ページネーション 検索条件 維持` | ページネーションのドキュメントに`withQueryString()`という便利なメソッドが紹介されているのを発見する。 |

## 6. 提供されているBladeファイルの確認

このプロジェクトでは、書籍一覧画面のBladeファイル（`resources/views/books/index.blade.php`）が事前に提供されています。コントローラーの改修により、提供されているビューの検索フォームが正しく機能するようになります。

### 提供されているBladeファイルのポイント

提供されている`books/index.blade.php`には、以下の要素が含まれています。

- **検索フォーム**: `GET`メソッドで`books.index`ルートにリクエストを送信します。検索条件はURLのクエリパラメータとして渡されます。
- **入力値の復元**: `request('keyword')`や`request('genre') == $genre->id ? 'selected' : ''`のようにして、検索実行後もユーザーが入力・選択した条件がフォームに残るようにしています。これにより、ユーザーは自分がどの条件で検索したかを常に把握できます。
- **ページネーションリンク**: `{{ $books->links() }}`でページネーションリンクを表示します。コントローラーで`withQueryString()`を使っているので、このリンクには自動で検索条件が付与されます。

## 7. まとめ

このChapterでは、`if`文や`switch`文、`whereHas`、`withQueryString`といった機能を組み合わせることで、柔軟でユーザーフレンドリーな高度検索機能を実装する方法を学びました。条件に応じてクエリを動的に組み立てるという考え方は、実務の様々な場面で応用できる非常に重要なテクニックです。
