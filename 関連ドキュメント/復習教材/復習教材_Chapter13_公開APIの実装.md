# Chapter 13: 公開APIの実装

---

## 🎯 このセクションで学ぶこと

このセクションでは、これまで作ってきた書籍管理アプリケーションのデータを、外部のプログラムやクライアント（モバイルアプリやSPAなど）から利用できるようにする**公開API**を実装します。

- **APIルート**の定義とバージョニング（`/api/v1/`）
- **APIリソース**（`JsonResource`）を使ったレスポンス形式の整形
- **FormRequest**を使ったAPIリクエストのバリデーション
- `withCount()` / `withAvg()` を使った**効率的なリレーション集計**
- `when()` / `whenLoaded()` による**条件付きレスポンス**
- **例外ハンドラ**のカスタマイズによるAPIエラーレスポンスの統一

> **注意:** この Step ではまだ認証を実装しません。全エンドポイントを認証なしで公開します。Chapter 18（Sanctum認証層の追加）で、書き込み系エンドポイントに認証を追加します。

---

## 📖 先輩エンジニアの思考プロセス

### なぜWeb画面とは別にAPIを作るのか？

> 「Webブラウザ向けの画面はHTMLを返すけど、APIはJSON形式のデータを返す。それぞれ利用者が違う。Web画面はブラウザで閲覧するユーザー向け、APIはモバイルアプリやフロントエンドフレームワーク、外部サービスなど、プログラムから利用されることを想定している。だから、データ構造やエラーハンドリングのアプローチもWeb画面とは異なってくるんだ。」

### なぜAPIリソースを使うのか？

> 「コントローラーでEloquentモデルをそのまま`response()->json()`に渡せば、確かにJSONは返せる。でも、それだとモデルの構造がそのまま外部に公開されてしまう。例えば、`created_at`や`updated_at`のような内部的なタイムスタンプや、APIの利用者には不要なカラムまで全部見えてしまう。
>
> **APIリソース**は、モデルと最終的なJSONレスポンスの間に立つ『通訳者』のようなもの。モデルからどのデータを取り出し、どのようなキー名で、どのような構造のJSONに変換するかを、リソースクラス内で一元的に管理できる。将来の仕様変更にも強くなるし、可読性も上がる。APIを作るなら、APIリソースを使うのがプロの作法だよ。」

### どうやってAPIのエラーレスポンスを統一するか？

> 「`routes/api.php`で定義されたルートへのリクエストで、存在しないIDが指定されたら、LaravelはデフォルトでHTMLの404エラーページを返そうとする。でも、APIの利用者はJSONを期待しているから、これは不親切だ。
>
> こういうアプリケーション全体に関わる例外処理は、`app/Exceptions/Handler.php`の`render`メソッドでカスタマイズするのが定石。`$request->is('api/*')`でAPIリクエストかどうかを判定し、`ModelNotFoundException`だったら要件通りのJSONを返すように上書きする。これで、どのAPIでモデルが見つからなくても、統一された親切なエラーメッセージを返せるようになる。」

---

## 📋 要件の確認

### 書籍一覧API (GET /api/v1/books)

**リクエストパラメータ**

| パラメータ | 型 | 必須 | 説明 |
|:---|:---|:---:|:---|
| `keyword` | string | | タイトル・著者での部分一致検索 |
| `genre_id` | integer | | ジャンルIDでの絞り込み |
| `page` | integer | | ページ番号（デフォルト: 1） |
| `per_page` | integer | | 1ページあたりの件数（デフォルト: 20、最大: 100） |

### 書籍詳細API (GET /api/v1/books/{book})

- 成功時: 200 OK（書籍情報 + レビュー一覧を含む）
- 書籍が見つからない場合: 404 Not Found（`{"error": "書籍が見つかりませんでした。"}`）

### 書籍登録API (POST /api/v1/books)

- この段階ではリクエストボディに`user_id`を含める（Chapter 18で`$request->user()`に変更）

### 書籍更新API (PUT /api/v1/books/{book})

- この段階ではリクエストボディに`user_id`を含める（Chapter 18で削除）

### 書籍削除API (DELETE /api/v1/books/{book})

- 成功時: 204 No Content

---

## 💭 実装の設計

以下のファイルを作成・編集します。

| ファイル | 役割 |
|:---|:---|
| `app/Http/Requests/Api/V1/IndexBookRequest.php` | 一覧APIのバリデーション |
| `app/Http/Requests/Api/V1/StoreBookRequest.php` | 登録APIのバリデーション（user_id含む） |
| `app/Http/Requests/Api/V1/UpdateBookRequest.php` | 更新APIのバリデーション（user_id含む） |
| `app/Http/Resources/Api/V1/GenreResource.php` | ジャンルのJSON整形 |
| `app/Http/Resources/Api/V1/ReviewResource.php` | レビューのJSON整形 |
| `app/Http/Resources/Api/V1/BookResource.php` | 書籍のJSON整形 |
| `app/Http/Controllers/Api/V1/BookController.php` | APIコントローラー |
| `app/Exceptions/Handler.php` | 例外ハンドラ（API用カスタマイズ） |
| `routes/api.php` | APIルート定義 |

