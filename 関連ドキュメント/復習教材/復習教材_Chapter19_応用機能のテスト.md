# Chapter 19: 応用機能のテスト

---

## 🎯 このセクションで学ぶこと

- **応用機能**（検索、CSVエクスポート、ISBN検索、読書レポート）のFeatureテスト
- **コントローラーのメソッドを直接呼び出す**テスト手法
- レスポンスとして返される**Viewオブジェクトの中身を検証する**方法
- `Http::fake()`を使った**外部API連携のテスト**
- `assertSeeInOrder`, `assertHeader`, `streamedContent`など、応用的なアサーションメソッド

---

## 🧠 先輩エンジニアの思考プロセス

### なぜ応用機能のテストも必要なのか？

Chapter 13では、基本的なCRUD操作や認証・認可のテストを行いました。しかし、実務で開発するアプリケーションには、検索、外部連携、データエクスポートなど、より複雑な「応用機能」が数多く存在します。これらの機能は、複数の条件分岐や特殊なデータ処理を含むことが多く、手動でのテストには限界があります。

例えば、**検索機能**では、「キーワードとジャンルの両方を指定した場合」「並び替え順を変更した場合」など、組み合わせパターンが爆発的に増加します。**外部API連携**では、「APIが正常にレスポンスを返さなかった場合」「APIキーが正しいか」など、正常系以外のケースも考慮しなければなりません。

応用機能のテストを書くことで、これらの複雑なロジックが仕様通りに動作することを保証し、将来の変更にも強い、堅牢なアプリケーションを構築することができます。

### どうやってテストの「観点」を見つけるか？

テストを書く際、「何をテストすればいいのか？」と迷うことがあります。基本的な考え方は、「**ユーザーの操作**」と「**システムの振る舞い**」を分解し、それぞれの重要なポイントを検証することです。

| 機能 | ユーザーの操作 / システムのイベント | システムの振る舞い（テストの観点） |
|:---|:---|:---|
| **検索機能** | キーワードを入力して検索ボタンを押す | - 検索結果が正しく表示されるか？ (`assertSee`)
- 関係ないデータは表示されていないか？ (`assertDontSee`)
- 並び替え順は正しいか？ (`assertSeeInOrder`) |
| **CSVエクスポート** | エクスポートボタンを押す | - CSVファイルがダウンロードされるか？ (`assertOk`, `assertHeader`)
- ファイル名は正しいか？ (`assertHeader`)
- 検索条件がCSVの内容に反映されているか？ (`streamedContent`) |
| **ISBN検索** | ISBNを入力して検索ボタンを押す | - 正常に書籍情報が返ってくるか？ (`assertOk`, `assertJson`)
- APIキーは正しく送信されているか？ (`Http::fake`)
- バリデーションは機能しているか？ (`assertStatus(400)`)
- APIエラー時に適切なレスポンスを返すか？ (`assertStatus(500)`) |
| **読書レポート** | レポートページにアクセスする | - 各種統計情報が正しく計算されているか？ (`assertViewHas`, `assertSame`)
- レビューがないユーザーの場合、ゼロ件として表示されるか？ (`assertTrue($stats[...]->isEmpty())`) |

### テスト手法をどう使い分けるか？

Chapter 13では、主にHTTPリクエストを送信するFeatureテストを学びました。しかし、テストには様々なアプローチがあります。

- **HTTPリクエスト vs コントローラー直接呼び出し**
  - **HTTPリクエスト**: ユーザー操作全体の流れを検証したい場合に最適です。ルーティングやミドルウェアも含めた総合的なテストになります。
  - **コントローラー直接呼び出し**: ルーティングなどを介さず、コントローラーの特定のメソッドが返す「データ」そのものを検証したい場合に有効です。例えば、「`edit`メソッドが、Viewに正しい書籍データとジャンル一覧を渡しているか」を直接確認できます。

- **外部API連携のテスト**
  - **`Http::fake()`**: 実際の外部APIにリクエストを送信する代わりに、偽のレスポンスを返すように設定します。これにより、APIの障害やネットワークの問題に影響されず、安定して高速にテストを実行できます。また、「APIがエラーを返した場合」など、意図的に異常系の状況を作り出すことも容易になります。

