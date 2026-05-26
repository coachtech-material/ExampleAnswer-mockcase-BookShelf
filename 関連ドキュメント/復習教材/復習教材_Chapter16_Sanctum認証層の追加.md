# Chapter 18: Sanctum認証層の追加

---

## 🎯 このセクションで学ぶこと

Chapter 13 で実装した公開APIは、全てのエンドポイントが認証なしで利用可能でした。このChapterでは、書き込み系のAPIエンドポイント（登録・更新・削除）に**Laravel Sanctum**によるトークン認証を追加します。

- **Laravel Sanctum**のインストールと設定
- **APIルート**の認証不要（読み取り系）と認証必須（書き込み系）への分離
- **FormRequest**からの`user_id`フィールド削除と、`$request->user()`への移行
- **APIコントローラー**への認可（`$this->authorize()`）の追加
- **Sanctum::actingAs()**を使ったAPIテストの書き方

---

## 1. はじめに 📖

### なぜAPIに認証が必要なのか？

> 「Chapter 13では、認証なしでAPIを全公開した。一覧や詳細の取得（読み取り系）は誰でもアクセスできていいけど、書籍の登録・更新・削除（書き込み系）を認証なしで公開し続けるのは危険だ。誰でも勝手にデータを変更できてしまう。だから、書き込み系のエンドポイントには、『誰がリクエストしているのか』を検証する仕組みが必要になる。これが認証だ。」

### なぜSanctumを使うのか？

> 「LaravelにはPassportという本格的なOAuth2認証パッケージもあるが、今回のようなSPAやモバイルアプリ向けのAPIなら、Sanctumの方がずっとシンプルで軽量だ。Sanctumはトークンベースの認証を提供してくれて、設定も最小限で済む。ユーザーに対してAPIトークンを発行し、リクエストの`Authorization`ヘッダーにそのトークンを含めてもらうだけで認証が完了する。」

### どこを変更するのか？

> 「変更箇所は大きく4つだ。
>
> 1. **routes/api.php**: 読み取り系と書き込み系のルートを分離し、書き込み系に`auth:sanctum`ミドルウェアを適用する。
> 2. **StoreBookRequest / UpdateBookRequest**: `user_id`フィールドを削除する。認証済みユーザーは`$request->user()`で取得できるようになるため、リクエストボディで指定する必要がなくなる。
> 3. **Api\V1\BookController**: `store`メソッドで`$request->user()`を使い、`update`/`destroy`に`$this->authorize()`を追加する。
> 4. **テスト**: `Sanctum::actingAs()`を使ってトークン認証をシミュレートする。」

---

## 2. 要件の確認 📋

| ファイル | 変更内容 |
|:---|:---|
| Sanctumパッケージ | インストール・マイグレーション |
| `app/Models/User.php` | `HasApiTokens`トレイトの確認 |
| `routes/api.php` | 読み取り/書き込みルートの分離 |
| `app/Http/Requests/Api/V1/StoreBookRequest.php` | `user_id`ルール削除 |
| `app/Http/Requests/Api/V1/UpdateBookRequest.php` | `user_id`ルール削除 |
| `app/Http/Controllers/Api/V1/BookController.php` | `$request->user()` + `authorize` 追加 |

---

## 3. 先輩エンジニアの思考プロセス 💭

このChapterの目的は、Chapter 13で作成した「認証なし」のAPIに「認証層」を追加することです。コードをゼロから書き直すのではなく、**差分（diff）で何が変わるのか**を理解することが重要です。

---

## 4. 実装 🚀

### 18.1. Sanctum のインストール

```bash
sail composer require laravel/sanctum
sail artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
sail artisan migrate
```

### 18.2. User モデルの確認

Chapter 3 で既に `HasApiTokens` トレイトを追加済みです。追加されていない場合は `app/Models/User.php` に追加してください。

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
```

### 18.3. APIルートの更新 (`routes/api.php`)

認証不要の読み取り系と、Sanctum 必須の書き込み系を分離します。

```php
<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 公開API v1。
| 読み取り系（index/show）は認証不要、書き込み系（store/update/destroy）は
| 応用機能で Laravel Sanctum によるトークン認証を必須化する。
|
*/

