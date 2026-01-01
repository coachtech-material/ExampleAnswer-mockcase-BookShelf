# Chapter 9: ランキング機能

このChapterでは、レビューの平均評価が高い書籍をランキング形式で表示する機能を実装します。

---

## 9.1. コントローラーの作成

```bash
sail artisan make:controller RankingController
```

---

## 9.2. RankingControllerの実装

```php
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
```

---

## 9.3. ルートの追加

`routes/web.php` のパブリックルートに以下を追加します。

```php
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
```

---

## 9.4. ランキングビュー (`resources/views/ranking/index.blade.php`)

```html
<x-app-layout>
    <x-slot name="header">評価ランキング TOP 10</x-slot>
    <ol>
        @foreach($rankedBooks as $book)
            <li>
                <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
                {{ number_format($book->average_rating, 2) }} ★
            </li>
        @endforeach
    </ol>
</x-app-layout>
```

---

## 9.5. 動作確認

1. `/ranking` にアクセスし、ランキングが表示されることを確認
2. レビューの平均評価が高い順に書籍が表示されていることを確認
3. 書籍名をクリックして詳細ページに遷移できることを確認

これで、ランキング機能の実装が完了しました。
