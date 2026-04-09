<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_like_relationships_are_defined(): void
    {
        $author = User::factory()->create();
        $book = Book::factory()->for($author)->create();
        $review = Review::factory()->for($book)->for($author)->create();
        $liker = User::factory()->create();

        $reviewLike = ReviewLike::create([
            'user_id' => $liker->id,
            'review_id' => $review->id,
        ]);

        $this->assertTrue($reviewLike->user->is($liker));
        $this->assertTrue($reviewLike->review->is($review));
    }
}
