# Chapter 10: ランキング & ジャンル管理機能

最終Chapterでは、これまでの応用として「レビュー評価ランキング」と、管理者向けの「ジャンル管理機能」を実装します。データベースの集計関数や、シンプルなCRUDの実装がポイントです。

## 10-1. ランキング機能の実装

レビューの平均評価が高い順に書籍を並べるランキングページを作成します。

### Step 1: Controllerの作成とルーティング

ランキング表示専用の`RankingController`を作成します。

```bash
sail artisan make:controller RankingController
```

**`routes/web.php`**
```php
use App\Http\Controllers\RankingController;

Route::get("ranking", [RankingController::class, "index"])->name("ranking.index");
```

### Step 2: Controllerの実装

**`app/Http/Controllers/RankingController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
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
}
```

> **思考プロセス:**
> - **なぜEloquentの`withAvg`を使わないのか？**: 模範解答では、よりSQLに近い柔軟な記述が可能な`DB::raw`と`join`を使用しています。`withAvg`はリレーションに基づいて集計しますが、この方法では`SELECT`句で直接集計結果を`average_rating`という別名で取得し、`orderByDesc`で直接ソートできるため、より直感的で効率的なクエリを組み立てられます。
> - `DB::raw("AVG(reviews.rating) as average_rating")`: SQLの`AVG()`関数を直接実行し、その結果を`average_rating`という名前のカラムとして取得します。
> - `join("reviews", ...)`: `books`テーブルと`reviews`テーブルを`book_id`で結合します。
> - `groupBy("books.id")`: 書籍ごとにレビューをグループ化し、`AVG()`関数が各書籍の平均評価を正しく計算できるようにします。

### Step 3: Bladeの実装

ランキングを表示するビューを作成します。

**`resources/views/ranking/index.blade.php`**
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            レビュー評価ランキング
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @foreach ($rankedBooks as $index => $book)
                        <div class="mb-4 p-4 border-b">
                            <span class="font-bold text-lg">{{ $index + 1 }}位</span>
                            <h3 class="text-lg font-bold">
                                <a href="{{ route("books.show", $book) }}">{{ $book->title }}</a>
                            </h3>
                            <p>平均評価: {{ number_format($book->average_rating, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

## 10-2. ジャンル管理機能の実装

管理者（この教材では便宜上、全ユーザーが操作可能）がジャンルを追加・編集・削除できる機能を実装します。

### Step 1: Controllerの作成とルーティング

```bash
sail artisan make:controller GenreController --resource --model=Genre
```

**`routes/web.php`**
```php
use App\Http\Controllers\GenreController;

Route::resource("genres", GenreController::class)->except(["show"]);
```
> **コード解説:**
> - `except(["show"])`: ジャンルには詳細ページが不要なため、`Route::resource`で生成されるルートから`show`アクションを除外しています。

### Step 2: Form Requestの作成

```bash
sail artisan make:request StoreGenreRequest
sail artisan make:request UpdateGenreRequest
```

**`app/Http/Requests/StoreGenreRequest.php`**
```php
public function rules(): array
{
    return [
        "name" => "required|string|max:255|unique:genres,name",
    ];
}
```

**`app/Http/Requests/UpdateGenreRequest.php`**
```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        "name" => ["required", "string", "max:255", Rule::unique("genres")->ignore($this->genre->id)],
    ];
}
```

### Step 3: Controllerの実装

**`app/Http/Controllers/GenreController.php`**
```php
// ... (use文)

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::all();
        return view("genres.index", compact("genres"));
    }

    public function create()
    {
        return view("genres.create");
    }

    public function store(StoreGenreRequest $request)
    {
        Genre::create($request->validated());
        return redirect()->route("genres.index")->with("success", "ジャンルを追加しました。");
    }

    public function edit(Genre $genre)
    {
        return view("genres.edit", compact("genre"));
    }

    public function update(UpdateGenreRequest $request, Genre $genre)
    {
        $genre->update($request->validated());
        return redirect()->route("genres.index")->with("success", "ジャンルを更新しました。");
    }

    public function destroy(Genre $genre)
    {
        $genre->delete();
        return redirect()->route("genres.index")->with("success", "ジャンルを削除しました。");
    }
}
```

### Step 4: Bladeの実装

ジャンルの一覧、登録、編集画面を作成します。書籍CRUDと同様の構成になるため、コードは割愛します。

---

お疲れ様でした！これで基本機能編のすべての機能が実装完了です。この教材を通して、Laravelを使ったWebアプリケーション開発の基本的な流れを体系的に学ぶことができたはずです。ぜひこの知識を土台に、さらに複雑な機能やオリジナルのアプリケーション開発に挑戦してみてください。
