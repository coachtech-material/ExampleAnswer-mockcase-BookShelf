# Chapter 19: 応用機能のテスト

---

## 🎯 このセクションで学ぶこと

- **応用機能**（検索・ソート、ISBN検索、読書レポート、Sanctum認証API）のFeatureテスト
- **コントローラーのメソッドを直接呼び出す**テスト手法
- レスポンスとして返される**Viewオブジェクトの中身を検証する**方法
- `Http::fake()`を使った**外部API連携のテスト**
- `Sanctum::actingAs()`を使った**API認証テスト**
- `assertSeeInOrder`, `assertJsonPath`, `assertExactJson`など、応用的なアサーションメソッド

---

## 📖 先輩エンジニアの思考プロセス

### なぜ応用機能のテストも必要なのか？

Chapter 14では、基本的なCRUD操作や認証・認可のテストを行いました。しかし、実務で開発するアプリケーションには、検索、外部連携、API認証など、より複雑な「応用機能」が数多く存在します。これらの機能は、複数の条件分岐や特殊なデータ処理を含むことが多く、手動でのテストには限界があります。

### どうやってテストの「観点」を見つけるか？

| 機能 | ユーザーの操作 / システムのイベント | システムの振る舞い（テストの観点） |
|:---|:---|:---|
| **検索機能** | キーワードを入力して検索する | 検索結果が正しいか？ 関係ないデータは表示されていないか？ 並び替え順は正しいか？ |
| **ISBN検索** | ISBNを入力して検索する | 正常に書籍情報が返ってくるか？ バリデーションは機能しているか？ APIエラー時に適切なレスポンスを返すか？ |
| **読書レポート** | レポートページにアクセスする | 各種統計情報が正しく計算されているか？ レビューがないユーザーでもエラーにならないか？ |
| **Sanctum認証API** | 認証あり/なしでAPIにアクセスする | 未認証時に401が返るか？ 認証済みで正常に動作するか？ 他人のデータを変更できないか？ |

### 外部API連携のテスト

- **`Http::fake()`**: 実際の外部APIにリクエストを送信する代わりに、偽のレスポンスを返すように設定します。これにより、APIの障害やネットワークの問題に影響されず、安定して高速にテストを実行できます。

### Sanctum認証のテスト

- **`Sanctum::actingAs($user, ['*'])`**: テスト中にSanctumトークン認証をシミュレートします。Web画面の`actingAs()`と同様の役割ですが、API認証（Bearerトークン）をシミュレートする点が異なります。

---

## 📋 テストファイルの構成

| テストファイル | テスト対象 |
|:---|:---|
| `tests/Feature/BookControllerTest.php` | 検索・ソート・ISBN検索・書籍編集認可 |
| `tests/Feature/ReportTest.php` | マイ読書レポートの統計データ |
| `tests/Feature/Api/V1/BookApiTest.php` | 公開API + Sanctum認証テスト |

---

## 🚀 テストコード

### 19.1. BookControllerTest の完全版

