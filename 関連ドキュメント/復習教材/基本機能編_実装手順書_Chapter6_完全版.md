# Chapter 6: その他機能の実装

このChapterでは、お気に入り機能、いいね機能、ジャンル管理機能、ランキング機能を実装します。

## 6-1. お気に入り機能

### 要件の確認

| 機能 | 概要 |
|---|---|
| お気に入り登録 | 書籍をお気に入りに登録する |
| お気に入り解除 | お気に入りを解除する |
| お気に入り一覧 | 自分のお気に入り書籍を一覧表示する |

### 思考プロセス

お気に入り機能は「ユーザー」と「書籍」の**多対多リレーション**です。中間テーブル `book_user` を使用します。

### Step 1: コントローラの作成

```bash
sail artisan make:controller FavoriteController
```

### Step 2: コントローラの実装

**`app/Http/Controllers/FavoriteController.php`:**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * お気に入り一覧を表示
     */
    public function index(): View
    {
        $favorites = Auth::user()
            ->favorites()
            ->with(['user', 'genres'])
            ->paginate(10);

        return view('favorites.index', compact('favorites'));
    }

    /**
     * お気に入りを登録/解除（トグル）
     */
    public function toggle(Book $book): RedirectResponse
    {
        Auth::user()->favorites()->toggle($book->id);

        return redirect()->back();
    }
}
```

**コードリーディング:**

```php
Auth::user()->favorites()->toggle($book->id);
```
- `toggle()` メソッドは、中間テーブルにレコードが存在すれば削除し、存在しなければ追加します。お気に入りの登録/解除を1つのメソッドで実現できます。

```php
Auth::user()
    ->favorites()
    ->with(['user', 'genres'])
    ->paginate(10);
```
- `favorites()` リレーションを通じてお気に入り書籍を取得します。
- `with(['user', 'genres'])` でEager Loadingを行い、N+1問題を防ぎます。

### Step 3: ルーティングの設定

**`routes/web.php`:**

```php
use App\Http\Controllers\FavoriteController;

// お気に入り
Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])->name('books.favorite.toggle');
```

### Step 4: Bladeテンプレートの作成

**`resources/views/favorites/index.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'お気に入り一覧')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">お気に入り一覧</h1>

    @if ($favorites->isEmpty())
        <p class="text-gray-500">お気に入りの書籍がありません。</p>
    @else
        <div class="grid gap-4">
            @foreach ($favorites as $book)
                <div class="bg-white rounded-lg shadow-md p-4 flex justify-between items-center">
                    <div>
                        <a href="{{ route('books.show', $book) }}" class="text-lg font-bold text-blue-500 hover:underline">
                            {{ $book->title }}
                        </a>
                        <p class="text-gray-600">{{ $book->author }}</p>
                    </div>
                    <form action="{{ route('books.favorite.toggle', $book) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-red-500 hover:text-red-700">
                            ★ 解除
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $favorites->links() }}
        </div>
    @endif
</div>
@endsection
```

### Step 5: 書籍詳細ページにお気に入りボタンを追加

**`resources/views/books/show.blade.php`:**（お気に入りボタン部分）

```blade
@auth
    <form action="{{ route('books.favorite.toggle', $book) }}" method="POST" class="inline">
        @csrf
        @if (Auth::user()->favorites->contains($book->id))
            <button type="submit" class="text-yellow-500 hover:text-yellow-600">
                ★ お気に入り解除
            </button>
        @else
            <button type="submit" class="text-gray-400 hover:text-yellow-500">
                ☆ お気に入り登録
            </button>
        @endif
    </form>
@endauth
```

## 6-2. いいね機能

### 要件の確認

| 機能 | 概要 |
|---|---|
| いいね登録 | レビューにいいねを付ける |
| いいね解除 | いいねを解除する |

### 思考プロセス

いいね機能は「ユーザー」と「レビュー」の**多対多リレーション**です。中間テーブル `review_likes` を使用します。

### Step 1: コントローラの作成

```bash
sail artisan make:controller ReviewLikeController
```

### Step 2: コントローラの実装

**`app/Http/Controllers/ReviewLikeController.php`:**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * いいねを登録/解除（トグル）
     */
    public function toggle(Review $review): RedirectResponse
    {
        Auth::user()->likedReviews()->toggle($review->id);

        return redirect()->back();
    }
}
```

### Step 3: ルーティングの設定

**`routes/web.php`:**

```php
use App\Http\Controllers\ReviewLikeController;

// いいね
Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like.toggle');
```

### Step 4: 書籍詳細ページのレビュー一覧にいいねボタンを追加

**`resources/views/books/show.blade.php`:**（レビュー一覧のいいねボタン部分）

