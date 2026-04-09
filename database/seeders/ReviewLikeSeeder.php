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

        foreach ($reviews as $review) {
            $candidates = $users->where('id', '!=', $review->user_id);
            $maxLikes = min(3, $candidates->count());
            $likeCount = rand(0, $maxLikes);
            if ($likeCount > 0) {
                $likers = $candidates->random($likeCount);
                $review->likedByUsers()->attach($likers->pluck('id'));
            }
        }
    }
}
