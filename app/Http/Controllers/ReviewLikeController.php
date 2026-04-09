<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    // Bladeの要求に合わせて toggle メソッドに変更
    public function toggle(Review $review)
    {
        // ユーザーがすでにいいねしていれば解除、していなければ登録を自動で行う
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }
}
