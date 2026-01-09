# Chapter 11: ジャンル別一覧機能

このChapterでは、特定のジャンルに属する書籍を一覧表示する機能を実装します。これは、多くのECサイトやブログで「カテゴリ別一覧」として実装されている、非常に一般的な機能です。Eloquentリレーションシップの強力さを改めて実感できる良い機会です。

---

## 11-1. 先輩エンジニアの思考プロセス：リレーションを起点としたデータ取得

### Step 1: 要件とURL設計

**要件**: 「特定のジャンルに属する書籍を一覧表示する」

この要件を実現するためのURLは、直感的に`/genres/{genre_id}`のような形になります。ユーザーが「小説」ジャンルのページを見たいなら`/genres/1`、「技術書」なら`/genres/2`といった具合です。これは、特定の「リソース（ジャンル）」を表示する、RESTfulな設計の基本です。

> **先輩エンジニアの思考:**
> 「これは『ジャンル』が主役のページだ。だから、`GenreController`に`show`メソッドを作るのが自然な設計だろう。ルートモデルバインディングを使えば、URLの`{genre}`の部分から自動的に`Genre`モデルのインスタンスを取得できる。コントローラーのコードが非常にクリーンになる。」

### Step 2: データ取得方法の検討

特定のジャンルに紐づく書籍を取得するには、どうすれば良いでしょうか？

> **先輩エンジニアの思考:**
> 「Chapter 3で`Genre`モデルに`books()`という`belongsToMany`リレーションを定義したのを思い出そう。`$genre->books()`と呼び出すだけで、フレームワークが自動的に中間テーブル(`book_genre`)を検索し、紐づく書籍モデルのコレクションを返してくれる。これを使わない手はない。あとは、書籍一覧ページと同様にN+1問題を避けるための`with()`と、ページネーションを追加すれば完璧だ。」

**実装方針:**
1.  `GenreController`に`show(Genre $genre)`メソッドを作成する。
2.  `$genre->books()->with("genres")->paginate(10)`というコードで、対象ジャンルの書籍一覧を取得する。
3.  取得したデータ（`$genre`と`$books`）を`genres.show`ビューに渡す。
4.  `routes/web.php`に`/genres/{genre}`へのGETルートを追加する。

---

## 11.2. 部品の作成と実装

### 1. コントローラーの作成と`show`メソッドの実装

`GenreController`はまだ作成していなかったので、`artisan`コマンドで作成します。

```bash
sail artisan make:controller GenreController
```

次に、`app/Http/Controllers/GenreController.php`を開き、`show`メソッドを実装します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * 特定のジャンルに属する書籍の一覧を表示する
     */
    public function show(Genre $genre): View
    {
        // 要件：特定のジャンルに属する書籍を一覧表示する。
        // 思考：
        // 1. ルートモデルバインディングで受け取った`$genre`モデルを起点にする。
        // 2. `books()`リレーションを呼び出して、紐づく書籍を取得する。
        // 3. 書籍一覧表示なので、N+1問題対策の`with("genres")`とページネーション`paginate(10)`は必須。
        $books = $genre->books()->with("genres")->latest()->paginate(10);

        // ジャンル名と、そのジャンルに属する書籍一覧をビューに渡す
        return view("genres.show", compact("genre", "books"));
    }

    // index, create, storeなどのメソッドは後のChapterで実装
}
```

### 2. ルーティングの定義 (`routes/web.php`)

誰でも閲覧できる公開ルートとして、ジャンル別一覧ページのルートを定義します。

```php
// `routes/web.php` の `Route::middleware("auth")` の外に記述
Route::get("/genres/{genre}", [GenreController::class, "show"])->name("genres.show");
```

### 3. ビューの実装 (`resources/views/genres/show.blade.php`)

まず、必要なディレクトリと空のファイルを作成します。

```bash
# ディレクトリを作成
mkdir -p resources/views/genres

# 空のファイルを作成
touch resources/views/genres/show.blade.php
```

コントローラーから渡された`$genre`と`$books`を使って、ジャンル別の書籍一覧ページを作成します。

```html
<x-app-layout>
    <x-slot name="header">
        <h2>ジャンル: {{ $genre->name }}</h2>
    </x-slot>

    <div>
        @if ($books->count())
            <div>
                <!-- 書籍一覧の表示（books.indexとほぼ同じ） -->
                @foreach ($books as $book)
                    <div>
                        <a href="{{ route("books.show", $book) }}">
                            <h3>{{ $book->title }}</h3>
                            <p>著者: {{ $book->author }}</p>
                            <div>
                                @foreach($book->genres as $genre)
                                    <span>{{ $genre->name }}</span>
                                @endforeach
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <!-- ページネーションリンク -->
            {{ $books->links() }}
        @else
            <p>このジャンルに属する書籍はありません。</p>
        @endif
    </div>
</x-app-layout>
```

---

## 11.3. 動作確認

1.  書籍登録時に、複数の書籍に同じジャンル（例：「小説」）を紐付けておきます。
2.  ブラウザで`/genres/{id}`（例: `/genres/1`）にアクセスし、そのジャンルに紐付けた書籍だけが一覧表示されることを確認します。
3.  一覧に表示された書籍のタイトルをクリックすると、書籍詳細ページに正しく遷移することを確認します。
4.  書籍が10件以上ある場合は、ページネーションリンクが表示され、正しく機能することを確認します。

これで、ジャンル別一覧機能の実装が完了しました。Eloquentリレーションシップの強力さを改めて実感できたはずです。
