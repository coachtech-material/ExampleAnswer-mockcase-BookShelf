# Chapter 9: 検索 & 絞り込み機能

このChapterでは、書籍のタイトルや著者名での「キーワード検索」と、特定のジャンルに属する書籍を一覧表示する「絞り込み」機能を実装します。GETリクエストでクエリパラメータを受け取り、それに基づいてデータベースを検索する、Webアプリケーションの基本的な機能です。

## 9-1. 検索・絞り込みフォームの作成

まずは、ユーザーが検索キーワードを入力したり、ジャンルを選択したりするためのUIを、書籍一覧ページに設置します。

**`resources/views/books/index.blade.php`**
```blade
<x-app-layout>
    {{-- ... header ... --}}

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- 検索・絞り込みフォームを追加 --}}
            <div class="mb-4">
                <form action="{{ route("books.index") }}" method="GET" class="flex items-center space-x-2">
                    <input type="text" name="keyword" value="{{ request("keyword") }}" placeholder="書籍名や著者名で検索">
                    <select name="genre_id">
                        <option value="">すべてのジャンル</option>
                        @foreach ($genres as $genre)
                            <option value="{{ $genre->id }}" @if(request("genre_id") == $genre->id) selected @endif>
                                {{ $genre->name }}
                            </option>
                        @endforeach
                    </select>
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

> **思考プロセス:**
> - **なぜGETメソッドなのか？**: 検索や絞り込みはサーバー上のリソースを「取得」する操作であり、データを変更するものではありません。このような冪等（べきとう）な操作にはGETメソッドを使用するのがHTTPのセマンティクスとして適切です。GETリクエストでは、パラメータはURLの一部（クエリ文字列 `?keyword=...&genre_id=...`）として送信されるため、ユーザーは検索結果のURLをブックマークしたり、他人に共有したりできます。
> - **`request("keyword")`**: 検索を実行した後も、ユーザーが入力・選択した条件をフォームに表示し続けるのは、UX（ユーザー体験）を向上させるための重要な配慮です。`request()` ヘルパーは、現在のリクエストから指定されたキーの値を取得する便利な関数です。

## 9-2. Controllerでの検索・絞り込みロジックの実装

`BookController`の`index`メソッドを修正し、キーワードやジャンルIDが渡された場合に結果を絞り込むロジックを追加します。

**`app/Http/Controllers/BookController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre; // Genreモデルをインポート
use Illuminate\Http\Request; // Requestをインポート

class BookController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->input("keyword");
        $genreId = $request->input("genre_id");

        $query = Book::query();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where("title", "like", "%{$keyword}%")
                  ->orWhere("author", "like", "%{$keyword}%");
            });
        }

        if ($genreId) {
            $query->whereHas("genres", function ($q) use ($genreId) {
                $q->where("genres.id", $genreId);
            });
        }

        $books = $query->with("genres")->latest()->paginate(10);
        $genres = Genre::all(); // フォーム用に全ジャンルを取得

        return view("books.index", compact("books", "genres"));
    }

    // ... 他のメソッド
}
```

> **思考プロセス (クエリビルディング):**
> - **`Book::query()` から始める**: まずクエリビルダのインスタンスを生成し、条件に応じて `where` 句などを繋げていくことで、複雑な条件分岐にも対応できるクリーンなコードを維持できます。
> - **`whereHas`**: リレーション先のテーブル（`genres`）の条件で絞り込みたい場合に使用します。この例では、「`genres`リレーションの中に、IDが`$genreId`と一致するものが存在する書籍」という条件を指定しています。

## 9-3. ページネーションと検索条件の連携

このままでは、検索結果の2ページ目に移動した際に検索条件が消えてしまいます。ページネーションリンクに検索条件を引き継がせる必要があります。

**`app/Http/Controllers/BookController.php` (indexメソッドの末尾を修正)**
```php
// ...
$books = $query->with("genres")
    ->latest()
    ->paginate(10)
    ->appends($request->all()); // この行を追加（または修正）

$genres = Genre::all();

return view("books.index", compact("books", "genres"));
```

> **コード解説:**
> - `appends($request->all())`: 現在のリクエストに含まれる全てのクエリパラメータ（`keyword`と`genre_id`）を、生成されるページネーションのURLに追加します。これにより、`http://.../books?keyword=...&genre_id=...&page=2` という、検索条件を維持した正しいURLが生成されます。

---

これで、実用的な検索・絞り込み機能が完成しました。次のChapterでは、レビューの平均評価に基づいたランキング機能と、ジャンルの管理機能を実装します。
