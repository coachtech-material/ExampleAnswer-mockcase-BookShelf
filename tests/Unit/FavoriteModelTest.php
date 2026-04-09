<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_relationships_are_defined(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertTrue($favorite->user->is($user));
        $this->assertTrue($favorite->book->is($book));
    }
}