Route::prefix('v1')->group(function () {
    // 読み取り系（認証不要）
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    // 書き込み系（Sanctum 認証必須）
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
```

### 18.4. StoreBookRequest / UpdateBookRequest の更新

Sanctum 認証により `$request->user()` からユーザーを取得できるようになるため、`user_id` フィールドを削除します。

#### `app/Http/Requests/Api/V1/StoreBookRequest.php`（最終版）

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
            'description.string' => '説明は文字列で入力してください。',
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

#### `app/Http/Requests/Api/V1/UpdateBookRequest.php`（最終版）

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
            'description.string' => '説明は文字列で入力してください。',
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

### 18.5. Api\V1\BookController の更新

`store` / `update` / `destroy` を Sanctum 認証版に書き換えます。以下が**最終版の完全なコントローラ**です。

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
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
     * 書籍を新規登録（要 Sanctum 認証）
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $book = $request->user()->books()->create($validated);
        $book->genres()->sync($genreIds);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 書籍を更新（要 Sanctum 認証 + 書籍所有者）
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        $validated = $request->validated();
        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $book->update($validated);
        $book->genres()->sync($genreIds);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を削除（要 Sanctum 認証 + 書籍所有者）
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

---

## 5. コードの詳細解説 🔍

| 変更箇所 | Before (Chapter 13) | After (Chapter 18) |
|:---|:---|:---|
| `routes/api.php` | 全ルート認証なし | 読み取り系は認証なし、書き込み系は`auth:sanctum` |
| `StoreBookRequest` | `user_id`ルールあり | `user_id`ルール削除 |
| `UpdateBookRequest` | `user_id`ルールあり | `user_id`ルール削除 |
| `BookController@store` | `User::findOrFail($userId)` | `$request->user()` |
| `BookController@update` | 認可なし | `$this->authorize('update', $book)` |
| `BookController@destroy` | 認可なし | `$this->authorize('delete', $book)` |
| use文 | `use App\Models\User;` あり | 削除（不要になった） |

---

## 6. この実装にたどり着くための調べ方 🧐

### 「APIに認証を付けたい」

- **検索**: `Laravel API 認証 トークン`
- SanctumとPassportの2つの選択肢があることを知り、Sanctumの方がシンプルであることを理解する

### 「Sanctumの使い方は？」

- **検索**: `Laravel Sanctum 導入 手順`
- インストール、`HasApiTokens`トレイトの追加、`auth:sanctum`ミドルウェアの適用という手順を学ぶ

### 「テストでSanctum認証をどうやる？」

- **検索**: `Laravel Sanctum テスト actingAs`
- `Sanctum::actingAs($user, ['*'])`でテスト中の認証をシミュレートできることを知る

---

## 7. 動作確認 ✅

認証が正しく機能するか確認しましょう。

```bash
# 認証なしで書き込み系エンドポイントにアクセス（401が返る）
curl -s -X POST http://localhost/api/v1/books \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test"}' | jq

# 読み取り系は認証なしでアクセス可能（200が返る）
curl -s http://localhost/api/v1/books | jq
```

---

## 8. まとめ ✨

このChapterでは、Chapter 13で作成した公開APIに認証層を追加しました。

| 学んだこと | 内容 |
|:---|:---|
| **Sanctumの導入** | インストール、`HasApiTokens`トレイト、マイグレーション |
| **ルート分離** | `auth:sanctum`ミドルウェアで書き込み系のみ認証必須に |
| **$request->user()** | 認証済みユーザーをトークンから取得する方法 |
| **認可の追加** | `$this->authorize()`で書籍所有者のみ更新・削除を許可 |
| **FormRequestの簡素化** | 認証により`user_id`フィールドが不要になった |

認証と認可を適切に設定することで、APIのセキュリティが大幅に向上しました。次のChapter 19では、読書計画機能とリマインダー通知を実装します。
