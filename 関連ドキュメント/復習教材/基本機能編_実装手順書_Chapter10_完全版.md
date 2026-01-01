'''
# Chapter 10: 検索機能（リファクタリングとローカルスコープ）

このChapterでは、書籍のタイトルや著者名で検索できる機能を実装します。単純な実装から始め、より再利用性が高く、洗練されたコードへとリファクタリングするプロセスを通じて、Laravelの強力な機能である「ローカルスコープ」を学びます。

---

## 10-1. 先輩エンジニアの思考プロセス：DRY原則とリファクタリング

### Step 1: まずは素直に実装してみる（素振り）

**要件**: 「書籍のタイトルや著者名で検索できる」

この要件を最もシンプルに実装すると、以下のようになります。

1.  `BookController`に`search`メソッドを追加する。
2.  `search`メソッド内で、`where('title', 'like', ...)`と`orWhere('author', 'like', ...)`を使って検索クエリを構築する。
3.  結果を`books.index`ビューに渡して表示する。

```php
// BookController.php (最初の実装案)
public function search(Request $request): View
{
    $query = $request->input("query");
    $books = Book::where("title", "like", "%{$query}%")
        ->orWhere("author", "like", "%{$query}%")
        ->with("genres")
        ->latest()
        ->paginate(10);

    // 一覧表示と同じビューを使い回す
    return view("books.index", compact("books", "query"));
}
```

### Step 2: コードの「悪い匂い」を嗅ぎつける

一見、このコードは正しく動作します。しかし、経験豊富なエンジニアは、ここに「悪い匂い」を感じ取ります。

> **先輩エンジニアの思考:**
> 「待てよ、`search`メソッドと`index`メソッドのコードがすごく似ているな。`with("genres")->latest()->paginate(10)`の部分が完全に重複している。表示するビューも同じ`books.index`だ。これは**DRY原則（Don't Repeat Yourself - 同じことを繰り返すな）**に反している。将来、ページネーションの件数を10件から15件に変更したいと思ったら、`index`と`search`の両方を修正しなければならない。これはバグの温床だ。」

### Step 3: リファクタリング方針を立てる

このコードの重複をどう解消するか？

> **先輩エンジニアの思考:**
> 「`search`は、`index`（書籍一覧）に**『絞り込み条件が加わったもの』**と考えることができる。ならば、`index`メソッドに検索ロジックを統合してしまえばいい。リクエストに検索キーワード(`query`)があれば絞り込み、なければ全件表示する、という条件分岐を`index`メソッド内に作ろう。」

さらに、検索ロジックをより再利用しやすくするために、Laravelの**ローカルスコープ**という機能を使います。これは、特定のクエリ条件をモデルにメソッドとして定義しておける機能です。

**リファクタリング方針:**
1.  `Book`モデルに、検索ロジックをカプセル化した`scopeSearch`メソッド（ローカルスコープ）を定義する。
2.  `BookController`の`index`メソッドを修正し、リクエストに`query`があれば`search`スコープを適用するように変更する。
3.  不要になった`search`メソッドと、それに対応するルート(`books.search`)は削除する。

このリファクタリングにより、コントローラーはスリムになり、検索ロジックはモデルに集約され、コードの再利用性と可読性が大幅に向上します。

---

## 10.2. 実装とリファクタリング

### 1. 検索フォームの追加 (`resources/views/books/index.blade.php`)

まず、書籍一覧ページに検索フォームを設置します。

```html
<x-app-layout>
    <x-slot name="header">
        <h2>書籍一覧</h2>
        <!-- 検索フォーム -->
        <form action="{{ route('books.index') }}" method="GET">
            <input type="text" name="query" value="{{ $query ?? '' }}" placeholder="タイトルや著者名で検索...">
            <button type="submit">検索</button>
        </form>
    </x-slot>

    <!-- 書籍一覧の表示 (省略) -->
</x-app-layout>
```

> **【学習のポイント】**
> - `action`の送信先が`route('books.index')`になっていることに注目してください。検索も一覧表示も、同じ`index`アクションで処理します。
> - `value="{{ $query ?? '' }}"`とすることで、検索後も入力したキーワードが検索ボックスに残り、ユーザー体験が向上します。

### 2. ローカルスコープの実装 (`app/Models/Book.php`)

`Book`モデルに、検索ロジックをまとめたローカルスコープを追加します。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder; // Builderをインポート
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    // ... 既存のリレーションなど

    /**
     * タイトルと著者名で検索するローカルスコープ
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string|null $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch(Builder $builder, ?string $query): Builder
    {
        if ($query) {
            return $builder->where('title', 'like', "%{$query}%")
                         ->orWhere('author', 'like', "%{$query}%");
        }
        return $builder;
    }
}
```

> **【学習のポイント】**
> - ローカルスコープのメソッド名は、`scope`で始まり、キャメルケースで続きます（例: `scopeSearch`）。
> - 呼び出すときは、`scope`を除いた名前で呼び出します（例: `Book::search(...)`）。
> - 第1引数には必ず`Builder`インスタンスが渡され、これにクエリ条件を繋げていくことでスコープを実現します。

### 3. コントローラーのリファクタリング (`app/Http/Controllers/BookController.php`)

`index`メソッドを修正し、`search`スコープを使って検索機能を取り込みます。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request; // Requestをインポート
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->input('query');

        $books = Book::query()
            ->search($query) // 作成したローカルスコープを呼び出す
            ->with("genres")
            ->latest()
            ->paginate(10)
            ->appends($request->query()); // ページネーションリンクにクエリ文字列を引き継がせる

        return view("books.index", compact("books", "query"));
    }

    // ... 他のメソッド

    // searchメソッドは不要になったので削除する
}
```

> **【学習のポイント】**
> - `->appends($request->query())`: これは非常に重要な処理です。これを付けないと、検索結果の2ページ目に移動した際に検索キーワードがURLから消えてしまい、全件表示の2ページ目に移動してしまいます。`appends`は、ページネーションのリンクに現在のクエリ文字列（`?query=...`の部分）を自動で付与してくれます。

### 4. ルーティングの整理 (`routes/web.php`)

`BookController`から`search`メソッドを削除したので、対応するルートも削除します。検索は`books.index`ルートで処理されるようになりました。

```php
// 以下のルートは不要になったので削除する
// Route::get('/books/search', [BookController::class, 'search'])->name('books.search');
```

---

## 10.3. 動作確認

1.  `/books`にアクセスし、書籍一覧ページに検索フォームが表示されていることを確認します。
2.  キーワードを入力して検索し、タイトルまたは著者名にキーワードが含まれる書籍のみが表示されることを確認します。
3.  検索結果が10件以上ある場合、ページネーションのリンクをクリックしても検索キーワードが維持され、正しく次のページが表示されることを確認します。
4.  検索ボックスを空にして検索すると、全件が表示されることを確認します。

これで、DRY原則に則った、より洗練された検索機能の実装が完了しました。
'''
