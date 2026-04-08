<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\Api\V1\BookResource;
use App\Models\Book;

class BookController extends Controller
{
    public function index(IndexBookRequest $request)
    {
        $query = Book::with('genres')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('genre_id')) {
            $genreId = $request->input('genre_id');
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $perPage = $request->input('per_page', 20);
        $books = $query->latest()->paginate($perPage);

        return BookResource::collection($books);
    }

    public function show(Book $book)
    {
        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $validated = $request->validated();

        $book->update([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        $book->load(['genres', 'reviews.user']);
        $book->loadCount('reviews');
        $book->loadAvg('reviews', 'rating');

        return new BookResource($book);
    }

    public function destroy(Book $book)
    {
        $book->delete();

        return response()->json(null, 204);
    }
}