```blade
@foreach ($book->reviews as $review)
    <div class="border-b border-gray-200 pb-4">
        {{-- 既存のレビュー表示部分 --}}

        {{-- いいねボタン --}}
        @auth
            <form action="{{ route('reviews.like.toggle', $review) }}" method="POST" class="inline">
                @csrf
                @if (Auth::user()->likedReviews->contains($review->id))
                    <button type="submit" class="text-red-500 hover:text-red-600">
                        ♥ {{ $review->likedByUsers->count() }}
                    </button>
                @else
                    <button type="submit" class="text-gray-400 hover:text-red-500">
                        ♡ {{ $review->likedByUsers->count() }}
                    </button>
                @endif
            </form>
        @else
            <span class="text-gray-400">♡ {{ $review->likedByUsers->count() }}</span>
        @endauth
    </div>
@endforeach
```

## 6-3. ジャンル管理機能

### 要件の確認

| 機能 | 概要 |
|---|---|
| ジャンル一覧 | ジャンルを一覧表示する |
| ジャンル登録 | 新しいジャンルを登録する |
| ジャンル編集 | ジャンルを編集する |
| ジャンル削除 | ジャンルを削除する（紐付く書籍がない場合のみ） |

### Step 1: コントローラの作成

```bash
sail artisan make:controller GenreController
```

### Step 2: コントローラの実装

