<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポートを表示します。
     */
    public function index(): View
    {
        $user = Auth::user();
        $reviews = $user->reviews()->with('book.genres')->get();

        $stats = [
            'summary' => $this->calculateSummary($reviews),
            'rating_distribution' => $this->calculateRatingDistribution($reviews),
            'top_rated_books' => $this->calculateTopRatedBooks($reviews),
            'genre_ratings' => $this->calculateGenreRatings($reviews),
        ];

        return view('reports.index', compact('stats'));
    }

    /**
     * 基本サマリー（総レビュー数、読了冊数、平均評価）を計算する
     *
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function calculateSummary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => $reviews->avg('rating') ?? 0,
        ];
    }

    /**
     * 評価分布（1〜5星ごとの件数）を計算する
     *
     * @return array<int, int>
     */
    private function calculateRatingDistribution(Collection $reviews): array
    {
        $grouped = $reviews->groupBy('rating');

        return collect(range(1, 5))
            ->map(fn ($rating) => $grouped->has($rating) ? $grouped[$rating]->count() : 0)
            ->toArray();
    }

    /**
     * 高評価書籍TOP5（4星以上）を計算する
     *
     * @return array<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function calculateTopRatedBooks(Collection $reviews): array
    {
        return $reviews
            ->filter(fn ($review) => $review->rating >= 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn ($review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values()
            ->toArray();
    }

    /**
     * ジャンル別評価傾向TOP5を計算する
     *
     * flatMap()で多対多リレーションを展開し、groupBy()で集計する
     *
     * @return array<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function calculateGenreRatings(Collection $reviews): array
    {
        return $reviews
            // flatMap: 1レビュー → 複数ジャンルに展開（多対多の扱い）
            ->flatMap(fn ($review) => $review->book->genres->map(fn ($genre) => [
                'genre_id' => $genre->id,
                'genre_name' => $genre->name,
                'rating' => $review->rating,
            ]))
            // groupBy + map: ジャンルごとに集計
            ->groupBy('genre_id')
            ->map(fn ($items) => [
                'id' => $items->first()['genre_id'],
                'name' => $items->first()['genre_name'],
                'count' => $items->count(),
                'average_rating' => round($items->avg('rating'), 1),
            ])
            ->sortByDesc('average_rating')
            ->take(5)
            ->values()
            ->toArray();
    }
}
