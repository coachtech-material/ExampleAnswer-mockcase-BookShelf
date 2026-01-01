# Chapter 5: 発展機能 - 多対多リレーションと集計クエリ

CRUDと親子関係をマスターしたところで、最後により複雑なリレーション「多対多」と、データベースの集計機能を使ったランキングを実装します。

## 5-1. お気に入り機能（多対多リレーション）

「一人のユーザーが複数の書籍をお気に入りでき、一つの書籍は複数のユーザーからお気に入りされる」これは典型的な「多対多」のリレーションです。

### Step 1: M (Model & Migration) - 中間テーブルの役割

多対多リレーションを実現するには、2つのテーブル（`users` と `books`）の橋渡し役となる「中間テーブル」が必要です。添付手順書の「Step 2」にある `create_favorites_table` がそれにあたります。

1.  **マイグレーション**: `create_favorites_table` は `user_id` と `book_id` の2つのカラムしか持ちません。この2つの組み合わせで「誰がどの本をお気に入りしたか」を記録します。`primary(["user_id", "book_id"])` は、同じユーザーが同じ本を二重にお気に入りできないようにするための複合主キー制約です。

2.  **モデルのリレーション**: `User` モデルと `Book` モデルの両方に `belongsToMany` リレーションを定義します。

    ```php
    // app/Models/User.php
    public function favoriteBooks()
    {
        // Userは多くのBookをお気に入りする
        return $this->belongsToMany(Book::class, "favorites");
    }

    // app/Models/Book.php
    public function favoritedByUsers()
    {
        // Bookは多くのUserにお気に入りされる
        return $this->belongsToMany(User::class, "favorites");
    }
    ```

    **思考プロセス**: なぜ `belongsToMany` なのか？
    `hasMany` や `belongsTo` は「親子」のような一方向の関係でしたが、`belongsToMany` は対等な関係です。第二引数に中間テーブルの名前 (`favorites`) を指定するのがポイントです。

### Step 2: C (Controller) & R (Route) - `attach` と `detach`

添付手順書の「Step 7」に従い、`FavoriteController` を作成し、ルートを定義します。

```php
// app/Http/Controllers/FavoriteController.php
public function store(Book $book)
{
    // ログイン中のユーザーのお気に入りに、この書籍を追加する
    Auth::user()->favoriteBooks()->attach($book->id);
    return back();
}

public function destroy(Book $book)
{
    // ログイン中のユーザーのお気に入りから、この書籍を削除する
    Auth::user()->favoriteBooks()->detach($book->id);
    return back();
}
```

**思考プロセス**: `attach` と `detach` が多対多リレーションの肝です。
- `attach($id)`: 中間テーブルに新しいレコード（関連）を追加します。
- `detach($id)`: 中間テーブルからレコード（関連）を削除します。
- `sync([$id1, $id2])`: 配列で渡されたIDだけが中間テーブルに残るように、追加・削除を自動で行います。（書籍編集のジャンル更新で使われています）
- `toggle($id)`: あれば `detach`、なければ `attach` を自動で切り替えてくれます。「いいね」機能でこの `toggle` を使うと、さらにコードが簡潔になります。

### Step 3: V (View) - 状態の表示

書籍詳細ページ (`books/show.blade.php`) で、お気に入り状態に応じてボタンを出し分けます。

**Bladeの読み解き方**:

```blade
@if (Auth::user()->favoriteBooks()->where("book_id", $book->id)->exists())
    {{-- お気に入り解除ボタン --}}
    <form action="{{ route("favorites.destroy", $book) }}" method="POST">
        @csrf
        @method("DELETE")
        <button type="submit">お気に入り解除</button>
    </form>
@else
    {{-- お気に入り登録ボタン --}}
    <form action="{{ route("favorites.store", $book) }}" method="POST">
        @csrf
        <button type="submit">お気に入り登録</button>
    </form>
@endif
```

`Auth::user()->favoriteBooks()->where("book_id", $book->id)->exists()` で、ログイン中のユーザーのお気に入り書籍の中に、今見ている書籍IDが存在するかどうかをチェックしています。存在すれば「解除ボタン」、しなければ「登録ボタン」を表示します。

## 5-2. ランキング機能（集計クエリ）

最後に、レビューの平均評価点が高い順に書籍を並べるランキング機能を実装します。

### Step 1: C (Controller) - DB Facadeと集計関数

添付手順書の「Step 9」に従い、`RankingController` を作成します。

```php
// app/Http/Controllers/RankingController.php
use Illuminate\Support\Facades\DB; // DBファサードをインポート

public function index()
{
    $rankedBooks = Book::select("books.*", DB::raw("AVG(reviews.rating) as average_rating"))
        ->join("reviews", "books.id", "=", "reviews.book_id")
        ->groupBy("books.id")
        ->orderByDesc("average_rating")
        ->take(10)
        ->get();

    return view("ranking.index", compact("rankedBooks"));
}
```

**思考プロセス**: この複雑なクエリは何をしているのか？
- `join("reviews", ...)`: `books` テーブルと `reviews` テーブルを内部結合します。
- `groupBy("books.id")`: 書籍ごとにグループ化します。これをしないと、レビューの数だけ同じ本が結果に現れてしまいます。
- `DB::raw("AVG(reviews.rating) as average_rating")`: グループ化した各書籍に対して、`reviews` テーブルの `rating` カラムの平均値を計算し、`average_rating` という別名を付けています。`DB::raw()` は、SQLの生クエリを埋め込むための記述です。
- `orderByDesc("average_rating")`: 計算した平均評価点の高い順に並べ替えます。
- `take(10)`: 上位10件のみを取得します。

Eloquentの `withAvg` を使うと、より簡潔に書くこともできます。

```php
$rankedBooks = Book::withAvg("reviews", "rating")
    ->orderByDesc("reviews_avg_rating")
    ->take(10)
    ->get();
```

### Step 2: V (View) - 結果の表示

`ranking/index.blade.php` で、コントローラから渡された `$rankedBooks` をループで表示します。

```blade
@foreach($rankedBooks as $book)
    <li>
        <a href="{{ route("books.show", $book) }}">{{ $book->title }}</a>
        {{-- number_formatで小数点以下2桁まで表示 --}}
        {{ number_format($book->average_rating, 2) }} ★
    </li>
@endforeach
```

`$book->average_rating` で、コントローラで計算した平均評価点を表示できます。

これで全ての基本機能が完成しました！お疲れ様でした。この教材を通して、要件定義の曖昧な部分をどうやって固め、それをどのように堅牢なコードに落とし込んでいくか、その一連の思考プロセスを学べたはずです。
