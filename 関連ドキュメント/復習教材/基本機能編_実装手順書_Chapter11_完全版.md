# Chapter 11: ジャンル別一覧機能

このChapterでは、特定のジャンルに属する書籍を一覧表示する機能を実装します。

---

## 11.1. GenreControllerにshowメソッドを追加

`app/Http/Controllers/GenreController.php` に `show` メソッドを追加します。

```php
public function show(Genre $genre)
{
    $books = $genre->books()->with('genres')->paginate(10);
    return view('genres.show', compact('genre', 'books'));
}
```

---

## 11.2. ルートの追加

`routes/web.php` のパブリックルートに以下を追加します。

```php
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
```

---

## 11.3. 動作確認

1. `/genres/{genre_id}` にアクセスし、そのジャンルに属する書籍一覧が表示されることを確認
2. ページネーションが正しく動作することを確認
3. 書籍名をクリックして詳細ページに遷移できることを確認

これで、ジャンル別一覧機能の実装が完了しました。
