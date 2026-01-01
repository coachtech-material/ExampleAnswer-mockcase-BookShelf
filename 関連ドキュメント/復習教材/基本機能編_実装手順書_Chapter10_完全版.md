'''# Chapter 10: ランキング機能

このChapterでは、レビューの平均評価が高い順に書籍を並べるランキング機能を実装します。データベースの集計関数や、複雑なロジックをカプセル化するためのServiceクラスの導入がポイントです。

## 10-1. ランキング表示ページの作成

まずは、ランキングを表示するための専用ページと、そこへのルートを定義します。

### Step 1: ルーティング

**`routes/web.php`**
```php
use App\Http\Controllers\BookController;

// ... 他のルート

Route::get("/ranking", [BookController::class, "ranking"])->name("ranking");
```

### Step 2: Controllerメソッド

`BookController`に`ranking`メソッドを追加します。

**`app/Http/Controllers/BookController.php`**
```php
// ...
class BookController extends Controller
{
    // ...

    public function ranking()
    {
        // ランキング取得ロジック (後述)
        $rankedBooks = []; // 仮

        return view("books.ranking", compact("rankedBooks"));
    }
}
```

### Step 3: Bladeビュー

ランキングを表示するためのビューを作成します。

**`resources/views/books/ranking.blade.php`**
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            書籍評価ランキング
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @foreach ($rankedBooks as $book)
                        <div class="mb-4 p-4 border-b">
                            <h3 class="text-lg font-bold">{{ $loop->iteration }}位: {{ $book->title }}</h3>
                            <p>平均評価: {{ number_format($book->reviews_avg_rating, 2) }}</p>
                            {{-- ... 他の情報 ... --}}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

> **コード解説:**
> - `$loop->iteration`: Bladeの`@foreach`ループ内で使える特殊な変数で、現在のループが何回目か（1から始まる）を返します。順位表示に便利です。
> - `number_format($book->reviews_avg_rating, 2)`: `reviews_avg_rating`（後で定義する集計結果）を小数点以下2桁でフォーマットして表示します。

## 10-2. ランキング取得ロジックの実装

リレーション先のテーブル（`reviews`）の値（`rating`）で集計し、その結果でソートするという、少し複雑なクエリを構築します。

### Step 1: Eloquentでの実装

Eloquentには、リレーション先の集計結果を簡単に取得できる`withAvg`メソッドが用意されています。

**`app/Http/Controllers/BookController.php`**
```php
public function ranking()
{
    $rankedBooks = Book::withAvg("reviews", "rating") // reviewsリレーションのratingカラムの平均値を取得
        ->orderByDesc("reviews_avg_rating") // 平均評価で降順ソート
        ->take(10) // 上位10件を取得
        ->get();

    return view("books.ranking", compact("rankedBooks"));
}
```

> **コード解説:**
> - `withAvg("reviews", "rating")`: このメソッドを使うと、`reviews`リレーションの`rating`カラムの平均値を計算し、`{リレーション名}_avg_{カラム名}`という名前の属性（この場合は`reviews_avg_rating`）としてモデルに自動的に追加してくれます。SQLレベルでは、相関サブクエリやJOINを使って効率的に集計が行われます。
> - `orderByDesc("reviews_avg_rating")`: `withAvg`で追加された`reviews_avg_rating`属性を使って、書籍を降順に並び替えます。

## 10-3. ロジックの分離 (Serviceクラス)

ランキングの取得のような、特定のビジネスロジックはControllerに直接書くよりも、専用の「Serviceクラス」に分離する方が、コードの再利用性や保守性が向上します。

> **思考プロセス:**
> なぜServiceクラスに分離するのでしょうか？
> - **責務の分離**: Controllerの責務は「HTTPリクエストを受け取り、適切なレスポンスを返すこと」です。複雑なビジネスロジックはControllerの本来の責務ではありません。ロジックをServiceクラスに移動することで、Controllerは本来の責務に集中でき、コードがスリムで読みやすくなります。
> - **再利用性**: このランキングロジックを、WebページだけでなくAPIやコマンドラインなど、他の場所でも使いたくなるかもしれません。Serviceクラスにまとめておけば、どこからでも簡単に再利用できます。
> - **テストの容易性**: Serviceクラスは特定のビジネスロジックに特化しているため、単体テストが非常に書きやすくなります。

### Step 1: Serviceクラスの作成

`app/Services`ディレクトリを作成し、`RankingService.php`を配置します。

**`app/Services/RankingService.php`**
```php
<?php

namespace App\Services;

use App\Models\Book;

class RankingService
{
    /**
     * 書籍の評価ランキングを取得する
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBookRanking(int $limit = 10)
    {
        return Book::withAvg("reviews", "rating")
            ->orderByDesc("reviews_avg_rating")
            ->take($limit)
            ->get();
    }
}
```

### Step 2: ControllerからServiceクラスを呼び出す

ControllerのメソッドにServiceクラスをDI（依存性注入）して利用します。

**`app/Http/Controllers/BookController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Services\RankingService; // Serviceをインポート
// ...

class BookController extends Controller
{
    protected $rankingService;

    // コンストラクタでServiceをDI
    public function __construct(RankingService $rankingService)
    {
        $this->rankingService = $rankingService;
        // ... middlewareなど
    }

    // ...

    public function ranking()
    {
        $rankedBooks = $this->rankingService->getBookRanking();

        return view("books.ranking", compact("rankedBooks"));
    }
}
```

> **コード解説 (DI):**
> Controllerのコンストラクタで`RankingService`をタイプヒントすると、Laravelのサービスコンテナが自動的に`RankingService`のインスタンスを生成して注入してくれます。これにより、Controller内で`new RankingService()`のように手動でインスタンス化する必要がなくなり、クラス間の依存関係が疎になります。

## 10-4. 動作確認

1.  いくつかの書籍に複数のレビューを登録し、評価がばらけるようにします。
2.  `/ranking`にアクセスし、平均評価が高い順に書籍が10件表示されることを確認します。
3.  表示されている平均評価が、実際のレビューの評価の平均値と一致していることを確認します。

---

お疲れ様でした！これで基本機能編のすべてのChapterが完了です。これらの実装を通して、Laravelを使ったWebアプリケーション開発の基本的な流れと、その背景にある設計思想を学んできました。ぜひこの知識を元に、さらに機能を追加・改善して、自分だけのアプリケーションを育てていってください。
'''
