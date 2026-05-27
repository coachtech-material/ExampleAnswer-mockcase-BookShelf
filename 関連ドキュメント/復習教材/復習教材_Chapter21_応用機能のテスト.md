# Chapter 21: 応用機能のテスト

---

## 🎯 このセクションで学ぶこと

- **応用機能**（検索・ソート、ISBN検索、読書レポート、Sanctum認証API、読書計画 + リマインダー通知）のFeatureテスト
- **コントローラーのメソッドを直接呼び出す**テスト手法
- レスポンスとして返される**Viewオブジェクトの中身を検証する**方法
- `Http::fake()`を使った**外部API連携のテスト**
- `Sanctum::actingAs()`を使った**API認証テスト**
- `Carbon::setTestNow()`を使った**時刻固定テスト**（バッチ処理）
- `$this->artisan(...)`を使った**Console Command（バッチ）の実行テスト**
- `assertDatabaseHas('notifications', ['data->timing' => ...])`を使った**JSON path クエリ**による DB 検証
- `assertSeeInOrder`, `assertJsonPath`, `assertExactJson`など、応用的なアサーションメソッド

---

## 1. はじめに 📖

### なぜ応用機能のテストも必要なのか？

Chapter 14では、基本的なCRUD操作や認証・認可のテストを行いました。しかし、実務で開発するアプリケーションには、検索、外部連携、API認証など、より複雑な「応用機能」が数多く存在します。これらの機能は、複数の条件分岐や特殊なデータ処理を含むことが多く、手動でのテストには限界があります。

### どうやってテストの「観点」を見つけるか？

| 機能 | ユーザーの操作 / システムのイベント | システムの振る舞い（テストの観点） |
|:---|:---|:---|
| **検索機能** | キーワードを入力して検索する | 検索結果が正しいか？ 関係ないデータは表示されていないか？ 並び替え順は正しいか？ |
| **ISBN検索** | ISBNを入力して検索する | 正常に書籍情報が返ってくるか？ バリデーションは機能しているか？ APIエラー時に適切なレスポンスを返すか？ |
| **読書レポート** | レポートページにアクセスする | 各種統計情報が正しく計算されているか？ レビューがないユーザーでもエラーにならないか？ |
| **Sanctum認証API** | 認証あり/なしでAPIにアクセスする | 未認証時に401が返るか？ 認証済みで正常に動作するか？ 他人のデータを変更できないか？ |
| **読書計画 + リマインダー通知** | 計画の作成・編集・削除、毎日のバッチ実行 | CRUD と Policy 認可は正しいか？ 計画削除時に紐づく通知も削除されるか？ 3 タイミング（3 日前 / 当日 / 3 日後）で通知が発火するか？ 期日経過の進行中計画は expired に自動更新されるか？ |

### 外部API連携のテスト

- **`Http::fake()`**: 実際の外部APIにリクエストを送信する代わりに、偽のレスポンスを返すように設定します。これにより、APIの障害やネットワークの問題に影響されず、安定して高速にテストを実行できます。

### Sanctum認証のテスト

- **`Sanctum::actingAs($user, ['*'])`**: テスト中にSanctumトークン認証をシミュレートします。Web画面の`actingAs()`と同様の役割ですが、API認証（Bearerトークン）をシミュレートする点が異なります。

### バッチ処理 / 時刻固定のテスト

- **`Carbon::setTestNow('2026-05-12 20:00:00')`**: 現在時刻を任意のタイミングに固定します。リマインダーバッチの「3 日前 / 当日 / 3 日後」のような相対日付ロジックを、決定論的にテストするために必須です。
- **`$this->artisan('reading-plans:run-daily')->assertSuccessful()`**: Console Command（バッチ）を実行し、終了コードが 0 であることをアサートします。バッチ実行後の DB 状態は `assertDatabaseHas` / `assertDatabaseMissing` で検証します。
- **JSON path クエリ**: `notifications.data` のような JSON カラム内のフィールドは、`assertDatabaseHas('notifications', ['data->timing' => ...])` のように `->` で直接検証できます。

---

## 2. 要件の確認 📋

