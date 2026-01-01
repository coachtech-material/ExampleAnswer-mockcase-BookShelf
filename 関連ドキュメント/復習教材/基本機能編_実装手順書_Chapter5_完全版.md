# Chapter 5: その他機能 - 多対多リレーションと集計クエリ

最後の機能実装として、お気に入り、いいね、ジャンル管理、ランキングといった、アプリケーションをより豊かにする機能を実装します。ここでは、**多対多リレーション**の扱いや、**集計クエリ**の使い方が主なテーマとなります。

## 5-1. お気に入り機能 (多対多リレーション)

ユーザーが複数の書籍をお気に入り登録でき、一つの書籍は複数のユーザーからお気に入り登録される。これは典型的な**多対多（Many-to-Many）**の関係です。

### 1. モデルとリレーション

この関係を表現するために、`users`テーブルと`books`テーブルの間に`favorites`という**中間テーブル**が必要です（Chapter 1で作成済み）。

-   `User`モデル (`app/Models/User.php`)
    -   `favoriteBooks()`: `belongsToMany(Book::class, \'favorites\')`
-   `Book`モデル (`app/Models/Book.php`)
    -   `favoritedByUsers()`: `belongsToMany(User::class, \'favorites\')`

`belongsToMany`の第二引数に中間テーブル名を指定します。

### 2. ルーティング

お気に入りの登録と解除は、同じボタン（またはリンク）で行われることが多いため、HTTPメソッドで処理を分けます。

```php
// routes/web.php

use App\Http\Controllers\FavoriteController;

// ...

Route::middleware("auth")->group(function () {
    // ...

    // お気に入り機能のルート
    Route::post("/books/{book}/favorite"), [FavoriteController::class, "store"])->name("favorites.store");
    Route::delete("/books/{book}/favorite"), [FavoriteController::class, "destroy"])->name("favorites.destroy");
});
```

### 3. コントローラ

```bash
sail artisan make:controller FavoriteController
```

```php
// app/Http/Controllers/FavoriteController.php

use App\Models\Book;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function store(Book $book)
    {
        // ログインユーザーのお気に入り書籍に、この書籍を追加する
        Auth::user()->favoriteBooks()->attach($book->id);
        return back()->with("success", "お気に入りに追加しました。");
    }

    public function destroy(Book $book)
    {
        // ログインユーザーのお気に入り書籍から、この書籍を解除する
        Auth::user()->favoriteBooks()->detach($book->id);
        return back()->with("success", "お気に入りを解除しました。");
    }
}
```

-   `attach($id)`: 多対多リレーションで、中間テーブルに新しいレコードを追加します。
-   `detach($id)`: 中間テーブルからレコードを削除します。
-   `back()`: 直前のページにリダイレクトするヘルパー関数です。どのページから操作されても対応できるため便利です。

### 4. Bladeの読み解きと実装

`show.blade.php`には、お気に入り状態に応じてボタンを切り替えるロジックが必要です。

```php
@if (Auth::user()->favoriteBooks()->where(\'book_id\', $book->id)->exists())
    {{-- お気に入り解除ボタン --}}
    <form action="{{ route(\'favorites.destroy\", $book) }}" method="POST">
        @csrf
        @method(\'DELETE\')
        <button type="submit">お気に入り解除</button>
    </form>
@else
    {{-- お気に入り登録ボタン --}}
    <form action="{{ route(\'favorites.store\", $book) }}" method="POST">
        @csrf
        <button type="submit">お気に入り登録</button>
    </form>
@endif
```

-   `Auth::user()->favoriteBooks()->where(\'book_id\', $book->id)->exists()`: ログインユーザーのお気に入り書籍の中に、現在の書籍IDが存在するかどうかをチェックしています。これにより、お気に入り済みかどうかを判定できます。

## 5-2. いいね機能 (多対多リレーション)

レビューに対する「いいね」機能も、お気に入りと全く同じ構造の多対多リレーションです。`users`と`reviews`の間に`review_likes`という中間テーブルを使います。実装方法はお気に入り機能とほぼ同じなので、練習として自分で実装してみましょう。

