# Chapter 12: ルート定義の最終版（設計思想のまとめ）

このChapterでは、これまで実装してきた全機能のルート定義を`routes/web.php`にまとめ、なぜこのような構成になったのか、その背後にある設計思想を解説します。良いルート設計は、アプリケーションの保守性、拡張性、そしてセキュリティを支える土台となります。

---

## 12-1. 先輩エンジニアの思考プロセス：保守性の高いルート設計

アプリケーションが成長するにつれて、`routes/web.php`は複雑化し、見通しが悪くなりがちです。経験豊富なエンジニアは、将来の変更を見越して、ルートを論理的で分かりやすいグループに整理します。

### Step 1: 「誰がアクセスできるか？」で大きく分類する

最も重要な分類は、「認証が必要かどうか」です。

- **認証不要ルート（Public Routes）**: ログインしていないゲストユーザーでもアクセスできるページ。トップページ、書籍一覧、書籍詳細、ランキングなど。
- **認証必須ルート（Authenticated Routes）**: ログインしているユーザーのみがアクセスできる機能。投稿、編集、削除、お気に入り登録など。

> **先輩エンジニアの思考:**
> 「まず`Route::middleware('auth')->group(...)`で大きな壁を作る。この中に定義されたルートは、Laravelが自動的に認証チェックを行ってくれるので、個々のコントローラーで認証状態を気にする必要がなくなる。これを『関心の分離』と呼び、コードをクリーンに保つための基本原則だ。」

### Step 2: 「何に関する操作か？」で機能ごとにまとめる

次に、各グループ内を、関連する機能ごとにまとめます。

> **先輩エンジニアの思考:**
> 「`// Book management`, `// Review management` のように、コメントで明確にセクションを区切る。こうすることで、後から自分や他の開発者が見たときに、『書籍関連のルートはここだな』と一目でわかる。また、`Route::resource`を積極的に使うことで、標準的なCRUD操作のルート定義を1行に集約でき、ファイル全体の見通しが格段に良くなる。」

### Step 3: ルートの順序を意識する

Laravelのルーティングは、`routes/web.php`の上から順にマッチングされます。特に、同じようなURLパターンを持つルートでは、順序が重要になる場合があります。

> **先輩エンジニアの思考:**
> 「例えば、`/books/create`と`/books/{book}`はどちらも`/books/...`というパターンにマッチする。もし`/books/{book}`が先に定義されていたら、`/books/create`へのアクセスは`{book}`パラメータに`create`という文字列が渡されたと解釈されてしまい、`BookController@show`が呼ばれてしまう。これを避けるために、より具体的なルート（`/books/create`）を、より汎用的なルート（`/books/{book}`）よりも先に定義するのが鉄則だ。」

---

## 12.2. 最終的な `routes/web.php`

これまでの思考プロセスを全て反映した、最終的な`routes/web.php`は以下のようになります。

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// == Public Routes (認証不要) ==

// Top page & Book list
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// Ranking
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// Genre filtered list
Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');

// Book details (具体的なルートより後に定義)
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');


// == Authenticated Routes (認証必須) ==
Route::middleware('auth')->group(function () {

    // Book management (CRUD)
    Route::resource('books', BookController::class)->except(['index', 'show']);

    // Genre management (CRUD)
    Route::resource('genres', GenreController::class)->except(['show']);

    // Review management
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Favorite management
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // Review Like management
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    // Profile management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Breeze Authentication Routes
require __DIR__.'/auth.php';

```

---

## 12.3. 最終構成の解説

- **`toggle`メソッドへの統一**: お気に入り機能といいね機能は、`store`/`destroy`ではなく、単一の`toggle`メソッドで登録・解除を切り替えるように修正しました。これにより、ルート定義がシンプルになり、クライアント側の実装も容易になります。
- **`Route::resource`の活用**: 書籍管理とジャンル管理のルートは、`Route::resource`でまとめて定義しています。`except()`を使って、既に公開ルートとして定義済みの`index`や`show`アクションを除外することで、ルートの重複を防いでいます。
- **Profileルートの追加**: 模範解答に存在する、Breezeが提供するプロフィール管理機能のルートを追加しました。

これで、アプリケーション全体の機能とURLの対応が明確に定義されました。この`routes/web.php`は、アプリケーションの「目次」のような役割を果たします。