| テストファイル | テスト対象 |
|:---|:---|
| `tests/Feature/BookControllerTest.php` | 検索・ソート・ISBN検索・書籍編集認可 |
| `tests/Feature/ReportTest.php` | マイ読書レポートの統計データ |
| `tests/Feature/Api/V1/BookApiTest.php` | 公開API + Sanctum認証テスト |
| `tests/Feature/ReadingPlanCrudTest.php` | 読書計画 CRUD + Policy 認可 + 関連通知削除 |
| `tests/Feature/ReadingPlanDeadlineChangeTest.php` | 期日変更時の挙動（期限切れ復帰 / 完了済み編集不可） |
| `tests/Feature/ReadingPlanReminderBatchTest.php` | リマインダー通知の 3 タイミング発火検証 |
| `tests/Feature/ReadingPlanAutoExpireBatchTest.php` | 期日経過の進行中計画を expired に一括更新 |

---

## 3. 先輩エンジニアの思考プロセス 💭

応用機能（高度な検索 / ISBN 検索 / マイ読書レポート / Sanctum 認証 / 読書計画 + 通知 等）をどう設計しテストするかの設計判断は、各機能の Chapter （17-20）で詳述しています。本 Chapter ではテスト戦略（ユニット / フィーチャー / 認可・認証）の観点でテストを構成します。

---

## 4. 実装 🚀

以下のテストファイルを一括作成します（`BookControllerTest.php` は Chapter 14 で作成済みのため、内容を 20.1 で上書きします）:

```bash
mkdir -p tests/Feature/Api/V1
touch tests/Feature/ReportTest.php tests/Feature/Api/V1/BookApiTest.php \
      tests/Feature/ReadingPlanCrudTest.php tests/Feature/ReadingPlanDeadlineChangeTest.php \
      tests/Feature/ReadingPlanReminderBatchTest.php tests/Feature/ReadingPlanAutoExpireBatchTest.php
```

### 20.1. BookControllerTest の完全版

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
        $response->assertJson(['error' => '書籍が見つかりませんでした。']);
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
        $response->assertJson(['error' => 'API通信エラーが発生しました。']);
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

### 20.2. ReportTest

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

### 20.3. BookApiTest（Sanctum認証テスト含む）

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
        $hit = Book::factory()->for($user)->create(['title' => 'Laravel入門']);
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
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
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
        $response->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
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
        $response->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
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

### 20.4. ReadingPlanCrudTest

読書計画の CRUD 操作と、`ReadingPlanPolicy` による認可、計画削除時に紐づく通知も同時に削除されることを検証するテストです。

#### `tests/Feature/ReadingPlanCrudTest.php`

```php
<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login_for_index(): void
    {
        $this->get(route('reading-plans.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress->value,
        ]);
    }

    public function test_in_progress_duplicate_creation_is_rejected(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->for($user)->for($book)->inProgress()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('book_id');
        $this->assertSame(1, ReadingPlan::where('user_id', $user->id)->where('book_id', $book->id)->count());
    }

    public function test_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();

        $this->actingAs($owner)
            ->get(route('reading-plans.edit', $plan))
            ->assertOk();
    }

    public function test_other_user_cannot_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_owner_can_delete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_owner_delete_plan_also_removes_related_notifications(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherPlan = ReadingPlan::factory()->for($owner)->inProgress()->create();

        $owner->notify(new PlanReminderNotification(
            $plan,
            PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        ));
        $owner->notify(new PlanReminderNotification(
            $otherPlan,
            PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        ));
        $this->assertSame(2, $owner->fresh()->notifications()->count());

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'));

        // 削除した計画への通知のみ削除されている
        $remaining = $owner->fresh()->notifications()->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($otherPlan->id, $remaining->first()->data['plan_id']);
    }

    public function test_other_user_cannot_delete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }

    public function test_complete_action_marks_plan_as_completed(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create([
            'completed_at' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'));

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertNotNull($plan->completed_at);
    }

    public function test_other_user_cannot_complete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->post(route('reading-plans.complete', $plan))
            ->assertForbidden();
    }

    public function test_index_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create();
        ReadingPlan::factory()->for($user)->completed()->create();

        $response = $this->actingAs($user)
            ->get(route('reading-plans.index', ['status' => 'completed']));

        $response->assertOk();
        $plans = $response->viewData('readingPlans');
        $this->assertCount(1, $plans);
        $this->assertSame(ReadingPlanStatus::Completed, $plans->first()->status);
    }
}
```

**観点**

