## Chapter 5: アプリケーションに彩りを - 多対多リレーションの活用

### はじめに

CRUDという基本的な骨格が完成しました。この最後の機能実装Chapterでは、アプリケーションをさらに魅力的で使いやすくするための追加機能、「お気に入り」と「いいね」を実装します。これらの機能は、どちらも**多対多リレーション**の良い練習問題です。

- **お気に入り**: 1人のユーザーは、複数の書籍をお気に入りに登録できる。1冊の書籍は、複数のユーザーからお気に入りに登録される。
- **いいね**: 1人のユーザーは、複数のレビューにいいねできる。1つのレビューは、複数のユーザーからいいねされる。

これらの関係性を、Eloquentのリレーションを駆使してスマートに実装する方法を学びましょう。

---

### Section 1: お気に入り機能 - "好き"を集める

#### 1.1. データベースとモデルの準備

ユーザーと書籍の多対多関係を表現するための中間テーブル`favorites`が必要です。マイグレーションを作成しましょう。

```bash
sail artisan make:migration create_favorites_table
```

`up`メソッドを編集します。

```php
// database/migrations/..._create_favorites_table.php

public function up(): void
{
    Schema::create("favorites", function (Blueprint $table) {
        $table->foreignId("user_id")->constrained()->onDelete("cascade");
        $table->foreignId("book_id")->constrained()->onDelete("cascade");
        $table->primary(["user_id", "book_id"]); // 複合主キー
    });
}
```

データベースに反映します。

```bash
sail artisan migrate
```

次に、`User`モデルと`Book`モデルに、この中間テーブルを介したリレーションを定義します。

**`app/Models/User.php`**
```php
// ...

// Userがお気に入りにしたBook (多対多)
public function favoriteBooks()
{
    return $this->belongsToMany(Book::class, "favorites")->withTimestamps();
}
```

**`app/Models/Book.php`**
```php
// ...

// Bookをお気に入りにしたUser (多対多)
public function favoritedByUsers()
{
    return $this->belongsToMany(User::class, "favorites")->withTimestamps();
}
```

#### 1.2. お気に入り登録/解除機能の実装

お気に入り機能の面白いところは、「登録」と「解除」が同じボタンで行われる「トグル」動作である点です。Eloquentには、このトグル動作を1行で実現できる`toggle`メソッドが用意されています。

1.  **コントローラの作成とルーティング**

    ```bash
    sail artisan make:controller FavoriteController
    ```

    `routes/web.php`にルートを追加します。

    ```php
    // routes/web.php
    use App\Http\Controllers\FavoriteController;

    Route::middleware("auth")->group(function () {
        // ...
        Route::post("/favorites/{book}/toggle", [FavoriteController::class, "toggle")->name("favorites.toggle");
    });
    ```

2.  **コントローラの実装 (`FavoriteController@toggle`)**

    ```php
    // app/Http/Controllers/FavoriteController.php

    use App\Models\Book;
    use Illuminate\Support\Facades\Auth;

    class FavoriteController extends Controller
    {
        public function toggle(Book $book)
        {
            // ログインユーザーのfavoriteBooksリレーションを介して、
            // 中間テーブルのレコードをアタッチ（なければ）or デタッチ（あれば）する
            Auth::user()->favoriteBooks()->toggle($book->id);

            return back(); // 直前のページにリダイレクト
        }
    }
    ```

    たったこれだけです！`toggle`メソッドが、中間テーブルにレコードが存在するかを自動でチェックし、なければ作成（`attach`）、あれば削除（`detach`）してくれます。

3.  **ビューへのボタン設置**
    書籍一覧ページ（`index.blade.php`）や詳細ページ（`show.blade.php`）にボタンを設置します。

    ```blade
    <form action="{{ route("favorites.toggle", $book) }}" method="POST">
        @csrf
        <button type="submit">
            {{-- お気に入り状態に応じて表示を切り替える --}}
            @if (Auth::user()->favoriteBooks->contains($book))
                お気に入り解除
            @else
                お気に入り登録
            @endif
        </button>
    </form>
    ```

    > **【エンジニアの思考】**
    > `Auth::user()->favoriteBooks->contains($book)`という部分で、現在表示している`$book`が、ログインユーザーのお気に入りコレクションの中に含まれているかを判定しています。しかし、この書き方には**N+1問題**の罠が潜んでいます。一覧ページでこれをやると、書籍の数だけクエリが発行されてしまいます。これを解決するには、コントローラ側で`$books->load("favoritedByUsers")`のようにEager Loadingしておくか、より高度なテクニック（`withExists`など）を使う必要があります。まずは動くものを作り、後からパフォーマンスを改善していくのも、開発の常套手段です。

#### 1.3. お気に入り一覧ページの作成

自分がお気に入りにした書籍だけを一覧表示するページを作成します。

1.  **ルーティングとコントローラ**

    ```php
    // routes/web.php
    Route::middleware("auth")->group(function () {
        // ...
        Route::get("/favorites", [FavoriteController::class, "index"])->name("favorites.index");
    });
    ```

    `FavoriteController`に`index`メソッドを追加します。

    ```php
    // app/Http/Controllers/FavoriteController.php

    public function index()
    {
        $favoriteBooks = Auth::user()->favoriteBooks()->paginate(10);
        return view("favorites.index", compact("favoriteBooks"));
    }
    ```

2.  **ビューの作成**
    `resources/views/favorites/index.blade.php`を作成します。内容は書籍一覧（`books/index.blade.php`）とほぼ同じで、ループで回す変数が`$favoriteBooks`に変わるだけです。

---

### Section 2: いいね機能 - 共感を示す

