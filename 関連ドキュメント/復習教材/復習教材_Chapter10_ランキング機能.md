# Chapter 10: 集計の力 - ランキング機能を実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、レビューの平均評価に基づいて書籍をランキング表示する機能を実装します。SQLの集計関数（`AVG`, `COUNT`）をEloquentの `DB::raw()` と組み合わせ、JOINベースの効率的なクエリを構築する方法を学びます。

- **SQLの集計クエリ**: `AVG()` や `COUNT()` を使い、関連データの平均値や件数を取得する方法を学びます
- **`withAvg()` / `withCount()`**: リレーション先の平均値や件数をサブクエリで取得する方法を学びます
- **`has()` によるフィルタ**: リレーションが存在するレコードだけに絞り込む方法を学びます

## 1. はじめに 📖

### なぜランキングが必要なのか

書籍レビューアプリケーションにおいて、「評価の高い書籍はどれか」という情報はユーザーにとって非常に価値があります。ランキング機能は、レビューデータを集計して、ユーザーに有用な情報を提供する機能です。

### 集計アプローチの選択

ランキングの実装には複数のアプローチがありますが、本チャプターでは **JOINベースの集計クエリ** を採用しています。

| アプローチ | 特徴 | 本チャプターでの採用 |
|:---|:---|:---|
| `withAvg` / `withCount`（サブクエリ） | 簡潔で読みやすい。N+1問題なし | **採用** |
| `join` + `DB::raw`（JOINベース） | SQLの集計を直接制御。複雑な条件にも対応可能 | - |

JOINベースのアプローチを採用することで、SQLの集計クエリの仕組みを直接学ぶことができます。

## 2. 要件の確認 📋

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| 評価ランキング表示 | GET | `/ranking` | RankingController@index | 不要 |

### ランキングの仕様

| 項目 | 仕様 |
|:---|:---|
| ランキング基準 | レビューの平均評価（降順） |
| 対象書籍 | レビューが1件以上ある書籍のみ |
| 表示件数 | 上位10件 |
| 表示情報 | 書籍情報 + 平均評価 + レビュー件数 |

## 3. 先輩エンジニアの思考プロセス 💭

### Point 1: JOINで関連テーブルを結合する

ランキングを計算するためには、`books` テーブルと `reviews` テーブルのデータを結合する必要があります。`join()` を使うことで、レビューが存在する書籍だけを取得し、同時に集計も行えます。

### Point 2: `DB::raw()` で生のSQL式を埋め込む

Eloquentのメソッドだけでは `AVG(reviews.rating)` のようなSQL関数を直接書けません。`DB::raw()` を使うことで、SQL式をそのまま埋め込めます。

```php
// DB::raw() でSQL関数を埋め込む
Book::select('books.*', DB::raw('AVG(reviews.rating) as average_rating'))
```

### Point 3: `groupBy()` で書籍ごとに集計する

JOINした結果は書籍1冊に対して複数のレビュー行が展開されるため、`groupBy('books.id')` で書籍ごとにグループ化し、そのグループ内で `AVG` や `COUNT` を計算します。

## 4. 実装 🚀

`RankingController` は Chapter 06 の「ルート定義とコントローラーの準備」で既に作成済みです。中身を実装していきましょう。

### `app/Http/Controllers/RankingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキングを表示
     */
    public function index(): View
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

## 5. コードの詳細解説 🔍

### メソッドチェーンの分解

| コード / 構文 | 解説 |
|:---|:---|
| `index(): View` | ランキングページを表示するメソッド。`Route::get('/ranking', ...)` に対応します。認証不要のルートです。 |
| `Book::withAvg('reviews', 'rating')` | `reviews` リレーションの `rating` カラムの平均値をサブクエリで取得。結果は `reviews_avg_rating` 属性に格納されます。 |
| `->withCount('reviews')` | 各書籍のレビュー件数をサブクエリで取得。結果は `reviews_count` 属性に格納されます。 |
| `->has('reviews')` | レビューが1件以上ある書籍のみに絞り込みます。 |
| `->orderByDesc('reviews_avg_rating')` | 平均評価の高い順（降順）に並び替えます。 |
| `->take(10)` | 取得件数を上位10件に制限します。SQLの `LIMIT 10` に相当します。 |

