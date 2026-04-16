<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    /**
     * 書籍一覧を取得
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $query = Book::with('genres')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('genre_id')) {
            $genreId = $request->input('genre_id');
            $query->whereHas('genres', function ($q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $books = $query->latest()->paginate($perPage);

        return BookResource::collection($books);
    }

    /**
     * 書籍詳細を取得
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を新規登録（要 Sanctum 認証）
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $book = $request->user()->books()->create($validated);
        $book->genres()->sync($genreIds);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 書籍を更新（要 Sanctum 認証 + 書籍所有者）
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        $validated = $request->validated();
        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $book->update($validated);
        $book->genres()->sync($genreIds);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    /**
     * 書籍を削除（要 Sanctum 認証 + 書籍所有者）
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->json(null, 204);
    }
}
