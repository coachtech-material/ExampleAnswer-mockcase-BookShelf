# Chapter 10: ランキング機能（集計とSQL）

## 🎯 このセクションで学ぶこと

このセクションでは、レビューの平均評価に基づいて書籍をランキング表示する機能を実装します。

- **集計クエリ**: `join`と`DB::raw`を使って、リレーション先のデータを集計する方法を学びます。
- **並び替え**: 集計結果に基づいてデータを並び替える方法を学びます。
- **SQLの基礎**: `AVG`、`GROUP BY`、`ORDER BY`といったSQLの集計・並び替え構文を理解します。

---

## 🧠 先輩エンジニアの思考プロセス：ランキング機能の設計

ランキング機能を実装する際、以下の点を考慮します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| 何を基準にランキングするか | レビューの平均評価 | ユーザーにとって最も参考になる指標。 |
| レビューがない書籍はどうするか | ランキングから除外 | `join`を使用するため、レビューがない書籍は自動的に除外される。 |
| 何件表示するか | 上位10件 | ランキングとして適切な件数。 |

---

## 10.1. RankingController.php の実装

`RankingController`はChapter 6の「ルート定義とコントローラーの準備」で既に作成済みです。早速、中身を実装していきましょう。

`app/Http/Controllers/RankingController.php`を開き、以下の内容を記述してください。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index()
    {
        $rankedBooks = Book::select('books.*', DB::raw('AVG(reviews.rating) as average_rating'))
            ->join('reviews', 'books.id', '=', 'reviews.book_id')
            ->groupBy('books.id')
            ->orderByDesc('average_rating')
            ->take(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
```

### 📖 コードリーディング：メソッドチェーンの分解

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `use Illuminate\Support\Facades\DB;` | Laravelの`DB`ファサードをインポート。生のSQL式を書くために必要。 | ファサードを使うことで、`DB::raw()`などのデータベース操作メソッドにアクセスできる。 |
| `Book::select('books.*', DB::raw('...'))` | 取得するカラムを明示的に指定。`books.*`で書籍テーブルの全カラムを取得。 | `select()`メソッドで取得カラムをカスタマイズ。`DB::raw()`で生のSQL式を埋め込める。 |
| `DB::raw('AVG(reviews.rating) as average_rating')` | レビューの評価（rating）の平均値を計算し、`average_rating`という別名で取得。 | `AVG()`はSQLの集計関数。`as`で別名を付けることで、後から`$book->average_rating`でアクセス可能。 |
| `->join('reviews', 'books.id', '=', 'reviews.book_id')` | `books`テーブルと`reviews`テーブルを結合。 | 内部結合（INNER JOIN）のため、レビューがない書籍は結果に含まれない。 |
| `->groupBy('books.id')` | 書籍ごとにグループ化。 | `AVG()`などの集計関数を使う場合、`GROUP BY`で何を基準にグループ化するかを指定する必要がある。 |
| `->orderByDesc('average_rating')` | `average_rating`の降順（高い順）で並び替え。 | 平均評価が高い書籍が上位に来る。 |
| `->take(10)` | 上位10件のみ取得。 | ランキングなので、全件取得する必要はない。 |
| `->get()` | クエリを実行し、結果をコレクションとして取得。 | この時点で実際にSQLが発行される。 |
| `compact('rankedBooks')` | 変数`$rankedBooks`をビューに渡す。 | `['rankedBooks' => $rankedBooks]`と同じ意味。 |

> **📝 ルート定義について**
> ランキング機能のルート定義も、Chapter 6で既に定義済みです。そのため、`routes/web.php`を修正する必要はありません。

---

## 10.2. 発展：生成されるSQLの理解

上記のEloquentメソッドチェーンは、内部的に以下のようなSQLを生成しています。

```sql
SELECT 
    books.*,
    AVG(reviews.rating) as average_rating
FROM books
INNER JOIN reviews ON books.id = reviews.book_id
GROUP BY books.id
ORDER BY average_rating DESC
LIMIT 10
```

| SQL構文 | 説明 |
|:---|:---|
| `SELECT books.*, AVG(reviews.rating) as average_rating` | 書籍の全カラムと、レビュー評価の平均値を取得 |
| `INNER JOIN reviews ON books.id = reviews.book_id` | 書籍とレビューを結合（レビューがない書籍は除外） |
| `GROUP BY books.id` | 書籍ごとにグループ化して集計 |
| `ORDER BY average_rating DESC` | 平均評価の高い順に並び替え |
| `LIMIT 10` | 上位10件のみ取得 |

> **🧠 先輩エンジニアの思考プロセス**
> `join`と`DB::raw`を使うことで、SQLの集計機能を直接活用できます。Eloquentの`withAvg`などのメソッドもありますが、複雑な集計やパフォーマンスが重要な場面では、このように生のSQL式を使うことも有効な選択肢です。

これで、ランキング機能の実装が完了しました。次のChapterでは、ジャンル別一覧機能を実装していきます。
