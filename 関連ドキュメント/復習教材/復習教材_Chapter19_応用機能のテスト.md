# Chapter 19: 応用機能のテスト

---

## 🎯 このセクションで学ぶこと

- **応用機能**（検索、CSVエクスポート）のFeatureテスト
- **コントローラーのメソッドを直接呼び出す**テスト手法
- レスポンスとして返される**Viewオブジェクトの中身を検証する**方法
- モデルの**リレーションメソッドを直接テストする**Unitテスト的なアプローチ
- `assertSeeInOrder`, `assertHeader`, `streamedContent`など、応用的なアサーションメソッド

---

## 🧠 先輩エンジニアの思考プロセス

### なぜ応用機能のテストも必要なのか？

Chapter 13では、基本的なCRUD操作や認証・認可のテストを行いました。しかし、実務で開発するアプリケーションには、検索、外部連携、データエクスポートなど、より複雑な「応用機能」が数多く存在します。これらの機能は、複数の条件分岐や特殊なデータ処理を含むことが多く、手動でのテストには限界があります。

例えば、**検索機能**では、「キーワードとジャンルの両方を指定した場合」「並び替え順を変更した場合」など、組み合わせパターンが爆発的に増加します。**CSVエクスポート機能**では、「検索結果を正しく反映しているか」「大量データでもメモリを使いすぎないか」といった、目に見えにくい部分の品質も保証しなければなりません。

応用機能のテストを書くことで、これらの複雑なロジックが仕様通りに動作することを保証し、将来の変更にも強い、堅牢なアプリケーションを構築することができます。

### どうやってテストの「観点」を見つけるか？

テストを書く際、「何をテストすればいいのか？」と迷うことがあります。基本的な考え方は、「**ユーザーの操作**」と「**システムの振る舞い**」を分解し、それぞれの重要なポイントを検証することです。

| 機能 | ユーザーの操作 | システムの振る舞い（テストの観点） |
|:---|:---|:---|
| **検索機能** | キーワードを入力して検索ボタンを押す | - 検索結果が正しく表示されるか？ (`assertSee`)
- 関係ないデータは表示されていないか？ (`assertDontSee`)
- 並び替え順は正しいか？ (`assertSeeInOrder`) |
| **CSVエクスポート** | エクスポートボタンを押す | - CSVファイルがダウンロードされるか？ (`assertOk`, `assertHeader`)
- ファイル名は正しいか？ (`assertHeader`)
- 検索条件がCSVの内容に反映されているか？ (`streamedContent`) |

### テスト手法をどう使い分けるか？

Chapter 13では、主にHTTPリクエストを送信するFeatureテストを学びました。しかし、テストには様々なアプローチがあります。

- **HTTPリクエスト vs コントローラー直接呼び出し**
  - **HTTPリクエスト**: ユーザー操作全体の流れを検証したい場合に最適です。ルーティングやミドルウェアも含めた総合的なテストになります。
  - **コントローラー直接呼び出し**: ルーティングなどを介さず、コントローラーの特定のメソッドが返す「データ」そのものを検証したい場合に有効です。例えば、「`edit`メソッドが、Viewに正しい書籍データとジャンル一覧を渡しているか」を直接確認できます。

- **HTTPリクエスト vs モデルのメソッド直接呼び出し**
  - **HTTPリクエスト**: 「いいねボタンを押したら、DBにレコードが作られ、元のページにリダイレクトされる」という一連の機能（Feature）をテストします。
  - **モデルのメソッド直接呼び出し**: 「`user->likedReviews()->toggle()`というメソッドが、中間テーブルのレコードを正しく追加・削除するか」という単一の責務（Unit）をテストします。より高速で、テストの関心事を絞り込めます。

> **💡 テスト戦略**
> 完璧なテスト戦略というものはなく、プロジェクトの特性やチームの方針によって様々です。重要なのは、それぞれのテスト手法のメリット・デメリットを理解し、「何を保証したいのか」という目的に合わせて、最適な手法を選択することです。

---

## 19.1. 応用機能のテストコード

このセクションでは、Chapter 14（高度な検索機能）とChapter 15（CSVエクスポート機能）に対するテストを`BookTest`に追加します。また、`ReviewLikeTest`をモデルメソッド直接呼び出しの形式に修正し、テスト手法の違いを学びます。

