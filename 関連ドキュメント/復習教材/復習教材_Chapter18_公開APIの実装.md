# Chapter 18: 公開APIの実装

## 1. はじめに

近年、Webアプリケーションが外部のサービスやクライアント（例：スマートフォンアプリ、JavaScriptフレームワークを使ったSPA）に対してデータを提供するために、**JSON形式のAPI**を公開することが一般的になっています。

このChapterでは、これまで作ってきた書籍管理アプリケーションのデータを、外部から利用できるようにするための公開APIを実装します。LaravelにおけるAPI開発の基本、バージョニング、そしてAPIリソースを使ったレスポンス整形の方法を学びます。

## 2. 要件の確認

### 2.1. 書籍一覧API (GET /api/v1/books)

**リクエストパラメータ**

| パラメータ | 型 | 必須 | 説明 |
|:---|:---|:---:|:---|
| `keyword` | string | | タイトル・著者での部分一致検索 |
| `genre_id` | integer | | ジャンルIDでの絞り込み |
| `page` | integer | | ページ番号（デフォルト: 1） |
| `per_page` | integer | | 1ページあたりの件数（デフォルト: 20、最大: 100） |

**レスポンス (成功時): 200 OK**

```json
{
  "data": [
    {
      "id": 1,
      "title": "パーフェクトPHP",
      "author": "小川 雄大",
      "isbn": "9784297124219",
      "published_date": "2021-10-19",
      "genres": [
        {"id": 1, "name": "技術書"}
      ],
      "average_rating": 4.5,
      "review_count": 10
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### 2.2. 書籍詳細API (GET /api/v1/books/{book})

**レスポンス (成功時): 200 OK**

```json
{
  "data": {
    "id": 1,
    "title": "パーフェクトPHP",
    "author": "小川 雄大",
    "isbn": "9784297124219",
    "published_date": "2021-10-19",
    "description": "...",
    "image_url": "...",
    "genres": [
      {"id": 1, "name": "技術書"}
    ],
    "average_rating": 4.5,
    "reviews": [
      {
        "id": 1,
        "user_name": "山田太郎",
        "rating": 5,
        "comment": "とても参考になりました。",
        "created_at": "2026-01-01T12:00:00Z"
      }
    ]
  }
}
```

**レスポンス (書籍見つからず): 404 Not Found**

```json
{
  "error": "書籍が見つかりませんでした。"
}
```

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

### 4.1. API用のルート定義

`routes/api.php`を以下のように編集します。

```php
// routes/api.php

<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

// API v1 - 認証不要の公開API
Route::prefix("v1")->group(function () {
    Route::get("/books", [BookController::class, "index"]);
    Route::get("/books/{book}", [BookController::class, "show"]);
});
```

### 4.2. APIリソースの作成

APIのレスポンス形式を要件通りに整形するため、APIリソースを作成します。これにより、モデルのデータをJSONに変換するロジックを一元管理できます。

```bash
mkdir -p app/Http/Resources/Api/V1
# 以下は本来artisanコマンドで作成しますが、手動で作成します
```

#### `app/Http/Resources/Api/V1/GenreResource.php`

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
```

#### `app/Http/Resources/Api/V1/ReviewResource.php`

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user->name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

#### `app/Http/Resources/Api/V1/BookResource.php`

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->when($this->relationLoaded('reviews'), $this->description),
            'image_url' => $this->when($this->relationLoaded('reviews'), $this->image_url),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'average_rating' => round($this->reviews_avg_rating, 1),
            'review_count' => (int) $this->reviews_count,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
```

#### `app/Http/Resources/Api/V1/BookCollection.php`

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BookCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
```

### 4.3. API用コントローラーの作成

