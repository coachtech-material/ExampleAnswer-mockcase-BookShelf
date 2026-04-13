# Chapter 10: 集計の力 - ランキング機能を実装する

## 🎯 このChapterの目標

このチャプターでは、レビューの平均評価に基づいて書籍をランキング表示する機能を実装します。SQLの集計関数（`AVG`, `COUNT`）をEloquentの `DB::raw()` と組み合わせ、JOINベースの効率的なクエリを構築する方法を学びます。

---

## 📖 背景知識

### 集計アプローチの選択

| アプローチ | 特徴 | 本チャプターでの採用 |
|:---|:---|:---|
| `withAvg` / `withCount`（サブクエリ） | 簡潔で読みやすい | -- |
| `join` + `DB::raw`（JOINベース） | SQLの集計を直接制御 | **採用** |

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
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index()
    {
        $rankedBooks = Book::select('books.*', DB::raw('AVG(reviews.rating) as average_rating'), DB::raw('COUNT(reviews.id) as review_count'))
            ->join('reviews', 'books.id', '=', 'reviews.book_id')
            ->groupBy('books.id')
            ->orderByDesc('average_rating')
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
| `Book::select('books.*', DB::raw(...), DB::raw(...))` | 書籍の全カラム + 集計カラムを取得。 |
| `DB::raw('AVG(reviews.rating) as average_rating')` | 平均評価を計算。 |
| `DB::raw('COUNT(reviews.id) as review_count')` | レビュー件数を計算。 |
| `->join('reviews', 'books.id', '=', 'reviews.book_id')` | INNER JOINでレビューが存在する書籍のみ取得。 |
| `->groupBy('books.id')` | 書籍ごとにグループ化。 |
| `->orderByDesc('average_rating')` | 平均評価の高い順に並び替え。 |
| `->take(10)` | 上位10件に制限。 |

### 生成されるSQL

```sql
SELECT books.*, AVG(reviews.rating) as average_rating, COUNT(reviews.id) as review_count
FROM books
INNER JOIN reviews ON books.id = reviews.book_id
GROUP BY books.id
ORDER BY average_rating DESC
LIMIT 10
```

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| DB::raw の使い方 | 「LaravelでDB::raw()を使ったランキングクエリの書き方を教えてください。」 |
| JOIN vs withAvg | 「Laravel の join() と withAvg() の違いを教えてください。」 |

---

## ✨ このChapterのまとめ

- **JOINベースの集計クエリ**: `join()` + `groupBy()` で書籍ごとの平均評価を計算
- **`DB::raw()` の活用**: SQL集計関数を埋め込み
- **INNER JOINによる自動フィルタ**: レビューのない書籍を自動除外

次の Chapter 11 では、**ジャンル別一覧機能**を実装します。
