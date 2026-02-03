# Chapter 14: ISBN書籍検索 (Google Books API連携)

## 1. はじめに

現代のWebアプリケーション開発において、外部のサービスと連携する機能は不可欠です。このような連携は、一般的に**API（Application Programming Interface）**を介して行われます。

このChapterでは、書籍登録の手間を大幅に削減するため、**Google Books API**と連携し、ISBN（国際標準図書番号）を入力するだけで書籍情報を自動で取得・入力する機能を実装します。Laravelの便利なHTTPクライアントと、フロントエンド（JavaScript）との連携方法を学びます。

## 2. 要件の確認

| 機能 | 詳細仕様 |
|:---|:---|
| **ISBN検索** | 書籍の登録・編集画面でISBN（13桁）を入力し、検索ボタンを押すと、Google Books APIから書籍情報を取得する。 |
| **情報自動入力** | 取得した書籍情報（タイトル、著者、出版日、説明、書影URL）を、フォームの各入力欄に自動でセットする。 |
| **エラーハンドリング** | 書籍が見つからない場合や、API通信に失敗した場合には、ユーザーに適切なエラーメッセージを表示する。 |

## 3. How to: この実装にたどり着くための調べ方

| やりたいこと | 検索キーワード（例） | たどり着く答え（公式ドキュメントなど） |
|:---|:---|:---|
| **外部APIを叩きたい** | `laravel http client` | HTTPクライアントのドキュメントに`Http`ファサードの使い方が詳しく書かれている。`Http::get()`でGETリクエストを送り、`json()`メソッドでレスポンスを配列として受け取れることがわかる。 |
| **APIのURLに変数を埋め込みたい** | `laravel http client query parameters` | `Http::get()`の第二引数に連想配列を渡すことで、クエリパラメータを安全に追加できることがわかる。`'q' => 'isbn:' . $isbn`のように。 |
| **APIのレスポンスが複雑** | （APIのドキュメントを読む） | Google Books APIのドキュメントを読むと、レスポンスのJSON構造がわかる。`items[0].volumeInfo.title`のように、多階層のデータから必要な情報を取り出す必要があることに気づく。 |
| **API通信が失敗した場合** | `laravel http client error handling` | `Http`ファサードのレスポンスオブジェクトが持つ`successful()`や`failed()`、`status()`といったメソッドで、通信が成功したか、ステータスコードは何かを判別できることがわかる。 |
| **フロントと連携したい** | `laravel javascript fetch` / `axios laravel` | `fetch` APIを使って非同期リクエストを送信する方法が見つかる。`await`を使ってレスポンスを待ち、`response.json()`でJSONデータを受け取るのが基本的な流れだとわかる。 |

## 4. 実装

### 4.1. APIキーの設定

まず、Google Books APIを利用するためのAPIキーを取得し、Laravelプロジェクトに設定します。

1.  **Google Cloud Platform (GCP) でAPIキーを取得します。**（詳細な手順は割愛します。Google Cloudのドキュメントを参照してください。）
2.  取得したAPIキーを、プロジェクトの`.env`ファイルに追記します。

    ```dotenv
    // .env
    GOOGLE_BOOKS_API_KEY=あなたのAPIキーをここに貼り付け
    ```

3.  APIキーをアプリケーション内で安全に利用するために、`config/services.php`に設定を追加します。

    ```php
    // config/services.php
    
    return [
        // ...
        'google' => [
            'books_api_key' => env('GOOGLE_BOOKS_API_KEY'),
        ],
    ];
    ```

### 4.2. ルートの定義

APIリクエストを処理するためのエンドポイントを`routes/web.php`に追加します。

```php
// routes/web.php

// ...
Route::middleware('auth')->group(function () {
    // ...
    Route::get('/books/fetch', [BookController::class, 'fetch'])->name('books.fetch');
    // ...
});
```

### 4.3. BookControllerの実装

`BookController`に、APIからのリクエストを処理する`fetch`メソッドを追加します。

```php
// app/Http/Controllers/BookController.php

// ...
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class BookController extends Controller
{
    // ... 他のメソッド ...

    /**
     * ISBN検索（Google Books API）（応用機能）
     */
    public function fetch(Request $request): JsonResponse
    {
        $isbn = $request->input('isbn');

        if (!$isbn || strlen($isbn) !== 13) {
            return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
        }

        $apiKey = config('services.google.books_api_key');
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";

        if ($apiKey) {
            $url .= "&key={$apiKey}";
        }

        try {
            $response = Http::get($url);
            $data = $response->json();

            if (!isset($data['items'][0])) {
                return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
            }

            $volumeInfo = $data['items'][0]['volumeInfo'];

            return response()->json([
                'title' => $volumeInfo['title'] ?? '',
                'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
                'published_date' => $volumeInfo['publishedDate'] ?? '',
                'description' => $volumeInfo['description'] ?? '',
                'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'API通信エラーが発生しました。'], 500);
        }
    }
}
```

### 4.4. ビューとJavaScriptの実装

