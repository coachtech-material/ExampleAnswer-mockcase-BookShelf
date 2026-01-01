'''
# Chapter 9: ランキング機能（集計とSQL）

このChapterでは、レビューの平均評価が高い書籍をランキング形式で表示する機能を実装します。ここでは、Eloquentリレーションシップだけでは効率的に処理できない「集計処理」を、クエリビルダとSQLを直接使って実装する方法を学びます。

---

## 9-1. 先輩エンジニアの思考プロセス：Eloquentの限界とSQLの活用

### Step 1: 要件から「集計」の必要性を見抜く

まずは要件を確認します。

- **要件**: 「レビューの平均評価が高い順に書籍トップ10をランキング形式で表示する」

この要件には、これまでとは異なる性質が含まれています。

- **集計**: 書籍ごとに、複数のレビューの`rating`を**平均（AVG）**する必要がある。
- **並び替え**: 計算した平均評価で**並び替える（ORDER BY）**必要がある。

> **先輩エンジニアの思考:**
> 「これは単純なデータ取得（CRUD）ではないな。『集計』と『集計結果に基づくソート』が必要だ。こういう処理をアプリケーション側（PHP）でやろうとすると、パフォーマンスが著しく低下する可能性がある。データベースの得意なことはデータベースに任せるべきだ。つまり、SQLの力を借りる場面だ。」

### Step 2: 実装アプローチの比較検討

この要件を実装するには、大きく分けて2つのアプローチが考えられます。

#### アプローチA: Eloquentリレーションだけで頑張る方法（悪い例）

```php
// 1. まず全ての書籍を取得
$allBooks = Book::all();

// 2. PHP側で各書籍の平均評価を計算
$booksWithAvg = $allBooks->map(function ($book) {
    // このループ内で、書籍ごとにクエリが発行されてしまう (N+1問題)
    $book->average_rating = $book->reviews->avg('rating');
    return $book;
});

// 3. PHP側でソートし、上位10件を取得
$rankedBooks = $booksWithAvg->sortByDesc('average_rating')->take(10);
```

- **問題点**: 書籍が1000冊あれば、1001回（最初の全件取得 + ループ内の1000回）のクエリが発行されてしまいます。これは**N+1問題**の典型例であり、データが増えるにつれてパフォーマンスが致命的に悪化します。

#### アプローチB: クエリビルダでSQLを組み立てる方法（良い例）

やりたいことを、まずSQLで考えます。

```sql
SELECT
    books.*, -- 書籍の全情報
    AVG(reviews.rating) as average_rating -- レビュー評価の平均値を `average_rating` という名前で取得
FROM
    books
INNER JOIN -- booksテーブルとreviewsテーブルを結合
    reviews ON books.id = reviews.book_id
GROUP BY -- 書籍IDでグループ化
    books.id
ORDER BY -- 計算した平均評価で降順に並び替え
    average_rating DESC
LIMIT 10; -- 上位10件に絞り込む
```

このSQLを、Laravelのクエリビルダを使ってPHPのコードに「翻訳」します。

> **先輩エンジニアの思考:**
> 「Eloquentリレーションは便利だが、万能ではない。特に、複数のテーブルをまたいだ集計処理は、SQL（クエリビルダ）の出番だ。アプローチAのような実装は、小規模なデータでは動くかもしれないが、将来のパフォーマンス問題を予見できない未熟なコードと言える。常にデータ量の増加を想定し、最も効率的な方法を選択するのがプロの仕事だ。」

---

## 9.2. 部品の作成と実装

### 1. コントローラーの作成

```bash
sail artisan make:controller RankingController
```

### 2. ルーティングの定義 (`routes/web.php`)

誰でも閲覧できる公開ルートとして、ランキングページのルートを定義します。

```php
// `routes/web.php` の `Route::middleware('auth')` の外に記述
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
```

### 3. コントローラーの実装 (`RankingController.php`)

先ほど考えたSQLを、クエリビルダを使って実装します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\DB; // DBファサードをインポート
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        // アプローチB（良い例）の実装
        $rankedBooks = Book::query() // クエリビルダを開始
            // SELECT books.*, AVG(reviews.rating) as average_rating
            ->select('books.*', DB::raw('AVG(reviews.rating) as average_rating'))
            // INNER JOIN reviews ON books.id = reviews.book_id
            ->join('reviews', 'books.id', '=', 'reviews.book_id')
            // GROUP BY books.id
            ->groupBy('books.id')
            // ORDER BY average_rating DESC
            ->orderByDesc('average_rating')
            // LIMIT 10
            ->take(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
```

> **【学習のポイント】**
> - `DB::raw()`: Eloquentのメソッドでは表現できない、複雑なSQLの式（ここでは`AVG()`関数）を直接記述するための機能です。
> - `groupBy('books.id')`: `AVG()`のような集計関数を使う場合、どの単位で集計するかを`groupBy`で指定する必要があります。今回は「書籍ごと」なので`books.id`でグループ化します。
> - メソッドチェーン: クエリビルダは、このようにメソッドを繋げていくことで、SQL文を流れるように組み立てることができます。

---

## 9.3. ビューの実装

### ランキング表示画面 (`resources/views/ranking/index.blade.php`)

コントローラーから渡された`$rankedBooks`コレクションをループして、ランキングを表示します。

```html
<x-app-layout>
    <x-slot name="header">
        <h2>評価ランキング TOP 10</h2>
    </x-slot>

    <div>
        <ol>
            @foreach($rankedBooks as $index => $book)
                <li>
                    <span>{{ $index + 1 }}位</span>
                    <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
                    <span>
                        <!-- `average_rating` はDB::rawで計算した別名 -->
                        (平均評価: {{ number_format($book->average_rating, 2) }} ★)
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
</x-app-layout>
```

> **【学習のポイント】**
> `$book->average_rating`というプロパティに注目してください。これは`books`テーブルに存在するカラムではありませんが、コントローラーで`DB::raw('...') as average_rating`と別名を付けたことで、あたかもモデルのプロパティであるかのようにアクセスできています。

---

## 9.4. 動作確認

1.  複数の書籍に、それぞれ異なる評価点（例: 5点、3点、1点など）でレビューをいくつか投稿します。
2.  `/ranking`にアクセスし、レビューの平均評価が高い順に書籍が並んでいることを確認します。
3.  ランキングは上位10件までしか表示されないことを確認します。
4.  書籍名をクリックすると、その書籍の詳細ページに正しく遷移することを確認します。

これで、ランキング機能の実装が完了しました。Eloquentの便利さと、SQLのパワフルさを適切に使い分けるスキルは、高度な機能を実装する上で不可欠です。
'''
