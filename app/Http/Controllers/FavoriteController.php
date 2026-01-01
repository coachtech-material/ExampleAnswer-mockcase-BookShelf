<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧を表示
     */
    public function index(): View
    {
        $books = Auth::user()->favoriteBooks()->with('genres')->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りを追加/削除（トグル）
     */
    public function toggle(Book $book): RedirectResponse
    {
        Auth::user()->favoriteBooks()->toggle($book->id);

        return back();
    }

    /**
     * お気に入りに追加
     */
    public function store(Book $book): RedirectResponse
    {
        Auth::user()->favoriteBooks()->syncWithoutDetaching($book->id);

        return back();
    }

    /**
     * お気に入りから削除
     */
    public function destroy(Book $book): RedirectResponse
    {
        Auth::user()->favoriteBooks()->detach($book->id);

        return back();
    }
}
