
# Chapter 7: お気に入り機能

このChapterでは、ユーザーが特定の書籍を「お気に入り」としてブックマークできる機能を実装します。これは、`users`テーブルと`books`テーブルを中間テーブル`favorites`で繋ぐ、「多対多」リレーションシップの典型的な実装例です。

---

## 7-1. 先輩エンジニアの思考プロセス：多対多リレーションのトグル処理

### Step 1: 要件を「状態の反転」として捉える

お気に入り機能の要件は非常にシンプルです。

- **要件**: 「書籍をお気に入りに登録、またはお気に入りから解除できる」「自分がお気に入りに登録した書籍を一覧表示できる」

これをエンジニアの視点で分解すると、以下のようになります。

- **登録**: `favorites`テーブルに、`user_id`と`book_id`のペアの**レコードを追加**する。
- **解除**: `favorites`テーブルから、`user_id`と`book_id`のペアの**レコードを削除**する。

> **先輩エンジニアの思考:**
> 「お気に入りボタンを押すたびに、登録と解除が切り替わる（トグルする）のが一般的なUIだ。つまり、『すでにお気に入り済みなら解除、まだなら登録する』という単一のアクションとして実装するのがスマートだ。これを`toggle`メソッドとして実装しよう。コントローラーのロジックが一つにまとまり、ルーティングもシンプルになる。」

### Step 2: Eloquentの便利メソッドを選択する

このトグル処理を実装するために、Eloquentのメソッドを組み合わせます。

| メソッド | 処理内容 | ユースケース |
|:---|:---|:---|
| `attach($id)` | 中間テーブルにレコードを追加する。 | 関連を新規追加する。 |
| `detach($id)` | 中間テーブルからレコードを削除する。 | 関連を解除する。 |
| `exists()` | 条件に合うレコードが存在するかを`true`/`false`で返す。 | 関連が既に存在するかをチェックする。 |

> **先輩エンジニアの思考:**
> 「まず、`$user->favoriteBooks()->where('book_id', $book->id)->exists()` で、既にお気に入り登録済みかを確認する。もし`true`なら`detach()`を呼び出して解除、`false`なら`attach()`を呼び出して登録する。これで`toggle`処理が完成だ。」

---

## 7.2. 部品の作成と実装

### 1. コントローラーの作成

```bash
sail artisan make:controller FavoriteController
```

```bash
sail artisan make:controller FavoriteController
```

```bash
sail artisan make:controller FavoriteController
```

### 2. ルーティングの定義 (`routes/web.php`)

お気に入り操作のルートを`middleware('auth')`グループ内に追加します。

```php
// Favorite management
// お気に入り一覧
Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
// お気に入り登録/解除 (トグル)
Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
```

> **【学習のポイント】**
> 登録も解除も同じ`POST /books/{book}/favorite`というURLで受け付けます。コントローラー側で状態を判別して処理を切り替えるため、ルート定義が一つで済みます。

### 3. コントローラーの実装 (`FavoriteController.php`)

`User`モデルに定義した`favoriteBooks()`リレーションを活用して実装します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧表示
     */
    public function index(): View
    {
        $books = Auth::user()->favoriteBooks()->paginate(10);
        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入り登録/解除処理 (トグル)
     */
    public function toggle(Book $book): RedirectResponse
    {
        $user = Auth::user();

        // 既にお気に入り登録されているかチェック
        if ($user->favoriteBooks()->where('book_id', $book->id)->exists()) {
            // 登録済みなら解除
            $user->favoriteBooks()->detach($book->id);
            $message = 'お気に入りを解除しました。';
        } else {
            // 未登録なら登録
            $user->favoriteBooks()->attach($book->id);
            $message = 'お気に入りに追加しました。';
        }

        return back()->with('success', $message);
    }
}
```

---

## 7.3. ビューの実装

### 書籍詳細ページへのボタン追加 (`resources/views/books/show.blade.php`)

ユーザーがお気に入り登録しているかどうかに応じて、ボタンの表示やフォームのアクションを動的に変更します。

```html
@auth
    <form action="{{ route('favorites.toggle', $book) }}" method="POST">
        @csrf
        @if (Auth::user()->favoriteBooks->contains($book))
            <button type="submit">お気に入り解除</button>
        @else
            <button type="submit">お気に入り登録</button>
        @endif
    </form>
@endauth
```

> **【学習のポイント】**
> `Auth::user()->favoriteBooks`は、ユーザーがお気に入りに登録している`Book`モデルのコレクションです。`->contains($book)`で、そのコレクションの中に今表示している`$book`が含まれているかを判定し、ボタンの文言を出し分けています。

### お気に入り一覧画面 (`resources/views/favorites/index.blade.php`)

まず、必要なディレクトリと空のファイルを作成します。

```bash
# ディレクトリを作成
mkdir -p resources/views/favorites

# 空のファイルを作成
touch resources/views/favorites/index.blade.php
```

書籍一覧画面とほぼ同じ構成です。コントローラーから渡された`$books`をループして表示します。

まず、必要なディレクトリと空のファイルを作成します。

```bash
# ディレクトリを作成
mkdir -p resources/views/favorites

# 空のファイルを作成
touch resources/views/favorites/index.blade.php
```

書籍一覧画面とほぼ同じ構成です。コントローラーから渡された`$books`をループして表示します。

書籍一覧画面とほぼ同じ構成です。コントローラーから渡された`$books`をループして表示します。

```html
<x-app-layout>
    <x-slot name="header">お気に入り一覧</x-slot>

    <div>
        @if ($books->count())
            <div>
                @foreach ($books as $book)
                    <div>
                        <a href="{{ route('books.show', $book) }}">
                            <h2>{{ $book->title }}</h2>
                            <p>{{ $book->author }}</p>
                        </a>
                    </div>
                @endforeach
            </div>
            {{ $books->links() }}
        @else
            <p>お気に入りに登録されている書籍はありません。</p>
        @endif
    </div>
</x-app-layout>
```

---

## 7.4. 動作確認

1.  書籍詳細ページで「お気に入り登録」ボタンが表示されることを確認します。
2.  ボタンをクリックすると、「お気に入り解除」ボタンに切り替わることを確認します。
3.  ヘッダーなどから「お気に入り一覧」ページ (`/favorites`) にアクセスし、登録した書籍が表示されていることを確認します。
4.  「お気に入り解除」ボタンをクリックし、一覧から書籍が消えることを確認します。

これで、お気に入り機能の実装が完了しました。
