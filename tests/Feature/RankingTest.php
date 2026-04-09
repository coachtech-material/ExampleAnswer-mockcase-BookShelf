<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_page_can_be_rendered(): void
    {
        $book = Book::factory()->create(['title' => 'Ranked Book']);
        Review::factory()->for($book)->count(2)->create(['rating' => 5]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('Ranked Book');
    }

    public function test_ranking_is_ordered_by_average_rating(): void
    {
        $topBook = Book::factory()->create(['title' => 'Top Book']);
        Review::factory()->for($topBook)->count(2)->create(['rating' => 5]);

        $middleBook = Book::factory()->create(['title' => 'Middle Book']);
        Review::factory()->for($middleBook)->count(2)->create([
            'rating' => 3,
        ]);

        $lowBook = Book::factory()->create(['title' => 'Low Book']);
        Review::factory()->for($lowBook)->count(2)->create([
            'rating' => 1,
        ]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Top Book',
                'Middle Book',
                'Low Book',
            ]);
    }
}
