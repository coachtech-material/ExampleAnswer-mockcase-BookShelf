# Chapter 18: 公開APIの実装

## 1. はじめに

近年、Webアプリケーションが外部のサービスやクライアント（例：スマートフォンアプリ、JavaScriptフレームワークを使ったSPA）に対してデータを提供するために、**JSON形式のAPI**を公開することが一般的になっています。

このChapterでは、これまで作ってきた書籍管理アプリケーションのデータを、外部から利用できるようにするための公開APIを実装します。LaravelにおけるAPI開発の基本、バージョニング、そしてAPIリソースを使ったレスポンス整形の方法を学びます。

## 2. 要件の確認

（省略）

## 3. 先輩エンジニアの思考プロセス：実装の設計

今回の実装は、これまでのWeb画面向けの実装とは少し毛色が異なります。APIを設計・実装する上で、どのようなことを考えるべきでしょうか？

### 思考1：なぜAPIリソースを使うのか？

> 「コントローラーでEloquentモデルをそのまま`response()->json()`に渡せば、確かにJSONは返せる。でも、それだとモデルの構造がそのまま外部に公開されてしまう。例えば、`created_at`や`updated_at`のような内部的なタイムスタンプや、APIの利用者には不要なカラムまで全部見えてしまうんだ。それに、関連するモデルの情報をどういう形式で含めるか、といった細かい制御も難しい。
>
> **APIリソース**は、モデルと最終的なJSONレスポンスの間に立つ『通訳者』のようなもの。モデルからどのデータを取り出し、どのようなキー名で、どのような構造のJSONに変換するかを、リソースクラス内で一元的に管理できる。これにより、APIのレスポンス形式を柔軟かつクリーンに保つことができるんだ。将来の仕様変更にも強くなるし、可読性も上がる。APIを作るなら、APIリソースを使うのがプロの作法だよ。」

### 思考2：どうやって効率的にデータを取得するか？

> 「書籍一覧APIでは、書籍情報に加えて、レビューの数（`review_count`）と平均評価（`average_rating`）も返す必要がある。これを実現するために、書籍ごとにループを回してレビューを取得・計算するのは、N+1問題を引き起こす最悪のパターンだ。
>
> ここで活躍するのが、`withCount()`と`withAvg()`。これらはEloquentの強力な機能で、リレーション先のレコード数や特定カラムの平均値を、メインのクエリに集約関数として結合してくれる。たった1行追加するだけで、データベース側で効率的に集計処理を行ってくれるんだ。パフォーマンスを意識するなら、必須のテクニックだね。」

### 思考3：検索条件をどうやって組み立てるか？

> 「一覧APIには`keyword`と`genre_id`という、オプションの検索パラメータがある。もし`keyword`が指定されたら`WHERE`句を追加し、`genre_id`が指定されたら`WHERE EXISTS`句（`whereHas`）を追加する、という条件分岐が必要になる。
>
> こういう時に便利なのが`when()`メソッド。`if`文でクエリを組み立てることもできるけど、`when()`を使うとメソッドチェーンを保ったまま、条件に応じてクエリを追加できるから、コードがすごくスッキリする。第一引数が`true`の場合にのみ、第二引数のクロージャ（無名関数）が実行される仕組みだ。」

### 思考4：APIのエラーレスポンスをどうやって統一するか？

> 「書籍詳細APIで、存在しないIDが指定されたら、LaravelはデフォルトでHTMLの404エラーページを返そうとする。でも、APIの利用者はJSONを期待しているから、これは不親切だ。
>
> `routes/api.php`で定義されたルートへのリクエストの場合、Laravelは`ModelNotFoundException`が発生した際に、自動でJSON形式の404エラーを返してくれる。でも、その形式は`{"message": "No query results for model [App\\Models\\Book] 100"}`のような詳細なもので、要件の`{"error": "書籍が見つかりませんでした。"}`とは違う。
>
> こういうアプリケーション全体に関わる例外処理は、`app/Exceptions/Handler.php`の`render`メソッドでカスタマイズするのが定石。`$request->wantsJson()`でAPIリクエストかどうかを判定し、`ModelNotFoundException`だったら要件通りのJSONを返すように上書きする。これで、どのAPIでモデルが見つからなくても、統一された親切なエラーメッセージを返せるようになる。」

## 4. 実装

（省略）

## 5. コードの詳細解説

### 5.1. BookController

#### `index` メソッド

```php
public function index(Request $request)
{
    $perPage = $request->input("per_page", 20);
    if ($perPage > 100) {
        $perPage = 100;
    }

    $books = Book::query()
        ->with(["genres"])
        ->withCount("reviews")
        ->withAvg("reviews", "rating")
        ->when($request->input("keyword"), function ($query, $keyword) {
            $query->where("title", "like", "%{$keyword}%")
                ->orWhere("author", "like", "%{$keyword}%");
        })
        ->when($request->input("genre_id"), function ($query, $genreId) {
            $query->whereHas("genres", function ($q) use ($genreId) {
                $q->where("genres.id", $genreId);
            });
        })
        ->latest("published_date")
        ->paginate($perPage);

    return new BookCollection($books);
}
```

