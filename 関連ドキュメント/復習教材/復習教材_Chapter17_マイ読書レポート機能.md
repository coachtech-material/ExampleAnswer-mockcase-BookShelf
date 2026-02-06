# Chapter 17: マイ読書レポート機能の実装 (Collectionメソッド活用)

## 1. はじめに

ユーザー自身の活動履歴を可視化する「ダッシュボード」や「レポート」機能は、アプリケーションの付加価値を高める代表的な機能です。

このChapterでは、ユーザーが自身の読書傾向を振り返ることができる「マイ読書レポート」機能を実装します。この実装を通して、データベースから取得したデータを表示用に様々な形へ加工・集計する処理を学びます。特に、SQLで書くと複雑になりがちな処理を、PHPのコード上で直感的かつ柔軟に扱うことができる**Laravel Collection**の強力なメソッド群（`map`, `groupBy`, `filter`, `sortByDesc`など）を実践的に活用する方法を習得します。

## 2. 要件の確認

今回は、実装済みのBladeファイル（`resources/views/reports/index.blade.php`）が事前に提供されています。このファイルに記述された内容から、実装すべき要件を逆算して確認します。

| 機能 | 詳細仕様 |
|:---|:---|
| **基本統計** | ・総レビュー数<br>・読了冊数（レビューしたユニークな書籍数）<br>・平均評価（小数点以下1桁） |
| **評価の分布** | 星1〜5の各評価が、それぞれ何件のレビューで付けられたかをグラフで可視化する。 |
| **高評価書籍ランキング** | ユーザーが評価4以上を付けた書籍を、評価の高い順に最大5冊までランキング表示する。 |
| **ジャンル別評価傾向** | ユーザーがレビューした書籍のジャンルを分析し、平均評価が高い順に最大5ジャンルまでランキング表示する。 |

## 3. 先輩エンジニアの思考プロセス：実装の設計

複雑なデータ集計機能は、いきなりコードを書き始めると混乱しがちです。まずは、どのような考え方で実装を進めていくのか、設計段階の思考プロセスを覗いてみましょう。

### 思考1：まず何から手をつける？ → ゴールから逆算する

> 「今回は先にBladeファイル（ゴール）が提供されている。こういう時は、まずBladeファイルをじっくり読むのが定石だ。`$stats["summary"]["total_reviews"]` のような記述があるな。ここから、コントローラーが最終的にどのようなデータ構造（配列のキーや階層）をビューに渡せば良いのかを逆算して、設計図を描くことができる。ゴールがわかっていれば、そこに至る道筋もおのずと見えてくる。」

### 思考2：どうやってデータを集計するか？ → SQL vs Collection

> 「『評価ごとのレビュー件数』や『ジャンル別の平均評価ランキング』。これを全部SQLクエリでやろうとすると、`GROUP BY`やサブクエリが複雑に絡み合って、可読性もメンテナンス性も著しく低下してしまう。そこで登場するのが**Laravel Collection**だ。Eloquentで`get()`した結果は、この便利なCollectionオブジェクトになっている。DBからは大まかな生データを取得して、あとの細かい加工・集計はCollectionに任せる。この役割分担が、綺麗でメンテナンスしやすいコードの秘訣なんだ。」

### 思考3：どうやってコードを整理するか？ → 関心の分離

> 「レポート機能は複数の統計データ（基本統計、評価分布など）の集合体だ。これを全部コントローラーの`index`メソッドにベタ書きすると、巨大で読みにくいメソッドが出来上がってしまう。そこで、`generateSummary`（基本統計を生成する）や`generateRatingDistribution`（評価分布を生成する）のように、**機能ごとにプライベートメソッドに分割する**のが定石だ。こうすることで、各メソッドは自分の仕事に集中できるし、コードの見通しが格段に良くなる。これを『関心の分離』と呼ぶんだ。」

### 思考4：パフォーマンスは大丈夫か？ → N+1問題

> 「『ジャンル別評価傾向』を計算するには、レビューに紐づく書籍、さらにその書籍に紐づくジャンルの情報が必要になる。何も考えずに実装すると、レビューの数だけクエリが発行される『N+1問題』が発生して、パフォーマンスが著しく悪化する。`Review::with(["book.genres"])`のように、`with()`を使ってリレーション先のデータをあらかじめ一括で読み込んでおく（Eager Loading）ことを絶対に忘れてはいけない。」

## 4. 実装

それでは、上記の思考プロセスを元に、マイ読書レポート機能を実装していきましょう。

### 4.1. ルートとコントローラーの準備

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

