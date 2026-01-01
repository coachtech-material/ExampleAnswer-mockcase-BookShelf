<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookController extends Controller
{
    /**
     * 書籍一覧を表示（検索機能付き - 応用機能）
     */
    public function index(Request $request): View
    {
        $query = Book::with("genres");

        // キーワード検索（応用機能）
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンル絞り込み（応用機能）
        if ($genreId = $request->input('genre')) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

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

        $books = $query->paginate(10)->withQueryString();
        $genres = Genre::orderBy('name')->get();

        return view("books.index", compact("books", "genres"));
    }

    public function create(): View
    {
        $genres = Genre::all();
        return view("books.create", compact("genres"));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bookData = collect($validated)->except('genres')->toArray();
        $book = $request->user()->books()->create($bookData);
        $book->genres()->attach($validated['genres']);

        return redirect()->route("books.show", $book)->with("success", "書籍を登録しました。");
    }

    public function show(Book $book): View
    {
        $book->load(["reviews.user", "reviews.likedByUsers", "genres"]);
        return view("books.show", compact("book"));
    }

    public function edit(Book $book): View
    {
        $this->authorize("update", $book);
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize("update", $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);

        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize("delete", $book);
        $book->delete();

        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }

    /**
     * 書籍一覧をCSVでエクスポート（応用機能）
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $keyword = $request->input('keyword');
        $genreId = $request->input('genre');

        $query = Book::with('genres');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($genreId) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $books = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="books_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($books) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'タイトル', '著者', 'ISBN', '出版日', 'ジャンル', '登録日']);

            foreach ($books as $book) {
                fputcsv($handle, [
                    $book->id,
                    $book->title,
                    $book->author,
                    $book->isbn ?? '',
                    $book->published_date?->format('Y-m-d') ?? '',
                    $book->genres->pluck('name')->implode(', '),
                    $book->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * ISBN検索（Google Books API）（応用機能）
     */
    public function fetch(Request $request): JsonResponse
    {
        $isbn = $request->input('isbn');

        if (!$isbn || strlen($isbn) !== 13) {
            return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
        }

        $apiKey = config('services.google.books_api_key');
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";

        if ($apiKey) {
            $url .= "&key={$apiKey}";
        }

        try {
            $response = Http::get($url);
            $data = $response->json();

            if (!isset($data['items'][0])) {
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