1.  **`$request->input("per_page", 20)`**: リクエストから`per_page`パラメータを取得します。指定がなければデフォルト値の`20`を使います。
2.  **`if ($perPage > 100)`**: `per_page`が100を超えていたら、100に制限します。サーバーに過度な負荷をかけないための保護措置です。
3.  **`->with(["genres"])`**: N+1問題を避けるため、書籍情報と一緒にジャンル情報もEager Loading（事前読み込み）します。
4.  **`->withCount("reviews")`**: 各書籍に紐づくレビューの数を`reviews_count`という名前の属性として取得します。
5.  **`->withAvg("reviews", "rating")`**: 各書籍に紐づくレビューの`rating`カラムの平均値を`reviews_avg_rating`という名前の属性として取得します。
6.  **`->when(...)`**: 第一引数の値（`$request->input("keyword")`など）が存在する場合にのみ、第二引数のクロージャを実行します。条件に応じたクエリの追加をスマートに記述できます。
7.  **`->whereHas(...)`**: リレーション先のテーブル（`genres`）に特定の条件（`genres.id`が一致）のレコードが存在する書籍のみを絞り込みます。
8.  **`->paginate($perPage)`**: クエリの結果を指定された件数でページネーションします。
9.  **`return new BookCollection($books)`**: 取得したページネーション済みのコレクションを`BookCollection`リソースに渡して返却します。`BookCollection`が最終的なJSON構造を生成します。

#### `show` メソッド

```php
public function show(Book $book)
{
    $book->load(["genres", "reviews.user"]);
    return new BookResource($book);
}
```

1.  **`$book->load(...)`**: すでに取得済みの`$book`モデルに対して、追加でリレーションを読み込みます（Lazy Eager Loading）。ここでは、詳細情報として必要なジャンルと、レビュー（さらにその所有ユーザー）を読み込んでいます。
2.  **`return new BookResource($book)`**: 取得したモデルを`BookResource`に渡して返却します。`BookResource`が詳細用のJSON構造を生成します。

### 5.2. BookResource

```php
public function toArray(Request $request): array
{
    return [
        // ...
        "description" => $this->when($this->relationLoaded("reviews"), $this->description),
        "image_url" => $this->when($this->relationLoaded("reviews"), $this->image_url),
        "genres" => GenreResource::collection($this->whenLoaded("genres")),
        "average_rating" => round($this->reviews_avg_rating, 1),
        "review_count" => (int) $this->reviews_count,
        "reviews" => ReviewResource::collection($this->whenLoaded("reviews")),
    ];
}
```

1.  **`$this->when($this->relationLoaded("reviews"), ...)`**: `reviews`リレーションが読み込まれている場合（つまり、詳細APIの場合）にのみ、`description`と`image_url`をレスポンスに含めます。一覧APIでは不要なデータを隠蔽できます。
2.  **`GenreResource::collection($this->whenLoaded("genres"))`**: `genres`リレーションが読み込まれている場合に、`GenreResource`を使ってジャンルのコレクションを整形します。
3.  **`round($this->reviews_avg_rating, 1)`**: `withAvg`で取得した平均評価を、小数点以下1桁に丸めます。
4.  **`(int) $this->reviews_count`**: `withCount`で取得したレビュー数を整数にキャストします。
5.  **`ReviewResource::collection(...)`**: `reviews`リレーションが読み込まれている場合に、`ReviewResource`を使ってレビューのコレクションを整形します。

### 5.3. Handler.php

```php
public function render($request, Throwable $e)
{
    if ($e instanceof ModelNotFoundException && $request->wantsJson()) {
        return response()->json(["error" => "書籍が見つかりませんでした。"], 404);
    }

    return parent::render($request, $e);
}
```

1.  **`if ($e instanceof ModelNotFoundException ...)`**: 発生した例外`$e`が`ModelNotFoundException`（モデルが見つからなかった時の例外）であるかを確認します。
2.  **`... && $request->wantsJson()`**: かつ、リクエストがJSONレスポンスを期待している（`Accept: application/json`ヘッダが付いている）かを確認します。これにより、Web画面の404エラーには影響を与えません。
3.  **`return response()->json(...)`**: 条件に一致した場合、要件通りのJSONレスポンスと404ステータスコードを返します。
4.  **`return parent::render($request, $e)`**: 上記の条件に一致しない場合は、Laravelのデフォルトの例外処理に処理を委譲します。

## 6. How to: この実装にたどり着くための調べ方

| やりたいこと | 検索キーワード（例） | たどり着く答え（公式ドキュメントなど） |
|:---|:---|:---|
| **APIのレスポンス形式を統一したい** | `laravel api resources` | APIリソースのドキュメントが見つかる。モデルのデータを特定のJSON構造に変換するための専用クラスを作成できることを知る。 |
| **リレーション先の件数や平均値を取得したい** | `laravel withcount` `laravel withavg` | Eloquentの`withCount`や`withAvg`メソッドが見つかり、N+1問題を発生させずに効率的に集計できることを知る。 |
| **条件によってクエリを追加したい** | `laravel query builder if` `laravel conditional query` | `when`メソッドが見つかり、条件分岐をスマートに記述できることを知る。 |
| **APIでモデルが見つからない時のエラーをカスタマイズしたい** | `laravel api 404 not found json` | `app/Exceptions/Handler.php`の`render`メソッドで`ModelNotFoundException`を捕捉してカスタマイズする方法が見つかる。 |

## 7. まとめ

このChapterでは、Laravelで要件に基づいた公開APIを開発するための一連の流れを学びました。特に、APIリソースを使ったレスポンスの整形、`withCount`/`withAvg`による効率的なデータ取得、`Handler`でのエラーレスポンスの統一など、より実践的で堅牢なAPIを構築するための重要なテクニックを習得しました。
