# Chapter 17: マイ読書レポート機能の実装 (Collectionメソッド活用)

## 1. はじめに

これまでの機能開発では、主にデータベース（Eloquent）のクエリを駆使してデータを取得・加工してきました。しかし、複雑な集計や統計処理を行おうとすると、クエリだけでは限界があったり、コードが複雑になったりすることがあります。

そこで登場するのが、Laravelの**Collection（コレクション）**です。Collectionは、配列やデータベースの結果セットを操作するための非常に強力で便利なクラスです。SQLの`GROUP BY`や集計関数に似た操作を、PHPのコード上で、より柔軟かつ宣言的に行うことができます。

このChapterでは、ユーザー自身の読書活動を可視化する「マイ読書レポート」機能を実装しながら、Collectionメソッドの威力を体感します。

## 2. 要件の確認

| 機能 | 詳細仕様 |
|:---|:---|
| **読書サマリー** | 総レビュー数、平均評価、読んだ本の冊数などを表示する。 |
| **評価分布** | 5段階評価（1〜5）が、それぞれ何件投稿されたかをグラフなどで可視化する。 |
| **ジャンル別統計** | どのジャンルの本を何冊読み、その平均評価はどうだったか、などをランキング形式で表示する。 |
| **読書継続日数** | レビューを投稿した日にちを元に、最長の連続投稿日数（読書継続日数）を計算して表示する。 |

## 3. 実装

### 3.1. ルートとコントローラーの準備

まず、レポート表示用のルートとコントローラーを作成します。

```bash
sail artisan make:controller ReportController
```

`routes/web.php`にルートを追加します。

```php
// routes/web.php
use App\Http\Controllers\ReportController;

// ...
Route::middleware(\'auth\')->group(function () {
    // ...
    Route::get(\'/reports\', [ReportController::class, \'index\'])->name(\'reports.index\');
});
```

### 3.2. ReportControllerの実装

`ReportController`に、統計データを生成し、ビューに渡すロジックを実装します。ここがCollectionメソッド活用の本番です。

```php
// app/Http/Controllers/ReportController.php

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポートを表示
     */
    public function index(): View
    {
        $user = Auth::user();

        // ユーザーのレビューを全て取得（リレーション含む）
        $reviews = Review::with([\'book.genres\'])
            ->where(\'user_id\', $user->id)
            ->get();

        // お気に入り書籍を取得
        $favoriteBooks = $user->favoriteBooks()->with(\'genres\')->get();

        // 統計データを生成
        $stats = $this->generateStats($reviews, $favoriteBooks);

        return view(\'reports.index\', compact(\'stats\'));
    }

    /**
     * 統計データを生成
     *
     * @param Collection<int, Review> $reviews
     * @param Collection<int, Book> $favoriteBooks
     * @return array<string, mixed>
     */
    private function generateStats(Collection $reviews, Collection $favoriteBooks): array
    {
        return [
            \'summary\' => $this->generateSummary($reviews),
            \'rating_distribution\' => $this->generateRatingDistribution($reviews),
            \'genre_stats\' => $this->generateGenreStats($reviews),
            \'favorite_genres\' => $this->generateFavoriteGenres($favoriteBooks),
            \'top_rated_books\' => $this->getTopRatedBooks($reviews),
            \'reading_streak\' => $this->calculateReadingStreak($reviews),
        ];
    }

    /**
     * 基本統計サマリーを生成
     * 使用: count(), avg(), max(), min(), pluck(), unique()
     */
    private function generateSummary(Collection $reviews): array
    {
        return [
            \'total_reviews\' => $reviews->count(),
            \'average_rating\' => round($reviews->avg(\'rating\') ?? 0, 1),
            \'highest_rating\' => $reviews->max(\'rating\') ?? 0,
            \'lowest_rating\' => $reviews->min(\'rating\') ?? 0,
            \'total_books_reviewed\' => $reviews->pluck(\'book_id\')->unique()->count(),
        ];
    }

    /**
     * 評価分布を生成
     * 使用: groupBy(), map(), count(), mapWithKeys()
     */
    private function generateRatingDistribution(Collection $reviews): Collection
    {
        $distribution = $reviews
            ->groupBy(\'rating\')
            ->map(fn (Collection $group): int => $group->count());

        return collect(range(1, 5))
            ->mapWithKeys(fn (int $rating): array => [$rating => $distribution->get($rating, 0)]);
    }

    /**
     * ジャンル別統計を生成
     * 使用: flatMap(), groupBy(), map(), sortByDesc(), take(), values()
     */
    private function generateGenreStats(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(
                fn ($genre): array => [\'genre_name\' => $genre->name, \'rating\' => $review->rating]
            ))
            ->groupBy(\'genre_name\')
            ->map(function (Collection $genreReviews, string $genreName): array {
                $ratings = $genreReviews->pluck(\'rating\');
                return [
                    \'name\' => $genreName,
                    \'count\' => $genreReviews->count(),
                    \'average_rating\' => round($ratings->avg(), 1),
                ];
            })
            ->sortByDesc(\'count\')
            ->take(10)
            ->values();
    }

    /**
     * 読書継続日数を計算
     * 使用: map(), unique(), sort(), values(), reduce()
     */
    private function calculateReadingStreak(Collection $reviews): array
    {
        $reviewDates = $reviews
            ->map(fn (Review $review): string => $review->created_at->format(\'Y-m-d\'))
            ->unique()
            ->sort()
            ->values();

        if ($reviewDates->isEmpty()) {
            return [\'current_streak\' => 0, \'longest_streak\' => 0];
        }

        $streakData = $reviewDates->reduce(
            function (array $carry, string $date): array {
                $currentDate = Carbon::parse($date);
                if ($carry[\'last_date\'] === null) {
                    $carry[\'current\'] = 1;
                    $carry[\'longest\'] = 1;
                } else {
                    $lastDate = Carbon::parse($carry[\'last_date\']);
                    if ($lastDate->diffInDays($currentDate) === 1) {
                        $carry[\'current\']++;
                        $carry[\'longest\'] = max($carry[\'longest\'], $carry[\'current\']);
                    } elseif ($lastDate->diffInDays($currentDate) > 1) {
                        $carry[\'current\'] = 1;
                    }
                }
                $carry[\'last_date\'] = $date;
                return $carry;
            },
            [\'current\' => 0, \'longest\' => 0, \'last_date\' => null]
        );

        return [
            \'current_streak\' => $streakData[\'current\'],
            \'longest_streak\' => $streakData[\'longest\'],
        ];
    }
    
    // favorite_genres, top_rated_booksは模範解答を参考に実装
}
```

