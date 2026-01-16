# Chapter 10: 検索機能

## 🎯 このセクションで学ぶこと

このセクションでは、書籍のタイトルや著者名で検索する機能を実装します。

- **クエリパラメータの取得**: URLの`?query=xxx`からキーワードを取得する方法を学びます。
- **LIKE検索**: 部分一致検索をEloquentで実装する方法を学びます。
- **`orWhere`**: 複数条件のOR検索を実装する方法を学びます。

---

## 🧠 先輩エンジニアの思考プロセス：検索機能の設計

検索機能を実装する際、以下の点を考慮します。

| 考慮点 | 設計判断 | 理由 |
|:---|:---|:---|
| 何を検索対象にするか | タイトルと著者名 | ユーザーが最もよく検索する項目。 |
| 完全一致か部分一致か | 部分一致（LIKE検索） | ユーザーが正確なタイトルを覚えていなくても検索できる。 |
| 検索結果の表示方法 | 既存の一覧ページを再利用 | 新しいビューを作る必要がなく、UIの一貫性も保てる。 |

---

## 10.1. BookControllerにsearchメソッドを追加

既存の`BookController`に検索メソッドを追加します。

```php
// app/Http/Controllers/BookController.php

public function search(Request $request)
{
    $query = $request->input('query');

    $books = Book::where('title', 'like', "%{$query}%")
        ->orWhere('author', 'like', "%{$query}%")
        ->with('genres')
        ->latest()
        ->paginate(10);

    return view('books.index', compact('books', 'query'));
}
```

### 10.1.1. コードリーディング：メソッドチェーンの分解

```php
$books = Book::where('title', 'like', "%{$query}%")
    ->orWhere('author', 'like', "%{$query}%")
    ->with('genres')
    ->latest()
    ->paginate(10);
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `$request->input('query')` | リクエストから`query`パラメータを取得します。 | `string\|null` | URLが`/books/search?query=Laravel`の場合、`'Laravel'`が取得されます。 |
| `Book::where('title', 'like', "%{$query}%")` | `title`カラムに`$query`が含まれるレコードを検索します。 | `Builder` | `%`はワイルドカードで、任意の文字列にマッチします。 |
| `->orWhere('author', 'like', "%{$query}%")` | または、`author`カラムに`$query`が含まれるレコードを検索します。 | `Builder` | `where`と`orWhere`はOR条件で結合されます。 |
| `->with('genres')` | `genres`リレーションをEager Loadします。 | `Builder` | N+1問題を防ぎます。 |
| `->latest()` | `created_at`の降順で並び替えます。 | `Builder` | 新しい書籍が先に表示されます。 |
| `->paginate(10)` | 10件ずつページネーションします。 | `LengthAwarePaginator` | - |

> **💡 ポイント: LIKE検索のワイルドカード**
> - `%Laravel%`: 「Laravel」を含む文字列にマッチ（例: 「入門Laravel」「Laravel実践」）
> - `Laravel%`: 「Laravel」で始まる文字列にマッチ（例: 「Laravel入門」）
> - `%Laravel`: 「Laravel」で終わる文字列にマッチ（例: 「入門Laravel」）

---

## 10.2. ルートの追加

```php
// routes/web.php

// 認証不要のルート
Route::get('/books/search', [BookController::class, 'search'])->name('books.search');
```

> **⚠️ 注意: ルートの順序**
> `/books/search`は`/books/{book}`よりも**先に**定義する必要があります。そうしないと、`search`が書籍IDとして解釈されてしまいます。

```php
// ✅ 正しい順序
Route::get('/books/search', [BookController::class, 'search'])->name('books.search');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// ❌ 間違った順序
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
Route::get('/books/search', [BookController::class, 'search'])->name('books.search'); // 到達しない
```

---

## 10.3. 検索フォームの実装例

ヘッダーやサイドバーに検索フォームを設置する例です。

```blade
{{-- 検索フォーム --}}
<form action="{{ route('books.search') }}" method="GET" class="flex gap-2">
    <input 
        type="text" 
        name="query" 
        value="{{ $query ?? '' }}" 
        placeholder="タイトルまたは著者名で検索"
        class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
    >
    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
        検索
    </button>
</form>
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `method="GET"` | 検索はGETメソッドで行います。 | URLに検索キーワードが含まれるため、ブックマークや共有が可能になります。 |
| `value="{{ $query ?? '' }}"` | 検索後も入力欄にキーワードを表示します。 | `??`はnull合体演算子で、`$query`がnullの場合は空文字を返します。 |

---

## 10.4. 検索結果の表示

`books.index`ビューを再利用し、検索結果を表示します。検索キーワードがある場合は、検索結果であることを示すメッセージを表示します。

```blade
{{-- 検索結果のメッセージ --}}
@if (isset($query) && $query)
    <p class="mb-4 text-gray-600">
        「{{ $query }}」の検索結果: {{ $books->total() }}件
    </p>
@endif

{{-- 書籍一覧（既存のコード） --}}
@foreach ($books as $book)
    {{-- ... --}}
@endforeach
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `isset($query) && $query` | `$query`が存在し、かつ空でない場合に表示します。 | 通常の一覧表示時には表示しません。 |
| `$books->total()` | ページネーション全体の件数を取得します。 | 現在のページの件数ではなく、検索結果全体の件数です。 |

これで、検索機能の実装が完了しました。次のChapterでは、ジャンル別一覧機能を実装していきます。
