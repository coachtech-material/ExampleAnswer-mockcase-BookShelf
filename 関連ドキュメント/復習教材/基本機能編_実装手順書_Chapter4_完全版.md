# Chapter 4: レビュー機能 - 親子関係のデータを扱う

書籍管理機能ができたので、次は各書籍に紐づく「レビュー機能」を実装します。ここでは、特定の書籍（親）に属するレビュー（子）という、親子関係のデータの扱い方が主なテーマになります。

## 4-1. 要件の確認とBladeからの情報抽出

> **【要件定義書（詳細度50%）より抜粋】**
> 
> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | レビュー管理 | レビュー投稿 | 書籍の詳細ページからレビューを投稿できる |
> | | レビュー編集 | 自分が投稿したレビューを編集できる |
> | | レビュー削除 | 自分が投稿したレビューを削除できる |

### Bladeテンプレートの読み解き (`show.blade.php`)

書籍詳細ページ `resources/views/books/show.blade.php` のレビューセクションを見てみましょう。

```html
<!-- レビュー投稿フォーム -->
<form action="{{ route(\'reviews.store\', $book) }}" method="POST">
    <select name="rating">...</select>
    <textarea name="comment">...</textarea>
</form>

<!-- レビュー一覧 -->
@foreach($book->reviews as $review)
    <p>{{ $review->user->name }}</p>
    <p>{{ $review->comment }}</p>

    <!-- 編集・削除ボタン -->
    @can(\'update\', $review)
        <a href="{{ route(\'reviews.edit\', $review) }}">編集</a>
        <form action="{{ route(\'reviews.destroy\', $review) }}" method="POST">
            ...
        </form>
    @endcan
@endforeach
```

このBladeファイルから、以下の情報が読み取れます。

-   **親子関係のルーティング**: レビュー投稿の`action`が`route(\'reviews.store\', $book)`となっています。これは、**どの書籍に対するレビューなのか**をURLで示す必要があることを意味します。`POST /books/{book}/reviews`のようなURL構造（ネストされたルート）が適切だと考えられます。
-   **フォーム項目**: `name="rating"`と`name="comment"`から、`reviews`テーブルには`rating`と`comment`カラムが必要だとわかります。
-   **認可**: レビューの編集・削除ボタンが`@can(\'update\', $review)`で囲まれていることから、`ReviewPolicy`を作成し、`update`や`delete`のアクションを定義する必要があることがわかります。

## 4-2. モデルとリレーションの定義 (M)

Chapter 3で既に関連モデルの準備はできていますが、再確認しましょう。

-   `Review`モデル (`app/Models/Review.php`)
    -   `user()`: `belongsTo(User::class)`
    -   `book()`: `belongsTo(Book::class)`
-   `Book`モデル (`app/Models/Book.php`)
    -   `reviews()`: `hasMany(Review::class)`
-   `User`モデル (`app/Models/User.php`)
    -   `reviews()`: `hasMany(Review::class)`

これらのリレーションにより、`$review->user`でレビューの投稿者を、$book->reviews`で書籍のレビュー一覧を簡単に取得できます。

## 4-3. ルーティングの定義 (R)

親子関係を表現するために、**ネストされたリソースルート**を定義します。`routes/web.php`を編集します。

```php
// routes/web.php

use App\Http\Controllers\ReviewController;

// ...

Route::middleware("auth")->group(function () {
    // ... (他のルート)

    // レビュー管理のルート
    Route::post("/books/{book}/reviews"), [ReviewController::class, "store"])->name("reviews.store");
    Route::get("/reviews/{review}/edit"), [ReviewController::class, "edit"])->name("reviews.edit");
    Route::put("/reviews/{review}"), [ReviewController::class, "update"])->name("reviews.update");
    Route::delete("/reviews/{review}"), [ReviewController::class, "destroy"])->name("reviews.destroy");
});
```

-   `POST /books/{book}/reviews`: `store`アクションは、どの書籍（`{book}`）に対する投稿なのかをURLで受け取ります。
-   `edit`, `update`, `destroy`アクションは、どのレビュー（`{review}`）を対象とするかをURLで受け取るため、ネストさせる必要はありません。

## 4-4. コントローラとビジネスロジック (C)

レビュー管理用のコントローラを作成します。

```bash
sail artisan make:controller ReviewController
```

### 1. `store`メソッド (投稿処理)

書籍登録と同様に、FormRequestを作成してバリデーションロジックを分離します。

```bash
sail artisan make:request StoreReviewRequest
```

```php
// app/Http/Requests/StoreReviewRequest.php

