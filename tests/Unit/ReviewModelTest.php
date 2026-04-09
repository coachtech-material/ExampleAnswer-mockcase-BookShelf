<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_relationships_are_defined(): void
    {
        $author = User::factory()->create();
        $book = Book::factory()->for($author)->create();
        $review = Review::factory()->for($book)->for($author)->create();
        $liker = User::factory()->create();
        $review->likedByUsers()->attach($liker->id);

        $this->assertTrue($review->user->is($author));
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->likedByUsers->contains($liker));
    }
}
