# Chapter 10: ランキング機能（集計とSQL）

## 🎯 このセクションで学ぶこと

このセクションでは、レビューの平均評価に基づいて書籍をランキング表示する機能を実装します。

- **Eloquentでの集計**: `withAvg` や `withCount` を使い、関連するデータの平均値や件数を取得する方法を学びます。
- **並び替え**: 集計した結果（平均評価）に基づいて、データを降順に並び替える方法を学びます。
    
---

## 🧠 先輩エンジニアの思考プロセス：ランキング機能の設計

ランキング機能を実装する際、以下の点を考慮します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| 何を基準にランキングするか | レビューの平均評価 | ユーザーにとって最も参考になる指標。 |
| レビューがない書籍はどうするか | 0点として扱う（または下位に表示） | `withAvg` を使うとレビューがない書籍も取得できるため、より柔軟な表示制御が可能。 |
| 何件表示するか | 上位10件 | ランキングとして適切な件数。 |

---

## 10.1. RankingController.php の実装

`RankingController`はChapter 6の「ルート定義とコントローラーの準備」で既に作成済みです。早速、中身を実装していきましょう。

`app/Http/Controllers/RankingController.php`を開き、以下の内容を記述してください。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;

class RankingController extends Controller
{
    public function index()
    {
        // レビューが存在する書籍に絞り込み、評価の高い順に10件取得
        $rankedBooks = Book::whereHas('reviews')
            ->withCount('reviews as review_count')
            ->withAvg('reviews as average_rating', 'rating')
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
| `whereHas('reviews')` | レビューが少なくとも1件以上存在する書籍のみに絞り込みます。 | レビューがない（平均評価が計算できない）書籍をランキングから除外するために使用します。 |
| `withCount('reviews as review_count')` | 各書籍に関連付けられたレビューの数を `review_count` という名前で取得します。 | `$book->review_count` で件数にアクセスできるようになります。 |
| `withAvg('reviews as average_rating', 'rating')` | `reviews` テーブルの `rating` カラムの平均値を計算し、`average_rating` という名前で取得します。 | 内部的にサブクエリが発行されます。`groupBy` を明示的に書く必要がなく、可読性の高い記述が可能です。 |
| `orderByDesc('average_rating')` | 集計した平均評価が高い順に並び替えます。 | `DESC` は降順（大きい順）を意味します。 |
| `take(10)` | 取得する件数を最大10件に制限します。 | SQLの `LIMIT 10` に相当します。 |


> **📝 ルート定義について**
> ランキング機能のルート定義も、Chapter 6で既に定義済みです。そのため、`routes/web.php`を修正する必要はありません。

---

## 10.2. 発展：生成されるSQLの理解

withAvg などのメソッドを使うと、Laravelは内部的に「サブクエリ」を利用した効率的なSQLを生成します。

```sql
SELECT 
    books.*, 
    (SELECT COUNT(*) FROM reviews WHERE reviews.book_id = books.id) as review_count,
    (SELECT AVG(rating) FROM reviews WHERE reviews.book_id = books.id) as average_rating
FROM books
WHERE EXISTS (
    SELECT * FROM reviews WHERE reviews.book_id = books.id
)
ORDER BY average_rating DESC
LIMIT 10
```

### 📖 SQLコードリーディング：生成されたクエリの解剖

| 構文 | 役割 | 💡 ポイント |
|:---|:---|:---|
| `SELECT books.*` | `books` テーブルの全てのカラムを取得します。 | 書籍の基本情報をすべて取得します。 |
| `(SELECT ... ) as` `review_count` / `average_rating` | セレクト句の中で、各書籍に紐づくレビューの数と平均値を計算しています。 | これを「相関サブクエリ」と呼びます。1行ごとにその書籍に該当するデータを集計しに行きます。 |
| `WHERE EXISTS (...)` | レビューが1件以上存在する書籍のみを抽出対象にします。 | `whereHas` メソッドによって生成されます。条件に合致しない（レビューがない）書籍をこの段階で弾きます。 |
| `ORDER BY average_rating DESC` | 計算した平均評価を基準に、大きい順（降順）に並び替えます。 | 評価が高い順に並べるための指定です。 |
| `LIMIT 10` | 取得するデータの最大件数を10件に制限します。 | ランキングの上位のみを効率的に取得します。 |

> 🧠 **先輩エンジニアの思考プロセス**：`withAvg` を使う理由
> 
> 生の SQL（`DB::raw`）を書く手法は、複雑なクエリには向いていますが、記述ミスによるエラーや SQLインジェクションのリスクに注意が必要です。今回の手法は、Laravel の標準機能に乗っかることで、直感的でメンテナンスしやすいコードを実現しています。

これで、ランキング機能の実装が完了しました。次のChapterでは、ジャンル別一覧機能を実装していきます。
