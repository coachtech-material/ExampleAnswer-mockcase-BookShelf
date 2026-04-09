<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    // ===== GET /api/v1/books =====

    public function test_index_returns_data_and_meta_structure(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->count(3)->for($user)->create()->each(function ($book) use ($genre) {
            $book->genres()->attach($genre);
        });

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'title', 'author', 'isbn', 'published_date',
                    'genres', 'average_rating', 'review_count',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        // 一覧では description / image_url / reviews は含まれない（詳細時のみ）
        $firstBook = $response->json('data.0');
        $this->assertArrayNotHasKey('description', $firstBook);
        $this->assertArrayNotHasKey('image_url', $firstBook);
        $this->assertArrayNotHasKey('reviews', $firstBook);
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
        Book::factory()->count(5)->for($user)->create()->each(function ($book) use ($genre) {
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

    // ===== POST /api/v1/books =====

    public function test_store_creates_book_and_attaches_genres(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $payload = [
            'user_id' => $user->id,
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
        $response = $this->postJson('/api/v1/books', [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'invalid',
            'genres' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'user_id', 'title', 'author', 'isbn', 'published_date', 'genres',
        ]);
    }

    // ===== PUT /api/v1/books/{book} =====

    public function test_update_modifies_book_fields_and_genres(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['title' => 'Old Title']);
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create();
        $book->genres()->attach($oldGenre);

        $payload = [
            'user_id' => $user->id,
            'title' => 'Updated Title',
            'author' => 'Updated Author',
            'isbn' => '9784000000111',
            'published_date' => '2024-02-02',
            'description' => null,
            'image_url' => null,
            'genres' => [$newGenre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'isbn' => '9784000000111',
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

    public function test_update_returns_404_for_unknown_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->putJson('/api/v1/books/99999', [
            'user_id' => $user->id,
            'title' => 'X',
            'author' => 'Y',
            'isbn' => '9784000000222',
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

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '',
            'author' => '',
            'isbn' => 'short',
            'published_date' => 'no-date',
            'genres' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'user_id', 'title', 'author', 'isbn', 'published_date', 'genres',
        ]);
    }

    // ===== DELETE /api/v1/books/{book} =====

    public function test_destroy_deletes_book_and_returns_204(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_destroy_returns_404_for_unknown_book(): void
    {
        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }
}