Route::middleware("auth")->group(function () {
    // ... 既存のルート

    // マイ読書レポート
    Route::get("/reports", [ReportController::class, "index"])->name("reports.index");
});
```

### 4.2. ReportControllerの実装

`ReportController`に、統計データを生成し、提供されている`reports.index.blade.php`に渡すロジックを実装します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        // ユーザーのレビューを全て取得（パフォーマンスのためリレーションをEager Load）
        $reviews = Review::with(["book.genres"])
            ->where("user_id", $user->id)
            ->get();

        // 統計データを生成
        $stats = $this->generateStats($reviews);

        // ビューに統計データを渡して表示
        return view("reports.index", compact("stats"));
    }

    private function generateStats(Collection $reviews): array
    {
        return [
            "summary" => $this->generateSummary($reviews),
            "rating_distribution" => $this->generateRatingDistribution($reviews),
            "top_rated_books" => $this->getTopRatedBooks($reviews),
            "genre_ratings" => $this->generateGenreRatings($reviews),
        ];
    }

    private function generateSummary(Collection $reviews): array
    {
        return [
            "total_reviews" => $reviews->count(),
            "books_read" => $reviews->pluck("book_id")->unique()->count(),
            "average_rating" => round($reviews->avg("rating") ?? 0, 1),
        ];
    }

    private function generateRatingDistribution(Collection $reviews): array
    {
        $distribution = $reviews
            ->groupBy("rating")
            ->map(fn (Collection $group): int => $group->count());

        return collect(range(1, 5))
            ->mapWithKeys(fn (int $rating): array => [$rating - 1 => $distribution->get($rating, 0)])
            ->toArray();
    }

    private function getTopRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review): bool => $review->rating >= 4)
            ->sortByDesc("rating")
            ->take(5)
            ->map(fn (Review $review): array => [
                "id" => $review->book->id,
                "title" => $review->book->title,
                "author" => $review->book->author,
                "rating" => $review->rating,
            ])
            ->values();
    }

    private function generateGenreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres->map(
                fn ($genre) => [
                    "id" => $genre->id,
                    "name" => $genre->name,
                    "rating" => $review->rating,
                ]
            ))
            ->groupBy("name")
            ->map(function (Collection $genreReviews, string $genreName): array {
                return [
                    "id" => $genreReviews->first()["id"],
                    "name" => $genreName,
                    "count" => $genreReviews->count(),
                    "average_rating" => round($genreReviews->avg("rating"), 1),
                ];
            })
            ->sortByDesc("average_rating")
            ->take(5)
            ->values();
    }
}
```

## 5. コードの詳細解説

このコントローラーは、まさに「Collection使いこなし塾」です。`index`メソッド自体はシンプルですが、実際の複雑な処理は、意味のある単位で分割されたプライベートメソッド群が担当しています。

### `index()` メソッド

このメソッドが処理の入り口です。

1.  `Auth::user()`で現在ログインしているユーザーを取得します。
2.  `Review::with(["book.genres"])`で、ユーザーの全レビューを取得します。このとき`with()`を使って`book`と`book`に紐づく`genres`リレーションを**Eager Loading（先行読み込み）**しています。これにより、後続の処理でレビューごとにDBへクエリが発行される**N+1問題**を防ぎ、パフォーマンスを大幅に向上させます。
3.  取得したレビューのコレクションを`generateStats()`メソッドに渡し、統計データを生成します。
4.  `compact("stats")`を使って、`$stats`変数をビューに渡します。

### `generateSummary()` メソッド：基本統計

- **`$reviews->count()`**: レビューの総数を返します。
- **`$reviews->pluck("book_id")->unique()->count()`**: `pluck("book_id")`で`book_id`だけのコレクションを作成し、`unique()`で重複を除外してから`count()`することで、レビューしたユニークな書籍の冊数を数えます。
- **`round($reviews->avg("rating") ?? 0, 1)`**: `avg("rating")`で`rating`カラムの平均値を計算し、`round()`で小数点以下1桁に丸めています。`?? 0`は、レビューが1件もない場合に`avg`が`null`を返すため、その場合に`0`とするための記述です。

### `generateRatingDistribution()` メソッド：評価の分布

- **`$reviews->groupBy("rating")`**: 評価の値（1〜5）ごとにレビューをグループ化します。結果は `[1 => [Review, ...], 2 => [Review, ...]]` のようなコレクションになります。
- **`->map(...)`**: グループ化されたコレクションの各要素（各評価のレビューコレクション）に対して処理を行い、その要素数を数えることで、評価ごとの件数を算出します。
- **`collect(range(1, 5))->mapWithKeys(...)`**: このままではレビューがない評価（例：星1のレビューが0件）のデータが欠落してしまいます。そこで、1から5までのコレクションを元に、各評価に対応する件数が存在しない場合は`0`で補完し、Bladeが期待する`[0 => 件数, 1 => 件数, ...]`という配列を作成しています。