**ヒント**: `toggle`メソッド
`attach`と`detach`を kondisi分岐で使い分ける代わりに、`toggle`メソッドを使うとさらにコードが簡潔になります。`toggle`は、中間テーブルにレコードが存在すれば削除し、存在しなければ追加するという便利なメソッドです。

```php
// 例：FavoriteControllerをtoggleで書き換える
public function toggle(Book $book)
{
    $user = Auth::user();
    $user->favoriteBooks()->toggle($book->id);

    // メッセージを動的に変更する
    $isFavorited = $user->favoriteBooks()->where(\'book_id\', $book->id)->exists();
    $message = $isFavorited ? "お気に入りに追加しました。" : "お気に入りを解除しました。";

    return back()->with("success", $message);
}
```

## 5-3. ジャンル管理機能 (シンプルなCRUD)

書籍に紐付けるジャンルを管理する機能です。これはChapter 3で実装した書籍管理機能の簡易版と言えます。`GenreController`を作成し、`index`, `create`, `store`, `edit`, `update`, `destroy`を実装します。

**ポイント**: `destroy`アクション
ジャンルを削除する際に、そのジャンルに紐付いている書籍が一つでも存在する場合は、削除できないようにすべきです。これは**データ整合性**の観点から非常に重要です。

```php
// app/Http/Controllers/GenreController.php

public function destroy(Genre $genre)
{
    // このジャンルに紐づく書籍が存在するかチェック
    if ($genre->books()->exists()) {
        return back()->with("error", "このジャンルには書籍が紐付いているため削除できません。");
    }

    $genre->delete();
    return redirect()->route("genres.index")->with("success", "ジャンルを削除しました。");
}
```

## 5-4. ランキング機能 (集計クエリ)

最後に、レビューの平均評価点が高い順に書籍をランキング表示する機能を実装します。

### 1. ルーティング

```php
// routes/web.php
use App\Http\Controllers\RankingController;

Route::get("/ranking"), [RankingController::class, "index"])->name("ranking.index");
```

### 2. コントローラ

```bash
sail artisan make:controller RankingController
```

```php
// app/Http/Controllers/RankingController.php

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;

class RankingController extends Controller
{
    public function index()
    {
        $rankedBooks = Book::query()
            // reviewsリレーションが存在する書籍のみを対象
            ->whereHas("reviews")
            // reviewsリレーションをロードし、そのratingカラムの平均値を計算して`reviews_avg_rating`として取得
            ->withAvg("reviews", "rating")
            // 平均評価点の降順でソート
            ->orderByDesc("reviews_avg_rating")
            // 上位10件を取得
            ->take(10)
            ->get();

        return view("ranking.index", compact("rankedBooks"));
    }
}
```

-   `whereHas("reviews")`: `reviews`リレーションに少なくとも1つのレコードが存在する`Book`モデルのみを絞り込みます。レビューが1件もない書籍はランキングに表示されません。
-   `withAvg("reviews", "rating")`: Eloquentの強力な集計機能です。リレーション先のテーブル（`reviews`）の特定のカラム（`rating`）の平均値を計算し、`{relation}_avg_{column}`（この場合は`reviews_avg_rating`）という名前の属性としてモデルに追加してくれます。
-   `orderByDesc("reviews_avg_rating")`: `withAvg`で計算された平均評価点で降順ソートします。

## まとめ

おめでとうございます！これで基本機能編のすべての機能実装が完了しました。

このChapterでは、
-   **多対多リレーション**: `belongsToMany`と中間テーブルの概念、`attach`, `detach`, `toggle`メソッドの使い方を学びました。
-   **データ整合性**: 関連データが存在する場合の削除処理の注意点を学びました。
-   **集計クエリ**: `whereHas`や`withAvg`といったEloquentの高度な機能を使って、複雑な集計やランキングを簡単に実装する方法を学びました。

次のステップとして、これらの機能を応用した「応用機能編」に挑戦したり、自分で考えた新しい機能を追加してみるのも良いでしょう。Happy Coding!
