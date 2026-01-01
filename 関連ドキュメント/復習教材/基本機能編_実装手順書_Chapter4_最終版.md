# Chapter 4: レビュー機能 - 親子関係と認可をマスターする

書籍を登録できるようになったので、次はその書籍に対する「レビュー」を投稿する機能を実装します。ここでのポイントは「親子関係」のデータ構造と、自分以外のユーザーのレビューは編集・削除できないようにする「認可（Policy）」です。

## 4-1. PMへのヒアリングと設計

詳細度50%の要件定義書には「レビュー機能」としかありません。PMにヒアリングし、以下の仕様を固めます。

- **Create**: レビューはどの書籍に対して投稿されるのか？評価（星の数）とコメントが必要。一度投稿したユーザーは同じ書籍に再度投稿できないようにすべきか？
- **Update/Delete**: 誰がレビューを編集・削除できるのか？（投稿者本人のみ）

## 4-2. M-C-R-Vモデルによる実装

### Step 1: M (Model & Migration) - 親子関係の設計

添付手順書の「Step 2」と「Step 3」に従い、`reviews` テーブルのマイグレーションと `Review` モデルを確認・編集します。

1.  **マイグレーション**: `create_reviews_table` を見ると、`user_id` と `book_id` という2つの重要なカラムがあります。これらはそれぞれ `users` テーブルと `books` テーブルへの「外部キー」です。これにより、「どのユーザーが」「どの本に」対してレビューしたのか、という関係性をデータベースレベルで保証します。

    ```php
    // database/migrations/..._create_reviews_table.php
    $table->foreignId("user_id")->constrained()->onDelete("cascade");
    $table->foreignId("book_id")->constrained()->onDelete("cascade");
    ```

    **思考プロセス**: `onDelete("cascade")` とは？
    これは「親が削除されたら、子も一緒に削除する」という設定です。例えば、あるユーザーが退会して `users` テーブルから削除されたら、そのユーザーが書いたレビューも自動的に `reviews` テーブルから削除されます。これにより、親のいないデータ（孤児データ）が残るのを防ぎます。

2.  **モデルのリレーション**: `Review` モデルと `Book` モデル、`User` モデルにリレーションを定義します。

    ```php
    // app/Models/Book.php
    public function reviews()
    {
        // Bookは多くのReviewを持つ (hasMany)
        return $this->hasMany(Review::class);
    }

    // app/Models/Review.php
    public function book()
    {
        // Reviewは一つのBookに属する (belongsTo)
        return $this->belongsTo(Book::class);
    }

    public function user()
    {
        // Reviewは一人のUserに属する (belongsTo)
        return $this->belongsTo(User::class);
    }
    ```

### Step 2: C (Controller) & R (Route) - ネストされたリソース

レビューは必ず特定の書籍に紐づくため、URLもその関係性を表現するのが一般的です。これを「ネストされたリソース」と呼びます。

1.  **ルートの定義 (`routes/web.php`)**: 添付手順書の「Step 13」を見ると、レビュー関連のルートが以下のように定義されています。

    ```php
    // routes/web.php
    // ...
    Route::post("/books/{book}/reviews", [ReviewController::class, "store"])->name("reviews.store");
    Route::get("/reviews/{review}/edit", [ReviewController::class, "edit"])->name("reviews.edit");
    // ...
    ```

    **思考プロセス**: なぜ `/books/{book}/reviews` というURLなのか？
    これは「IDが `{book}` の書籍に対するレビューを `store` (保存) する」という意味になり、URL自体が親子関係を表現していて非常に分かりやすくなります。

2.  **コントローラの実装 (`ReviewController.php`)**: 添付手順書の「Step 6.3」に従い、コントローラを実装します。

    ```php
    // app/Http/Controllers/ReviewController.php
    public function store(StoreReviewRequest $request, Book $book)
    {
        // リレーション経由でレビューを作成・保存
        $book->reviews()->create([
            "user_id" => Auth::id(), // ログイン中のユーザーID
            "rating" => $request->rating,
            "comment" => $request->comment,
        ]);

        return redirect()->route("books.show", $book)->with("success", "レビューを投稿しました。");
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        // このレビューを更新する権限があるかチェック
        $this->authorize("update", $review);
        $review->update($request->validated());
        // ...
    }
    ```

    **思考プロセス**: `Auth::id()` と `$book->reviews()->create()` がポイントです。
    - `Auth::id()` で現在ログインしているユーザーのIDを取得し、なりすましを防ぎます。
    - `$book->reviews()->create(...)` は、`Book` モデルと `Review` モデルのリレーションを利用した書き方です。これにより、`book_id` を手動で指定しなくても、自動的に今いる書籍（`$book`）のIDがセットされた状態でレビューが作成され、コードが簡潔になります。

3.  **認可（Policy）の実装**: 自分以外のレビューを編集・削除できないようにします。添付手順書の「Step 6.1」と「Step 6.2」に従い、`ReviewPolicy` を作成・登録します。

    ```php
    // app/Policies/ReviewPolicy.php
    public function update(User $user, Review $review): bool
    {
        // ログイン中のユーザーIDと、レビューのユーザーIDが一致するかチェック
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
    ```

    コントローラ側で `$this->authorize("update", $review);` を呼び出すだけで、このポリシーが自動で実行されます。権限がなければ、403 Forbiddenエラーページが自動で表示されます。

### Step 3: V (View) - 画面への組み込み

書籍詳細ページ (`books/show.blade.php`) にレビュー投稿フォームとレビュー一覧を表示します。PMから提供されたBladeテンプレートを参考に実装します。

**Bladeの読み解き方**: `books/show.blade.php`
- `@auth ... @endauth`: ログインしているユーザーにのみ表示されるブロックです。レビュー投稿フォームはログインユーザーにしか見せません。
- `@can("update", $review) ... @endcan`: `ReviewPolicy` の `update` メソッドを呼び出し、結果が `true` の場合のみ、そのブロック（編集・削除ボタン）を表示します。これにより、自分以外のレビューの編集・削除ボタンは最初から表示されなくなります。
- `@foreach ($book->reviews as $review) ... @endforeach`: コントローラから渡された `$book` オブジェクトのリレーション (`reviews()`) を使って、その書籍に紐づくレビューをループで表示しています。

これで、親子関係を持つレビュー機能が安全に実装できました。次のChapterでは、お気に入りやいいねといった「多対多」の関係を実装する方法を学びます。