`app/Http/Controllers/Api/V1/BookController.php`を作成し、以下のように編集します。

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookCollection;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        if ($perPage > 100) {
            $perPage = 100;
        }

        $books = Book::query()
            ->with(['genres'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->when($request->input('keyword'), function ($query, $keyword) {
                $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            })
            ->when($request->input('genre_id'), function ($query, $genreId) {
                $query->whereHas('genres', function ($q) use ($genreId) {
                    $q->where('genres.id', $genreId);
                });
            })
            ->latest('published_date')
            ->paginate($perPage);

        return new BookCollection($books);
    }

    public function show(Book $book)
    {
        $book->load(['genres', 'reviews.user']);
        return new BookResource($book);
    }
}
```

### 4.4. 404エラーレスポンスのカスタマイズ

`app/Exceptions/Handler.php`を編集し、APIリクエストでモデルが見つからなかった場合に特定のJSONレスポンスを返すようにします。

```php
// app/Exceptions/Handler.php

<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof ModelNotFoundException && $request->wantsJson()) {
            return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
        }

        return parent::render($request, $e);
    }
}
```

## 5. コードの詳細解説

### 5.1. BookController - `index` メソッド

```php
public function index(Request $request)
{
    $perPage = $request->input('per_page', 20);
    if ($perPage > 100) {
        $perPage = 100;
    }

    $books = Book::query()
        ->with(['genres'])
        ->withCount('reviews')
        ->withAvg('reviews', 'rating')
        ->when($request->input('keyword'), function ($query, $keyword) {
            $query->where('title', 'like', "%{$keyword}%")
                ->orWhere('author', 'like', "%{$keyword}%");
        })
        ->when($request->input('genre_id'), function ($query, $genreId) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        })
        ->latest('published_date')
        ->paginate($perPage);

    return new BookCollection($books);
}
```

1. **`$request->input('per_page', 20)`**: リクエストから`per_page`パラメータを取得します。指定がなければデフォルト値の`20`を使います。
2. **`if ($perPage > 100)`**: `per_page`が100を超えていたら、100に制限します。サーバーに過度な負荷をかけないための保護措置です。
3. **`Book::query()`**: Bookモデルに対するクエリビルダを開始します。`Book::where(...)`と書くこともできますが、`query()`から始めることで、後続のメソッドチェーンがより明確になります。
4. **`->with(['genres'])`**: N+1問題を避けるため、書籍情報と一緒にジャンル情報もEager Loading（事前読み込み）します。
5. **`->withCount('reviews')`**: 各書籍に紐づくレビューの数を`reviews_count`という名前の属性として取得します。これにより、書籍ごとにレビューをカウントするための追加クエリが不要になります。
6. **`->withAvg('reviews', 'rating')`**: 各書籍に紐づくレビューの`rating`カラムの平均値を`reviews_avg_rating`という名前の属性として取得します。
7. **`->when($request->input('keyword'), function ($query, $keyword) { ... })`**: 第一引数の値（`$request->input('keyword')`）が存在する（`true`と評価される）場合にのみ、第二引数のクロージャを実行します。これにより、`keyword`パラメータが指定された時だけ検索条件が追加されます。
8. **`$query->where('title', 'like', "%{$keyword}%")->orWhere('author', 'like', "%{$keyword}%")`**: タイトルまたは著者名にキーワードが含まれる書籍を検索します。`%`はワイルドカードで、任意の文字列にマッチします。
9. **`->when($request->input('genre_id'), function ($query, $genreId) { ... })`**: `genre_id`パラメータが指定された時だけ、ジャンルでの絞り込み条件を追加します。
10. **`$query->whereHas('genres', function ($q) use ($genreId) { ... })`**: `whereHas`は、リレーション先（`genres`）に特定の条件を満たすレコードが存在する場合にのみ、親レコード（`books`）を取得します。`use ($genreId)`は、外側のスコープにある`$genreId`変数をクロージャ内で使えるようにするための構文です。
11. **`->latest('published_date')`**: `published_date`カラムを基準に降順（新しい順）で並べ替えます。`latest()`は`orderBy(..., 'desc')`のショートカットです。
12. **`->paginate($perPage)`**: クエリの結果を指定された件数でページネーションします。Laravelが自動的にページネーション用のメタ情報（`current_page`, `last_page`, `total`など）を付与してくれます。
13. **`return new BookCollection($books)`**: 取得したページネーション済みのコレクションを`BookCollection`リソースに渡して返却します。`BookCollection`が最終的なJSON構造を生成します。

### 5.2. BookController - `show` メソッド

```php
public function show(Book $book)
{
    $book->load(['genres', 'reviews.user']);
    return new BookResource($book);
}
```

1. **`Book $book`**: ルートモデルバインディングにより、URLの`{book}`部分に対応するIDを持つ`Book`モデルが自動的に取得されます。該当するモデルが見つからない場合は、`ModelNotFoundException`がスローされます。
2. **`$book->load(['genres', 'reviews.user'])`**: すでに取得済みの`$book`モデルに対して、追加でリレーションを読み込みます（Lazy Eager Loading）。`reviews.user`は、レビューとそのレビューを書いたユーザーの両方を読み込むことを意味します。
3. **`return new BookResource($book)`**: 取得したモデルを`BookResource`に渡して返却します。`BookResource`が詳細用のJSON構造を生成します。

### 5.3. BookResource

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'author' => $this->author,
        'isbn' => $this->isbn,
        'published_date' => $this->published_date,
        'description' => $this->when($this->relationLoaded('reviews'), $this->description),
        'image_url' => $this->when($this->relationLoaded('reviews'), $this->image_url),
        'genres' => GenreResource::collection($this->whenLoaded('genres')),
        'average_rating' => round($this->reviews_avg_rating, 1),
        'review_count' => (int) $this->reviews_count,
        'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
    ];
}
```

