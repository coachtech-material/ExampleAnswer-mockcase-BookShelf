# Chapter 8: 検索機能

このChapterでは、書籍のタイトルや著者名で検索できる機能を実装します。GETリクエストでクエリパラメータを受け取り、それに基づいてデータベースを検索する、Webアプリケーションの基本的な機能です。

## 8-1. 検索フォームの作成

まずは、ユーザーが検索キーワードを入力するためのフォームを、書籍一覧ページに設置します。

**`resources/views/books/index.blade.php`**
```blade
<x-app-layout>
    {{-- ... header ... --}}

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- 検索フォームを追加 --}}
            <div class="mb-4">
                <form action="{{ route('books.index') }}" method="GET">
                    <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="書籍名や著者名で検索">
                    <button type="submit">検索</button>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                {{-- ... 書籍一覧 ... --}}
            </div>
        </div>
    </div>
</x-app-layout>
```

**コードリーディング:**
- `action="{{ route('books.index') }}"`: フォームの送信先は書籍一覧ページ自身です。
- `method="GET"`: 検索のような、サーバーの状態を変更しない（冪等性を持つ）操作にはGETメソッドを使うのが一般的です。URLに `?keyword=...` のようにクエリパラメータが付与されます。
- `value="{{ request('keyword') }}"`: 検索後も入力ボックスに検索キーワードが残るように、リクエストから`keyword`を取得して表示しています。これにより、ユーザーは自分が何で検索したかを再確認できます。

## 8-2. Controllerでの検索ロジックの実装

`BookController`の`index`メソッドを修正し、検索キーワードが渡された場合に結果を絞り込むロジックを追加します。

**`app/Http/Controllers/BookController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request; // Requestをインポート

class BookController extends Controller
{
    public function index(Request $request) // Requestを受け取る
    {
        $keyword = $request->input('keyword');

        $query = Book::query();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        $books = $query->with('genres')->latest()->paginate(10);

        return view('books.index', compact('books'));
    }

    // ... 他のメソッド
}
```

**コードリーディング:**
- `index(Request $request)`: `index`メソッドに`Request`クラスのインスタンスをDI（依存性注入）することで、リクエスト情報を取得できるようにします。
- `$keyword = $request->input('keyword');`: リクエストから`keyword`という名前の入力値を取得します。
- `$query = Book::query();`: まず、Eloquentのクエリビルダインスタンスを生成します。ここから条件を繋げていきます。
- `if ($keyword)`: `keyword`が存在する場合のみ、絞り込み処理を実行します。
- `$query->where(function ($q) use ($keyword) { ... });`: `where`句をグループ化するためにクロージャ（無名関数）を使用します。これは、`(A or B) and C` のような複雑な条件を正しく構築するために重要です。
- `$q->where('title', 'like', "%{$keyword}%")->orWhere('author', 'like', "%{$keyword}%");`: `title` **または** `author` にキーワードが部分一致する書籍を検索します。`%`はSQLの`LIKE`句で「0文字以上の任意の文字列」を表すワイルドカードです。
- `$books = $query->...->paginate(10);`: 最終的に構築されたクエリを実行し、結果をページネーション付きで取得します。

## 8-3. ページネーションと検索キーワードの連携

このままでは、検索結果の2ページ目に移動した際に検索キーワードが消えてしまい、全件表示の2ページ目に移動してしまいます。ページネーションリンクに検索キーワードを引き継がせる必要があります。

### Step 1: `Book`モデルに`scope`を定義 (任意だが推奨)

検索ロジックをControllerからモデルの`scope`に移動させることで、Controllerをよりクリーンに保ち、ロジックを再利用しやすくします。

**`app/Models/Book.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder; // Builderをインポート
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    // ... 他のプロパティやリレーション

    /**
     * キーワードで検索するスコープ
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $keyword
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch(Builder $query, ?string $keyword)
    {
        if ($keyword) {
            return $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }
        return $query;
    }
}
```

**コードリーディング:**
- `public function scopeSearch(...)`: `scope`で始まるメソッドを定義すると、`Book::search($keyword)`のように呼び出せるようになります。第一引数には自動的にクエリビルダが渡されます。

### Step 2: Controllerを`scope`を使うように修正

**`app/Http/Controllers/BookController.php`**
```php
public function index(Request $request)
{
    $keyword = $request->input('keyword');

    $books = Book::search($keyword) // scopeを利用
        ->with('genres')
        ->latest()
        ->paginate(10);

    return view('books.index', compact('books'));
}
```

### Step 3: ページネーションリンクの修正

`paginate`メソッドの結果に`appends`メソッドをチェーンすることで、ページネーションのリンクに現在のクエリパラメータ（この場合は`keyword`）を自動的に付与できます。

**`app/Http/Controllers/BookController.php`**
```php
public function index(Request $request)
{
    $keyword = $request->input('keyword');

    $books = Book::search($keyword)
        ->with('genres')
        ->latest()
        ->paginate(10)
        ->appends($request->all()); // この行を追加

    return view('books.index', compact('books'));
}
```

**コードリーディング:**
- `appends($request->all())`: 現在のリクエストに含まれる全てのクエリパラメータ（`['keyword' => '...']`）を、生成されるページネーションのURLに追加します。これにより、`http://.../books?page=2` ではなく `http://.../books?keyword=...&page=2` というURLが生成されます。

## 8-4. 動作確認

1.  書籍一覧ページに検索フォームが表示されていることを確認します。
2.  キーワードを入力して検索し、タイトルまたは著者にそのキーワードが含まれる書籍のみが表示されることを確認します。
3.  検索結果が複数ページにわたる場合、2ページ目に移動しても検索結果が維持されていることを確認します。（URLに`keyword`と`page`の両方が含まれていることを確認）
4.  キーワードを空にして検索すると、全件が表示されることを確認します。

---

これで、実用的な検索機能が完成しました。次のChapterでは、非同期処理について学びます。
