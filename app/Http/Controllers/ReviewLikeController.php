<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    /**
     * いいねを追加/削除（トグル）
     */
    public function toggle(Review $review): RedirectResponse
    {
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }

    /**
     * いいねを追加
     */
    public function store(Review $review): RedirectResponse
    {
        Auth::user()->likedReviews()->syncWithoutDetaching($review->id);

        return back();
    }

    /**
     * いいねを削除
     */
    public function destroy(Review $review): RedirectResponse
    {
        Auth::user()->likedReviews()->detach($review->id);

        return back();
    }
}
