# Chapter 9: ランキング機能（集計とSQL）

## 🎯 このセクションで学ぶこと

このセクションでは、レビューの平均評価に基づいて書籍をランキング表示する機能を実装します。

- **集計クエリ**: `withAvg`や`withCount`を使って、リレーション先のデータを集計する方法を学びます。
- **並び替え**: 集計結果に基づいてデータを並び替える方法を学びます。
- **Eloquentの強力な機能**: 複雑なSQLを書かずに、Eloquentのメソッドチェーンで集計・並び替えを実現します。

---

## 🧠 先輩エンジニアの思考プロセス：ランキング機能の設計

ランキング機能を実装する際、以下の点を考慮します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| 何を基準にランキングするか | レビューの平均評価 | ユーザーにとって最も参考になる指標。 |
| レビューがない書籍はどうするか | ランキングから除外 | 評価がない書籍を上位に表示しても意味がない。 |
| 同点の場合はどうするか | レビュー数が多い方を上位に | より多くの評価を受けている方が信頼性が高い。 |

---

## 9.1. コントローラーの作成

```bash
sail artisan make:controller RankingController
```

---

## 9.2. RankingControllerの実装

```php
// app/Http/Controllers/RankingController.php

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index()
    {
        $books = Book::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->having('reviews_count', '>', 0)
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->take(10)
            ->get();

        return view('ranking.index', compact('books'));
    }
}
```

### 9.2.1. コードリーディング：メソッドチェーンの分解

```php
$books = Book::withAvg('reviews', 'rating')
    ->withCount('reviews')
    ->having('reviews_count', '>', 0)
    ->orderByDesc('reviews_avg_rating')
    ->orderByDesc('reviews_count')
    ->take(10)
    ->get();
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `Book::withAvg('reviews', 'rating')` | `reviews`リレーションの`rating`カラムの平均値を計算し、`reviews_avg_rating`という仮想カラムとして追加します。 | `Builder` | 生成されるSQL: `SELECT *, (SELECT AVG(rating) FROM reviews WHERE books.id = reviews.book_id) AS reviews_avg_rating` |
| `->withCount('reviews')` | `reviews`リレーションのレコード数を計算し、`reviews_count`という仮想カラムとして追加します。 | `Builder` | - |
| `->having('reviews_count', '>', 0)` | `reviews_count`が0より大きいレコードのみに絞り込みます。 | `Builder` | レビューがない書籍を除外します。 |
| `->orderByDesc('reviews_avg_rating')` | `reviews_avg_rating`の降順（高い順）で並び替えます。 | `Builder` | 平均評価が高い書籍が上位に来ます。 |
| `->orderByDesc('reviews_count')` | 同点の場合、`reviews_count`の降順で並び替えます。 | `Builder` | レビュー数が多い書籍が上位に来ます。 |
| `->take(10)` | 上位10件のみ取得します。 | `Builder` | ランキングなので、全件取得する必要はありません。 |
| `->get()` | クエリを実行し、結果をコレクションとして取得します。 | `Collection` | - |

> **💡 ポイント: `where` vs `having`**
> - **`where`**: 集計前のデータに対する条件（通常のカラム）
> - **`having`**: 集計後のデータに対する条件（`withCount`や`withAvg`で作成した仮想カラム）
> 
> `reviews_count`は`withCount`で作成した仮想カラムなので、`having`を使います。

---

## 9.3. ルートの追加

```php
// routes/web.php

// 認証不要のルート
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
```

---

## 9.4. ビューの作成

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/ranking
touch resources/views/ranking/index.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 9.5. Bladeテンプレートでのランキング表示例

```blade
{{-- ランキング一覧 --}}
<ol class="space-y-4">
    @foreach ($books as $index => $book)
        <li class="flex items-center gap-4 p-4 bg-white rounded-lg shadow">
            {{-- 順位 --}}
            <span class="text-2xl font-bold text-gray-500">
                {{ $index + 1 }}
            </span>

            {{-- 書籍情報 --}}
            <div class="flex-1">
                <a href="{{ route('books.show', $book) }}" class="text-lg font-semibold hover:underline">
                    {{ $book->title }}
                </a>
                <p class="text-sm text-gray-600">{{ $book->author }}</p>
            </div>

            {{-- 評価情報 --}}
            <div class="text-right">
                <p class="text-xl font-bold text-yellow-500">
                    ★ {{ number_format($book->reviews_avg_rating, 1) }}
                </p>
                <p class="text-sm text-gray-500">
                    {{ $book->reviews_count }}件のレビュー
                </p>
            </div>
        </li>
    @endforeach
</ol>
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `$index + 1` | 0始まりのインデックスを1始まりの順位に変換します。 | - |
| `$book->reviews_avg_rating` | `withAvg`で追加された仮想カラムにアクセスします。 | 通常のプロパティと同じようにアクセスできます。 |
| `number_format($book->reviews_avg_rating, 1)` | 小数点以下1桁で表示します。 | 例: `4.5` |
| `$book->reviews_count` | `withCount`で追加された仮想カラムにアクセスします。 | - |

---

## 9.6. 発展：生SQLとの比較

Eloquentのメソッドチェーンは、内部的に以下のようなSQLを生成しています。

```sql
SELECT 
    books.*,
    (SELECT AVG(rating) FROM reviews WHERE books.id = reviews.book_id) AS reviews_avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE books.id = reviews.book_id) AS reviews_count
FROM books
HAVING reviews_count > 0
ORDER BY reviews_avg_rating DESC, reviews_count DESC
LIMIT 10
```

> **🧠 先輩エンジニアの思考プロセス**
> Eloquentを使うことで、複雑なSQLを書かずに同じ結果を得られます。ただし、パフォーマンスが重要な場面では、生SQLや`DB::raw()`を使うことも検討します。

これで、ランキング機能の実装が完了しました。次のChapterでは、検索機能を実装していきます。
