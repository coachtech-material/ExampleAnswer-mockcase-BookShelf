# Chapter 10: 検索機能

このChapterでは、書籍のタイトルや著者名で検索できる機能を実装します。

---

## 10.1. BookControllerに検索メソッドを追加

`app/Http/Controllers/BookController.php` に `search` メソッドを追加します。

```php
public function search(Request $request): View
{
    $query = $request->input("query");
    $books = Book::where("title", "like", "%{$query}%")
        ->orWhere("author", "like", "%{$query}%")
        ->with("genres")
        ->latest()
        ->paginate(10);

    return view("books.index", compact("books", "query"));
}
```

---

## 10.2. ルートの追加

`routes/web.php` のパブリックルートに以下を追加します。

```php
Route::get('/books/search', [BookController::class, 'search'])->name('books.search');
```

---

## 10.3. 動作確認

1. `/books/search?query=キーワード` にアクセスし、検索結果が表示されることを確認
2. タイトルまたは著者名に検索キーワードが含まれる書籍のみが表示されることを確認
3. ページネーションが正しく動作することを確認

これで、検索機能の実装が完了しました。
