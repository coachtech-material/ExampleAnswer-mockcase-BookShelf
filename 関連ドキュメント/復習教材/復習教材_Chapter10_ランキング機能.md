# Chapter 10: 集計の力 - ランキング機能を実装する

## 🎯 このChapterの目標

このチャプターでは、レビューの平均評価に基づいて書籍をランキング表示する機能を実装します。Eloquent の `withAvg` / `withCount` を使い、サブクエリベースの簡潔なクエリでランキングを構築する方法を学びます。

---

## 📖 背景知識

### 集計アプローチの選択

| アプローチ | 特徴 | 本チャプターでの採用 |
|:---|:---|:---|
| `withAvg` / `withCount`（サブクエリベース） | 簡潔で読みやすい | **採用** |
| `join` + `DB::raw`（JOINベース） | SQLの集計を直接制御 | -- |

---

## 📋 要件の確認

| 項目 | 仕様 |
|:---|:---|
| ランキング基準 | レビューの平均評価（降順） |
| 対象書籍 | レビューが1件以上ある書籍のみ |
| 表示件数 | 上位10件 |

---

## 🚀 コードの実装

### `app/Http/Controllers/RankingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;

class RankingController extends Controller
{
    public function index()
    {
        $rankedBooks = Book::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->has('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->take(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
```

---

## 🔍 コードリーディング

### メソッドチェーンの分解

| コード | 解説 |
|:---|:---|
| `Book::withAvg('reviews', 'rating')` | サブクエリで各書籍のレビュー平均評価を `reviews_avg_rating` として取得。 |
| `->withCount('reviews')` | サブクエリで各書籍のレビュー件数を `reviews_count` として取得。 |
| `->has('reviews')` | レビューが1件以上存在する書籍のみに絞り込み。 |
| `->orderByDesc('reviews_avg_rating')` | 平均評価の高い順に並び替え。 |
| `->take(10)` | 上位10件に制限。 |

### 生成されるSQL

```sql
SELECT "books".*,
       (SELECT AVG("reviews"."rating") FROM "reviews" WHERE "books"."id" = "reviews"."book_id") AS "reviews_avg_rating",
       (SELECT COUNT(*) FROM "reviews" WHERE "books"."id" = "reviews"."book_id") AS "reviews_count"
FROM "books"
WHERE EXISTS (SELECT * FROM "reviews" WHERE "books"."id" = "reviews"."book_id")
ORDER BY "reviews_avg_rating" DESC
LIMIT 10
```

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| withAvg の使い方 | 「Laravel の withAvg() を使ったランキングクエリの書き方を教えてください。」 |
| withAvg vs JOIN | 「Laravel の withAvg() と join() + DB::raw() の違いを教えてください。」 |

---

## ✨ このChapterのまとめ

- **サブクエリベースの集計**: `withAvg` / `withCount` で書籍ごとの平均評価・レビュー件数を取得
- **`has()` による絞り込み**: レビューのない書籍を除外
- **Eloquentらしい記述**: `DB::raw()` を使わず、メソッドチェーンだけで集計クエリを構築

次の Chapter 11 では、**ジャンル別一覧機能**を実装します。