### `getTopRatedBooks()` メソッド：高評価書籍ランキング

- **`->filter(...)`**: `rating`が4以上のレビューのみをフィルタリングします。
- **`->sortByDesc("rating")`**: 評価の高い順に並べ替えます。
- **`->take(5)`**: 上位5件を取得します。
- **`->map(...)`**: ビューで表示するために必要な書籍の`id`, `title`, `author`, `rating`だけを抽出した新しいコレクションを生成します。
- **`->values()`**: コレクションのキーをリセットし、`0`, `1`, `2`...という連番に振り直します。これにより、Blade側でインデックスを使った処理（例：1位、2位のメダル表示）が容易になります。

### `generateGenreRatings()` メソッド：ジャンル別評価傾向

- **`->flatMap(...)`**: ここでの最重要メソッドです。`map()`と似ていますが、`flatMap()`は多次元のコレクションを一次元のフラットなコレクションに展開します。ここでは、各レビューが持つ書籍の、さらにその書籍が持つ複数のジャンルを全て取り出し、`["id" => ..., "name" => ..., "rating" => ...]`という形式の配列のフラットなコレクションを生成します。
- **`->groupBy("name")`**: フラット化されたコレクションを、ジャンル名でグループ化します。
- **`->map(...)`**: 各ジャンルグループのレビュー数（`count`）と平均評価（`average_rating`）を計算します。
- **`->sortByDesc("average_rating")`**: 平均評価の高い順にソートし、`take(5)`で上位5件を取得して返します。

## 6. How to: この実装にたどり着くための調べ方

もし自力でこの実装にたどり着くとしたら、どのように調べれば良いでしょうか？

### 調べ方1：「配列をいい感じに処理したい」

**最初の検索（日本語で素朴に）:**
```
Laravel 配列 集計
```

**検索結果から得られる情報:**
- Laravelには「コレクション」という便利な機能があるらしいことがわかる。
- `map`や`filter`といった、JavaScriptでおなじみのメソッドが使えるようだ。

**次の疑問と検索:**
```
Laravel Collection 使い方
```

**最終的にたどり着く答え:**
- 公式ドキュメントの「コレクション」のページにたどり着く。利用可能なメソッドの一覧を見て、「こんなこともできるのか！」と可能性に気づくことができる。

### 調べ方2：「ランキングを作りたい」

**最初の検索（やりたいことをそのまま）:**
```
Laravel ランキング 作成
```

**検索結果から得られる情報:**
- `orderBy`や`orderByDesc`でソートできることがわかる。
- Collectionにも`sortBy`や`sortByDesc`というメソッドがあることに気づく。

**次の疑問と検索:**
```
Laravel Collection sortByDesc
```

**最終的にたどり着く答え:**
- `sortByDesc`メソッドを使えば、DBから取得した後のデータでも簡単に並び替えができることを理解する。`take(5)`と組み合わせれば、トップ5が簡単に取得できることもわかる。

## 7. 提供されているBladeファイルの確認

このプロジェクトでは、マイ読書レポート画面のBladeファイル（`resources/views/reports/index.blade.php`）が事前に提供されています。コントローラーで`$stats`変数に正しいデータ構造をセットすることで、レポートが正しく表示されます。

**Bladeが期待する`$stats`変数の構造:**

```php
$stats = [
    "summary" => [
        "total_reviews" => int,      // 総レビュー数
        "books_read" => int,         // 読了冊数（ユニークな書籍数）
        "average_rating" => float,   // 平均評価
    ],
    "rating_distribution" => [int, int, int, int, int], // 星1〜5の件数（0始まりのインデックス）
    "top_rated_books" => [
        ["id" => int, "title" => string, "author" => string, "rating" => int],
        // ...
    ],
    "genre_ratings" => [
        ["id" => int, "name" => string, "count" => int, "average_rating" => float],
        // ...
    ],
];
```

## 8. まとめ

このChapterでは、Laravel Collectionが提供する多彩なメソッドを駆使して、複雑な統計データを効率的かつ宣言的に生成する方法を学びました。

- **Collectionの真価**: `foreach`で複雑なループを書く代わりに、`map`, `groupBy`, `flatMap`などのメソッドチェーンで、宣言的にデータを処理できる。
- **関心の分離**: 複雑な処理は、意味のある単位でプライベートメソッドに分割することで、コードの可読性とメンテナンス性を高める。
- **パフォーマンス意識**: N+1問題を避けるため、`with()`によるEager Loadingを常に意識する。

データベースから取得した後のデータ加工は、Collectionを使いこなせるかどうかでコードの品質が大きく変わります。ぜひ、公式ドキュメントを片手に、様々なメソッドを試してみてください。
