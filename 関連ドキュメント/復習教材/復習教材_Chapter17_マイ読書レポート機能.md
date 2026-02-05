# Chapter 17: マイ読書レポート機能の実装 (Collectionメソッド活用)

## 🎯 このセクションで学ぶこと

- 複雑なデータ集計におけるLaravel Collectionの活用法
- `map`, `groupBy`, `filter`, `sortByDesc`などの主要なCollectionメソッドの実践的な使い方
- EloquentリレーションをEager Loadingしてパフォーマンスを最適化する手法
- 複数の統計データを効率的に生成し、ビューに渡すコントローラーの設計パターン
- Collectionのメソッドチェーンによる、宣言的で可読性の高いコードの書き方

---

## 🧠 先輩エンジニアの思考プロセス

### なぜCollectionを使うのか？

> 実装も終盤に差し掛かり、ユーザー体験をもう一段階引き上げるための「付加価値機能」を考えるフェーズだね。ユーザー自身の活動履歴を可視化する「ダッシュボード」や「レポート」機能は、その代表格だ。今回の「マイ読書レポート」もその一つ。
> 
> このような機能で必要になるのは、**DBから取得したデータを、表示のために様々な形に加工・集計する処理**。例えば、「評価ごとのレビュー件数」や「ジャンル別の平均評価ランキング」などだね。これを全部SQLクエリでやろうとすると、クエリがどんどん複雑になって、可読性もメンテナンス性も著しく低下してしまう。
> 
> そこで登場するのが**Laravel Collection**。Eloquentで`get()`した結果は、ただの配列じゃなくて、この便利なCollectionオブジェクトになっている。こいつは、配列操作のための強力なメソッドを山ほど持っているんだ。`map`や`filter`はもちろん、`groupBy`や`reduce`といった高度なメソッドまで揃っている。SQLで複雑な`GROUP BY`やサブクエリを書く代わりに、PHPのコード上で、より柔軟かつ直感的にデータを扱えるようになる。DBから大まかなデータを取得したら、あとの加工はCollectionに任せる。この役割分担が、綺麗でメンテナンスしやすいコードの秘訣なんだ。

### 実装の進め方

> 1.  **まずはゴール（画面）を確認する**: 今回は先にBladeファイルが提供されている。最終的にどんなデータが必要なのかを把握するために、まずはこのBladeファイルをじっくり読む。`$stats['summary']['total_reviews']` のような記述から、コントローラーが渡すべきデータ構造（配列のキーや階層）を逆算して、設計図を描くんだ。
> 2.  **土台を作る**: レポート表示用のルート (`routes/web.php`) とコントローラー (`ReportController`) を作成する。これはもう慣れた作業だね。
> 3.  **心臓部（データ集計ロジック）を実装する**: `ReportController`に、統計データを生成するロジックを実装する。ここが今回のメインディッシュ。Bladeが要求するデータ構造を頭に置きながら、必要なメソッドを一つずつ作っていく。このとき、いきなり`index`メソッドに全部書くのではなく、`generateSummary`や`generateRatingDistribution`のように、**機能ごとにプライベートメソッドに分割する**のが定石。コードの見通しが良くなるし、後から修正するのも楽になるからね。
> 4.  **Collectionメソッドを使いこなす**: 各プライベートメソッドの中で、やりたいことを実現できるCollectionメソッドを探す。「評価ごとにグループ分けしたい」なら`groupBy`、「高評価の書籍だけ欲しい」なら`filter`、「ランキング順に並べたい」なら`sortByDesc`、といった具合に、メソッドチェーンを組み立てていく。まるでパズルを解くような感覚で、宣言的にデータを処理できるのがCollectionの面白いところだ。
> 5.  **ビューにデータを渡して完成**: 最後に、完成した`$stats`配列を`view()`ヘルパーでビューに渡せば完成。Blade側はすでにあるから、ブラウザでアクセスすれば綺麗なレポートが表示されるはずだ。

---

## 1. ルートとコントローラーの準備

まず、レポート表示用のルートとコントローラーを作成します。

```bash
sail artisan make:controller ReportController
```

次に、`routes/web.php`に認証が必要なルートとして、レポートページのルートを追加します。

```php
// routes/web.php

// ... 既存のuse文に追記
use App\Http\Controllers\ReportController;

// ...

Route::middleware('auth')->group(function () {
    // ... 既存のルート

    // マイ読書レポート
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});
```

## 2. ReportControllerの実装

