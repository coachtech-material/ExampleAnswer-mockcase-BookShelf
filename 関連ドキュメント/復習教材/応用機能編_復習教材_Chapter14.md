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

これらの要件を満たすために、`BookController`の`index`メソッドを改修していきます。

## 3. 実装：BookControllerの改修

それでは、`app/Http/Controllers/BookController.php`の`index`メソッドを以下のように修正します。

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
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンル絞り込み（応用機能）
        if ($genreId = $request->input('genre')) {
            $query->whereHas('genres', function ($q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        }

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

        $books = $query->paginate(10)->withQueryString();
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    // ... 他のメソッドは省略 ...
}
```

## 4. 先輩エンジニアの思考プロセスと「調べ方」

なぜこのような実装になっているのか、先輩エンジニアの視点で解説します。

### 思考1：クエリビルダを段階的に組み立てる

> 「検索条件はユーザーの操作によって増えたり減ったりする。`if`文を使って、リクエストに特定のパラメータ（`keyword`や`genre`）が存在する場合にのみ、クエリビルダに`where`句を追加していくのが定石だね。こうすることで、条件がない場合は全件検索、条件がある場合はその条件で絞り込まれた検索、というように柔軟に対応できる。」

### 思考2：複雑な`WHERE`句はクロージャでまとめる

> 「キーワード検索では、『タイトル OR 著者』で検索したい。`orWhere`を使うんだけど、他の`where`句と混ざると意図しない結果になることがある（`WHERE A AND B OR C`と`WHERE A AND (B OR C)`の違い）。これを避けるために、`where(function($q) { ... })`のようにクロージャ（無名関数）で囲むのが安全策。これで`AND (title LIKE ? OR author LIKE ?)`というSQLが生成されるんだ。」

### 思考3：リレーション先のテーブルで絞り込むなら`whereHas`

> 「ジャンルでの絞り込みは、`books`テーブルではなく、リレーション先の`genres`テーブルのIDで絞り込む必要がある。こういう時は`whereHas`が便利。`whereHas`の第一引数にリレーション名、第二引数にそのリレーション先のテーブルに対する絞り込み条件を書くクロージャを渡す。これで『特定ジャンルに属する書籍』を効率的に取得できる。」

### 思考4：並び替えは`switch`文でシンプルに

> 「並び替えの条件もユーザーの選択によって変わる。`switch`文を使うと、`sort`パラメータの値に応じて処理をきれいに分岐できる。`default`ケースでデフォルトの並び順（今回は`latest()`）を指定しておくのが親切だね。評価順（`rating`）の場合は、`withAvg`で平均評価を計算し、その結果（`reviews_avg_rating`）で`orderByDesc`する必要がある点に注意しよう。」

### 思考5：ページネーションと検索条件の維持は`withQueryString()`にお任せ

### How to: この実装にたどり着くための調べ方

> 「じゃあ、こういう機能要件から、どうやって`whereHas`や`withQueryString`みたいな具体的なメソッドにたどり着くのか？僕が新人の頃にやっていた思考プロセスはこんな感じだよ」

| やりたいこと | 検索キーワード（例） | たどり着く答え（公式ドキュメントなど） |
|:---|:---|:---|
| **タイトルか著者で検索したい** | `laravel query builder where or` | `where`句のパラメータとしてクロージャを渡すことで、`AND (A OR B)`という条件を作れることがわかる。`orWhere`を単純に繋げると`AND A OR B`になってしまう問題点にも気づける。 |
| **関連テーブルの条件で絞り込みたい** | `laravel eloquent relation where` / `laravel リレーション 絞り込み` | Eloquentの「リレーションの存在クエリ」のセクションに`whereHas`というまさにやりたいこと通りのメソッドが見つかる。 |
| **評価の高い順で並び替えたい** | `laravel order by relation count` / `laravel リレーション 集計 並び替え` | `withCount`や`withAvg`といったメソッドでリレーション先の集計結果をSELECT句に追加し、そのエイリアス（`reviews_avg_rating`）で`orderBy`できることがわかる。 |
| **検索条件をページャーに引き継ぎたい** | `laravel pagination query parameter` / `laravel ページネーション 検索条件 維持` | ページネーションのドキュメントに`withQueryString()`という便利なメソッドが紹介されているのを発見する。 |

> 「検索結果が複数ページにわたる場合、2ページ目に移動したときに検索条件が消えてしまったらユーザーはがっかりする。`paginate(10)`の後ろに`withQueryString()`を繋げるだけで、Laravelが自動的にURLのクエリパラメータ（`?keyword=...&genre=...`）をページネーションのリンクに引き継いでくれる。これは本当に便利だから絶対に覚えておこう。」

## 5. ビューの実装

コントローラーの改修に合わせて、ビューファイル（`resources/views/books/index.blade.php`）に検索フォームを追加します。

```html
<!-- resources/views/books/index.blade.php の一部 -->

<div class="mb-4">
    <form action="{{ route('books.index') }}" method="GET" class="flex items-center space-x-2">
        <input type="text" name="keyword" placeholder="書籍名または著者名" value="{{ request('keyword') }}" class="border rounded px-2 py-1">
        <select name="genre" class="border rounded px-2 py-1">
            <option value="">すべてのジャンル</option>
            @foreach ($genres as $genre)
                <option value="{{ $genre->id }}" {{ request('genre') == $genre->id ? 'selected' : '' }}>
                    {{ $genre->name }}
                </option>
            @endforeach
        </select>
        <select name="sort" class="border rounded px-2 py-1">
            <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>登録日の新しい順</option>
            <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>登録日の古い順</option>
            <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>タイトル順</option>
            <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>評価の高い順</option>
        </select>
        <button type="submit" class="bg-blue-500 text-white px-4 py-1 rounded">検索</button>
    </form>
</div>

<!-- 書籍一覧表示... -->

<div class="mt-4">
    {{ $books->links() }}
</div>
```

### 実装のポイント

- **フォームの`action`と`method`**: `GET`メソッドで`books.index`ルートにリクエストを送信します。検索条件はURLのクエリパラメータとして渡されます。
- **入力値の復元**: `request('keyword')`や`request('genre') == $genre->id ? 'selected' : ''`のようにして、検索実行後もユーザーが入力・選択した条件がフォームに残るようにしています。これにより、ユーザーは自分がどの条件で検索したかを常に把握できます。
- **ページネーションリンク**: `{{ $books->links() }}`でページネーションリンクを表示します。コントローラーで`withQueryString()`を使っているので、このリンクには自動で検索条件が付与されます。

## 6. まとめ

このChapterでは、`if`文や`switch`文、`whereHas`、`withQueryString`といった機能を組み合わせることで、柔軟でユーザーフレンドリーな高度検索機能を実装する方法を学びました。条件に応じてクエリを動的に組み立てるという考え方は、実務の様々な場面で応用できる非常に重要なテクニックです。