1. **`$this->id`, `$this->title`, etc.**: APIリソース内では、`$this`を通じて元のモデルのプロパティにアクセスできます。
2. **`$this->when($this->relationLoaded('reviews'), $this->description)`**: `when`メソッドは、第一引数が`true`の場合にのみ、第二引数の値をレスポンスに含めます。`$this->relationLoaded('reviews')`は、`reviews`リレーションが読み込まれているかどうかを確認します。これにより、詳細APIでは`description`と`image_url`が含まれ、一覧APIでは含まれない、という出し分けが実現できます。
3. **`GenreResource::collection($this->whenLoaded('genres'))`**: `whenLoaded`は、指定したリレーションが読み込まれている場合にのみ、そのリレーションのデータを返します。`GenreResource::collection()`は、複数のジャンルを`GenreResource`を使って変換します。
4. **`round($this->reviews_avg_rating, 1)`**: `withAvg`で取得した平均評価を、小数点以下1桁に丸めます。
5. **`(int) $this->reviews_count`**: `withCount`で取得したレビュー数を整数にキャストします。

### 5.4. Handler.php - `render` メソッド

```php
public function render($request, Throwable $e)
{
    if ($e instanceof ModelNotFoundException && $request->wantsJson()) {
        return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
    }

    return parent::render($request, $e);
}
```

1. **`$e instanceof ModelNotFoundException`**: 発生した例外`$e`が`ModelNotFoundException`（モデルが見つからなかった時の例外）であるかを確認します。
2. **`$request->wantsJson()`**: リクエストがJSONレスポンスを期待している（`Accept: application/json`ヘッダが付いている、またはAjaxリクエストである）かを確認します。これにより、Web画面の404エラーには影響を与えません。
3. **`return response()->json(['error' => '書籍が見つかりませんでした。'], 404)`**: 条件に一致した場合、要件通りのJSONレスポンスと404ステータスコードを返します。
4. **`return parent::render($request, $e)`**: 上記の条件に一致しない場合は、Laravelのデフォルトの例外処理に処理を委譲します。

## 6. How to: この実装にたどり着くための調べ方

もし自力でこのAPI実装にたどり着くとしたら、どのような思考と検索を繰り返すでしょうか？初心者の視点で、その道のりを再現してみましょう。

### Step 1: 「APIってどうやって作るの？」という最初の疑問

- **思考**: 「書籍データを外部のプログラムから使えるようにしたい。こういうのをAPIって言うらしいけど、Laravelでどうやって作るんだろう？」
- **最初の検索**: `Laravel API 作り方`
- **得られる情報**: 検索結果から、`routes/api.php`というファイルにルートを定義すること、コントローラーでJSONを返すこと、`response()->json()`ヘルパが便利であること、などがわかります。また、「APIリソース」というキーワードも頻繁に目にするでしょう。

