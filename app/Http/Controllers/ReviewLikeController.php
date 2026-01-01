<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    public function toggle(Review $review)
    {
        $user = Auth::user();
        
        if ($user->likedReviews()->where('review_id', $review->id)->exists()) {
            $user->likedReviews()->detach($review->id);
        } else {
            $user->likedReviews()->attach($review->id);
        }
        
        return back();
    }

    public function store(Review $review)
    {
        Auth::user()->likedReviews()->syncWithoutDetaching($review->id);
        return back();
    }

    public function destroy(Review $review)
    {
        Auth::user()->likedReviews()->detach($review->id);
        return back();
    }
}
