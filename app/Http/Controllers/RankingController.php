<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index()
    {
        $rankedBooks = Book::select('books.*', DB::raw('AVG(reviews.rating) as average_rating'))
            ->join('reviews', 'books.id', '=', 'reviews.book_id')
            ->groupBy('books.id')
            ->orderByDesc('average_rating')
            ->take(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}