### Step 2: 「レスポンスの形を整えたい」という次の欲求

- **思考**: 「`response()->json($book)`だと、モデルの全データが丸見えだ。要件みたいに、キーの名前を変えたり、不要なデータを隠したり、関連データの件数や平均値を入れたりしたい。さっき見かけた『APIリソース』ってのが怪しいな。」
- **次の検索**: `Laravel APIリソース 使い方`
- **得られる情報**: 公式ドキュメントの「Eloquent: APIリソース」が見つかります。`make:resource`コマンドでクラスを作り、`toArray`メソッドでレスポンスの構造を自由に定義できることを学びます。`collection`メソッドや、`whenLoaded`、`when`などの条件付きで属性を含める方法もここで理解できます。

### Step 3: 「レビュー数や平均評価はどうやって効率的に取るの？」というパフォーマンスへの懸念

- **思考**: 「一覧表示で、書籍ごとにレビュー数と平均評価が必要だ。まさか1件ずつループしてSQLを投げるわけにはいかないよな（N+1問題）。もっと効率的な方法があるはずだ。」
- **次の検索**: `Laravel リレーション カウント 効率的` や `Laravel リレーション 平均 取得`
- **得られる情報**: `withCount()`と`withAvg()`という、まさにうってつけのメソッドが見つかります。これらを使えば、1回のクエリでリレーション先の集計ができることを知り、パフォーマンス問題を回避できます。

### Step 4: 「検索条件が色々あって、どうやってクエリを組み立てる？」という実装の悩み

- **思考**: 「`keyword`があったりなかったり、`genre_id`があったりなかったりする。`if`文でクエリを組み立てると、コードがごちゃごちゃしそうだ。もっとスマートな書き方はないかな？」
- **次の検索**: `Laravel クエリ 条件分岐 スマート` や `Laravel where 条件 あるときだけ`
- **得られる情報**: `when()`メソッドの存在を知ります。メソッドチェーンを保ったまま、条件に応じてクエリを追加できるため、コードが非常にスッキリすることを学びます。

### Step 5: 「APIでデータが見つからなかった時のエラー表示をいい感じにしたい」

- **思考**: 「存在しないIDで詳細APIを叩くと、HTMLのエラー画面が出ちゃう。APIなんだから、JSONで『見つかりません』って返したい。しかも、要件通りのフォーマットで。」
- **次の検索**: `Laravel API 404 JSON 返す` や `Laravel エラーハンドリング カスタマイズ`
- **得られる情報**: `app/Exceptions/Handler.php`の`render`メソッドをカスタマイズする方法が見つかります。`ModelNotFoundException`を捕捉し、`$request->wantsJson()`でAPIリクエストかどうかを判定して、独自のJSONレスポンスを返す方法を習得します。

このように、**「やりたいこと」を素朴な日本語で検索**し、得られたキーワードから**より具体的な公式ドキュメントの機能名で再検索**していくことで、一つ一つの課題を解決し、最終的な実装にたどり着くことができます。

## 7. まとめ

このChapterでは、Laravelで要件に基づいた公開APIを開発するための一連の流れを学びました。

学んだ主なポイントは以下の通りです。

1. **APIルートの定義**: `routes/api.php`にルートを定義し、`prefix`でバージョニングを行う方法。
2. **APIリソースの活用**: `JsonResource`と`ResourceCollection`を使って、モデルのデータを要件通りのJSON形式に整形する方法。
3. **効率的なデータ取得**: `withCount()`と`withAvg()`を使って、N+1問題を回避しながらリレーション先の集計データを取得する方法。
4. **条件付きクエリ**: `when()`メソッドを使って、オプションの検索パラメータに応じたクエリを組み立てる方法。
5. **エラーハンドリング**: `Handler.php`の`render`メソッドをカスタマイズして、APIリクエストに対するエラーレスポンスを統一する方法。

これらのテクニックを組み合わせることで、より実践的で堅牢なAPIを構築できるようになります。