| 観点 | テストメソッド | 確認内容 |
|:---|:---|:---|
| **CRUD 動作** | `test_authenticated_user_can_create_plan` / `test_owner_can_delete_plan` / `test_complete_action_marks_plan_as_completed` | 作成・削除・読了マークが正しく永続化される |
| **重複制御** | `test_in_progress_duplicate_creation_is_rejected` | 同じ書籍で進行中の計画を二重に作れない（FormRequest の Closure チェック） |
| **Policy 認可** | `test_other_user_cannot_view_edit_form` / `test_other_user_cannot_delete_plan` / `test_other_user_cannot_complete_plan` | 他人の計画の編集・削除・読了マークは 403 |
| **関連通知の同時削除** | `test_owner_delete_plan_also_removes_related_notifications` | 計画削除時に DB::transaction で「その計画 ID を持つ通知」のみ削除されるか（`data['plan_id']` で確認） |
| **ステータスフィルタ** | `test_index_can_filter_by_status` | `?status=completed` で完了済みのみ取得できる |

---

### 20.5. ReadingPlanDeadlineChangeTest

期日変更時の特殊な挙動 — 期限切れ計画の編集で進行中に復帰すること、完了済み計画は編集できないこと — を検証するテストです。

#### `tests/Feature/ReadingPlanDeadlineChangeTest.php`

```php
<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanDeadlineChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_change_updates_target_date(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ])->assertRedirect(route('reading-plans.index'));

        $plan->refresh();
        $this->assertSame(now()->addDays(10)->format('Y-m-d'), $plan->target_date->format('Y-m-d'));
    }

    public function test_expired_plan_recovers_to_in_progress(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->expired()->create();
        $this->assertSame(ReadingPlanStatus::Expired, $plan->status);

        $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(7)->format('Y-m-d'),
        ])->assertRedirect(route('reading-plans.index'));

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
    }

    public function test_completed_plan_edit_returns_403(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->completed()->create();

        $this->actingAs($user)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertForbidden();
    }
}
```

**観点**

| 観点 | テストメソッド | 確認内容 |
|:---|:---|:---|
| **期日更新** | `test_deadline_change_updates_target_date` | `target_date` が新しい値に更新される |
| **期限切れ復帰** | `test_expired_plan_recovers_to_in_progress` | `expired` 状態の計画を編集すると `in_progress` に戻る（Controller の status 更新ロジック） |
| **完了済みの編集不可** | `test_completed_plan_edit_returns_403` | `Completed` 状態の計画は edit / update ともに 403（ReadingPlanPolicy@update の status チェック） |

---

### 20.6. ReadingPlanReminderBatchTest

リマインダー通知バッチ（`reading-plans:run-daily`）が、3 つのタイミング（3 日前 / 当日 / 3 日後）で正しく通知を発火させることを検証するテストです。

#### `tests/Feature/ReadingPlanReminderBatchTest.php`

```php
<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanReminderBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_days_before_reminder_is_dispatched(): void
    {
        Carbon::setTestNow('2026-05-12 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-15',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => PlanReminderNotification::class,
            'data->timing' => PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        ]);
    }

    public function test_on_due_date_reminder_is_dispatched(): void
    {
        Carbon::setTestNow('2026-05-15 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-15',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => PlanReminderNotification::class,
            'data->timing' => PlanReminderNotification::TIMING_ON_DUE_DATE,
        ]);
    }

    public function test_three_days_after_reminder_is_dispatched_for_expired_plans(): void
    {
        Carbon::setTestNow('2026-05-18 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->create([
            'status' => ReadingPlanStatus::Expired,
            'target_date' => '2026-05-15',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => PlanReminderNotification::class,
            'data->timing' => PlanReminderNotification::TIMING_THREE_DAYS_AFTER,
        ]);
    }

    public function test_no_reminder_for_out_of_window_plans(): void
    {
        Carbon::setTestNow('2026-05-12 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-20', // 8 日後 → 対象外
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $user->id,
        ]);
    }
}
```

**観点**

| 観点 | テストメソッド | 確認内容 |
|:---|:---|:---|
| **時刻固定** | 全メソッド | `Carbon::setTestNow()` で現在時刻を固定し、相対日付に依存しない決定論的なテストに |
| **3 日前通知** | `test_three_days_before_reminder_is_dispatched` | `target_date - 3` の進行中計画でバッチ実行 → `TIMING_THREE_DAYS_BEFORE` の通知が発火 |
| **当日通知** | `test_on_due_date_reminder_is_dispatched` | `target_date == 今日` の計画 → `TIMING_ON_DUE_DATE` の通知が発火 |
| **3 日後通知** | `test_three_days_after_reminder_is_dispatched_for_expired_plans` | `target_date + 3` で `expired` 状態の計画 → `TIMING_THREE_DAYS_AFTER` の通知が発火 |
| **対象外確認** | `test_no_reminder_for_out_of_window_plans` | 8 日先など対象ウィンドウ外の計画では通知が発火しない |
| **JSON path クエリ** | 全メソッド | `assertDatabaseHas('notifications', ['data->timing' => ...])` で `notifications.data` JSON カラム内のフィールドを直接検証 |

