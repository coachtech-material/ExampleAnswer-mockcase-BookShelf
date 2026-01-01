'''# Chapter 9: 検索機能

このChapterでは、書籍のタイトルや著者名で検索できる機能を実装します。GETリクエストでクエリパラメータを受け取り、それに基づいてデータベースを検索する、Webアプリケーションの基本的な機能です。

## 9-1. 検索フォームの作成

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

> **思考プロセス:**
> - **なぜGETメソッドなのか？**: 検索はサーバー上のリソースを「取得」する操作であり、データを変更するものではありません。このような冪等（べきとう）な操作にはGETメソッドを使用するのがHTTPのセマンティクスとして適切です。GETリクエストでは、パラメータはURLの一部（クエリ文字列 `?keyword=...`）として送信されるため、ユーザーは検索結果のURLをブックマークしたり、他人に共有したりできます。
> - **`request('keyword')`**: 検索を実行した後も、ユーザーが入力したキーワードを検索ボックスに表示し続けるのは、UX（ユーザー体験）を向上させるための重要な配慮です。`request()` ヘルパーは、現在のリクエストから指定されたキーの値を取得する便利な関数です。

## 9-2. Controllerでの検索ロジックの実装

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

> **思考プロセス (クエリビルディング):**
> - **`Book::query()` から始める**: 最初から `Book::where(...)` と書くのではなく、まずクエリビルダのインスタンス `Book::query()` を生成し、変数 (`$query`) に格納します。そして、条件 (`if ($keyword)`) に応じてその変数に `where` 句を繋げていきます。このアプローチにより、条件分岐が複雑になっても、コードの構造をクリーンに保つことができます。
> - **クロージャによる `where` のグループ化**: `where(function ($q) { ... })` を使うことで、`WHERE (title LIKE ? OR author LIKE ?)` というSQL文を生成できます。もしこれをグループ化しないと `WHERE title LIKE ? OR author LIKE ? AND ...` のようになり、意図しない検索結果になる可能性があります。複数の`OR`条件をまとめる際の定石です。
> - **`%` (ワイルドカード)**: `"%{$keyword}%"` は、キーワードの前後に任意の文字列が存在することを許容する「部分一致」検索を意味します。

## 9-3. ページネーションと検索キーワードの連携

このままでは、検索結果の2ページ目に移動した際に検索キーワードが消えてしまい、全件表示の2ページ目に移動してしまいます。ページネーションリンクに検索キーワードを引き継がせる必要があります。

### Step 1: `Book`モデルに`scope`を定義 (推奨)

検索ロジックをControllerからモデルの`scope`に移動させることで、Controllerをよりクリーンに保ち、ロジックを再利用しやすくします。

**`app/Models/Book.php`**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder; // Builderをインポート
// ...

class Book extends Model
{
    // ...

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

> **思考プロセス (ロジックの再利用性):**
> 「キーワードで検索する」というロジックは、このアプリケーションの他の場所でも必要になるかもしれません。このロジックを`scopeSearch`としてモデルに定義しておくことで、`Book::search($keyword)->...` のように、どこからでも直感的かつ一貫した方法で呼び出すことができます。Controllerは「何をするか」を簡潔に記述し、「どのようにするか」という具体的な実装はモデルに委ねる、という責務の分離が実現できます。

### Step 2: Controllerとページネーションの修正

`paginate`メソッドの結果に`appends`メソッドをチェーンすることで、ページネーションのリンクに現在のクエリパラメータを自動的に付与できます。

**`app/Http/Controllers/BookController.php`**
```php
public function index(Request $request)
{
    $keyword = $request->input('keyword');

    $books = Book::with('genres')
        ->search($keyword) // scopeを利用
        ->latest()
        ->paginate(10)
        ->appends($request->all()); // この行を追加

    return view('books.index', compact('books'));
}
```

> **コード解説:**
> - `appends($request->all())`: 現在のリクエストに含まれる全てのクエリパラメータ（この場合は `['keyword' => '...']`）を、生成されるページネーションのURL（例: `/books?page=2`）に追加します。これにより、`http://.../books?keyword=...&page=2` という、検索条件を維持した正しいURLが生成されます。

## 9-4. 動作確認

1.  書籍一覧ページに検索フォームが表示されていることを確認します。
2.  キーワードを入力して検索し、タイトルまたは著者にそのキーワードが含まれる書籍のみが表示されることを確認します。
3.  検索結果が複数ページにわたる場合、2ページ目に移動しても検索結果が維持されていることを確認します。（URLに`keyword`と`page`の両方が含まれていることを確認）
4.  キーワードを空にして検索すると、全件が表示されることを確認します。

---

これで、実用的な検索機能が完成しました。次のChapterでは、レビューの平均評価に基づいたランキング機能を実装します。'''