### BookTest.php の全体像

Chapter 13で作成した`BookTest.php`に、応用機能のテストを追加した完全なコードは以下のようになります。

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\BookController;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\View;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    // ========== Chapter 13: 基本機能のテスト ========== 

    public function test_book_index_page_can_be_rendered(): void
    {
        Book::factory()->count(2)->create();

        $this->get(route("books.index"))
            ->assertOk();
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route("books.create"))
            ->assertOk();
    }

    public function test_guest_cannot_view_create_form(): void
    {
        $this->get(route("books.create"))
            ->assertRedirect(route("login"));
    }

    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            "title" => "My Test Book",
            "isbn" => "1111111111111",
            "genres" => $genres->pluck("id")->toArray(),
        ]);

        $response = $this->actingAs($user)->post(route("books.store"), $payload);

        $book = Book::where("title", "My Test Book")->first();
        $this->assertNotNull($book);

        $response->assertRedirect(route("books.show", $book));

        $this->assertDatabaseHas("books", [
            "id" => $book->id,
            "user_id" => $user->id,
            "title" => "My Test Book",
        ]);

        foreach ($genres as $genre) {
            $this->assertDatabaseHas("book_genre", [
                "book_id" => $book->id,
                "genre_id" => $genre->id,
            ]);
        }
    }

    public function test_book_store_validation_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route("books.store"), [
            "title" => "",
            "author" => "",
            "isbn" => "123",
            "published_date" => "invalid-date",
            "genres" => [],
        ]);

        $response->assertSessionHasErrors(["title", "isbn", "genres"]);
        $this->assertDatabaseCount("books", 0);
    }

    public function test_book_show_page_can_be_rendered(): void
    {
        $book = Book::factory()->create(["title" => "Detail Book"]);

        $this->get(route("books.show", $book))
            ->assertOk()
            ->assertSee("Detail Book");
    }

    public function test_authenticated_user_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $originalGenre = Genre::factory()->create();
        $book->genres()->attach($originalGenre);
        $newGenres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            "title" => "Updated Title",
            "isbn" => "9876543210123",
            "genres" => $newGenres->pluck("id")->toArray(),
        ]);

        $response = $this->actingAs($user)->put(route("books.update", $book), $payload);

        $response->assertRedirect(route("books.show", $book));

        $this->assertDatabaseHas("books", [
            "id" => $book->id,
            "title" => "Updated Title",
            "isbn" => "9876543210123",
        ]);

        foreach ($newGenres as $genre) {
            $this->assertDatabaseHas("book_genre", [
                "book_id" => $book->id,
                "genre_id" => $genre->id,
            ]);
        }

        $this->assertDatabaseMissing("book_genre", [
            "book_id" => $book->id,
            "genre_id" => $originalGenre->id,
        ]);
    }

    public function test_authenticated_user_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route("books.destroy", $book))
            ->assertRedirect(route("books.index"));

        $this->assertDatabaseMissing("books", ["id" => $book->id]);
    }

    // ========== Chapter 19: 応用機能のテスト ========== 

    /** @test */
    public function test_book_index_with_search_query_displays_results(): void
    {
        Book::factory()->create(["title" => "Laravel Testing Guide"]);
        Book::factory()->create(["title" => "Another Book"]);

        $this->get(route("books.index", ["keyword" => "Laravel"]))
            ->assertOk()
            ->assertSee("Laravel Testing Guide")
            ->assertDontSee("Another Book");
    }

    /** @test */
    public function test_book_index_with_genre_filter_displays_results(): void
    {
        $genre = Genre::factory()->create();
        $bookInGenre = Book::factory()->create();
        $bookInGenre->genres()->attach($genre);
        $bookNotInGenre = Book::factory()->create();

        $this->get(route("books.index", ["genre" => $genre->id]))
            ->assertOk()
            ->assertSee($bookInGenre->title)
            ->assertDontSee($bookNotInGenre->title);
    }

    /** @test */
    public function test_book_index_is_ordered_correctly(): void
    {
        $latestBook = Book::factory()->create(["created_at" => now()]);
        $oldestBook = Book::factory()->create(["created_at" => now()->subDay()]);

        // 新着順（デフォルト）
        $this->get(route("books.index", ["sort" => "newest"]))
            ->assertOk()
            ->assertSeeInOrder([$latestBook->title, $oldestBook->title]);

        // 古い順
        $this->get(route("books.index", ["sort" => "oldest"]))
            ->assertOk()
            ->assertSeeInOrder([$oldestBook->title, $latestBook->title]);
    }

    /** @test */
    public function test_authenticated_user_can_export_csv(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route("books.export"));

        $response->assertOk();
        $response->assertHeader("Content-Type", "text/csv; charset=UTF-8");
        $this->assertStringContainsString(
            "attachment; filename=",
            $response->headers->get("Content-Disposition")
        );
        $this->assertStringContainsString(
            ".csv",
            $response->headers->get("Content-Disposition")
        );
    }

    /** @test */
    public function test_csv_export_with_search_filters(): void
    {
        $user = User::factory()->create();
        Book::factory()->create(["title" => "Laravel Book"]);
        Book::factory()->create(["title" => "PHP Book"]);

        $response = $this->actingAs($user)->get(route("books.export", ["keyword" => "Laravel"]));

        $response->assertOk();
        $this->assertStringContainsString("Laravel Book", $response->streamedContent());
        $this->assertStringNotContainsString("PHP Book", $response->streamedContent());
    }

    /** @test */
    public function test_guest_cannot_export_csv(): void
    {
        $this->get(route("books.export"))
            ->assertRedirect(route("login"));
    }

    /** @test */
    public function test_only_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()
            ->for($owner)
            ->create(["published_date" => "2024-01-01"]);
        $otherUser = User::factory()->create();

        $this->assertTrue($owner->can("update", $book));

        $this->actingAs($otherUser)
            ->get(route("books.edit", $book))
            ->assertForbidden();
    }

    /** @test */
    public function test_owner_receives_edit_view_with_genres(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create(["published_date" => "2024-01-01"]);
        $genres = Genre::factory()->count(2)->create();

        $this->actingAs($owner);

        $response = app(BookController::class)->edit($book);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame("books.edit", $response->name());

        $data = $response->getData();
        $this->assertSame($book->id, $data["book"]->id);
        $this->assertCount($genres->count(), $data["genres"]);
    }

    private function validBookData(array $overrides = []): array
    {
        $genres = $overrides["genres"] ?? Genre::factory()->count(2)->create()->pluck("id")->toArray();

        return array_merge([
            "title" => "Sample Book",
            "author" => "Sample Author",
            "isbn" => "1234567890123",
            "published_date" => "2024-01-01",
            "description" => "Sample description",
            "image_url" => "https://example.com/image.jpg",
            "genres" => $genres,
        ], $overrides);
    }
}
```

### ReviewLikeTest.php の修正

`tests/Feature/ReviewLikeTest.php`のテストを、HTTPリクエストベースからモデルメソッド直接呼び出しの形式に全面的に書き換えます。

```php
<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_user_can_like_review_via_model_method(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        // モデルのメソッドを直接呼び出し
        $user->likedReviews()->syncWithoutDetaching([$review->id]);

        $this->assertDatabaseHas("review_likes", [
            "user_id" => $user->id,
            "review_id" => $review->id,
        ]);

        $this->assertTrue(
            $user->likedReviews()->whereKey($review->id)->exists()
        );
    }

    /** @test */
    public function test_user_can_unlike_review_via_model_method(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);

        // モデルのメソッドを直接呼び出し
        $user->likedReviews()->detach($review->id);

        $this->assertDatabaseMissing("review_likes", [
            "user_id" => $user->id,
            "review_id" => $review->id,
        ]);
    }

    /** @test */
    public function test_like_toggle_works_correctly_via_model_method(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        // 1回目のtoggle: いいね追加
        $user->likedReviews()->toggle($review->id);
        $this->assertDatabaseHas("review_likes", [
            "user_id" => $user->id,
            "review_id" => $review->id,
        ]);

        // 2回目のtoggle: いいね解除
        $user->likedReviews()->toggle($review->id);
        $this->assertDatabaseMissing("review_likes", [
            "user_id" => $user->id,
            "review_id" => $review->id,
        ]);
    }

    /** @test */
    public function test_guest_cannot_like_review_via_http_request(): void
    {
        $review = Review::factory()->create();

        // 認証部分はHTTPリクエストでテスト
        $this->post(route("reviews.like", $review))
            ->assertRedirect(route("login"));
    }
}
```

---

## 19.2. コードの詳細解説

### 検索機能のテスト (`BookTest`)

```php
// test_book_index_with_search_query_displays_results
$this->get(route("books.index", ["keyword" => "Laravel"]))
    ->assertOk()
    ->assertSee("Laravel Testing Guide")
    ->assertDontSee("Another Book");