---

## 🚀 実装手順

### 13.1. API用 FormRequest

#### `app/Http/Requests/Api/V1/IndexBookRequest.php`

```bash
mkdir -p app/Http/Requests/Api/V1
sail artisan make:request Api/V1/IndexBookRequest
```

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre_id.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_id.exists' => '指定されたジャンルが存在しません。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
            'per_page.integer' => '1ページあたりの件数は整数で指定してください。',
            'per_page.min' => '1ページあたりの件数は1以上で指定してください。',
            'per_page.max' => '1ページあたりの件数は100以下で指定してください。',
        ];
    }
}
```

#### `app/Http/Requests/Api/V1/StoreBookRequest.php`

```bash
sail artisan make:request Api/V1/StoreBookRequest
```

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ユーザーIDは必須です。',
            'user_id.integer' => 'ユーザーIDは整数で指定してください。',
            'user_id.exists' => '指定されたユーザーが存在しません。',
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'description.string' => '概要は文字列で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは配列で入力してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
```

> **注意:** `user_id` フィールドは Chapter 18（Sanctum導入時）で削除します。この段階ではリクエストボディで書籍登録者を指定します。

#### `app/Http/Requests/Api/V1/UpdateBookRequest.php`

```bash
sail artisan make:request Api/V1/UpdateBookRequest
```

```php
<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->route('book'))],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ユーザーIDは必須です。',
            'user_id.integer' => 'ユーザーIDは整数で指定してください。',
            'user_id.exists' => '指定されたユーザーが存在しません。',
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者名は必須です。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'そのISBNは既に使用されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は有効な日付形式で入力してください。',
            'description.string' => '概要は文字列で入力してください。',
            'image_url.url' => '画像URLは有効なURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは配列で入力してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }
}
```

> **注意:** `user_id` フィールドは Chapter 18（Sanctum導入時）で削除します。

### 13.2. APIリソース

#### `app/Http/Resources/Api/V1/GenreResource.php`

```bash
mkdir -p app/Http/Resources/Api/V1
sail artisan make:resource Api/V1/GenreResource
```

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
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

