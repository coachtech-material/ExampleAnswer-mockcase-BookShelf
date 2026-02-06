# Chapter 18: 公開APIの実装

## 1. はじめに

近年、Webアプリケーションが外部のサービスやクライアント（例：スマートフォンアプリ、JavaScriptフレームワークを使ったSPA）に対してデータを提供するために、**JSON形式のAPI**を公開することが一般的になっています。

このChapterでは、これまで作ってきた書籍管理アプリケーションのデータを、外部から利用できるようにするための公開APIを実装します。LaravelにおけるAPI開発の基本、バージョニング、そしてAPIリソースを使ったレスポンス整形の方法を学びます。

## 2. 要件の確認

| 機能 | エンドポイント | HTTPメソッド | 詳細仕様 |
|:---|:---|:---:|:---|
| **書籍一覧取得API** | `/api/v1/books` | GET | 書籍の一覧をJSON形式で返す。検索、ページネーションに対応する。 |
| **書籍詳細取得API** | `/api/v1/books/{book}` | GET | 指定されたIDの書籍詳細情報をJSON形式で返す。 |

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

## 3. 実装

### 3.1. API用のルート定義

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

### 3.2. APIリソースの作成

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

### 3.3. API用コントローラーの作成

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

### 3.4. 404エラーレスポンスのカスタマイズ

`app/Exceptions/Handler.php`を編集し、APIリクエストでモデルが見つからなかった場合に特定のJSONレスポンスを返すようにします。

```php
// app/Exceptions/Handler.php

// ...
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Handler extends ExceptionHandler
{
    // ...

    public function render($request, Throwable $e)
    {
        if ($e instanceof ModelNotFoundException && $request->wantsJson()) {
            return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
        }

        return parent::render($request, $e);
    }
}
```

## 4. 先輩エンジニアの思考プロセス（実装の振り返り）

（省略）

## 5. 動作確認

（省略）

## 6. まとめ

（省略）