---

### 20.7. ReadingPlanAutoExpireBatchTest

期日経過の進行中計画が、リマインダーバッチ実行時に一括で `expired` ステータスへ更新されることを検証するテストです。

#### `tests/Feature/ReadingPlanAutoExpireBatchTest.php`

```php
<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanAutoExpireBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_plans_past_due_date_are_marked_as_expired(): void
    {
        Carbon::setTestNow('2026-05-15 20:00:00');

        $user = User::factory()->create();
        $expiredPlan = ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-14',
        ]);
        $activePlan = ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-20',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $expiredPlan->refresh();
        $this->assertSame(ReadingPlanStatus::Expired, $expiredPlan->status);

        $activePlan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $activePlan->status);
    }
}
```

**観点**

| 観点 | テストメソッド | 確認内容 |
|:---|:---|:---|
| **一括 expired 化** | `test_in_progress_plans_past_due_date_are_marked_as_expired` | `target_date < 今日` の `in_progress` 計画が `expired` に更新される（バッチ内 `update()` が複数行に効く） |
| **対象外不変** | 同上 | `target_date >= 今日` の進行中計画は `in_progress` のまま（クエリ条件の正しさを保証） |

---

## 5. コードの詳細解説 🔍

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

## 6. この実装にたどり着くための調べ方 🧐

```bash
# 全テストを実行
sail artisan test

# 特定のテストファイルを実行
sail artisan test tests/Feature/BookControllerTest.php
sail artisan test tests/Feature/ReportTest.php
sail artisan test tests/Feature/Api/V1/BookApiTest.php
sail artisan test tests/Feature/ReadingPlanCrudTest.php
sail artisan test tests/Feature/ReadingPlanDeadlineChangeTest.php
sail artisan test tests/Feature/ReadingPlanReminderBatchTest.php
sail artisan test tests/Feature/ReadingPlanAutoExpireBatchTest.php
```

全テストがパスすることを確認してください。

---

## 7. 動作確認 ✅

| テスト観点 | 確認内容 |
|:---|:---|
| **検索・ソート** | キーワード/ジャンル絞り込み、各ソート順が正しい |
| **ISBN検索** | Http::fake()でモック、正常系/400/404/500 |
| **読書レポート** | 基本統計、評価分布、ランキング、ジャンル別、ゼロ件 |
| **API認証（401）** | 未認証での書き込み系アクセスが拒否される |
| **API認可（403）** | 他人の書籍の更新・削除が拒否される |
| **API正常系** | 認証済みユーザーがCRUD操作できる |
| **APIバリデーション（422）** | 不正なデータで適切なエラーが返る |
| **読書計画 CRUD** | 作成・編集・削除・読了マークが正しく動作する |
| **読書計画 Policy 認可** | 他人の計画の編集・削除・読了マークで 403 が返る |
| **計画削除と関連通知** | 計画削除時に紐づく通知が同時に削除される（DB::transaction） |
| **期日変更挙動** | 期限切れ計画の編集で進行中復帰、完了済みは編集不可 |
| **リマインダー通知** | 3 タイミング（3 日前 / 当日 / 3 日後）で通知が発火する |
| **自動 expired 化** | 期日経過の進行中計画が一括で expired に更新される |

---

## 8. まとめ ✨

このChapterでは、応用機能に対する包括的なテストを実装しました。

| 学んだこと | 内容 |
|:---|:---|
| **Http::fake()** | 外部API連携を安定してテストするためのモック機能 |
| **Sanctum::actingAs()** | APIトークン認証をシミュレートするテスト手法 |
| **viewData()** | コントローラーがビューに渡すデータを直接検証する方法 |
| **assertExactJson()** | APIレスポンスの完全一致を確認する厳密なアサーション |
| **認証・認可テスト** | 401/403の両方をカバーするセキュリティテスト |
| **Carbon::setTestNow()** | バッチ処理など時刻に依存するロジックを決定論的にテストする手法 |
| **JSON path クエリ** | `assertDatabaseHas` の `data->timing` で JSON カラム内のフィールドを検証する手法 |
| **`$this->artisan(...)`** | Console Command（バッチ）の実行と成否をテストするアサーション |

テストに裏付けされた堅牢な応用機能が完成しました。次のChapter 21で最終確認を行います。