```bash
sail artisan make:resource Api/V1/ReviewResource
```

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user->name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
        ];
    }
}
```

#### `app/Http/Resources/Api/V1/BookResource.php`

```bash
sail artisan make:resource Api/V1/BookResource
```

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
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

> **ポイント:** `BookCollection` クラスは作りません。`BookResource::collection($paginator)` で Laravel が自動的に `data` + `meta` + `links` を付与します。

### 13.3. 例外ハンドラのカスタマイズ

#### `app/Exceptions/Handler.php`

404 と 403 を API 用にカスタム JSON レスポンスとして返すように `render()` メソッドを追加します。

```php
<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
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
        if ($request->is('api/*')) {
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'error' => '書籍が見つかりませんでした。',
                ], 404);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'error' => 'この操作を実行する権限がありません。',
                ], 403);
            }
        }

        return parent::render($request, $e);
    }
}
```

### 13.4. APIコントローラ

#### `app/Http/Controllers/Api/V1/BookController.php`

この段階では認証なし（CRUD全公開）で実装します。Sanctum 認証層は Chapter 18 で追加します。

```bash
mkdir -p app/Http/Controllers/Api/V1
sail artisan make:controller Api/V1/BookController
```

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookController extends Controller
{
    /**
     * 書籍一覧を取得
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $query = Book::with('genres')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('genre_id')) {
            $genreId = $request->input('genre_id');
            $query->whereHas('genres', function ($q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $books = $query->latest()->paginate($perPage);

        return BookResource::collection($books);
    }

    /**
     * 書籍詳細を取得
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を新規登録（認証なし -- Chapter 18 で Sanctum 認証を追加する）
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genres'];
        $userId = $validated['user_id'];
        unset($validated['genres'], $validated['user_id']);

        $book = User::findOrFail($userId)->books()->create($validated);
        $book->genres()->sync($genreIds);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 書籍を更新（認証なし -- Chapter 18 で Sanctum 認証 + BookPolicy を追加する）
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $validated = $request->validated();
        $genreIds = $validated['genres'];
        unset($validated['genres'], $validated['user_id']);

        $book->update($validated);
        $book->genres()->sync($genreIds);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を削除（認証なし -- Chapter 18 で Sanctum 認証 + BookPolicy を追加する）
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

### 13.5. APIルート定義 (`routes/api.php`)

この段階では全エンドポイントを認証なしで公開します。

```php
<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('books', BookController::class);
});
```

> **注意:** Chapter 18 で Sanctum 認証を追加する際に、読み取り系と書き込み系を分離します。

---

## 🔍 コードの詳細解説

### APIコントローラー - `index` メソッド

```php
public function index(IndexBookRequest $request): AnonymousResourceCollection
{
    $query = Book::with('genres')
        ->withCount('reviews')
        ->withAvg('reviews', 'rating');
```

1. **`IndexBookRequest $request`**: 専用のFormRequestを使うことで、リクエストパラメータのバリデーションがコントローラーに到達する前に自動実行されます。
2. **`Book::with('genres')`**: N+1問題を避けるため、ジャンル情報をEager Loadingします。
3. **`withCount('reviews')`**: 各書籍のレビュー数を`reviews_count`として集計します。書籍ごとにカウントクエリを発行する必要がなくなります。
4. **`withAvg('reviews', 'rating')`**: 各書籍の平均評価を`reviews_avg_rating`として集計します。

### APIリソース - `BookResource`

```php
'genres' => GenreResource::collection($this->whenLoaded('genres')),
'average_rating' => $this->reviews_avg_rating !== null
    ? round((float) $this->reviews_avg_rating, 1)
    : null,
'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
```

1. **`whenLoaded('genres')`**: `genres`リレーションが読み込まれている場合にのみレスポンスに含めます。読み込まれていない場合、このフィールドはレスポンスから除外されます。
2. **`GenreResource::collection(...)`**: 複数のジャンルを`GenreResource`で変換します。
3. **`round((float) $this->reviews_avg_rating, 1)`**: 平均評価を小数点以下1桁に丸めます。

### 例外ハンドラ - `render` メソッド

```php
if ($request->is('api/*')) {
    if ($e instanceof ModelNotFoundException) {
        return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
    }
    if ($e instanceof AuthorizationException) {
        return response()->json(['error' => 'この操作を実行する権限がありません。'], 403);
    }
}
```

1. **`$request->is('api/*')`**: URLが`api/`で始まるリクエスト、つまりAPIリクエストに対してのみカスタム処理を行います。Web画面の例外処理には影響を与えません。
2. **`ModelNotFoundException`**: ルートモデルバインディングでモデルが見つからなかった時にスローされる例外です。
3. **`AuthorizationException`**: Chapter 18で追加する`$this->authorize()`が失敗した時にスローされる例外です。

---

## 🧐 How to: この実装にたどり着くための調べ方

### 「APIってどうやって作るの？」

- **検索**: `Laravel API 作り方`
- `routes/api.php`にルートを定義し、コントローラーでJSONを返すこと、APIリソースの存在を知る

### 「レスポンスの形を整えたい」

- **検索**: `Laravel APIリソース 使い方`
- 公式ドキュメントの「Eloquent: APIリソース」で`toArray`メソッドやリソースコレクション、`whenLoaded`を学ぶ

### 「レビュー数や平均評価を効率的に取るには？」

- **検索**: `Laravel リレーション カウント 効率的`
- `withCount()`と`withAvg()`で1回のクエリでリレーション先の集計ができることを知る

### 「APIでデータが見つからなかった時のエラー表示」

- **検索**: `Laravel API 404 JSON 返す`
- `app/Exceptions/Handler.php`の`render`メソッドをカスタマイズする方法を習得する

---

## ✅ 動作確認

APIが正しく動作するか、以下のコマンドで確認しましょう。

```bash
# 書籍一覧を取得
curl -s http://localhost/api/v1/books | jq

# 書籍詳細を取得（IDを指定）
curl -s http://localhost/api/v1/books/1 | jq

# キーワード検索
curl -s "http://localhost/api/v1/books?keyword=Laravel" | jq

# 存在しないIDでの詳細取得（404エラーの確認）
curl -s http://localhost/api/v1/books/99999 | jq
```

---

## ✨ まとめ

このChapterでは、LaravelでRESTful APIを開発するための一連の流れを学びました。

| 学んだこと | 内容 |
|:---|:---|
| **APIルートの定義** | `routes/api.php`にルートを定義し、`prefix`でバージョニングを行う |
| **FormRequest** | APIリクエストのバリデーションを専用クラスに分離する |
| **APIリソースの活用** | `JsonResource`を使ってモデルのデータを要件通りのJSON形式に整形する |
| **効率的なデータ取得** | `withCount()` / `withAvg()`でN+1問題を回避しつつ集計する |
| **エラーハンドリング** | `Handler.php`の`render`メソッドでAPIエラーレスポンスを統一する |

これらのテクニックを組み合わせることで、実践的で堅牢なAPIを構築できます。次の Chapter 14 ではテストを学び、Chapter 18 でこのAPIに Sanctum 認証を追加します。
