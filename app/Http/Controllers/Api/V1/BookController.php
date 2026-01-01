<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * 書籍一覧を取得
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->input('query');

        $books = Book::query()
            ->with('genres')
            ->when($query, function ($q, $query): void {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('author', 'like', "%{$query}%");
            })
            ->latest()
            ->paginate(10);

        return response()->json($books);
    }

    /**
     * 書籍詳細を取得
     *
     * @param Book $book
     * @return JsonResponse
     */
    public function show(Book $book): JsonResponse
    {
        $book->load(['genres', 'reviews.user']);

        return response()->json($book);
    }
}