提供されている書籍登録・編集画面（`resources/views/books/create.blade.php`と`edit.blade.php`）のISBN検索機能が動作するように、JavaScriptとコントローラーを連携させます。以下は`create.blade.php`のコード例です。

```html
<!-- resources/views/books/create.blade.php -->

@extends('layouts.app')

@section('content')
    <div class="container mx-auto">
        <h1 class="text-2xl font-bold mb-4">書籍登録</h1>

        <!-- ISBN検索フォーム -->
        <div class="mb-4 p-4 bg-gray-100 rounded">
            <label for="isbn-search" class="block font-bold mb-2">ISBNで書籍情報を自動入力</label>
            <div class="flex">
                <input type="text" id="isbn-search" placeholder="13桁のISBNを入力" class="border rounded-l px-2 py-1 w-full">
                <button id="isbn-search-button" class="bg-blue-500 text-white px-4 py-1 rounded-r">検索</button>
            </div>
            <p id="isbn-search-error" class="text-red-500 text-sm mt-1"></p>
        </div>

        <form action="{{ route('books.store') }}" method="POST">
            @csrf
            @include('books._form', ['book' => null])
        </form>
    </div>

@push('scripts')
<script>
    document.getElementById('isbn-search-button').addEventListener('click', async () => {
        const isbn = document.getElementById('isbn-search').value;
        const errorElement = document.getElementById('isbn-search-error');
        errorElement.textContent = '';

        if (!isbn) {
            errorElement.textContent = 'ISBNを入力してください。';
            return;
        }

        try {
            const response = await fetch(`{{ route('books.fetch') }}?isbn=${isbn}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'エラーが発生しました。');
            }

            document.querySelector('[name="title"]').value = data.title || '';
            document.querySelector('[name="author"]').value = data.author || '';
            document.querySelector('[name="published_date"]').value = data.published_date || '';
            document.querySelector('[name="description"]').value = data.description || '';
            document.querySelector('[name="image_url"]').value = data.image_url || '';

        } catch (error) {
            errorElement.textContent = error.message;
        }
    });
</script>
@endpush
@endsection
```

## 5. 先輩エンジニアの思考プロセス（実装の振り返り）

### 思考1：機密情報は`.env`ファイルで管理する

> 「APIキーやデータベースのパスワードのような機密情報を、Gitでバージョン管理されるファイル（`config/services.php`など）に直接書き込むのは絶対にNG。必ず`.env`ファイルに記述して、`config()`や`env()`ヘルパー経由で読み込むようにする。`.gitignore`に`.env`が含まれていることを確認するのも忘れずに。これはセキュリティの基本中の基本だよ。」

### 思考2：バックエンドとフロントエンドの役割分担

> 「今回の機能は、①フロントエンド（ブラウザ）がISBNをバックエンドに送り、②バックエンドがGoogle APIと通信して結果を返し、③フロントエンドがその結果をフォームに反映させる、という流れ。バックエンドはAPIとの通信とデータ整形に専念し、フロントエンドはUIの操作と非同期通信に専念する。この役割分担を意識することが大事。Laravel側はJSONを返すAPIエンドポイント（`/books/fetch`）を用意するだけでいい。」

### 思考3：LaravelのHTTPクライアントは超優秀

> 「外部APIと通信するなら、`Illuminate\Support\Facades\Http`を使うのがLaravelの流儀。`Http::get($url)`と書くだけでGETリクエストを送れるし、`$response->json()`でレスポンスボディを簡単に連想配列に変換できる。タイムアウト設定やヘッダー追加も簡単。自前でcURLを叩くよりずっと楽で、可読性も高い。」

### 思考4：堅牢なエラーハンドリングを心がける

> 「外部API連携は『失敗する可能性』を常に考慮しないといけない。通信エラー、APIからのエラーレスポンス、期待したデータ形式じゃない、など。`try...catch`ブロックで通信例外を捕捉するのはもちろん、APIのレスポンスをちゃんとチェックして（`!isset($data['items'][0])`）、想定外のデータが来てもエラーにならないようにする。ユーザーに『書籍が見つかりませんでした』とか『通信エラーです』とか、何が起きたかちゃんと伝えることが重要だ。」

### 思考5：フロントエンドはモダンな`fetch`と`async/await`で

> 「昔はAjaxといえばjQueryの`$.ajax`が主流だったけど、今はブラウザ標準の`fetch` APIを使うのが一般的。さらに`async/await`構文を使えば、非同期処理が同期処理のように直感的に書ける。`try...catch`でエラーハンドリングもできるし、コードがすごくスッキリする。この書き方はモダンなフロントエンド開発の必須スキルだよ。」

## 6. まとめ

このChapterでは、外部APIと連携する機能の実装を通して、多くの実践的な知識を学びました。APIキーの安全な管理、バックエンドとフロントエンドの適切な役割分担、LaravelのHTTPクライアントの活用、そして堅牢なエラーハンドリング。これらはすべて、実務で即戦力となるための重要なスキルセットです。
