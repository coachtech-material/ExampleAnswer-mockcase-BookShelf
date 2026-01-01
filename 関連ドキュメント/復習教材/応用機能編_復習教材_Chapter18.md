# Chapter 18: 公開APIの実装

## 1. はじめに

近年、Webアプリケーションが外部のサービスやクライアント（例：スマートフォンアプリ、JavaScriptフレームワークを使ったSPA）に対してデータを提供するために、**JSON形式のAPI**を公開することが一般的になっています。

このChapterでは、これまで作ってきた書籍管理アプリケーションのデータを、外部から利用できるようにするための公開APIを実装します。LaravelにおけるAPI開発の基本、バージョニング、そしてAPIリソースを使ったレスポンス整形の方法を学びます。

## 2. 要件の確認

| 機能 | エンドポイント | HTTPメソッド | 詳細仕様 |
|:---|:---|:---:|:---|
| **書籍一覧取得API** | `/api/v1/books` | GET | 書籍の一覧をJSON形式で返す。ページネーションに対応する。 |
| **書籍詳細取得API** | `/api/v1/books/{book}` | GET | 指定されたIDの書籍詳細情報をJSON形式で返す。 |

## 3. 実装

### 3.1. API用のルート定義

APIのエンドポイントは、通常のWeb画面用のルートとは別のファイル`routes/api.php`に定義するのがLaravelの慣習です。これにより、ミドルウェアの適用などをWeb用とAPI用で分離できます。

`routes/api.php`を以下のように編集します。

```php
// routes/api.php

<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(\'auth:sanctum\')->get(\'/user\', function (Request $request) {
    return $request->user();
});

// API v1
Route::prefix(\'v1\')->group(function () {
    Route::get(\'/books\', [BookController::class, \'index\']);
    Route::get(\'/books/{book}\', [BookController::class, \'show\']);
});
```

### 3.2. API用コントローラーの作成

API用のコントローラーは、Web用とは別のディレクトリに配置するのが整理しやすくて良いでしょう。`app/Http/Controllers/Api/V1`ディレクトリを作成し、そこに`BookController`を作成します。

```bash
mkdir -p app/Http/Controllers/Api/V1
sail artisan make:controller Api/V1/BookController
```

作成した`app/Http/Controllers/Api/V1/BookController.php`を以下のように編集します。

```php
// app/Http/Controllers/Api/V1/BookController.php

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * 書籍一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->input(\'query\');

        $books = Book::query()
            ->with(\'genres\')
            ->when($query, function ($q, $query): void {
                $q->where(\'title\', \'like\', "%{$query}%")
                  ->orWhere(\'author\', \'like\', "%{$query}%");
            })
            ->latest()
            ->paginate(10);

        return response()->json($books);
    }

    /**
     * 書籍詳細を取得
     */
    public function show(Book $book): JsonResponse
    {
        $book->load([\'genres\', \'reviews.user\']);

        return response()->json($book);
    }
}
```

## 4. 先輩エンジニアの思考プロセス

### 思考1：APIのルートは`routes/api.php`に書く

> 「Web画面用のルートとAPI用のルートでは、適用したいミドルウェアが違うことが多い。例えば、APIではCSRF保護は不要だけど、代わりにトークンベースの認証（Sanctumなど）が必要になったりする。Laravelでは`routes/web.php`と`routes/api.php`で自動的に適用されるミドルウェアグループが分かれているんだ。だから、APIを作るなら`routes/api.php`に書くのが大原則。」

### 思考2：APIにはバージョニングが不可欠

> 「APIは一度公開すると、自分たち以外の誰か（スマホアプリとか）が使い始める可能性がある。もし後からAPIのレスポンス形式をガラッと変えちゃうと、そのAPIを使っていたアプリが全部動かなくなって大混乱になる。それを防ぐために、`/api/v1/`のようにURLにバージョン番号を含めるのが一般的。将来、大きな変更が必要になったら、古いv1は残したまま、新しく`/api/v2/`を作る。これで後方互換性を保てるんだ。`Route::prefix(\'v1\')->group(...)`を使うと、このバージョニングが簡単に実現できる。」

### 思考3：コントローラーもバージョンごとに分ける

> 「ルートをバージョンで分けたなら、コントローラーも`Api/V1/BookController.php`のようにディレクトリを分けて管理するのが自然な流れ。こうしておけば、v2を作るときに`Api/V2/BookController.php`を新しく作ればよくて、v1のコードに影響を与えることなく安全に開発が進められる。」

### 思考4：レスポンスは`response()->json()`で返す

> 「APIコントローラーのメソッドは、ビューではなくJSONを返す。Eloquentのモデルやコレクションは、そのまま`response()->json()`に渡すだけで、Laravelが自動的にいい感じのJSONに変換してくれる。ページネーションの情報（`total`, `per_page`, `current_page`など）も自動で含めてくれるからすごく便利。戻り値の型ヒントを`: JsonResponse`にしておくのも忘れずに。」

### 思考5：APIレスポンスの整形には「APIリソース」を検討する

> 「今回はEloquentモデルを直接JSONに変換したけど、実務ではもっと複雑な要件が出てくる。『このカラムはAPIに含めたくない』とか、『ユーザーの役割によって返す情報を変えたい』とかね。そういうときは**APIリソース**（`php artisan make:resource`）を使うのがベスト。モデルとAPIレスポンスの間に一層を挟むことで、レスポンスの構造を柔軟に、かつ一元的に管理できるようになる。小規模なAPIなら直接変換でもいいけど、本格的なAPIを作るならAPIリソースは必須テクニックだよ。」

## 5. 動作確認

APIが正しく動作するか、ブラウザやAPIクライアントツール（Postman、Insomniaなど）を使って確認してみましょう。

-   **書籍一覧:** `http://localhost/api/v1/books` にアクセスする。
-   **書籍詳細:** `http://localhost/api/v1/books/1` のように、存在する書籍IDを指定してアクセスする。
-   **検索:** `http://localhost/api/v1/books?query=Laravel` のように、`query`パラメータを付けてアクセスする。

期待通りのJSONデータが返ってくれば成功です。

## 6. まとめ

このChapterでは、Laravelで公開APIを開発するための基本的な流れを学びました。ルートの分離、バージョニングの重要性、APIコントローラーの実装パターンなど、API開発の第一歩となる知識を習得しました。実務では、ここからさらに認証（SanctumやPassport）、APIリソース、テストなどを組み合わせて、より堅牢で実用的なAPIを構築していくことになります。
