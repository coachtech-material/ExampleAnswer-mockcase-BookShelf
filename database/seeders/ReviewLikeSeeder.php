<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $reviews = Review::all();

        // ランダムにいいねを付ける
        foreach ($reviews as $review) {
            // 各レビューに0〜3人のユーザーがいいねする
            $likeCount = rand(0, 3);
            $likeUsers = $users->where('id', '!=', $review->user_id)->random(min($likeCount, $users->count() - 1));
            
            foreach ($likeUsers as $user) {
                $user->likedReviews()->syncWithoutDetaching([$review->id]);
            }
        }
    }
}
