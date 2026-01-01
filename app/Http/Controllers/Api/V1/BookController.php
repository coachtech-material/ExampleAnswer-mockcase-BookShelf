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
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->input('query');

        $books = Book::with('genres')
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
     */
    public function show(Book $book): JsonResponse
    {
        $book->load(['genres', 'reviews.user']);

        return response()->json($book);
    }

    /**
     * 書籍を登録
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'size:13', 'unique:books'],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['exists:genres,id'],
        ]);

        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);
        $book->load('genres');

        return response()->json($book, 201);
    }

    /**
     * 書籍を更新
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'size:13', 'unique:books,isbn,' . $book->id],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['exists:genres,id'],
        ]);

        $bookData = collect($validated)->except('genres')->toArray();
        $book->update($bookData);
        $book->genres()->sync($validated['genres']);
        $book->load('genres');

        return response()->json($book);
    }

    /**
     * 書籍を削除
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return response()->json(null, 204);
    }
}