**`app/Http/Controllers/GenreController.php`:**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * ジャンル一覧を表示
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->paginate(10);

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル作成フォームを表示
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを登録
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを作成しました。');
    }

    /**
     * ジャンル編集フォームを表示
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンルを更新
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを更新しました。');
    }

    /**
     * ジャンルを削除
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        // 紐付く書籍がある場合は削除不可
        if ($genre->books()->exists()) {
            return redirect()
                ->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();

        return redirect()
            ->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
```

**コードリーディング:**

```php
$genres = Genre::withCount('books')->paginate(10);
```
- `withCount('books')`: 各ジャンルに紐付く書籍数を `books_count` として取得します。

```php
if ($genre->books()->exists()) {
    return redirect()
        ->route('genres.index')
        ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
}
```
- `exists()`: 紐付く書籍が1件でもあれば `true` を返します。データの整合性を保つため、紐付きがある場合は削除を拒否します。

### Step 3: FormRequestの作成

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

**`app/Http/Requests/StoreGenreRequest.php`:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ジャンル名',
        ];
    }
}
```

**`app/Http/Requests/UpdateGenreRequest.php`:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres', 'name')->ignore($this->genre),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ジャンル名',
        ];
    }
}
```

**コードリーディング:**
```php
Rule::unique('genres', 'name')->ignore($this->genre)
```
- 更新時は自分自身を除外して一意性チェックを行います。`$this->genre` はルートモデルバインディングで注入されたGenreインスタンスです。

### Step 4: ルーティングの設定

**`routes/web.php`:**

```php
use App\Http\Controllers\GenreController;

Route::resource('genres', GenreController::class)->except(['show']);
```

### Step 5: Bladeテンプレートの作成

**`resources/views/genres/index.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'ジャンル一覧')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">ジャンル一覧</h1>
        <a href="{{ route('genres.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            新規作成
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left">ジャンル名</th>
                    <th class="px-6 py-3 text-left">書籍数</th>
                    <th class="px-6 py-3 text-left">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($genres as $genre)
                    <tr>
                        <td class="px-6 py-4">{{ $genre->name }}</td>
                        <td class="px-6 py-4">{{ $genre->books_count }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('genres.edit', $genre) }}" class="text-blue-500 hover:underline mr-2">編集</a>
                            <form action="{{ route('genres.destroy', $genre) }}" method="POST" class="inline" onsubmit="return confirm('本当に削除しますか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline">削除</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">ジャンルがありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $genres->links() }}
    </div>
</div>
@endsection
```

**`resources/views/genres/create.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'ジャンル作成')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-6">ジャンル作成</h1>

    <form method="POST" action="{{ route('genres.store') }}">
        @csrf

        <div class="mb-6">
            <label for="name" class="block text-gray-700 font-medium mb-2">ジャンル名 <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('genres.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                キャンセル
            </a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                作成
            </button>
        </div>
    </form>
</div>
@endsection
```

**`resources/views/genres/edit.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'ジャンル編集')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-6">ジャンル編集</h1>

    <form method="POST" action="{{ route('genres.update', $genre) }}">
        @csrf
        @method('PUT')

        <div class="mb-6">
            <label for="name" class="block text-gray-700 font-medium mb-2">ジャンル名 <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $genre->name) }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('genres.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                キャンセル
            </a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                更新
            </button>
        </div>
    </form>
</div>
@endsection
```

## 6-4. ランキング機能

### 要件の確認

| 機能 | 概要 |
|---|---|
| ランキング表示 | 平均評価の高い書籍をランキング形式で表示する |

### Step 1: コントローラの作成

```bash
sail artisan make:controller RankingController
```

### Step 2: コントローラの実装

**`app/Http/Controllers/RankingController.php`:**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * ランキングを表示
     */
    public function index(): View
    {
        $books = Book::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->having('reviews_count', '>', 0)
            ->orderByDesc('reviews_avg_rating')
            ->take(10)
            ->get();

        return view('rankings.index', compact('books'));
    }
}
```

**コードリーディング:**

```php
Book::withAvg('reviews', 'rating')
```
- `withAvg('reviews', 'rating')`: レビューの `rating` カラムの平均値を `reviews_avg_rating` として取得します。

```php
->withCount('reviews')
```
- `withCount('reviews')`: レビュー数を `reviews_count` として取得します。

```php
->having('reviews_count', '>', 0)
```
- `having()`: レビューが1件以上ある書籍のみを対象にします。`where()` ではなく `having()` を使うのは、集計結果に対する条件だからです。

```php
->orderByDesc('reviews_avg_rating')
->take(10)
```
- 平均評価の降順で並べ替え、上位10件を取得します。

### Step 3: ルーティングの設定

**`routes/web.php`:**

```php
use App\Http\Controllers\RankingController;

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
```

### Step 4: Bladeテンプレートの作成

**`resources/views/rankings/index.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'ランキング')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">書籍ランキング</h1>

    @if ($books->isEmpty())
        <p class="text-gray-500">まだレビューがありません。</p>
    @else
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left">順位</th>
                        <th class="px-6 py-3 text-left">タイトル</th>
                        <th class="px-6 py-3 text-left">著者</th>
                        <th class="px-6 py-3 text-left">平均評価</th>
                        <th class="px-6 py-3 text-left">レビュー数</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($books as $index => $book)
                        <tr>
                            <td class="px-6 py-4 font-bold">
                                @if ($index === 0)
                                    🥇
                                @elseif ($index === 1)
                                    🥈
                                @elseif ($index === 2)
                                    🥉
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('books.show', $book) }}" class="text-blue-500 hover:underline">
                                    {{ $book->title }}
                                </a>
                            </td>
                            <td class="px-6 py-4">{{ $book->author }}</td>
                            <td class="px-6 py-4">
                                <span class="text-yellow-500">
                                    {{ str_repeat('★', round($book->reviews_avg_rating)) }}{{ str_repeat('☆', 5 - round($book->reviews_avg_rating)) }}
                                </span>
                                <span class="text-gray-600 ml-1">
                                    ({{ number_format($book->reviews_avg_rating, 1) }})
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ $book->reviews_count }}件</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
```

## 6-5. ナビゲーションの更新

すべての機能へのリンクをナビゲーションに追加します。

**`resources/views/layouts/app.blade.php`:**（ナビゲーション部分）

```blade
<nav class="bg-white shadow-md">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <div class="flex space-x-4">
                <a href="{{ route('books.index') }}" class="text-gray-700 hover:text-blue-500">書籍一覧</a>
                <a href="{{ route('ranking.index') }}" class="text-gray-700 hover:text-blue-500">ランキング</a>
                @auth
                    <a href="{{ route('books.create') }}" class="text-gray-700 hover:text-blue-500">書籍登録</a>
                    <a href="{{ route('favorites.index') }}" class="text-gray-700 hover:text-blue-500">お気に入り</a>
                    <a href="{{ route('genres.index') }}" class="text-gray-700 hover:text-blue-500">ジャンル管理</a>
                @endauth
            </div>
            <div class="flex space-x-4">
                @auth
                    <span class="text-gray-700">{{ Auth::user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-700 hover:text-blue-500">ログアウト</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-gray-700 hover:text-blue-500">ログイン</a>
                    <a href="{{ route('register') }}" class="text-gray-700 hover:text-blue-500">会員登録</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
```

---

これで基本機能編のすべての機能の実装が完了しました。

## まとめ

本教材では、以下の機能を実装しました：

| Chapter | 内容 |
|---|---|
| Chapter 1 | 環境構築（Docker、Laravel Sail、Tailwind CSS） |
| Chapter 2 | DB設計・モデル（マイグレーション、リレーション） |
| Chapter 3 | 認証機能（Laravel Fortify） |
| Chapter 4 | 書籍管理機能（CRUD、FormRequest、Policy） |
| Chapter 5 | レビュー機能（ネストされたリソース、認可） |
| Chapter 6 | その他機能（多対多リレーション、集計クエリ） |

各機能を実装する際のポイント：

1. **要件定義書の読み解き**: 不明点はPMにヒアリングして明確にする
2. **Bladeテンプレートの分析**: 必要なデータ構造を逆算する
3. **M-C-R-Vの順序**: Model → Controller → Route → View の順で実装
4. **認可の実装**: Policyを使って適切なアクセス制御を行う
5. **N+1問題の回避**: Eager Loadingを活用する
