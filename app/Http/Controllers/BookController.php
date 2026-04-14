<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧を表示（検索機能付き - 応用機能）
     */
    public function index(Request $request): View
    {
        $query = Book::with('genres');

        // キーワード検索（応用機能）
        $query->when($request->input('keyword'), function ($q, $keyword): void {
            $q->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        });

        // ジャンル絞り込み（応用機能）
        $query->when($request->input('genre'), function ($q, $genreId): void {
            $q->whereHas('genres', function ($q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        });

        // 並び順（応用機能）
        switch ($request->input('sort')) {
            case 'oldest':
                $query->oldest();
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'rating':
                $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating');
                break;
            default:
                $query->latest();
                break;
        }

        $books = $query->paginate(10)->appends(request()->query());
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 書籍登録フォームを表示
     */
    public function create(): View
    {
        $genres = Genre::orderBy('name')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 書籍を登録
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細を表示
     */
    public function show(Book $book): View
    {
        $book->load(['reviews.user', 'reviews.likedByUsers', 'genres']);

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集フォームを表示
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);
        $genres = Genre::orderBy('name')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍を更新
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book->update($bookData);
        $book->genres()->sync($validated['genres']);

        return redirect()->route('books.show', $book)->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }

    /**
     * ISBN検索（Google Books API）（応用機能）
     */
    public function searchByIsbn(string $isbn): JsonResponse
    {
        if (strlen($isbn) !== 13) {
            return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
        }

        $apiKey = config('services.google.books_api_key');
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";

        if ($apiKey) {
            $url .= "&key={$apiKey}";
        }

        try {
            $response = Http::get($url);

            if ($response->status() === 429) {
                return response()->json([
                    'error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。',
                ], 429);
            }

            $data = $response->json();

            if (! isset($data['items'][0])) {
                return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
            }

            $volumeInfo = $data['items'][0]['volumeInfo'];

            return response()->json([
                'title' => $volumeInfo['title'] ?? '',
                'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
                'published_date' => $volumeInfo['publishedDate'] ?? '',
                'description' => $volumeInfo['description'] ?? '',
                'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'API通信エラーが発生しました。'], 500);
        }
    }
}