レビューに対する「いいね」機能も、お気に入りと全く同じ構造です。`User`と`Review`の多対多リレーションとして実装します。

#### 2.1. データベースとモデルの準備

1.  **マイグレーション作成**

    ```bash
    sail artisan make:migration create_review_likes_table
    ```

    `up`メソッドを編集します。

    ```php
    // database/migrations/..._create_review_likes_table.php
    public function up(): void
    {
        Schema::create("review_likes", function (Blueprint $table) {
            $table->foreignId("user_id")->constrained()->onDelete("cascade");
            $table->foreignId("review_id")->constrained()->onDelete("cascade");
            $table->primary(["user_id", "review_id"]);
        });
    }
    ```

    `sail artisan migrate`で反映します。

2.  **リレーション定義**

    **`app/Models/User.php`**
    ```php
    public function likedReviews()
    {
        return $this->belongsToMany(Review::class, "review_likes")->withTimestamps();
    }
    ```

    **`app/Models/Review.php`**
    ```php
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, "review_likes")->withTimestamps();
    }
    ```

#### 2.2. いいね登録/解除機能の実装

お気に入り機能と全く同じ流れです。

1.  **コントローラの作成とルーティング**

    ```bash
    sail artisan make:controller ReviewLikeController
    ```

    ```php
    // routes/web.php
    use App\Http\Controllers\ReviewLikeController;

    Route::middleware("auth")->group(function () {
        // ...
        Route::post("/reviews/{review}/like", [ReviewLikeController::class, "toggle"])->name("reviews.like");
    });
    ```

2.  **コントローラの実装 (`ReviewLikeController@toggle`)**

    ```php
    // app/Http/Controllers/ReviewLikeController.php

    use App\Models\Review;
    use Illuminate\Support\Facades\Auth;

    class ReviewLikeController extends Controller
    {
        public function toggle(Review $review)
        {
            Auth::user()->likedReviews()->toggle($review->id);
            return back();
        }
    }
    ```

3.  **ビューへのボタン設置**
    書籍詳細ページ（`show.blade.php`）のレビュー表示ループの中に、いいねボタンを設置します。

    ```blade
    <form action="{{ route("reviews.like", $review) }}" method="POST">
        @csrf
        <button type="submit">
            @if (Auth::user()->likedReviews->contains($review))
                いいね解除
            @else
                いいね
            @endif
            {{-- いいね数を表示 --}}
            <span>{{ $review->likedByUsers->count() }}</span>
        </button>
    </form>
    ```

---

### Section 3: ランキング機能 - 集合知の可視化

最後に、レビューの平均評価が高い書籍のランキングを表示する機能を実装します。これは、データベースから集計した結果を表示する、少し高度なRead処理です。

#### 3.1. コントローラの作成とルーティング

```bash
sail artisan make:controller RankingController
```

```php
// routes/web.php
use App\Http\Controllers\RankingController;

Route::get("/ranking", [RankingController::class, "index"])->name("ranking.index");
```

#### 3.2. 集計クエリの実装

`RankingController`の`index`メソッドに、ランキングを算出するロジックを記述します。Eloquentには、`withCount`や`withAvg`といった、関連モデルの集計を簡単に行うための便利なメソッドが用意されています。

```php
// app/Http/Controllers/RankingController.php

use App\Models\Book;

class RankingController extends Controller
{
    public function index()
    {
        $rankedBooks = Book::query()
            ->withCount("reviews") // reviewsの数を`reviews_count`として取得
            ->withAvg("reviews", "rating") // reviewsのratingの平均を`reviews_avg_rating`として取得
            ->having("reviews_count", ">", 0) // レビューが1件以上あるものに絞り込み
            ->orderByDesc("reviews_avg_rating") // 平均評価の降順で並び替え
            ->take(10) // 上位10件を取得
            ->get();

        return view("ranking.index", compact("rankedBooks"));
    }
}
```

> **【コードリーディング】**
> - `Book::query()`: クエリビルダを開始します。メソッドチェーンで複雑なクエリを組み立てる際に便利です。
> - `withCount("reviews")`: 各書籍に紐づくレビューの数を`reviews_count`というプロパティとして追加してくれます。
> - `withAvg("reviews", "rating")`: レビューの`rating`カラムの平均値を`reviews_avg_rating`というプロパティとして追加してくれます。
> - `having(...)`: `where`が通常のカラムを条件にするのに対し、`having`は`COUNT`や`AVG`などで集計した結果を条件に絞り込む際に使います。

#### 3.3. ビューの作成

`resources/views/ranking/index.blade.php`を作成し、コントローラから渡された`$rankedBooks`をループで表示します。順位、書籍情報、平均評価（`$book->reviews_avg_rating`）、レビュー数（`$book->reviews_count`）などを表示すれば、ランキングページの完成です。

### まとめ

おめでとうございます！これで基本機能編のすべての機能実装が完了しました。

このChapterでは、多対多リレーションを扱う実践的な方法として、お気に入り機能といいね機能を実装しました。特に`toggle`メソッドの便利さや、`withCount`, `withAvg`といった集計メソッドの強力さを体験できたと思います。

ここまでの道のりで、あなたは単なるコードの書き方だけでなく、

- 要件をどう解釈し、機能に落とし込むか
- データベースをどう設計し、モデルとどう関連付けるか
- Laravelの機能をどう活用して、安全で効率的なコードを書くか

といった、**現場のエンジニアの思考プロセス**を追体験してきました。この経験は、あなたのこれからの学習や開発において、非常に強力な武器となるはずです。

最終Chapterでは、これまでの成果をGitHubに記録し、プロジェクトを締めくくります。