### 生成されるSQL

```sql
SELECT 
    books.*, 
    AVG(reviews.rating) as average_rating,
    COUNT(reviews.id) as review_count
FROM books
INNER JOIN reviews ON books.id = reviews.book_id
GROUP BY books.id
ORDER BY average_rating DESC
LIMIT 10
```

### SQLの処理フロー

| ステップ | SQL句 | 処理内容 |
|:---|:---|:---|
| 1 | `FROM books INNER JOIN reviews` | `books` テーブルと `reviews` テーブルを結合。レビューのない書籍は除外される |
| 2 | `GROUP BY books.id` | 書籍IDごとにグループ化。1書籍に複数レビューがある場合、1行にまとめられる |
| 3 | `SELECT ... AVG(...) ... COUNT(...)` | 各グループ内で平均評価とレビュー件数を計算 |
| 4 | `ORDER BY average_rating DESC` | 平均評価の高い順に並び替え |
| 5 | `LIMIT 10` | 上位10件のみ取得 |

## 6. この実装にたどり着くための調べ方 🧐

### Step 1: 公式ドキュメントを読みやすくまとめる

**プロンプト例**
```
以下はLaravelのクエリビルダに関する公式ドキュメントの一部です。
特に「集計関数」「join」「groupBy」に焦点を当ててまとめてください。

出力してほしい内容：
- DB::raw() を使うべき場面と使わないべき場面
- join() と withAvg()/withCount() の違い
- groupBy を使う際の注意点
- SQLインジェクションのリスクと対策

--- ここから ---
（ここにLaravelのQuery Builderに関する公式ドキュメントを貼り付ける）
--- ここまで ---
```

### Step 2: 「なぜそうなる？」をはっきりさせる

**プロンプト例**
```
LaravelでDB::raw()を使ったランキングクエリを実装しようとしています。
私の理解はこうです：
「joinでbooksとreviewsを結合し、groupByで書籍ごとにまとめ、
AVGで平均評価を計算してorderByDescで降順に並べる。」

お願い：
1) INNER JOINの結果、レビューがない書籍が除外される理由を教えてください
2) groupByなしでAVGを使うとどうなるか教えてください
3) DB::raw() のSQLインジェクションリスクについて教えてください
4) withAvg を使う方法との違い（メリット・デメリット）を教えてください
```

## 7. 動作確認 ✅

| 確認項目 | 確認方法 |
|:---|:---|
| ランキング画面の表示 | `/ranking` にアクセスすると、レビューが付いている書籍が平均評価の降順で上位 10 件まで表示される |
| レビューなし書籍は除外 | レビューが 1 件も付いていない書籍がランキングに含まれていないこと（`has('reviews')` の効果） |
| 平均評価とレビュー件数 | 各書籍に `reviews_avg_rating`（平均評価）と `reviews_count`（レビュー件数）が表示されること |
| 認証不要 | ログアウト状態でも `/ranking` にアクセス可能（認証ミドルウェア外） |
| N+1 が発生しない | `sail artisan db:listen` 等で発行 SQL を観察し、書籍ごとに個別 SQL が発行されていないこと |

---

## 8. まとめ ✨

このチャプターでは、ランキング機能を実装しました。

- **`withAvg()` / `withCount()`**: サブクエリで書籍ごとの平均評価とレビュー件数を取得しました
- **`has()` によるフィルタ**: レビューが存在する書籍のみに絞り込みました
- **Eloquentベースの集計**: JOINや `DB::raw()` を使わず、シンプルなメソッドチェーンで実現しました

次の Chapter 11 では、特定のジャンルに属する書籍を一覧表示する**ジャンル別一覧機能**を実装します。