```
1.  **`route("books.index", ["keyword" => "Laravel"])`**: `books.index`ルートに、クエリパラメータ`?keyword=Laravel`を付与してGETリクエストを送信します。
2.  **`assertSee()`**: レスポンスのHTML内に、検索キーワードにマッチする書籍のタイトルが含まれていることを確認します。
3.  **`assertDontSee()`**: マッチしない書籍のタイトルが含まれていないことを確認します。これにより、検索が正しく機能していることをより確実に検証できます。

```php
// test_book_index_is_ordered_correctly
$this->get(route("books.index", ["sort" => "newest"]))
    ->assertOk()
    ->assertSeeInOrder([$latestBook->title, $oldestBook->title]);
```
1.  **`assertSeeInOrder([...])`**: レスポンスのHTML内に、配列で指定した文字列が、**この順番通りに**出現することを確認します。ランキングや並び替え機能のテストに非常に強力なアサーションです。

### CSVエクスポート機能のテスト (`BookTest`)

```php
// test_authenticated_user_can_export_csv
$response->assertHeader("Content-Type", "text/csv; charset=UTF-8");
$this->assertStringContainsString(
    "attachment; filename=",
    $response->headers->get("Content-Disposition")
);
```
1.  **`$response->headers->get("Content-Disposition")`**: `StreamedResponse`オブジェクトから直接ヘッダーの値を取得します。`assertHeaderContains`が使えないため、この方法で値を取得します。
2.  **`$this->assertStringContainsString($needle, $haystack)`**: 取得したヘッダーの値（`$haystack`）に、期待する文字列（`$needle`）が含まれているかを検証します。ファイル名にタイムスタンプが含まれるため、部分一致で検証しています。

```php
// test_csv_export_with_search_filters
$this->assertStringContainsString("Laravel Book", $response->streamedContent());
```
1.  **`$response->streamedContent()`**: `StreamedResponse`の場合、レスポンスのコンテンツ（CSVの中身）を文字列として取得します。
2.  **`assertStringContainsString()`**: 取得したCSVコンテンツの文字列内に、期待する書籍のタイトルが含まれていることを確認します。

### 応用的なテスト手法

```php
// test_owner_receives_edit_view_with_genres
$response = app(BookController::class)->edit($book);
```
1.  **`app(BookController::class)`**: サービスコンテナから`BookController`のインスタンスを取得します。これにより、ルーティングやミドルウェアを介さずに、コントローラーのメソッドを直接呼び出す準備ができます。
2.  **`->edit($book)`**: 取得したコントローラーインスタンスの`edit`メソッドを、引数を渡して直接実行します。戻り値（この場合は`View`オブジェクト）が`$response`に格納されます。

```php
$this->assertInstanceOf(View::class, $response);
$this->assertSame("books.edit", $response->name());
$data = $response->getData();
$this->assertTrue($data["book"]->is($book));
```
1.  **`assertInstanceOf(View::class, $response)`**: 戻り値が`View`クラスのインスタンスであることを確認します。
2.  **`$response->name()`**: `View`オブジェクトがどのBladeファイル（`books.edit`）を指しているかを取得します。
3.  **`$response->getData()`**: `View`オブジェクトに渡された全てのデータを連想配列として取得します。
4.  **`$data["book"]`**: `compact("book", "genres")`で渡された書籍データにアクセスし、`is()`メソッドで期待通りのデータか検証します。

```php
// test_user_can_like_review_via_model_method
$user->likedReviews()->syncWithoutDetaching([$review->id]);
```
1.  **`$user->likedReviews()`**: `User`モデルに定義された`likedReviews`リレーション（`BelongsToMany`）のクエリビルダを取得します。
2.  **`->syncWithoutDetaching([...])`**: HTTPリクエストを送信する代わりに、リレーションのメソッドを直接呼び出して、中間テーブルにレコードを追加します。これにより、テストの関心事を「リレーションのロジックが正しいか」という点に絞り込むことができます。

---

## 19.3. How to: この実装にたどり着くための調べ方

応用的なテスト手法は、公式ドキュメントのどこを読めば良いか分かりにくいことがあります。以下のような検索経路を辿ることで、必要な情報にたどり着くことができます。

### Step 1: 「並び順をテストしたい」

- **最初の検索**: `Laravel test order` / `Laravel テスト 並び順`
- **検索結果から得られる情報**: `assertSeeInOrder`というメソッドの存在を知る。
- **次の疑問と検索**: `assertSeeInOrder`の具体的な使い方が知りたい → `Laravel assertSeeInOrder example`
- **最終的にたどり着く答え**: `assertSeeInOrder`の公式ドキュメントや解説記事にたどり着き、配列で文字列を渡せば良いことを理解する。

### Step 2: 「CSVダウンロードをテストしたい」

- **最初の検索**: `Laravel test csv download` / `Laravel テスト ファイルダウンロード`
- **検索結果から得られる情報**: レスポンスヘッダーを検証する必要があることが分かる。`assertHeader`というメソッドの存在を知る。
- **次の疑問と検索**: CSVの中身はどうやってテストする？ → `Laravel test streamedresponse content`
- **最終的にたどり着く答え**: `streamedContent()`メソッドでコンテンツを文字列として取得し、`assertStringContainsString`などで検証する方法を学ぶ。

### Step 3: 「Viewに渡したデータをテストしたい」

- **最初の検索**: `Laravel test view data` / `Laravel テスト ビュー データ`
- **検索結果から得られる情報**: `assertViewHas`や`assertViewIs`といったメソッドの存在を知る。しかし、これらはHTTPリクエストのレスポンス全体を検証するもの。
- **次の疑問と検索**: コントローラーのメソッドだけをテストして、Viewのデータを直接見たい → `Laravel test controller method directly` / `Laravel コントローラー メソッド 単体テスト`
- **最終的にたどり着く答え**: `app()`ヘルパでコントローラーをインスタンス化し、メソッドを直接呼び出す手法を知る。返ってきた`View`オブジェクトの`getData()`メソッドでデータを取得できることを学ぶ。

### Step 4: 「リレーションのテストをもっとシンプルに書きたい」

- **最初の検索**: `Laravel test many to many relationship` / `Laravel テスト 多対多`
- **検索結果から得られる情報**: `attach()`や`sync()`を使ったテスト方法が多く見つかる。HTTPリクエストを使わない方法はないか？
- **次の疑問と検索**: `Laravel test model method directly` / `Laravel モデル メソッド テスト`
- **最終的にたどり着く答え**: FeatureテストではなくUnitテストとして、モデルのインスタンスを生成し、リレーションメソッド（`syncWithoutDetaching`, `toggle`など）を直接呼び出してDBの状態を検証する、というアプローチがあることを理解する。

---

## 19.4. まとめ

このChapterでは、応用機能に対するテスト手法を学びました。

- **検索機能のテスト**: `assertSeeInOrder`や`assertDontSee`を使い、複雑な条件や並び順を検証しました。
- **CSVエクスポートのテスト**: `assertHeader`でヘッダーを、`streamedContent()`でレスポンスの中身を検証しました。
- **コントローラーの直接テスト**: `app()`ヘルパを使い、Viewに渡されるデータを直接検証する手法を学びました。
- **モデルメソッドの直接テスト**: HTTPリクエストを介さず、リレーションのロジックを直接検証するUnitテスト的なアプローチを学びました。

テストには様々なアプローチがあり、絶対的な正解はありません。しかし、これらの多様な手法を知っていることで、テストしたい内容に応じて最適な方法を選択できるようになります。これにより、アプリケーションの品質と開発効率をさらに高めることができるでしょう。