public function rules(): array
{
    return [
        "rating" => ["required", "integer", "min:1", "max:5"],
        "comment" => ["nullable", "string", "max:1000"],
    ];
}
```

そして、`ReviewController`に`store`メソッドを実装します。

```php
// app/Http/Controllers/ReviewController.php

use App\Models\Book;
use App\Http\Requests\StoreReviewRequest;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        // Bookモデルのリレーションを利用して、ログインユーザーと書籍に紐づくレビューを作成
        $book->reviews()->create([
            "user_id" => Auth::id(),
            "rating" => $request->rating,
            "comment" => $request->comment,
        ]);

        return redirect()->route("books.show", $book)->with("success", "レビューを投稿しました。");
    }

    // ... (他のメソッド)
}
```

-   `$book->reviews()->create([...])`: これが親子関係データを扱う際の最もエレガントな方法です。`Book`モデルの`reviews`リレーション（`hasMany`）を通じて`create`メソッドを呼び出すことで、`book_id`が自動的に設定された`Review`モデルが作成・保存されます。

### 2. `edit` / `update` / `destroy` メソッド (編集・更新・削除)

これらのアクションには、「自分のレビューしか操作できない」という認可が必要です。`ReviewPolicy`を作成します。

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

`app/Policies/ReviewPolicy.php`を編集します。

```php
// app/Policies/ReviewPolicy.php

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

`AuthServiceProvider`に`ReviewPolicy`を登録するのを忘れないようにしましょう。

```php
// app/Providers/AuthServiceProvider.php

protected $policies = [
    Book::class => BookPolicy::class,
    Review::class => ReviewPolicy::class, // この行を追記
];
```

最後に、コントローラの各メソッドを実装します。

```php
// ReviewController.php

// use App\Models\Review; を追記
// use App\Http\Requests\UpdateReviewRequest; を追記

// ...

    public function edit(Review $review)
    {
        $this->authorize("update", $review);
        return view("reviews.edit", compact("review"));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize("update", $review);

        // UpdateReviewRequestはStoreReviewRequestと内容は同じ
        $review->update($request->validated());

        return redirect()->route("books.show", $review->book)->with("success", "レビューを更新しました。");
    }

    public function destroy(Review $review)
    {
        $this->authorize("delete", $review);

        // リダイレクト先のために、削除前に書籍情報を保持しておく
        $book = $review->book;
        $review->delete();

        return redirect()->route("books.show", $book)->with("success", "レビューを削除しました。");
    }
```

-   `$this->authorize("update", $review)`: この一行で、`ReviewPolicy`の`update`メソッドが呼び出され、認可チェックが実行されます。もし`false`が返されると、ユーザーには403 Forbidden（アクセス権がありません）エラーページが自動的に表示されます。

## まとめ

このChapterでは、親子関係にあるレビュー機能の実装を通じて、以下の重要な概念を学びました。

-   **ネストされたルート**: `POST /books/{book}/reviews`のように、親子関係をURLで表現する方法。
-   **リレーション経由でのデータ作成**: `$book->reviews()->create()`のように、リレーションを使って簡単かつ安全に子データを作成する方法。
-   **Policyによる認可**: `@can`ディレクティブと`authorize`メソッドを使って、特定の操作を行えるユーザーを制限する方法。

これで、ユーザーは書籍を登録し、それに対してレビューを投稿・編集・削除できるようになりました。次のChapterでは、お気に入りやいいねといった、さらに複雑なリレーションを持つ機能を実装していきます。