`ReportController`に、統計データを生成し、提供されている`reports.index.blade.php`に渡すロジックを実装します。ここがCollectionメソッド活用の本番です。

既存の`app/Http/Controllers/ReportController.php`を以下の内容で上書きしてください。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\
Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポートを表示します。
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $user = Auth::user();

        // ユーザーのレビューを全て取得（パフォーマンスのためリレーションをEager Load）
        $reviews = Review::with(['book.genres'])
            ->where('user_id', $user->id)
            ->get();

        // 統計データを生成
        $stats = $this->generateStats($reviews);

        // ビューに統計データを渡して表示
        return view('reports.index', compact('stats'));
    }

    /**
     * レビューデータから統計情報を生成します。
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return array<string, mixed>
     */
    private function generateStats(Collection $reviews): array
    {
        return [
            'summary' => $this->generateSummary($reviews),
            'rating_distribution' => $this->generateRatingDistribution($reviews),
            'top_rated_books' => $this->getTopRatedBooks($reviews),
            'genre_ratings' => $this->generateGenreRatings($reviews),
        ];
    }

    /**
     * 基本統計サマリーを生成します。
     * 使用するCollectionメソッド: count(), pluck(), unique(), avg()
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return array<string, int|float>
     */
    private function generateSummary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => round($reviews->avg('rating') ?? 0, 1),
        ];
    }

    /**
     * 評価の分布を生成します。
     * 使用するCollectionメソッド: groupBy(), map(), get(), range(), mapWithKeys()
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return array<int, int>
     */
    private function generateRatingDistribution(Collection $reviews): array
    {
        // 評価（1〜5）ごとにレビューをグループ化し、それぞれの件数をカウント
        $distribution = $reviews
            ->groupBy('rating')
            ->map(fn (Collection $group): int => $group->count());

        // 評価が存在しない星も0件として結果に含めるために、1〜5の範囲で結果を補完
        return collect(range(1, 5))
            ->mapWithKeys(fn (int $rating): array => [$rating - 1 => $distribution->get($rating, 0)])
            ->toArray();
    }

    /**
     * 高評価の書籍トップ5を取得します。
     * 使用するCollectionメソッド: filter(), sortByDesc(), take(), map(), values()
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getTopRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            // 評価4以上のレビューのみをフィルタリング
            ->filter(fn (Review $review): bool => $review->rating >= 4)
            // 評価が高い順にソート
            ->sortByDesc('rating')
            // 上位5件を取得
            ->take(5)
            // ビューで必要な情報だけを抽出して新しいコレクションを作成
            ->map(fn (Review $review): array => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values(); // キーを0から始まる連番にリセット
    }

    /**
     * ジャンル別の評価傾向トップ5を生成します。
     * 使用するCollectionメソッド: flatMap(), map(), groupBy(), map(), round(), avg(), sortByDesc(), take(), values()
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Review>  $reviews
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function generateGenreRatings(Collection $reviews): Collection
    {
        return $reviews
            // 各レビューが持つ書籍の全ジャンルを展開し、フラットなコレクションを作成
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(
                fn ($genre) => [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'rating' => $review->rating,
                ]
            ))
            // ジャンル名でグループ化
            ->groupBy('name')
            // 各ジャンルの統計情報（レビュー数、平均評価）を計算
            ->map(function (Collection $genreReviews, string $genreName): array {
                return [
                    'id' => $genreReviews->first()['id'],
                    'name' => $genreName,
                    'count' => $genreReviews->count(),
                    'average_rating' => round($genreReviews->avg('rating'), 1),
                ];
            })
            // 平均評価が高い順にソート
            ->sortByDesc('average_rating')
            // 上位5件を取得
            ->take(5)
            ->values(); // キーを0から始まる連番にリセット
    }
}
```

### 📖 詳細解説: ReportController

> **💡 先輩エンジニアの視点**
> このコントローラーは、まさに「Collection使いこなし塾」だね。`index`メソッド自体は、データを取得してプライベートメソッドに渡し、結果をビューに返すだけ、と非常にシンプル。実際の複雑な処理は、意味のある単位で分割されたプライベートメソッド群が担当している。この「**関心の分離**」が、コードを綺麗に保つための基本原則だよ。
> 
> 特に注目してほしいのは、`flatMap`や`groupBy`、`map`、`reduce`といったメソッドが、`foreach`ループの代わりに見事な仕事をしている点。例えば`generateGenreRatings`を見てみよう。レビューのコレクションから、そのレビューが持つ書籍の、さらにその書籍が持つジャンルを全て取り出して、ジャンル名でグループ化し、それぞれの平均点を計算して、ランキング付けする...。これを`foreach`で書いたら、ネストが深くて複雑なコードになってしまうだろう。でも、Collectionのメソッドチェーンを使えば、まるで英語の文章を読むように「何をしたいか」が宣言的に記述できる。これがCollectionの真価なんだ。

**コードリーディング**

- **`index()`**: このメソッドがエントリーポイントです。
    1.  `Auth::user()`で現在ログインしているユーザーを取得します。
    2.  `Review::with(['book.genres'])`で、ユーザーの全レビューを取得します。このとき`with()`を使って`book`と`book`に紐づく`genres`リレーションを**Eager Loading（先行読み込み）**しています。これにより、後続の処理でレビューごとにDBへクエリが発行される**N+1問題**を防ぎ、パフォーマンスを大幅に向上させます。
    3.  取得したレビューのコレクションを`generateStats()`メソッドに渡し、統計データを生成します。
    4.  `compact('stats')`を使って、`$stats`変数をビューに渡します。

- **`generateStats()`**: 複数の統計生成メソッドを呼び出し、結果を一つの配列にまとめる役割を担います。Bladeファイルが必要とするキー（`summary`, `rating_distribution`など）に対応した配列を返します。

- **`generateSummary()`**: 基本的な統計情報を計算します。
    - `total_reviews`: `count()`でレビューの総数を取得します。
    - `books_read`: `pluck('book_id')`で`book_id`だけのコレクションを作成し、`unique()`で重複を除外してから`count()`することで、レビューしたユニークな書籍の冊数を数えます。
    - `average_rating`: `avg('rating')`で`rating`カラムの平均値を計算し、`round()`で小数点以下1桁に丸めています。

- **`generateRatingDistribution()`**: 評価（星1〜5）の分布を計算します。
    - `groupBy('rating')`で評価の値ごとにレビューをグループ化します。
    - `map()`で各グループの要素数（レビュー数）を数えます。
    - このままではレビューがない評価（例：星1のレビューが0件）のデータが欠落してしまうため、`collect(range(1, 5))`で1から5までのコレクションを作成し、`mapWithKeys()`を使って、各評価に対応する件数が存在しない場合は`0`で補完しています。最後に`toArray()`でただの配列に変換してビューに渡します。

- **`getTopRatedBooks()`**: 高評価書籍のランキングを生成します。
    - `filter()`で評価が4以上のレビューに絞り込みます。
    - `sortByDesc('rating')`で評価の高い順に並べ替えます。
    - `take(5)`で上位5件を取得します。
    - `map()`で、ビューで表示するために必要な書籍の`id`, `title`, `author`, `rating`だけを抽出した新しいコレクションを生成します。
    - `values()`で、コレクションのキーをリセットし、`0`, `1`, `2`...という連番に振り直します。これにより、Blade側でインデックスを使った処理（例：1位、2位のメダル表示）が容易になります。

- **`generateGenreRatings()`**: ジャンルごとの評価傾向を計算します。
    - `flatMap()`がここでのキーポイントです。`map()`と似ていますが、`flatMap()`は多次元のコレクションを一次元のフラットなコレクションに展開します。ここでは、各レビューが持つ書籍の、さらにその書籍が持つ複数のジャンルを全て取り出し、`['id' => ..., 'name' => ..., 'rating' => ...]`という形式の配列のフラットなコレクションを生成します。
    - その後、`groupBy('name')`でジャンル名ごとにグループ化し、`map()`で各ジャンルのレビュー数（`count`）と平均評価（`average_rating`）を計算します。
    - 最後に`sortByDesc('average_rating')`で平均評価の高い順にソートし、`take(5)`で上位5件を取得して返します。

---

## 3. ビューの実装

コントローラーから渡された`$stats`変数を表示するために、`resources/views/reports/index.blade.php`を以下の内容で作成または上書きします。このコードは、提供されたBladeファイルそのものです。

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('マイ読書レポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- 基本サマリー -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">基本統計</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="border rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-blue-600 mb-2">{{ $stats['summary']['total_reviews'] }}</div>
                            <div class="text-sm text-gray-600">総レビュー数</div>
                        </div>
                        <div class="border rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-green-600 mb-2">{{ $stats['summary']['books_read'] }}</div>
                            <div class="text-sm text-gray-600">読了冊数</div>
                        </div>
                        <div class="border rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-yellow-500 mb-2">
                                @if ($stats['summary']['average_rating'] > 0)
                                    {{ number_format($stats['summary']['average_rating'], 1) }}
                                @else
                                    -
                                @endif
                            </div>
                            <div class="text-sm text-gray-600">平均評価</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- 評価分布 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">評価分布</h3>
                        <div class="space-y-3">
                            @foreach ($stats['rating_distribution'] as $index => $count)
                                @php
                                    $rating = $index + 1;
                                    $maxCount = max($stats['rating_distribution']) ?: 1;
                                    $percentage = ($count / $maxCount) * 100;
                                @endphp
                                <div class="flex items-center">
                                    <div class="w-16 text-sm text-gray-700">
                                        <span class="text-yellow-500">{{ str_repeat('★', $rating) }}</span>
                                    </div>
                                    <div class="flex-1 mx-3">
                                        <div class="bg-gray-200 rounded-full h-5 overflow-hidden">
                                            <div class="bg-yellow-400 h-5 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <div class="w-12 text-sm text-gray-600 text-right font-medium">{{ $count }}件</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 高評価書籍TOP5 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">高評価書籍 TOP5</h3>
                        @if (count($stats['top_rated_books']) > 0)
                            <div class="space-y-3">
                                @foreach ($stats['top_rated_books'] as $index => $book)
                                    @php
                                        $rankColors = [
                                            0 => 'bg-yellow-400 text-white',
                                            1 => 'bg-gray-400 text-white',
                                            2 => 'bg-amber-600 text-white',
                                        ];
                                        $rankColor = $rankColors[$index] ?? 'bg-gray-200 text-gray-600';
                                    @endphp
                                    <a href="{{ route('books.show', $book['id']) }}" class="flex items-center p-3 border rounded-lg hover:shadow-md transition">
                                        <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full {{ $rankColor }} font-bold text-sm">
                                            {{ $index + 1 }}
                                        </div>
                                        <div class="flex-grow min-w-0 ml-3">
                                            <div class="font-medium text-gray-900 truncate">{{ $book['title'] }}</div>
                                            <div class="text-sm text-gray-500">{{ $book['author'] }}</div>
                                        </div>
                                        <div class="flex-shrink-0 ml-3 text-yellow-500 text-sm">
                                            {{ str_repeat('★', $book['rating']) }}{{ str_repeat('☆', 5 - $book['rating']) }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-8">4星以上の書籍がありません</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ジャンル別評価傾向 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">ジャンル別評価傾向 TOP5</h3>
                    <p class="text-sm text-gray-500 mb-4">どのジャンルを高く評価する傾向があるかを表示</p>
                    @if (count($stats['genre_ratings']) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($stats['genre_ratings'] as $index => $genre)
                                @php
                                    $rankColors = [
                                        0 => 'bg-yellow-400 text-white',
                                        1 => 'bg-gray-400 text-white',
                                        2 => 'bg-amber-600 text-white',
                                    ];
                                    $rankColor = $rankColors[$index] ?? 'bg-gray-200 text-gray-600';
                                @endphp
                                <a href="{{ route('genres.show', $genre['id']) }}" class="flex items-center p-4 border rounded-lg hover:shadow-md transition">
                                    <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full {{ $rankColor }} font-bold text-sm">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="flex-grow ml-3">
                                        <div class="font-medium text-gray-900">{{ $genre['name'] }}</div>
                                        <div class="text-sm text-gray-500">{{ $genre['count'] }}件のレビュー</div>
                                    </div>
                                    <div class="flex-shrink-0 ml-3 text-right">
                                        <div class="text-lg font-bold text-yellow-500">{{ number_format($genre['average_rating'], 1) }}</div>
                                        <div class="text-xs text-gray-400">平均評価</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">ジャンルが設定された書籍のレビューがありません</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

これで、`/reports`にアクセスすると、ログインユーザー自身の読書アクティビティに基づいた美しいレポートが表示されるようになります。

---

## ✨ まとめ

このChapterでは、Laravel Collectionが提供する多彩なメソッドを駆使して、複雑な統計データを効率的かつ宣言的に生成する方法を学びました。データベースから取得した後のデータ加工は、Collectionを使いこなせるかどうかでコードの品質が大きく変わります。`foreach`で複雑なループを書く前に、「これを実現できるCollectionメソッドはないか？」と一歩立ち止まって考える癖をつけることが、より良いLaravelデベロッパーへの近道です。ぜひ、公式ドキュメントを片手に、様々なメソッドを試してみてください。