Chapter 14 で作成した基本テストに加え、検索・ソート・ISBN検索・書籍編集認可のテストを追加します。以下が **完全な** `tests/Feature/BookControllerTest.php` です。

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // ===== 基本機能（basic と同一） =====

    public function test_book_index_page_can_be_rendered(): void
    {
        Book::factory()->count(2)->create();

        $this->get(route('books.index'))
            ->assertOk();
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('books.create'))
            ->assertOk();
    }

    public function test_guest_cannot_view_create_form(): void
    {
        $this->get(route('books.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            'title' => 'My Test Book',
            'isbn' => '1111111111111',
            'genres' => $genres->pluck('id')->toArray(),
        ]);

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $book = Book::where('title', 'My Test Book')->first();
        $this->assertNotNull($book);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'My Test Book',
        ]);

        foreach ($genres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    public function test_book_store_validation_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'invalid-date',
            'genres' => [],
        ]);

        $response->assertSessionHasErrors(['title', 'isbn', 'genres']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_show_page_can_be_rendered(): void
    {
        $book = Book::factory()->create(['title' => 'Detail Book']);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Detail Book');
    }

    public function test_authenticated_user_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $originalGenre = Genre::factory()->create();
        $book->genres()->attach($originalGenre);
        $newGenres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            'title' => 'Updated Title',
            'isbn' => '9876543210123',
            'genres' => $newGenres->pluck('id')->toArray(),
        ]);

        $response = $this->actingAs($user)->put(route('books.update', $book), $payload);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'isbn' => '9876543210123',
        ]);

        foreach ($newGenres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $originalGenre->id,
        ]);
    }

    public function test_authenticated_user_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_only_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('books.edit', $book))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route('books.edit', $book))
            ->assertForbidden();
    }

    // ===== 検索・フィルタ（応用機能） =====

    public function test_book_index_with_search_query_displays_results(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $hit = Book::factory()->create(['user_id' => $user->id, 'title' => 'Laravel入門']);
        $miss = Book::factory()->create(['user_id' => $user->id, 'title' => 'PHP基礎']);
        $hit->genres()->attach($genre->id);
        $miss->genres()->attach($genre->id);

        $response = $this->get(route('books.index', ['keyword' => 'Laravel']));

        $response->assertOk();
        $response->assertSee('Laravel入門');
        $response->assertDontSee('PHP基礎');
    }

    public function test_book_index_can_filter_by_genre(): void
    {
        $user = User::factory()->create();
        $genreA = Genre::factory()->create(['name' => 'GenreA']);
        $genreB = Genre::factory()->create(['name' => 'GenreB']);

        $bookA = Book::factory()->create(['user_id' => $user->id, 'title' => 'BookA-Title']);
        $bookB = Book::factory()->create(['user_id' => $user->id, 'title' => 'BookB-Title']);
        $bookA->genres()->attach($genreA->id);
        $bookB->genres()->attach($genreB->id);

        $response = $this->get(route('books.index', ['genre' => $genreA->id]));

        $response->assertOk();
        $response->assertSee('BookA-Title');
        $response->assertDontSee('BookB-Title');
    }

    // ===== ソート（応用機能） =====

    public function test_book_index_default_sort_is_newest(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $oldBook = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old Book',
            'created_at' => now()->subDays(5),
        ]);
        $newBook = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'New Book',
            'created_at' => now(),
        ]);
        $oldBook->genres()->attach($genre->id);
        $newBook->genres()->attach($genre->id);

        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['New Book', 'Old Book']);
    }

    public function test_book_index_can_sort_by_oldest(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $oldBook = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old Book',
            'created_at' => now()->subDays(5),
        ]);
        $newBook = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'New Book',
            'created_at' => now(),
        ]);
        $oldBook->genres()->attach($genre->id);
        $newBook->genres()->attach($genre->id);

        $response = $this->get(route('books.index', ['sort' => 'oldest']));

        $response->assertOk();
        $response->assertSeeInOrder(['Old Book', 'New Book']);
    }

    public function test_book_index_can_sort_by_title(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $bookB = Book::factory()->create(['user_id' => $user->id, 'title' => 'B Book']);
        $bookA = Book::factory()->create(['user_id' => $user->id, 'title' => 'A Book']);
        $bookB->genres()->attach($genre->id);
        $bookA->genres()->attach($genre->id);

        $response = $this->get(route('books.index', ['sort' => 'title']));

        $response->assertOk();
        $books = $response->viewData('books');
        $titles = $books->pluck('title')->all();

        $this->assertSame(['A Book', 'B Book'], $titles);
    }

    public function test_book_index_can_sort_by_rating(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $highBook = Book::factory()->create(['user_id' => $user->id, 'title' => 'High Rated']);
        $lowBook = Book::factory()->create(['user_id' => $user->id, 'title' => 'Low Rated']);
        $highBook->genres()->attach($genre->id);
        $lowBook->genres()->attach($genre->id);

        Review::factory()->for($highBook)->for($user)->create(['rating' => 5]);
        Review::factory()->for($lowBook)->for($user)->create(['rating' => 1]);

        $response = $this->get(route('books.index', ['sort' => 'rating']));

        $response->assertOk();
        $books = $response->viewData('books');
        $titles = $books->pluck('title')->all();

        $this->assertSame('High Rated', $titles[0]);
        $this->assertSame('Low Rated', $titles[1]);
    }

    // ===== 書籍編集認可（応用機能 - BookPolicy + View 検証） =====

    public function test_owner_receives_edit_view_with_genres(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        Genre::factory()->count(3)->create();

        $this->assertTrue($owner->can('update', $book));

        $response = $this->actingAs($owner)->get(route('books.edit', $book));

        $response->assertOk();
        $view = $response->viewData('book');
        $this->assertInstanceOf(Book::class, $view);
        $this->assertSame($book->id, $view->id);

        $genres = $response->viewData('genres');
        $this->assertCount(3, $genres);
    }

    // ===== ISBN検索（応用機能 - Http::fake モック） =====

    public function test_search_by_isbn_returns_book_information(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'モックされた本',
                            'authors' => ['著者A', '著者B'],
                            'publishedDate' => '2024-06-01',
                            'description' => 'これはモック説明文',
                            'imageLinks' => [
                                'thumbnail' => 'https://example.com/thumb.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('books.searchByIsbn', ['isbn' => '9784000000000']));

        $response->assertStatus(200);
        $response->assertJson([
            'title' => 'モックされた本',
            'author' => '著者A, 著者B',
            'published_date' => '2024-06-01',
            'description' => 'これはモック説明文',
            'image_url' => 'https://example.com/thumb.jpg',
        ]);
    }

    public function test_search_by_isbn_returns_400_for_non_13_digit(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('books.searchByIsbn', ['isbn' => '12345']));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'ISBNは13桁で入力してください。']);
    }

    public function test_search_by_isbn_returns_404_when_no_results(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::response(['totalItems' => 0], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('books.searchByIsbn', ['isbn' => '9784000000000']));

        $response->assertStatus(404);
        $response->assertJson(['error' => '書籍が��つかりませんでした。']);
    }

    public function test_search_by_isbn_returns_500_on_api_exception(): void
    {
        Http::fake(function (): void {
            throw new \Exception('Network error');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('books.searchByIsbn', ['isbn' => '9784000000000']));

        $response->assertStatus(500);
        $response->assertJson(['error' => 'API通信エラーが発生���ました。']);
    }

    private function validBookData(array $overrides = []): array
    {
        $genres = $overrides['genres'] ?? Genre::factory()->count(2)->create()->pluck('id')->toArray();

        return array_merge([
            'title' => 'Sample Book',
            'author' => 'Sample Author',
            'isbn' => '1234567890123',
            'published_date' => '2024-01-01',
            'description' => 'Sample description',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => $genres,
        ], $overrides);
    }
}
```

### 19.2. ReportTest

マイ読書レポートの統計データが正しく計算されるかを検証するテストです。

#### `tests/Feature/ReportTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_report_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('マイ読書レポート');
    }

    public function test_summary_statistics_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        Review::factory()->for($user)->for($bookA)->create(['rating' => 5]);
        Review::factory()->for($user)->for($bookB)->create(['rating' => 3]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $stats = $response->viewData('stats');
        $summary = $stats['summary'];

        $this->assertSame(2, $summary['total_reviews']);
        $this->assertSame(2, $summary['books_read']);
        $this->assertEqualsWithDelta(4.0, $summary['average_rating'], 0.001);
    }

    public function test_rating_distribution_counts_each_star_rating(): void
    {
        $user = User::factory()->create();

        Review::factory()->for($user)->count(2)->create(['rating' => 5]);
        Review::factory()->for($user)->count(1)->create(['rating' => 4]);
        Review::factory()->for($user)->count(3)->create(['rating' => 3]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $distribution = $response->viewData('stats')['rating_distribution'];

        $this->assertInstanceOf(Collection::class, $distribution);
        // インデックス 0..4 が rating 1..5 に対応
        $this->assertSame([0, 0, 3, 1, 2], $distribution->values()->all());
    }

    public function test_top_rated_books_includes_only_4_stars_or_higher(): void
    {
        $user = User::factory()->create();
        $highBook = Book::factory()->create(['title' => 'High Rated Book']);
        $midBook = Book::factory()->create(['title' => 'Mid Rated Book']);

        Review::factory()->for($user)->for($highBook)->create(['rating' => 5]);
        Review::factory()->for($user)->for($midBook)->create(['rating' => 3]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $top = $response->viewData('stats')['top_rated_books'];

        $this->assertInstanceOf(Collection::class, $top);
        $this->assertCount(1, $top);
        $this->assertSame('High Rated Book', $top->first()['title']);
        $this->assertSame(5, $top->first()['rating']);
    }

    public function test_genre_ratings_aggregates_by_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'テストジャンル']);
        $book = Book::factory()->create();
        $book->genres()->attach($genre);

        Review::factory()->for($user)->for($book)->create(['rating' => 4]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $genreRatings = $response->viewData('stats')['genre_ratings'];

        $this->assertInstanceOf(Collection::class, $genreRatings);
        $this->assertCount(1, $genreRatings);
        $this->assertSame('テストジャンル', $genreRatings->first()['name']);
        $this->assertSame(1, $genreRatings->first()['count']);
        $this->assertEqualsWithDelta(4.0, $genreRatings->first()['average_rating'], 0.001);
    }

    public function test_user_with_no_reviews_sees_empty_safe_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(0, $stats['summary']['total_reviews']);
        $this->assertSame(0, $stats['summary']['books_read']);
        $this->assertSame(0, $stats['summary']['average_rating']);
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->values()->all());
    }
}
```

### 19.3. BookApiTest（Sanctum認証テスト含む）

公開APIの読み取り系テストに加え、Sanctum認証が必要な書き込み系のテストを実装します。

#### `tests/Feature/Api/V1/BookApiTest.php`

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    // ===== GET /api/v1/books =====

    public function test_index_returns_data_and_meta_structure(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->count(3)->for($user)->create()->each(function ($book) use ($genre): void {
            $book->genres()->attach($genre);
        });

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'title', 'author', 'isbn', 'published_date',
                    'description', 'image_url', 'genres',
                    'average_rating', 'review_count',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        // 一覧では reviews フィールドは含まれない（whenLoaded のため）
        $response->assertJsonMissing(['reviews' => []]);
    }

    public function test_index_filters_by_keyword(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $hit = Book::factory()->for($user)->create(['title' => 'Laravel���門']);
        $miss = Book::factory()->for($user)->create(['title' => 'PHP基礎']);
        $hit->genres()->attach($genre);
        $miss->genres()->attach($genre);

        $response = $this->getJson('/api/v1/books?keyword=Laravel');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Laravel入門');
    }

    public function test_index_filters_by_genre_id(): void
    {
        $user = User::factory()->create();
        $genreA = Genre::factory()->create();
        $genreB = Genre::factory()->create();
        $bookA = Book::factory()->for($user)->create(['title' => 'BookA']);
        $bookB = Book::factory()->for($user)->create(['title' => 'BookB']);
        $bookA->genres()->attach($genreA);
        $bookB->genres()->attach($genreB);

        $response = $this->getJson("/api/v1/books?genre_id={$genreA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'BookA');
    }

    public function test_index_pagination_per_page(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->count(5)->for($user)->create()->each(function ($book) use ($genre): void {
            $book->genres()->attach($genre);
        });

        $response = $this->getJson('/api/v1/books?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonPath('meta.total', 5);
    }

    public function test_index_returns_422_when_per_page_exceeds_max(): void
    {
        $response = $this->getJson('/api/v1/books?per_page=200');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    // ===== GET /api/v1/books/{book} =====

    public function test_show_returns_book_with_reviews(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create();
        $book->genres()->attach($genre);
        Review::factory()->for($book)->for($user)->create();

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id', 'title', 'author', 'isbn', 'published_date',
                'description', 'image_url', 'genres',
                'average_rating', 'review_count',
                'reviews' => [
                    '*' => ['id', 'user_name', 'rating', 'comment', 'created_at'],
                ],
            ],
        ]);
        $response->assertJsonPath('data.id', $book->id);
    }

    public function test_show_returns_custom_404_json_when_not_found(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでし��。']);
    }

    // ===== POST /api/v1/books (Sanctum 必須) =====

    public function test_store_returns_401_when_unauthenticated(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->postJson('/api/v1/books', [
            'title' => 'New Book',
            'author' => 'Author',
            'isbn' => '9784000000000',
            'published_date' => '2024-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(401);
    }

    public function test_store_creates_book_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        Sanctum::actingAs($user, ['*']);

        $payload = [
            'title' => 'New API Book',
            'author' => 'API Author',
            'isbn' => '9784000000000',
            'published_date' => '2024-01-01',
            'description' => 'desc',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'New API Book');

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => 'New API Book',
            'isbn' => '9784000000000',
        ]);

        $book = Book::where('isbn', '9784000000000')->first();
        foreach ($genres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    public function test_store_returns_422_with_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/books', [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'genres' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'author', 'isbn', 'genres']);
    }

    // ===== PUT /api/v1/books/{book} (Sanctum + 所有者) =====

    public function test_update_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $genre = Genre::factory()->create();

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => 'X',
            'author' => 'Y',
            'isbn' => '9784000000111',
            'published_date' => '2024-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(401);
    }

    public function test_owner_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['title' => 'Old Title']);
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create();
        $book->genres()->attach($oldGenre);

        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => 'Updated Title',
            'author' => 'Updated Author',
            'isbn' => '9784000000222',
            'published_date' => '2024-02-02',
            'description' => null,
            'image_url' => null,
            'genres' => [$newGenre->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'isbn' => '9784000000222',
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);
    }

    public function test_other_user_cannot_update_book_returns_403_json(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $genre = Genre::factory()->create();

        Sanctum::actingAs($other, ['*']);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => 'Hacked',
            'author' => 'Hacker',
            'isbn' => '9784000000333',
            'published_date' => '2024-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(403);
        $response->assertExactJson(['error' => 'この操作を実行する権��がありません。']);
    }

    public function test_update_returns_404_for_unknown_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson('/api/v1/books/99999', [
            'title' => 'X',
            'author' => 'Y',
            'isbn' => '9784000000444',
            'published_date' => '2024-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    public function test_update_returns_422_with_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '',
            'author' => '',
            'isbn' => 'short',
            'genres' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'author', 'isbn', 'genres']);
    }

    // ===== DELETE /api/v1/books/{book} (Sanctum + 所有者) =====

    public function test_destroy_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(401);
    }

    public function test_owner_can_destroy_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_other_user_cannot_destroy_book_returns_403_json(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        Sanctum::actingAs($other, ['*']);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(403);
        $response->assertExactJson(['error' => 'この操作を実行する権限���ありません。']);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_destroy_returns_404_for_unknown_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }
}
```

