'''
# Chapter 7: お気に入り機能

このChapterでは、ユーザーが特定の書籍を「お気に入り」としてブックマークできる機能を実装します。これは、`users`テーブルと`books`テーブルを中間テーブル`favorites`で繋ぐ、「多対多」リレーションシップの典型的な実装例です。

---

## 7-1. 先輩エンジニアの思考プロセス：多対多リレーションのCRUD

### Step 1: 要件を「状態の変更」として捉える

お気に入り機能の要件は非常にシンプルです。

- **要件**: 「書籍をお気に入りに登録、またはお気に入りから解除できる」「自分がお気に入りに登録した書籍を一覧表示できる」

これをエンジニアの視点で分解すると、以下のようになります。

- **登録**: `favorites`テーブルに、`user_id`と`book_id`のペアの**レコードを追加**する。
- **解除**: `favorites`テーブルから、`user_id`と`book_id`のペアの**レコードを削除**する。
- **一覧**: `favorites`テーブルを`user_id`で検索し、紐づく`book_id`の書籍情報を取得する。

> **先輩エンジニアの思考:**
> 「お気に入り機能は、新しいデータ（書籍やレビューのような）を作成するのではなく、既存のユーザーと書籍の間に『関係性（リレーション）』を追加したり削除したりするだけだ。これは、中間テーブルのレコードを操作することに他ならない。LaravelのEloquentには、この多対多リレーションの操作を非常に簡単にするための便利なメソッド（`attach`, `detach`, `sync`など）が用意されている。これらを適切に使い分けるのが実装の鍵だ。」

### Step 2: Eloquentの便利メソッドを選択する

多対多リレーションの操作には、主に3つのメソッドがあります。どれを使うのが最も意図に合っているかを考えます。

| メソッド | 処理内容 | ユースケース |
|:---|:---|:---|
| `attach($id)` | 中間テーブルにレコードを追加する。既に存在する場合は**エラー**になる（重複登録される場合もある）。 | 厳密に一意性を保ちたい場合。 |
| `detach($id)` | 中間テーブルからレコードを削除する。 | 関連を解除する。 |
| `sync($ids)` | 配列で渡されたIDのみが中間テーブルに存在する状態にする。既存の関連は全て削除され、新しい関連が登録される。 | チェックボックスなどで複数の関連を一括更新する場合に便利。 |
| `syncWithoutDetaching($id)` | `attach`と似ているが、既にレコードが存在していてもエラーにならず、単に無視される。 | 「とりあえず追加しておきたい」という場合に非常に便利。 |

> **先輩エンジニアの思考:**
> 「お気に入り登録ボタンは、ユーザーが何回も押す可能性がある。そのたびに『既にお気に入り登録済みです』とエラーを出すのはUXが悪い。かといって、`attach`の前に毎回『既にお気に入り済みか？』をチェックするのも冗長だ。こういうケースでは`syncWithoutDetaching`が最適解。重複を気にせず『この本をお気に入りに追加する』という命令を実行できる。解除はシンプルに`detach`で良い。」

---

## 7.2. 部品の作成と実装

### 1. コントローラーの作成

```bash
sail artisan make:controller FavoriteController
```

### 2. ルーティングの定義 (`routes/web.php`)

お気に入り操作のルートを`middleware('auth')`グループ内に追加します。

```php
// Favorite management
// お気に入り登録
Route::post('/books/{book}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
// お気に入り解除
Route::delete('/books/{book}/unfavorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
// お気に入り一覧
Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
```

> **【学習のポイント】**
> 登録は`POST`、解除は`DELETE`と、HTTPメソッドを使い分けることで、同じようなURLでも異なる操作を表現しています。これがRESTfulな設計の基本です。（`unfavorite`という名前は厳密にはRESTfulではありませんが、分かりやすさを優先しています）

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
        // 要件：自分がお気に入りに登録した書籍を一覧表示する。
        // 思考：ログインユーザーの`favoriteBooks`リレーションを呼び出し、ページネーションを適用するだけ。
        $books = Auth::user()->favoriteBooks()->paginate(10);
        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入り登録処理
     */
    public function store(Request $request, Book $book): RedirectResponse
    {
        // 要件：書籍をお気に入りに登録する。
        // 思考：重複を気にせず追加できる`syncWithoutDetaching`が最適。
        $request->user()->favoriteBooks()->syncWithoutDetaching($book->id);

        // 元の画面に戻る
        return back()->with('success', 'お気に入りに追加しました。');
    }

    /**
     * お気に入り解除処理
     */
    public function destroy(Request $request, Book $book): RedirectResponse
    {
        // 要件：書籍をお気に入りから解除する。
        // 思考：シンプルに`detach`で関連を削除する。
        $request->user()->favoriteBooks()->detach($book->id);

        return back()->with('success', 'お気に入りを解除しました。');
    }
}
```

---

## 7.3. ビューの実装

### 書籍詳細ページへのボタン追加 (`resources/views/books/show.blade.php`)

ユーザーがお気に入り登録しているかどうかに応じて、「お気に入り登録」ボタンと「お気に入り解除」ボタンを出し分けます。

```html
@auth
    @if (Auth::user()->favoriteBooks->contains($book))
        <!-- お気に入り解除ボタン -->
        <form action="{{ route('favorites.destroy', $book) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit">お気に入り解除</button>
        </form>
    @else
        <!-- お気に入り登録ボタン -->
        <form action="{{ route('favorites.store', $book) }}" method="POST">
            @csrf
            <button type="submit">お気に入り登録</button>
        </form>
    @endif
@endauth
```

> **【学習のポイント】**
> `Auth::user()->favoriteBooks`は、ユーザーがお気に入りに登録している`Book`モデルのコレクション（配列のようなもの）を返します。`->contains($book)`は、そのコレクションの中に今表示している`$book`が含まれているかを判定するメソッドです。これにより、簡単にお気に入り状態をチェックできます。

### お気に入り一覧画面 (`resources/views/favorites/index.blade.php`)

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
'''
