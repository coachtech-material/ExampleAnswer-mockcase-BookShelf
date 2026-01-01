<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookController extends Controller
{
    /**
     * 書籍一覧を表示（検索機能付き）
     */
    public function index(Request $request): View
    {
        $keyword = $request->input('keyword');
        $genreId = $request->input('genre');
        $sort = $request->input('sort', 'newest');

        $query = Book::with('genres')->withAvg('reviews', 'rating');

        // キーワード検索
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンル絞り込み
        if ($genreId) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        // 並び順
        switch ($sort) {
            case 'oldest':
                $query->oldest();
                break;
            case 'rating':
                $query->orderByDesc('reviews_avg_rating');
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $books = $query->paginate(10)->withQueryString();
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres', 'keyword', 'genreId', 'sort'));
    }

    /**
     * 書籍検索
     */
    public function search(Request $request): View
    {
        return $this->index($request);
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

        DB::transaction(function () use ($validated, $request) {
            $bookData = collect($validated)->except('genres')->toArray();
            $book = $request->user()->books()->create($bookData);
            $book->genres()->attach($validated['genres']);
        });

        return redirect()->route('books.index')->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細を表示
     */
    public function show(Book $book): View
    {
        $book->load(['genres', 'reviews.user', 'reviews.likedByUsers']);
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

        DB::transaction(function () use ($validated, $book) {
            $bookData = collect($validated)->except('genres')->toArray();
            $book->update($bookData);
            $book->genres()->sync($validated['genres']);
        });

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
     * 書籍一覧をCSVでエクスポート
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $keyword = $request->input('keyword');
        $genreId = $request->input('genre');

        $query = Book::with('genres');

        // キーワード検索
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンル絞り込み
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

            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // ヘッダー行
            fputcsv($handle, ['ID', 'タイトル', '著者', 'ISBN', '出版日', 'ジャンル', '登録日']);

            // データ行
            $books->each(function ($book) use ($handle) {
                fputcsv($handle, [
                    $book->id,
                    $book->title,
                    $book->author,
                    $book->isbn ?? '',
                    $book->published_date?->format('Y-m-d') ?? '',
                    $book->genres->pluck('name')->implode(', '),
                    $book->created_at->format('Y-m-d H:i:s'),
                ]);
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * ISBN検索（Google Books API）
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
