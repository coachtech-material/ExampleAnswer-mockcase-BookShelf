<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポートを表示
     */
    public function index(): View
    {
        $user = Auth::user();

        // ユーザーのレビューを全て取得（リレーション含む）
        $reviews = Review::with(['book.genres'])
            ->where('user_id', $user->id)
            ->get();

        // お気に入り書籍を取得
        $favoriteBooks = $user->favoriteBooks()->with('genres')->get();

        // 統計データを生成
        $stats = $this->generateStats($reviews, $favoriteBooks);

        return view('reports.index', compact('stats'));
    }

    /**
     * 統計データを生成
     */
    private function generateStats(Collection $reviews, Collection $favoriteBooks): array
    {
        return [
            'summary' => $this->generateSummary($reviews),
            'rating_distribution' => $this->generateRatingDistribution($reviews),
            'genre_stats' => $this->generateGenreStats($reviews),
            'favorite_genres' => $this->generateFavoriteGenres($favoriteBooks),
            'top_rated_books' => $this->getTopRatedBooks($reviews),
            'reading_streak' => $this->calculateReadingStreak($reviews),
        ];
    }

    /**
     * 基本統計サマリーを生成
     * 使用: count(), avg(), max(), min()
     */
    private function generateSummary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'average_rating' => $reviews->avg('rating') ?? 0,
            'highest_rating' => $reviews->max('rating') ?? 0,
            'lowest_rating' => $reviews->min('rating') ?? 0,
            'total_books_reviewed' => $reviews->pluck('book_id')->unique()->count(),
        ];
    }

    /**
     * 評価分布を生成
     * 使用: groupBy(), map(), count()
     */
    private function generateRatingDistribution(Collection $reviews): Collection
    {
        // 1〜5の評価ごとにグループ化し、件数をカウント
        $distribution = $reviews
            ->groupBy('rating')
            ->map(fn (Collection $group) => $group->count());

        // 1〜5の全ての評価を含むように補完
        return collect(range(1, 5))
            ->mapWithKeys(fn (int $rating) => [$rating => $distribution->get($rating, 0)]);
    }

    /**
     * ジャンル別統計を生成
     * 使用: flatMap(), groupBy(), map(), sortByDesc(), take()
     */
    private function generateGenreStats(Collection $reviews): Collection
    {
        return $reviews
            // 各レビューから書籍のジャンルを展開（flatMap）
            ->flatMap(function (Review $review) {
                return $review->book->genres->map(fn ($genre) => [
                    'genre_name' => $genre->name,
                    'rating' => $review->rating,
                ]);
            })
            // ジャンル名でグループ化
            ->groupBy('genre_name')
            // 各ジャンルの統計を計算
            ->map(function (Collection $genreReviews, string $genreName) {
                $ratings = $genreReviews->pluck('rating');
                return [
                    'name' => $genreName,
                    'count' => $genreReviews->count(),
                    'average_rating' => round($ratings->avg(), 1),
                    'total_rating' => $ratings->sum(),
                ];
            })
            // レビュー数で降順ソート
            ->sortByDesc('count')
            // 上位10件を取得
            ->take(10)
            ->values();
    }

    /**
     * お気に入りジャンルを分析
     * 使用: flatMap(), countBy(), sortDesc(), take()
     */
    private function generateFavoriteGenres(Collection $favoriteBooks): Collection
    {
        return $favoriteBooks
            // 各書籍からジャンル名を展開
            ->flatMap(fn ($book) => $book->genres->pluck('name'))
            // ジャンル名ごとにカウント
            ->countBy()
            // 降順ソート
            ->sortDesc()
            // 上位5件を取得
            ->take(5);
    }

    /**
     * 高評価書籍を取得
     * 使用: filter(), sortByDesc(), take()
     */
    private function getTopRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            // 評価4以上をフィルタ
            ->filter(fn (Review $review) => $review->rating >= 4)
            // 評価で降順ソート
            ->sortByDesc('rating')
            // 上位5件を取得
            ->take(5)
            // 必要な情報のみ抽出
            ->map(fn (Review $review) => [
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
                'reviewed_at' => $review->created_at->format('Y-m-d'),
            ])
            ->values();
    }

    /**
     * 読書継続日数を計算
     * 使用: map(), unique(), sort(), reduce()
     */
    private function calculateReadingStreak(Collection $reviews): array
    {
        // レビュー日をユニークな日付リストに変換
        $reviewDates = $reviews
            ->map(fn (Review $review) => $review->created_at->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        if ($reviewDates->isEmpty()) {
            return ['current_streak' => 0, 'longest_streak' => 0];
        }

        // 連続日数を計算（reduce使用）
        $streakData = $reviewDates->reduce(function (array $carry, string $date) {
            $currentDate = \Carbon\Carbon::parse($date);
            
            if ($carry['last_date'] === null) {
                $carry['current'] = 1;
                $carry['longest'] = 1;
            } else {
                $lastDate = \Carbon\Carbon::parse($carry['last_date']);
                $diffDays = $lastDate->diffInDays($currentDate);
                
                if ($diffDays === 1) {
                    $carry['current']++;
                    $carry['longest'] = max($carry['longest'], $carry['current']);
                } elseif ($diffDays > 1) {
                    $carry['current'] = 1;
                }
            }
            
            $carry['last_date'] = $date;
            return $carry;
        }, ['current' => 0, 'longest' => 0, 'last_date' => null]);

        return [
            'current_streak' => $streakData['current'],
            'longest_streak' => $streakData['longest'],
        ];
    }
}
