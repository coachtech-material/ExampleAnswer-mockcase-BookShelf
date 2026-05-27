# Chapter 17: マイ読書レポート機能の実装 (Collectionメソッド活用)

## 🎯 このセクションで学ぶこと

（このセクションで学ぶ内容の要約をここに追記予定）

---


## 1. はじめに 📖

ユーザー自身の活動履歴を可視化する「ダッシュボード」や「レポート」機能は、アプリケーションの付加価値を高める代表的な機能です。

このChapterでは、ユーザーが自身の読書傾向を振り返ることができる「マイ読書レポート」機能を実装します。この実装を通して、データベースから取得したデータを表示用に様々な形へ加工・集計する処理を学びます。特に、SQLで書くと複雑になりがちな処理を、PHPのコード上で直感的かつ柔軟に扱うことができる**Laravel Collection**の強力なメソッド群（`map`, `groupBy`, `filter`, `sortByDesc`など）を実践的に活用する方法を習得します。

## 2. 要件の確認 📋

今回は、実装済みのBladeファイル（`resources/views/reports/index.blade.php`）が事前に提供されています。このファイルに記述された内容から、実装すべき要件を逆算して確認します。

| 機能 | 詳細仕様 |
|:---|:---|
| **基本統計** | ・総レビュー数<br>・読了冊数（レビューしたユニークな書籍数）<br>・平均評価（小数点以下1桁） |
| **評価の分布** | 星1〜5の各評価が、それぞれ何件のレビューで付けられたかをグラフで可視化する。 |
| **高評価書籍ランキング** | ユーザーが評価4以上を付けた書籍を、評価の高い順に最大5冊までランキング表示する。 |
| **ジャンル別評価傾向** | ユーザーがレビューした書籍のジャンルを分析し、平均評価が高い順に最大5ジャンルまでランキング表示する。 |

## 3. 先輩エンジニアの思考プロセス 💭

複雑なデータ集計機能は、いきなりコードを書き始めると混乱しがちです。まずは、どのような考え方で実装を進めていくのか、設計段階の思考プロセスを覗いてみましょう。

### 思考1：まず何から手をつける？ → ゴールから逆算する

> 「今回は先にBladeファイル（ゴール）が提供されている。こういう時は、まずBladeファイルをじっくり読むのが定石だ。`$stats["summary"]["total_reviews"]` のような記述があるな。ここから、コントローラーが最終的にどのようなデータ構造（配列のキーや階層）をビューに渡せば良いのかを逆算して、設計図を描くことができる。ゴールがわかっていれば、そこに至る道筋もおのずと見えてくる。」

### 思考2：どうやってデータを集計するか？ → SQL vs Collection

> 「『評価ごとのレビュー件数』や『ジャンル別の平均評価ランキング』。これを全部SQLクエリでやろうとすると、`GROUP BY`やサブクエリが複雑に絡み合って、可読性もメンテナンス性も著しく低下してしまう。そこで登場するのが**Laravel Collection**だ。Eloquentで`get()`した結果は、この便利なCollectionオブジェクトになっている。DBからは大まかな生データを取得して、あとの細かい加工・集計はCollectionに任せる。この役割分担が、綺麗でメンテナンスしやすいコードの秘訣なんだ。」

### 思考3：どうやってコードを整理するか？ → 関心の分離

> 「レポート機能は複数の統計データ（基本統計、評価分布など）の集合体だ。これを全部コントローラーの`index`メソッドにベタ書きすると、巨大で読みにくいメソッドが出来上がってしまう。そこで、`calculateSummary`（基本統計を計算する）や`calculateRatingDistribution`（評価分布を計算する）のように、**機能ごとにプライベートメソッドに分割する**のが定石だ。こうすることで、各メソッドは自分の仕事に集中できるし、コードの見通しが格段に良くなる。これを『関心の分離』と呼ぶんだ。」

### 思考4：パフォーマンスは大丈夫か？ → N+1問題

> 「『ジャンル別評価傾向』を計算するには、レビューに紐づく書籍、さらにその書籍に紐づくジャンルの情報が必要になる。何も考えずに実装すると、レビューの数だけクエリが発行される『N+1問題』が発生して、パフォーマンスが著しく悪化する。`$user->reviews()->with('book.genres')`のように、`with()`を使ってリレーション先のデータをあらかじめ一括で読み込んでおく（Eager Loading）ことを絶対に忘れてはいけない。」