---

## 🔍 テストコードの詳細解説

### Sanctum::actingAs() の使い方

```php
Sanctum::actingAs($user, ['*']);

$response = $this->postJson('/api/v1/books', $payload);
```

- **`Sanctum::actingAs($user, ['*'])`**: `$user`がSanctumトークンで認証されている状態をシミュレートします。第2引数の`['*']`は、全てのアビリティ（権限）を持つトークンを意味します。
- Web画面テストの`$this->actingAs($user)`とは異なり、APIトークン認証をシミュレートするため、`auth:sanctum`ミドルウェアを通過できます。

### 認証なしのテスト（401の確認）

```php
public function test_store_returns_401_when_unauthenticated(): void
{
    $response = $this->postJson('/api/v1/books', [...]);
    $response->assertStatus(401);
}
```

- `Sanctum::actingAs()`を呼ばずにリクエストを送信すると、`auth:sanctum`ミドルウェアが401を返します。
- 認証が正しく機能していることを確認する、非常に重要なテストです。

### 認可のテスト（403の確認）

```php
public function test_other_user_cannot_update_book_returns_403_json(): void
{
    Sanctum::actingAs($other, ['*']);  // 他人として認証

    $response = $this->putJson("/api/v1/books/{$book->id}", [...]);

    $response->assertStatus(403);
    $response->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
}
```

