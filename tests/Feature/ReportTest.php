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
