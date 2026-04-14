<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $from = route('books.show', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_user_can_remove_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);
        $from = route('books.show', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_toggle_works_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $from = route('books.show', $book);

        // 1st toggle: add
        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 2nd toggle: remove
        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 3rd toggle: re-add
        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => 'Favorite Book']);
        $user->favoriteBooks()->attach($book->id);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('Favorite Book');
    }

    public function test_guest_cannot_toggle_favorite(): void
    {
        $book = Book::factory()->create();

        $this->post(route('favorites.toggle', $book))
            ->assertRedirect(route('login'));
    }
}