- 認証は通るが、BookPolicyによる認可で弾かれるケースをテストしています。
- `assertExactJson()`は、レスポンスのJSONが指定した内容と**完全に一致する**ことを確認します。

---

## 🧐 テスト実行

```bash
# 全テストを実行
sail artisan test

# 特定のテストファイルを実行
sail artisan test tests/Feature/BookControllerTest.php
sail artisan test tests/Feature/ReportTest.php
sail artisan test tests/Feature/Api/V1/BookApiTest.php
```

全テストがパスすることを確認してください。

---

## ✅ テスト結果の確認ポイント

| テスト観点 | 確認内容 |
|:---|:---|
| **検索・ソート** | キーワード/ジャンル絞り込み、各ソート順が正しい |
| **ISBN検索** | Http::fake()でモック、正常系/400/404/500 |
| **読書レポート** | 基本統計、評価分布、ランキング、ジャンル別、ゼロ件 |
| **API認証（401）** | 未認証での書き込み系アクセスが拒否される |
| **API認可（403）** | 他人の書籍の更新・削除が拒否される |
| **API正常系** | 認証済みユーザーがCRUD操作できる |
| **APIバリデーション（422）** | 不正なデータで適切なエラーが返る |

---

## ✨ まとめ

このChapterでは、応用機能に対する包括的なテストを実装しました。

| 学んだこと | 内容 |
|:---|:---|
| **Http::fake()** | 外部API連携を安定してテストするためのモック機能 |
| **Sanctum::actingAs()** | APIトークン認証をシミュレートするテスト手法 |
| **viewData()** | コントローラーがビューに渡すデータを直接検証する方法 |
| **assertExactJson()** | APIレスポンスの完全一致を確認する厳密なアサーション |
| **認証・認可テスト** | 401/403の両方をカバーするセキュリティテスト |

テストに裏付けされた堅牢な応用機能が完成しました。次のChapter 20で最終確認を行います。
