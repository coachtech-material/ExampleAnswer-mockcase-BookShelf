# Chapter 12: ジャンル管理機能 (CRUD)

このChapterでは、ジャンルの作成・一覧表示・編集・削除機能を実装します。

---

## 12.1. GenreControllerの完全実装

```bash
sail artisan make:controller GenreController --resource
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::withCount('books')->get();
        return view('genres.index', compact('genres'));
    }

    public function create()
    {
        return view('genres.create');
    }

    public function store(StoreGenreRequest $request)
    {
        Genre::create($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを作成しました。');
    }

    public function show(Genre $genre)
    {
        $books = $genre->books()->with('genres')->paginate(10);
        return view('genres.show', compact('genre', 'books'));
    }

    public function edit(Genre $genre)
    {
        return view('genres.edit', compact('genre'));
    }

    public function update(UpdateGenreRequest $request, Genre $genre)
    {
        $genre->update($request->validated());
        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    public function destroy(Genre $genre)
    {
        if ($genre->books()->count() > 0) {
            return redirect()->route('genres.index')->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }
        $genre->delete();
        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
```

---

## 12.2. ルートの追加

`routes/web.php` の認証必須ルートに以下を追加します。

```php
Route::resource('genres', GenreController::class)->except(['show']);
```

---

## 12.3. 動作確認

1. `/genres` にアクセスし、ジャンル一覧が表示されることを確認
2. ジャンルの作成・編集・削除ができることを確認
3. 書籍が紐付いているジャンルは削除できないことを確認

これで、ジャンル管理機能の実装が完了しました。
