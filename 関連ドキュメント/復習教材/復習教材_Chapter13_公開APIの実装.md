# Chapter 13: Web を超えて - 公開APIの実装

## 🎯 このChapterの目標

このチャプターでは、BookShelfアプリケーションの書籍データを外部から利用できる**公開API（RESTful API）**を実装します。Webブラウザ以外のクライアント（モバイルアプリ、他のWebサービスなど）からもデータにアクセスできるようにします。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| APIルートの定義 | `routes/api.php` にAPIエンドポイントを定義する方法 |
| API Resourceの活用 | レスポンスのJSON形式を制御する `JsonResource` の使い方 |
| API専用のFormRequest | API向けのバリデーションと `user_id` をリクエストで受け取る設計 |
| 例外ハンドリング | API向けの404レスポンスをカスタマイズする方法 |

---

## 📖 背景知識

### なぜAPIが必要なのか？

Webアプリケーションは通常ブラウザからアクセスしますが、APIを提供することで以下のような利点があります:

- モバイルアプリからBookShelfのデータにアクセスできる
- 他のWebサービスと連携できる（例：ブログに書籍データを表示）
- フロントエンドをReactやVue.jsなどのSPAで構築できる

### 基本版のAPI設計

基本版では**認証なしの公開API**として実装します。ユーザーの特定は `user_id` をリクエストパラメータで送信する方式を採用しています。

---

## 📋 要件の確認

### エンドポイント一覧

| 操作 | HTTPメソッド | URI | 認証 |
|:---|:---|:---|:---|
| 書籍一覧 | GET | `/api/v1/books` | 不要 |
| 書籍詳細 | GET | `/api/v1/books/{book}` | 不要 |
| 書籍登録 | POST | `/api/v1/books` | 不要（user_idをリクエストで送信） |
| 書籍更新 | PUT | `/api/v1/books/{book}` | 不要（user_idをリクエストで送信） |
| 書籍削除 | DELETE | `/api/v1/books/{book}` | 不要 |

---

## 💭 なぜこう作るのか？

### Point 1: バージョニング（`/api/v1/`）

APIのURLに `v1` を含めることで、将来的にAPIの仕様を変更する際に `v2` として別バージョンを追加できます。既存のクライアントへの影響を最小限にできます。

### Point 2: API Resource でレスポンス形式を制御する

Eloquentモデルをそのまま返すと、不要なフィールドやリレーションが含まれてしまいます。`JsonResource` を使うことで、APIクライアントに返すデータの形式を明確に制御できます。

### Point 3: 例外ハンドリングのカスタマイズ

APIでは、存在しない書籍にアクセスした場合にHTMLではなくJSONでエラーを返す必要があります。`Handler.php` で `ModelNotFoundException` をキャッチし、カスタムJSONを返します。

---

## 🚀 コードの実装

### APIルート定義 (`routes/api.php`)

```php
<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('books', BookController::class);
});
```

### API コントローラー (`app/Http/Controllers/Api/V1/BookController.php`)

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;

class BookController extends Controller
{
    public function index(IndexBookRequest $request)
    {
        $query = Book::with('genres')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('genre_id')) {
            $genreId = $request->input('genre_id');
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $perPage = $request->input('per_page', 20);
        $books = $query->latest()->paginate($perPage);

        return BookResource::collection($books);
    }

    public function show(Book $book)
    {
        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $validated = $request->validated();

        $book->update([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    public function destroy(Book $book)
    {
        $book->delete();

        return response()->json(null, 204);
    }
}
```

### API Resource (`app/Http/Resources/Api/V1/BookResource.php`)

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'average_rating' => $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'review_count' => (int) ($this->reviews_count ?? 0),
            'description' => $this->description,
            'image_url' => $this->image_url,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
```

### 例外ハンドリング (`app/Exceptions/Handler.php`)

```php
public function render($request, Throwable $e)
{
    if ($request->is('api/*') && $e instanceof ModelNotFoundException) {
        return response()->json([
            'error' => '書籍が見つかりませんでした。',
        ], 404);
    }

    return parent::render($request, $e);
}
```

---

## 🔍 コードリーディング

| コード | 解説 |
|:---|:---|
| `Route::apiResource('books', ...)` | CRUDの5つのルート（index, show, store, update, destroy）を一括定義。 |
| `BookResource::collection($books)` | コレクションをAPI Resource形式に変換。ページネーション情報も含まれる。 |
| `$this->whenLoaded('genres')` | Eager Loadingされた場合のみデータを含める。 |
| `->response()->setStatusCode(201)` | 作成成功のHTTPステータスコード。 |
| `response()->json(null, 204)` | 削除成功。レスポンスボディなし。 |
| `$request->filled('keyword')` | パラメータが存在し、かつ空でないかチェック。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| API Resource | 「Laravel の JsonResource を使ってAPIレスポンスを整形する方法を教えてください。」 |
| apiResource ルート | 「Laravel の Route::apiResource が生成するルート一覧を教えてください。」 |
| HTTPステータスコード | 「REST APIで使用する主要なHTTPステータスコード（200, 201, 204, 404, 422）の意味を教えてください。」 |

---

## ✅ 動作確認

curlコマンドやPostmanで以下を確認してください。

| 確認項目 | 確認方法 |
|:---|:---|
| 書籍一覧 | `curl http://localhost/api/v1/books` でJSONが返ること |
| 書籍詳細 | `curl http://localhost/api/v1/books/1` で書籍情報 + レビューが返ること |
| 404 | `curl http://localhost/api/v1/books/99999` で `{"error": "書籍が見つかりませんでした。"}` が返ること |
| 書籍登録 | POSTリクエストで201が返り、データベースに保存されること |
| バリデーション | 不正データのPOSTで422 + エラー詳細が返ること |

---

## ✨ このChapterのまとめ

| 構成要素 | ファイル | 役割 |
|:---|:---|:---|
| APIルート | `routes/api.php` | `/api/v1/books` エンドポイント定義 |
| APIコントローラー | `Api/V1/BookController.php` | CRUD処理 |
| APIリソース | `BookResource.php` / `GenreResource.php` / `ReviewResource.php` | レスポンスJSON形式の制御 |
| APIバリデーション | `Api/V1/StoreBookRequest.php` 等 | 入力チェック（`user_id` 必須） |
| 例外ハンドリング | `Handler.php` | API向け404カスタムJSON |

次の Chapter 14 では、ここまでに実装した機能の**テスト**を作成します。
