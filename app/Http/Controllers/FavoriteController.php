<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    // Bladeの要求に合わせて toggle メソッドに変更
    public function toggle(Book $book)
    {
        // ユーザーがすでにお気に入りにしていれば解除、していなければ登録を自動で行うメソッド
        Auth::user()->favoriteBooks()->toggle($book->id);

        return back();
    }

    public function index()
    {
        $books = Auth::user()->favoriteBooks()->paginate(10);

        return view('favorites.index', compact('books'));
    }
}