## 4. 先輩エンジニアの思考プロセス

### 思考1：まずDBから必要なデータを大まかに取得する

> 「統計処理をする前に、元になるデータをまず取得する。このとき、後で使うことが分かっているリレーション（`book.genres`など）は`with()`でEager Loadingしておくのが鉄則。DBへのクエリは最小限に抑えたいからね。`get()`でCollectionとしてデータを取得したら、ここから先はPHP（Collection）の世界。DBへの負荷はもうかからない。」

### 思考2：処理を意味のある単位でプライベートメソッドに分割する

> 「`index`メソッドに全ての統計処理ロジックを書くと、巨大で可読性の低いメソッドになってしまう。`generateSummary`、`generateRatingDistribution`のように、計算したい統計ごとにプライベートメソッドに分割するのが良い設計だ。各メソッドが何を計算しているか明確になるし、テストもしやすくなる。」

### 思考3：やりたいことをCollectionメソッドで表現できないか考える

> 「『評価ごとの件数を数えたい』→ `groupBy(\'rating\')`して`map()`で`count()`すれば良さそうだな。『ジャンルごとの平均評価を出したい』→ `flatMap()`で全レビューのジャンルを展開して、`groupBy(\'genre_name\')`して、`map()`で`avg()`を計算すればいけるな。というように、`foreach`でループを回す前に、**やりたいことを実現できるCollectionメソッドがないか？**と考える癖をつけることが大事。Collectionメソッドを組み合わせることで、コードは驚くほど宣言的で簡潔になる。」

### 思考4：複雑なロジックは`reduce`でエレガントに

> 「『読書継続日数』の計算は少し複雑だ。前の日付を記憶しながら、現在の日付と比較してカウンターを増やすかリセットするかを判断する必要がある。こういう『前の値（状態）を引き継ぎながら畳み込み計算をする』処理は、`reduce`メソッドの独壇場。初期値（`$carry`）とコールバック関数を渡すだけで、複雑なループ処理をエレガントに記述できる。`reduce`を使いこなせると、書けるコードの幅が格段に広がるよ。」

### 思考5：PHPDocで型情報を補う

> 「Collectionの中身が何なのか（`Review`のコレクションなのか、`Book`のコレクションなのか）は、コードを読む上で非常に重要な情報。`@param Collection<int, Review> $reviews`のようにPHPDoc（アノテーション）で型情報をしっかり記述しておくことで、IDEの補完も効くようになるし、他の開発者もコードを理解しやすくなる。これもプロとしての気遣いだね。」

## 5. ビューの実装

`resources/views/reports/index.blade.php`を作成し、コントローラーから渡された`$stats`変数の内容を表示します。（ビューの具体的なコードは、Tailwind CSSなどを用いて自由にデザインしてください）

```html
<!-- resources/views/reports/index.blade.php の例 -->

@extends(\'layouts.app\')

@section(\'content\')
    <div class="container mx-auto">
        <h1 class="text-2xl font-bold mb-4">マイ読書レポート</h1>

        <!-- 読書サマリー -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="p-4 bg-white rounded shadow text-center">
                <div class="text-3xl font-bold">{{ $stats[\'summary\'][\'total_reviews\'] }}</div>
                <div class="text-gray-600">総レビュー数</div>
            </div>
            <div class="p-4 bg-white rounded shadow text-center">
                <div class="text-3xl font-bold">{{ $stats[\'summary\'][\'average_rating\'] }}</div>
                <div class="text-gray-600">平均評価</div>
            </div>
            <!-- 他のサマリー項目... -->
        </div>

        <!-- 評価分布 -->
        <div class="p-4 bg-white rounded shadow mb-8">
            <h2 class="text-xl font-bold mb-2">評価の分布</h2>
            @foreach ($stats[\'rating_distribution\'] as $rating => $count)
                <div class="flex items-center">
                    <div class="w-12">{{ $rating }} ★</div>
                    <div class="w-full bg-gray-200 rounded h-6">
                        <div class="bg-blue-500 h-6 rounded" style="width: {{ $stats[\'summary\'][\'total_reviews\'] > 0 ? ($count / $stats[\'summary\'][\'total_reviews\']) * 100 : 0 }}%;"></div>
                    </div>
                    <div class="w-12 text-right">{{ $count }}件</div>
                </div>
            @endforeach
        </div>

        <!-- ジャンル別統計 -->
        <!-- ... -->
    </div>
@endsection
```

## 6. まとめ

このChapterでは、Laravel Collectionが提供する多彩なメソッド（`map`, `filter`, `groupBy`, `reduce`など）を駆使して、複雑な統計データを効率的かつ宣言的に生成する方法を学びました。データベースから取得した後のデータ加工は、Collectionを使いこなせるかどうかでコードの品質が大きく変わります。ぜひ、公式ドキュメントを読み込み、様々なメソッドを試してみてください。