> **💡 テスト戦略**
> 完璧なテスト戦略というものはなく、プロジェクトの特性やチームの方針によって様々です。重要なのは、それぞれのテスト手法のメリット・デメリットを理解し、「何を保証したいのか」という目的に合わせて、最適な手法を選択することです。

---

## 19.1. 応用機能のテストコード

このセクションでは、応用機能（高度な検索、CSVエクスポート、ISBN検索、読書レポート）に対するテストコードを実装します。

### BookTest.php の全体像

Chapter 13で作成した`BookTest.php`に、応用機能のテストを追加した完全なコードは以下のようになります。

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\BookController;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
    public function test_book_index_can_sort_by_title(): void
    {
        $bookZ = Book::factory()->create(["title" => "Zeta Title"]);
        $bookA = Book::factory()->create(["title" => "Alpha Title"]);

        $response = $this->get(route("books.index", ["sort" => "title"]));

        $response->assertOk();

        $titles = collect($response->viewData("books")->items())->pluck("title")->all();

        $this->assertSame(
            [$bookA->title, $bookZ->title],
            $titles
        );
    }

    /** @test */
    public function test_book_index_can_sort_by_average_rating(): void
    {
        $highRatedBook = Book::factory()->create(["title" => "High Rated"]);
        Review::factory()->for($highRatedBook)->create(["rating" => 5]);
        Review::factory()->for($highRatedBook)->create(["rating" => 4]);

        $lowRatedBook = Book::factory()->create(["title" => "Low Rated"]);
        Review::factory()->for($lowRatedBook)->create(["rating" => 2]);

        $response = $this->get(route("books.index", ["sort" => "rating"]));

        $response->assertOk();

        $titles = collect($response->viewData("books")->items())->pluck("title")->all();

        $this->assertSame(
            [$highRatedBook->title, $lowRatedBook->title],
            $titles
        );
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
    public function test_export_csv_can_sort_by_oldest(): void
    {
        $user = User::factory()->create();
        $oldestBook = Book::factory()->create([
            "title" => "Oldest Book",
            "created_at" => now()->subDays(5),
        ]);
        $newestBook = Book::factory()->create([
            "title" => "Newest Book",
            "created_at" => now(),
        ]);

        $response = $this->actingAs($user)->get(route("books.export", ["sort" => "oldest"]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertTrue(strpos($content, $oldestBook->title) < strpos($content, $newestBook->title));
    }

    /** @test */
    public function test_export_csv_can_sort_by_title(): void
    {
        $user = User::factory()->create();
        $bookZ = Book::factory()->create(["title" => "Zeta Book"]);
        $bookA = Book::factory()->create(["title" => "Alpha Book"]);

        $response = $this->actingAs($user)->get(route("books.export", ["sort" => "title"]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertTrue(strpos($content, $bookA->title) < strpos($content, $bookZ->title));
    }

    /** @test */
    public function test_export_csv_can_sort_by_average_rating(): void
    {
        $user = User::factory()->create();
        $topRatedBook = Book::factory()->create(["title" => "Top Rated Book"]);
        Review::factory()->for($topRatedBook)->create(["rating" => 5]);
        Review::factory()->for($topRatedBook)->create(["rating" => 4]);

        $lowRatedBook = Book::factory()->create(["title" => "Low Rated Book"]);
        Review::factory()->for($lowRatedBook)->create(["rating" => 2]);

        $response = $this->actingAs($user)->get(route("books.export", ["sort" => "rating"]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertTrue(strpos($content, $topRatedBook->title) < strpos($content, $lowRatedBook->title));
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

    /** @test */
    public function test_search_by_isbn_returns_book_information(): void
    {
        $isbn = "9781234567890";
        config(["services.google.books_api_key" => "fake-key"]);
        Http::fake(function ($request) use ($isbn) {
            $this->assertStringContainsString("isbn:{$isbn}", $request->url());
            $this->assertStringContainsString("fake-key", $request->url());

            return Http::response([
                "items" => [
                    [
                        "volumeInfo" => [
                            "title" => "API Title",
                            "authors" => ["John Doe"],
                            "publishedDate" => "2020-01-01",
                            "description" => "Fetched description",
                            "imageLinks" => [
                                "thumbnail" => "https://example.com/image.jpg",
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        $this->get(route("books.searchByIsbn", ["isbn" => $isbn]))
            ->assertOk()
            ->assertJson([
                "title" => "API Title",
                "author" => "John Doe",
                "published_date" => "2020-01-01",
                "description" => "Fetched description",
                "image_url" => "https://example.com/image.jpg",
            ]);
    }

    /** @test */
    public function test_search_by_isbn_requires_13_digit_value(): void
    {
        $this->get(route("books.searchByIsbn", ["isbn" => "123"]))
            ->assertStatus(400)
            ->assertJson(["error" => "ISBNは13桁で入力してください。"]);
    }

    /** @test */
    public function test_search_by_isbn_returns_404_when_results_empty(): void
    {
        $isbn = "9781234567890";

        Http::fake(function () {
            return Http::response(["items" => []], 200);
        });

        $this->get(route("books.searchByIsbn", ["isbn" => $isbn]))
            ->assertStatus(404)
            ->assertJson(["error" => "書籍が見つかりませんでした。"]);
    }

    /** @test */
    public function test_search_by_isbn_handles_http_exception(): void
    {
        $isbn = "9781234567890";

        Http::fake(function (): void {
            throw new \Exception("API failure");
        });

        $this->get(route("books.searchByIsbn", ["isbn" => $isbn]))
            ->assertStatus(500)
            ->assertJson(["error" => "API通信エラーが発生しました。"]);
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

### ReportTest.php（新規作成）

読書レポート機能のテストを`tests/Feature/ReportTest.php`として新規に作成します。

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_reports_index(): void
    {
        $this->get(route("reports.index"))
            ->assertRedirect(route("login"));
    }

    public function test_reports_index_displays_stats_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $bookOne = Book::factory()->create(["title" => "First Book", "author" => "Author A"]);
        $bookTwo = Book::factory()->create(["title" => "Second Book", "author" => "Author B"]);
        $bookThree = Book::factory()->create(["title" => "Third Book", "author" => "Author C"]);

        $genreA = Genre::factory()->create(["name" => "Fantasy"]);
        $genreB = Genre::factory()->create(["name" => "Sci-Fi"]);

        $bookOne->genres()->attach($genreA);
        $bookTwo->genres()->attach([$genreA->id, $genreB->id]);
        $bookThree->genres()->attach($genreB);

        Review::factory()->for($user)->for($bookOne)->create(["rating" => 5]);
        Review::factory()->for($user)->for($bookTwo)->create(["rating" => 4]);
        Review::factory()->for($user)->for($bookThree)->create(["rating" => 3]);

        // 他のユーザーのレビュー（集計対象外）
        Review::factory()->create(["rating" => 1]);

        $response = $this->actingAs($user)->get(route("reports.index"));

        $response->assertOk()
            ->assertViewIs("reports.index")
            ->assertViewHas("stats");

        $stats = $response->viewData("stats");

        // 基本統計の検証
        $this->assertSame(3, $stats["summary"]["total_reviews"]);
        $this->assertSame(3, $stats["summary"]["books_read"]);
        $this->assertSame(4.0, $stats["summary"]["average_rating"]);

        // 評価の分布の検証
        $this->assertSame([0, 0, 1, 1, 1], $stats["rating_distribution"]);

        // 高評価書籍ランキングの検証
        $this->assertSame(
            [
                [
                    "id" => $bookOne->id,
                    "title" => $bookOne->title,
                    "author" => $bookOne->author,
                    "rating" => 5,
                ],
                [
                    "id" => $bookTwo->id,
                    "title" => $bookTwo->title,
                    "author" => $bookTwo->author,
                    "rating" => 4,
                ],
            ],
            $stats["top_rated_books"]->toArray()
        );

        // ジャンル別評価傾向の検証
        $this->assertSame(
            [
                [
                    "id" => $genreA->id,
                    "name" => $genreA->name,
                    "count" => 2,
                    "average_rating" => 4.5,
                ],
                [
                    "id" => $genreB->id,
                    "name" => $genreB->name,
                    "count" => 2,
                    "average_rating" => 3.5,
                ],
            ],
            $stats["genre_ratings"]->toArray()
        );
    }

    public function test_reports_index_handles_user_without_reviews(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route("reports.index"));

        $response->assertOk()
            ->assertViewHas("stats");

        $stats = $response->viewData("stats");

        // 基本統計（空の状態）の検証
        $this->assertSame(0, $stats["summary"]["total_reviews"]);
        $this->assertSame(0, $stats["summary"]["books_read"]);
        $this->assertSame(0.0, $stats["summary"]["average_rating"]);

        // 評価の分布（空の状態）の検証
        $this->assertSame([0, 0, 0, 0, 0], $stats["rating_distribution"]);
        
        // ランキング・ジャンル別評価（空の状態）の検証
        $this->assertTrue($stats["top_rated_books"]->isEmpty());
        $this->assertTrue($stats["genre_ratings"]->isEmpty());
    }
}
```

---

## 19.2. コードの詳細解説

### 並び替えのテスト (`BookTest`)

```php
// test_book_index_can_sort_by_title
$response = $this->get(route("books.index", ["sort" => "title"]));
$titles = collect($response->viewData("books")->items())->pluck("title")->all();
$this->assertSame([$bookA->title, $bookZ->title], $titles);
```
1.  **`$response->viewData("books")`**: `assertSeeInOrder`ではHTML全体の出現順しか確認できません。より厳密に、コントローラーからViewに渡された`books`（ページネーションオブジェクト）の**データそのもの**の並び順を検証するために、`viewData`メソッドを使います。
2.  **`->items()`**: ページネーションオブジェクトから、そのページに含まれるレコードの配列を取得します。
3.  **`collect(...)->pluck("title")->all()`**: 取得したレコード配列をコレクションに変換し、`pluck`でタイトルのみを抜き出し、最終的にPHPの配列に変換します。
4.  **`$this->assertSame([...], $titles)`**: 2つの配列が**型と順序を含めて**完全に同一であることを検証します。これにより、タイトル順（A→Z）に正しくソートされていることを保証します。

```php
// test_export_csv_can_sort_by_oldest
$content = $response->streamedContent();
$this->assertTrue(strpos($content, $oldestBook->title) < strpos($content, $newestBook->title));
```
1.  **`strpos($haystack, $needle)`**: 文字列`$haystack`（CSVコンテンツ全体）の中から、文字列`$needle`（書籍タイトル）が最初に出現する位置（インデックス）を返します。
2.  **`<`**: 古い書籍のタイトルが出現する位置が、新しい書籍のタイトルが出現する位置よりも**小さい**（＝先に出現する）ことを検証します。これにより、CSVの内容が古い順にソートされていることを確認できます。

### ISBN検索のテスト (`BookTest`)

```php
// test_search_by_isbn_returns_book_information
config(["services.google.books_api_key" => "fake-key"]);
Http::fake(function ($request) use ($isbn) {
    $this->assertStringContainsString("isbn:{$isbn}", $request->url());
    $this->assertStringContainsString("fake-key", $request->url());
    return Http::response([...], 200);
});
```
1.  **`config([...])`**: テスト実行中に、コンフィグ値（この場合はAPIキー）を一時的に設定します。
2.  **`Http::fake(...)`**: LaravelのHTTPクライアントに、「実際にはリクエストを送信せず、このクロージャ（無名関数）を実行せよ」と指示します。
3.  **`$this->assertStringContainsString(...)`**: クロージャ内で、送信されるはずだったリクエストのURLに、ISBNとAPIキーが正しく含まれているかを検証します。
4.  **`return Http::response(...)`**: 偽のレスポンスを返します。これにより、実際のAPIの動作に関わらず、テストを安定して実行できます。

### 読書レポートのテスト (`ReportTest`)

```php
// test_reports_index_displays_stats_for_authenticated_user
$response = $this->actingAs($user)->get(route("reports.index"));
$stats = $response->viewData("stats");
$this->assertSame(3, $stats["summary"]["total_reviews"]);
$this->assertSame([0, 0, 1, 1, 1], $stats["rating_distribution"]);
$this->assertSame([...], $stats["top_rated_books"]->toArray());
```
1.  **`$response->viewData("stats")`**: レポートページに渡された`stats`変数の内容を全て取得します。
2.  **`$this->assertSame(...)`**: 取得した`stats`配列の各キー（`summary`, `rating_distribution`など）の値が、事前に準備したデータから計算される期待値と完全に一致するかを検証します。`toArray()`は、EloquentコレクションをPHPの配列に変換するために使用します。

```php
// test_reports_index_handles_user_without_reviews
$this->assertTrue($stats["top_rated_books"]->isEmpty());
```
1.  **`isEmpty()`**: コレクションが空であることを検証します。レビューがないユーザーの場合、ランキングなどが空のコレクションとして正しく処理されていることを確認します。

---

## 19.3. How to: この実装にたどり着くための調べ方

応用的なテスト手法は、公式ドキュメントのどこを読めば良いか分かりにくいことがあります。以下のような検索経路を辿ることで、必要な情報にたどり着くことができます。

### Step 1: 「外部APIを使う機能をテストしたい」

- **最初の検索**: `Laravel test external api` / `Laravel テスト API連携`
- **検索結果から得られる情報**: `Http::fake()`という機能があることを知る。「モック」という概念に触れる。
- **次の疑問と検索**: `Http::fake()`の具体的な使い方が知りたい → `Laravel Http fake example`
- **最終的にたどり着く答え**: 公式ドキュメントの「HTTPクライアント > テスト」のセクションにたどり着き、リクエストの検証方法や、成功・失敗レスポンスを返す方法を学ぶ。

### Step 2: 「Viewに渡した『データそのもの』をテストしたい」

- **最初の検索**: `Laravel test view data` / `Laravel テスト ビュー データ`
- **検索結果から得られる情報**: `assertViewHas`や`assertViewIs`といったメソッドの存在を知る。
- **次の疑問と検索**: Viewに渡された配列やオブジェクトの中身を、もっと詳しく検証したい → `Laravel test view data contents`
- **最終的にたどり着く答え**: `TestResponse`オブジェクトの`viewData()`メソッドを使うと、Viewに渡されたデータを直接取得できることを知る。あとはPHPUnitの`assertSame`や`assertCount`などを使って自由に検証できると理解する。

### Step 3: 「CSVの中身の『並び順』をテストしたい」

- **最初の検索**: `Laravel test csv content order` / `Laravel テスト CSV 並び順`
- **検索結果から得られる情報**: `assertSeeInOrder`はHTML向けで、CSVのようなプレーンテキストには使えないことが分かる。
- **次の疑問と検索**: 文字列の中で、ある単語が別の単語より前に出現するかを調べたい → `php check if string appears before another`
- **最終的にたどり着く答え**: PHPの標準関数である`strpos()`を使って各単語の位置を取得し、そのインデックスを比較すれば良い、というロジックにたどり着く。

---

## 19.4. まとめ

このChapterでは、応用機能に対するテスト手法を学びました。

- **検索・並び替え・CSVのテスト**: `viewData()`や`strpos()`を使い、HTMLの見た目だけでなく、データそのものやコンテンツの順序を厳密に検証しました。
- **外部API連携のテスト**: `Http::fake()`を使い、外部APIに依存しない、安定・高速なテストを実装しました。
- **読書レポートのテスト**: `viewData()`でコントローラーから渡された集計結果を取得し、その内容が期待値と完全に一致するかを検証しました。

テストは、書けば書くほど「こういう場合はどうやって検証しよう？」という新しい疑問が生まれます。その疑問をトリガーに、公式ドキュメントやWeb上の記事を調べることで、より多様なアサーションメソッドやテスト手法を身につけることができます。これにより、アプリケーションの品質と開発効率をさらに高めることができるでしょう。