## 4. 実装 🚀

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

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポートを表示します。
     */
    public function index(): View
    {
        $user = Auth::user();
        $reviews = $user->reviews()->with('book.genres')->get();

        $stats = [
            'summary' => $this->calculateSummary($reviews),
            'rating_distribution' => $this->calculateRatingDistribution($reviews),
            'top_rated_books' => $this->calculateTopRatedBooks($reviews),
            'genre_ratings' => $this->calculateGenreRatings($reviews),
        ];

        return view('reports.index', compact('stats'));
    }

    /**
     * 基本サマリー（総レビュー数、読了冊数、平均評価）を計算する
     *
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function calculateSummary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => $reviews->avg('rating') ?? 0,
        ];
    }

    /**
     * 評価分布（1〜5星ごとの件数）を計算する
     *
     * @return Collection<int, int>
     */
    private function calculateRatingDistribution(Collection $reviews): Collection
    {
        $grouped = $reviews->groupBy('rating');

        return collect(range(1, 5))
            ->map(fn ($rating) => $grouped->has($rating) ? $grouped[$rating]->count() : 0);
    }

    /**
     * 高評価書籍TOP5（4星以上）を計算する
     *
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function calculateTopRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn ($review) => $review->rating >= 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn ($review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values();
    }

    /**
     * ジャンル別評価傾向TOP5を計算する
     *
     * flatMap()で多対多リレーションを展開し、groupBy()で集計する
     *
     * @return Collection<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function calculateGenreRatings(Collection $reviews): Collection
    {
        return $reviews
            // flatMap: 1レビュー → 複数ジャンルに展開（多対多の扱い）
            ->flatMap(fn ($review) => $review->book->genres->map(fn ($genre) => [
                'genre_id' => $genre->id,
                'genre_name' => $genre->name,
                'rating' => $review->rating,
            ]))
            // groupBy + map: ジャンルごとに集計
            ->groupBy('genre_id')
            ->map(fn ($items) => [
                'id' => $items->first()['genre_id'],
                'name' => $items->first()['genre_name'],
                'count' => $items->count(),
                'average_rating' => round($items->avg('rating'), 1),
            ])
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();
    }
}
```

## 5. コードの詳細解説 🔍

このコントローラーは、まさに「Collection使いこなし塾」です。`index`メソッド自体はシンプルで、`$stats`配列の組み立てと各`calculate*`メソッドの呼び出しだけを行っています。実際の複雑な処理は、意味のある単位で分割されたプライベートメソッド群が担当しています。

### `index()` メソッド

```php
public function index(): View
{
    $user = Auth::user();
    $reviews = $user->reviews()->with('book.genres')->get();

    $stats = [
        'summary' => $this->calculateSummary($reviews),
        'rating_distribution' => $this->calculateRatingDistribution($reviews),
        'top_rated_books' => $this->calculateTopRatedBooks($reviews),
        'genre_ratings' => $this->calculateGenreRatings($reviews),
    ];

    return view('reports.index', compact('stats'));
}
```

1.  **`Auth::user()`**: 現在ログインしている認証済みユーザーのモデルインスタンスを取得します。
2.  **`$user->reviews()->with('book.genres')->get()`**: ユーザーモデルの`reviews()`リレーションメソッドを経由してレビューを取得しています。`Review::where('user_id', ...)`と書く代わりに、Eloquentのリレーションを活用することで、より宣言的で読みやすいコードになっています。`with('book.genres')`でEager Loading（先行読み込み）を行い、N+1問題を回避しています。
3.  **`$stats = [...]`**: 各種統計データを直接`index`メソッド内で組み立てています。中間の`generateStats`メソッドを経由せず、`$stats`配列のキーと各`calculate*`メソッドの対応関係が一目でわかるシンプルな構造です。
4.  **`view('reports.index', compact('stats'))`**: `reports.index`というBladeビューを返します。`compact('stats')`は、`['stats' => $stats]`という配列を作成するのと同じ意味で、ビュー内で`$stats`変数を使えるようにします。

### `calculateSummary()` メソッド：基本統計

```php
private function calculateSummary(Collection $reviews): array
{
    return [
        'total_reviews' => $reviews->count(),
        'books_read' => $reviews->pluck('book_id')->unique()->count(),
        'average_rating' => $reviews->avg('rating') ?? 0,
    ];
}
```

1.  **`$reviews->count()`**: コレクションに含まれるレビューの総数を返します。
2.  **`$reviews->pluck('book_id')`**: コレクション内の各レビューから`book_id`の値だけを抽出した、新しいコレクションを生成します。
3.  **`->unique()`**: `pluck`で作成したコレクションから、重複する`book_id`を取り除きます。
4.  **`->count()`**: `unique`で重複を除外した後のコレクションの要素数を数えることで、レビューしたユニークな書籍の総数を取得します。
5.  **`$reviews->avg('rating')`**: コレクション内の全レビューの`rating`カラムの平均値を計算します。レビューが0件の場合は`null`を返します。
6.  **`?? 0`**: Null合体演算子。`avg()`が`null`を返した場合（レビューが0件の場合）に、代わりに`0`を使用します。これにより、エラーを防ぎます。

### `calculateRatingDistribution()` メソッド：評価の分布

```php
private function calculateRatingDistribution(Collection $reviews): Collection
{
    $grouped = $reviews->groupBy('rating');

    return collect(range(1, 5))
        ->map(fn ($rating) => $grouped->has($rating) ? $grouped[$rating]->count() : 0);
}
```

1.  **`$reviews->groupBy('rating')`**: レビューのコレクションを、`rating`の値（1〜5）に基づいてグループ化します。結果は `[1 => Collection, 2 => Collection, ...]` のような、評価値をキーとするコレクションのコレクションになります。
2.  **`collect(range(1, 5))`**: PHPの`range(1, 5)`関数で `[1, 2, 3, 4, 5]` という配列を作成し、それをLaravelのコレクションに変換します。これは、レビューが存在しない評価も結果に含めるための土台となります。
3.  **`->map(fn ($rating) => ...)`**: 1〜5の各評価について、`$grouped->has($rating)`でその評価のグループが存在するかを確認します。存在する場合はその件数を`count()`で取得し、存在しない場合は`0`を返します。
4.  **戻り値が`Collection`**: 配列に変換せず`Collection`のまま返している点に注目してください。Bladeテンプレートでは`Collection`も配列と同様にインデックスでアクセスできるため、`toArray()`は不要です。

### `calculateTopRatedBooks()` メソッド：高評価書籍ランキング

```php
private function calculateTopRatedBooks(Collection $reviews): Collection
{
    return $reviews
        ->filter(fn ($review) => $review->rating >= 4)
        ->sortByDesc('rating')
        ->take(5)
        ->map(fn ($review) => [
            'id' => $review->book->id,
            'title' => $review->book->title,
            'author' => $review->book->author,
            'rating' => $review->rating,
        ])
        ->values();
}
```

1.  **`->filter(...)`**: コレクション内の各レビューをチェックし、`rating`が4以上のレビューだけを残した新しいコレクションを返します。クロージャの引数に型宣言（`Review $review`）を付けず`$review`だけにすることで、シンプルに記述しています。
2.  **`->sortByDesc('rating')`**: フィルタリングされたコレクションを、`rating`の値に基づいて降順（高い順）に並べ替えます。
3.  **`->take(5)`**: 並べ替えたコレクションの先頭から5件を取得します。
4.  **`->map(...)`**: 上位5件のレビューコレクションを元に、ビューで表示するために必要な情報（書籍のid, title, author, そしてレビューのrating）だけを抽出した新しいコレクションを生成します。
5.  **`->values()`**: コレクションのキーをリセットし、`0`, `1`, `2`...という連番に振り直します。これにより、Blade側でインデックスを使った順位表示などが容易になります。

### `calculateGenreRatings()` メソッド：ジャンル別評価傾向

```php
private function calculateGenreRatings(Collection $reviews): Collection
{
    return $reviews
        // flatMap: 1レビュー → 複数ジャンルに展開（多対多の扱い）
        ->flatMap(fn ($review) => $review->book->genres->map(fn ($genre) => [
            'genre_id' => $genre->id,
            'genre_name' => $genre->name,
            'rating' => $review->rating,
        ]))
        // groupBy + map: ジャンルごとに集計
        ->groupBy('genre_id')
        ->map(fn ($items) => [
            'id' => $items->first()['genre_id'],
            'name' => $items->first()['genre_name'],
            'count' => $items->count(),
            'average_rating' => round($items->avg('rating'), 1),
        ])
        ->sortByDesc('average_rating')
        ->take(5)
        ->values();
}
```

1.  **`->flatMap(...)`**: ここでの最重要メソッドです。`map`と似ていますが、`flatMap`は多次元のコレクションを一次元のフラットなコレクションに展開します。ここでは、各レビューについて、その書籍が持つ全ジャンルを展開し、`['genre_id' => ..., 'genre_name' => ..., 'rating' => ...]` という配列のフラットなコレクションを生成します。キーを`genre_id`/`genre_name`としているのは、後の`groupBy`でジャンルIDを使い、集計結果にも正しいキー名で値を取り出せるようにするためです。
2.  **`->groupBy('genre_id')`**: フラット化されたコレクションを、ジャンルIDでグループ化します。ジャンル名ではなくIDでグループ化することで、同名の異なるジャンルが混同されるのを防ぎます。
3.  **`->map(...)`**: 各ジャンルグループについて、`$items->first()['genre_id']`と`$items->first()['genre_name']`でジャンル情報を取得し、レビュー数（`count`）と平均評価（`average_rating`）を計算した新しいコレクションを生成します。
4.  **`->sortByDesc('average_rating')`**: 平均評価の高い順にソートします。
5.  **`->take(5)`**: 上位5件を取得し、`values()`でキーをリセットして返します。

### **構文解説：アロー関数とメソッドチェーン**

`calculateGenreRatings`メソッドのコードは、アロー関数とメソッドチェーンが多用されており、初学者には複雑に見えるかもしれません。少し分解して見ていきましょう。

```php
// ...
->flatMap(fn ($review) => $review->book->genres->map(fn ($genre) => [
    'genre_id' => $genre->id,
    'genre_name' => $genre->name,
    'rating' => $review->rating,
]))
// ...
```

#### **アロー関数 `fn (...) => ...`**

これは、PHP 7.4から導入された短い無名関数（クロージャ）の書き方で、「アロー関数」と呼ばれます。

例えば、`fn ($review) => ...` の部分は、従来の書き方では以下のようになります。

```php
function ($review) {
    return ...;
}
```

アロー関数を使うと、より簡潔に処理を記述できます。`map`や`filter`のような、コールバック関数を引数に取るメソッドで特によく使われます。なお、型宣言（`Review $review`や`: Collection`など）を省略することで、さらに簡潔に書けます。

#### **メソッドチェーン `->method1()->method2()`**

`->` で繋がれた一連の処理は「メソッドチェーン」と呼ばれます。これは、前のメソッドが返した**コレクションオブジェクト**に対して、次のメソッドを呼び出しています。

`calculateGenreRatings`の処理の流れを日本語にすると、以下のようになります。

1.  `$reviews` というレビューのコレクションに対して…
2.  `flatMap` を使って、各レビューからジャンル情報を取り出し、一つの大きなコレクションに平坦化しなさい。
3.  その平坦化されたコレクションに対して…
4.  `groupBy('genre_id')` を使って、ジャンルIDでグループ分けしなさい。
5.  そのグループ分けされたコレクションに対して…
6.  `map` を使って、各ジャンルのレビュー数と平均評価を計算しなさい。
7.  その計算結果のコレクションに対して…
8.  `sortByDesc('average_rating')` を使って、平均評価の高い順に並べ替えなさい。
9.  その並べ替えたコレクションに対して…
10. `take(5)` を使って、上位5件を取得しなさい。
11. 最後に `values()` を使って、キーをリセットして完成。

このように、コレクションという「データが入った箱」を、メソッドチェーンという「加工ライン」に乗せて、次々と処理を加えていくイメージを持つと理解しやすくなります。

## 6. この実装にたどり着くための調べ方 🧐

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

## 7. 動作確認 ✅

| 確認項目 | 確認方法 |
|:---|:---|
| マイレポート画面表示 | ログイン状態で `/reports` にアクセス → 「マイ読書レポート」ヘッダーと 4 つの統計ブロック（サマリー / 評価分布 / 高評価書籍 TOP5 / ジャンル別評価傾向 TOP5）が表示される |
| 基本サマリー算出 | レビュー総数・読了冊数（ユニーク書籍数）・平均評価が、自分のレビューデータと一致すること |
| 評価分布 | 1〜5 星ごとの件数が、自分のレビューデータと一致すること |
| 高評価書籍 TOP5 | 自分が 4 星以上付けた書籍の中で評価の高い順に上位 5 件表示されること |
| ジャンル別評価傾向 | 自分のレビュー対象書籍のジャンルごとに平均評価が集計され、高い順に上位 5 件表示されること |
| 他ユーザーのデータが混ざらない | 別ユーザーでログインし直すと、レポート内容が切り替わること |
| 未認証時のリダイレクト | ログアウト状態で `/reports` にアクセスするとログイン画面にリダイレクトされる |
| レビューが 0 件のユーザー | レビューを 1 件も投稿していないユーザーで `/reports` を見ると、空でもエラーにならず統計値が 0 で表示されること |

---

## 8. まとめ ✨

このChapterでは、Laravel Collectionが提供する多彩なメソッドを駆使して、複雑な統計データを効率的かつ宣言的に生成する方法を学びました。

- **Collectionの真価**: `foreach`で複雑なループを書く代わりに、`map`, `groupBy`, `flatMap`などのメソッドチェーンで、宣言的にデータを処理できる。
- **関心の分離**: 複雑な処理は、意味のある単位でプライベートメソッドに分割することで、コードの可読性とメンテナンス性を高める。
- **パフォーマンス意識**: N+1問題を避けるため、`with()`によるEager Loadingを常に意識する。

データベースから取得した後のデータ加工は、Collectionを使いこなせるかどうかでコードの品質が大きく変わります。ぜひ、公式ドキュメントを片手に、様々なメソッドを試してみてください。
