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
