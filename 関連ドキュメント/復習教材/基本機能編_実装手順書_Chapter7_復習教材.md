# Chapter 7: レビュー機能

## 🎯 このセクションで学ぶこと

このセクションでは、書籍に対するレビュー（評価とコメント）を投稿・編集・削除する機能を実装します。

- **ネストしたリソース**: 書籍（`Book`）に紐づくレビュー（`Review`）という、親子関係のあるリソースの扱い方を学びます。
- **リレーションを活用したデータ作成**: `$book->reviews()->create([...])`のように、リレーションを通じてデータを作成する方法を学びます。
- **ポリシーによる認可**: 自分が投稿したレビューのみ編集・削除できるように制御します。

---

## 🧠 先輩エンジニアの思考プロセス：レビュー機能の設計

レビュー機能を設計する際、以下の点を考慮します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| レビューは書籍に紐づく | URLを`/books/{book}/reviews`のようにネストする | 「どの書籍に対するレビューか」が明確になる。 |
| レビューは投稿者のみ編集・削除可能 | `ReviewPolicy`で認可を制御する | 他人のレビューを勝手に編集・削除されないようにする。 |
| レビュー投稿後は書籍詳細ページに戻る | `redirect()->route(\'books.show\', $book)` | ユーザーが自分の投稿を確認しやすい。 |

---

## 6.1. コントローラーとリクエストの作成

```bash
sail artisan make:controller ReviewController
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
sail artisan make:policy ReviewPolicy --model=Review
```

---

## 6.2. ポリシーの登録

`app/Providers/AuthServiceProvider.php`に`ReviewPolicy`を追加します。

```php
// app/Providers/AuthServiceProvider.php

<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Review;
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

---

## 6.3. ReviewPolicyの実装

```php
// app/Policies/ReviewPolicy.php

<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

---

## 6.4. ReviewControllerの実装
```php
// app/Http/Controllers/ReviewController.php

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        $book->reviews()->create([
            \'user_id\' => Auth::id(),
            \'rating\' => $request->rating,
            \'comment\' => $request->comment,
        ]);

        return redirect()->route(\'books.show\', $book)->with(\'success\', \'レビューを投稿しました。\');
    }

    public function edit(Review $review)
    {
        $this->authorize(\'update\', $review);
        return view(\'reviews.edit\', compact(\'review\'));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize(\'update\', $review);
        $review->update([
            \'rating\' => $request->rating,
            \'comment\' => $request->comment,
        ]);

        return redirect()->route(\'books.show\', $review->book)->with(\'success\', \'レビューを更新しました。\');
    }

    public function destroy(Review $review)
    {
        $this->authorize(\'delete\', $review);
        $book = $review->book;
        $review->delete();

        return redirect()->route(\'books.show\', $book)->with(\'success\', \'レビューを削除しました。\');
    }
}
```

### 6.4.1. コードリーディング：`store`メソッド

```php
public function store(StoreReviewRequest $request, Book $book)
{
    $book->reviews()->create([
        \'user_id\' => Auth::id(),
        \'rating\' => $request->rating,
        \'comment\' => $request->comment,
    ]);

    return redirect()->route(\'books.show\', $book)->with(\'success\', \'レビューを投稿しました。\');
}
```

| 部分 | 説明 | 戻り値 |
|:---|:---|:---|
| `$book->reviews()` | `Book`モデルの`reviews`リレーションを取得します。 | `HasMany` |
| `->create([...])` | リレーションを通じて新しい`Review`を作成します。 | `Review` |
| `Auth::id()` | 現在ログインしているユーザーのIDを取得します。 | `int` |
| `redirect()->route(\'books.show\', $book)` | 書籍詳細ページにリダイレクトします。 | `RedirectResponse` |
| `->with(\'success\', \'...\')` | セッションにフラッシュメッセージを保存します。 | `RedirectResponse` |

> **💡 ポイント: リレーションを通じた`create`**
> `$book->reviews()->create([...])`を使うと、`book_id`が自動的に設定されます。手動で`\'book_id\' => $book->id`を指定する必要はありません。

### 6.4.2. コードリーディング：`destroy`メソッド
```php
public function destroy(Review $review)
{
    $this->authorize(\'delete\', $review);
    $book = $review->book;
    $review->delete();

    return redirect()->route(\'books.show\', $book)->with(\'success\', \'レビューを削除しました。\');
}
```

| 部分 | 説明 | 戻り値 |
|:---|:---|:---|
| `$this->authorize(\'delete\', $review)` | `ReviewPolicy`の`delete`メソッドで認可を確認します。 | `void`（認可されない場合は例外がスローされます） |
| `$book = $review->book` | 削除前に、リダイレクト先の書籍を取得しておきます。 | `Book` |
| `$review->delete()` | レビューを削除します。 | `bool` |

> **❌ よくある間違い**
> ```php
> $review->delete();
> return redirect()->route(\'books.show\', $review->book); // ❌ 削除後は$review->bookにアクセスできない
> ```
> 
> **✅ 正解**
> ```php
> $book = $review->book; // 削除前に取得
> $review->delete();
> return redirect()->route(\'books.show\', $book); // ✅ 事前に取得した$bookを使用
> ```

---

## 6.5. フォームリクエストの実装

### 6.5.1. StoreReviewRequest

```php
// app/Http/Requests/StoreReviewRequest.php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            \'rating\' => [\'required\', \'integer\', \'min:1\', \'max:5\'],
            \'comment\' => [\'nullable\', \'string\', \'max:1000\'],
        ];
    }
}
```

### 6.5.2. UpdateReviewRequest

```php
// app/Http/Requests/UpdateReviewRequest.php

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            \'rating\' => [\'required\', \'integer\', \'min:1\', \'max:5\'],
            \'comment\' => [\'nullable\', \'string\', \'max:1000\'],
        ];
    }
}
```

| ルール | 説明 | 💡 ポイント |
|:---|:---|:---|
| `\'integer\'` | 整数である必要があります。 | - |
| `\'min:1\', \'max:5\'` | 1〜5の範囲内である必要があります。 | 5段階評価を想定しています。 |
| `\'nullable\'` | 空の値を許可します。 | コメントは任意入力です。 |

---

## 6.6. ルートの追加

```php
// routes/web.php

use App\Http\Controllers\ReviewController;

// ... (他のルート)

Route::middleware(\'auth\')->group(function () {
    // ... 既存のルート

    // Review management
    Route::post(\'/books/{book}/reviews\', [ReviewController::class, \'store\'])->name(\'reviews.store\');
    Route::get(\'/reviews/{review}/edit\', [ReviewController::class, \'edit\'])->name(\'reviews.edit\');
    Route::put(\'/reviews/{review}\', [ReviewController::class, \'update\'])->name(\'reviews.update\');
    Route::delete(\'/reviews/{review}\', [ReviewController::class, \'destroy\'])->name(\'reviews.destroy\');
});
```

| ルート | 説明 | 💡 ポイント |
|:---|:---|:---|
| `POST /books/{book}/reviews` | 特定の書籍にレビューを投稿します。 | URLに書籍IDが含まれるため、どの書籍へのレビューか明確です。 |
| `GET /reviews/{review}/edit` | レビューの編集画面を表示します。 | 編集・更新・削除はレビューIDのみで操作します。 |

---

## 6.7. ビューの作成

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/reviews
touch resources/views/reviews/edit.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

これで、レビュー機能の実装が完了しました。次のChapterでは、お気に入り機能を実装していきます。
