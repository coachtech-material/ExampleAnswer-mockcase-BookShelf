# Chapter 16: ISBN書籍検索 (Google Books API連携)

## 1. はじめに

現代のWebアプリケーション開発において、外部のサービスと連携する機能は不可欠です。このような連携は、一般的に**API（Application Programming Interface）**を介して行われます。

このChapterでは、書籍登録の手間を大幅に削減するため、**Google Books API**と連携し、ISBN（国際標準図書番号）を入力するだけで書籍情報を自動で取得・入力する機能を実装します。Laravelの便利なHTTPクライアントと、フロントエンド（JavaScript）との連携方法を学びます。

## 2. 要件の確認

| 機能 | 詳細仕様 |
|:---|:---|
| **ISBN検索** | 書籍の登録・編集画面でISBN（13桁）を入力し、検索ボタンを押すと、Google Books APIから書籍情報を取得する。 |
| **情報自動入力** | 取得した書籍情報（タイトル、著者、出版日、説明、書影URL）を、フォームの各入力欄に自動でセットする。 |
| **エラーハンドリング** | 書籍が見つからない場合や、API通信に失敗した場合には、ユーザーに適切なエラーメッセージを表示する。 |

## 3. 先輩エンジニアの思考プロセス：実装の設計

これから外部APIと連携する機能を実装しますが、その前に、どのような考え方で実装を進めていくのか、設計段階の思考プロセスを覗いてみましょう。

### 思考1：フロントエンドの動きからバックエンドの責務を考える

> 「今回は書籍登録・編集のBladeファイルが事前に提供されている。まずは、その中にあるJavaScriptのコードを読んで、フロントエンドがどのように動作するのかを理解しよう。
>
> **フロントエンドの処理の流れ:**
> 1.  ユーザーがISBNを入力し、「検索」ボタンを押す。
> 2.  JavaScriptが発動し、入力されたISBNを取得する。
> 3.  `fetch`という機能を使って、`/books/isbn/{入力されたISBN}`というURLに対して非同期でリクエストを送信する。
> 4.  バックエンドからの応答（JSON形式）を待つ。
> 5.  応答が成功なら、受け取ったJSONデータ（`title`, `author`など）をフォームの各入力欄にセットする。
> 6.  応答がエラーなら、エラーメッセージを表示する。
>
> この動きを理解すれば、我々が実装すべきバックエンドの責務が明確になる。フロントエンドは、特定のURLにリクエストを送り、特定のキー（`title`や`author`）を持つJSONが返ってくることを期待している。つまり、バックエンド側は、その期待に応えるAPIエンドポイントを用意すればいいんだ。」

### 思考2：APIキーのような秘密の情報をどう扱うか？

> 「APIキーやデータベースのパスワードのような機密情報を、Gitでバージョン管理されるファイル（`config/services.php`など）に直接書き込むのは絶対にNG。必ず`.env`ファイルに記述して、`config()`や`env()`ヘルパー経由で読み込むようにする。`.gitignore`に`.env`が含まれていることを確認するのも忘れずに。これはセキュリティの基本中の基本だよ。」

### 思考3：どうやって外部APIと通信するか？

> 「外部APIと通信するなら、`Illuminate\Support\Facades\Http`を使うのがLaravelの流儀。`Http::get($url)`と書くだけでGETリクエストを送れるし、`$response->json()`でレスポンスボディを簡単に連想配列に変換できる。タイムアウト設定やヘッダー追加も簡単。自前でcURLを叩くよりずっと楽で、可読性も高い。」

### 思考4：API連携で起こりうる問題は？

> 「外部API連携は『失敗する可能性』を常に考慮しないといけない。通信エラー、APIからのエラーレスポンス、期待したデータ形式じゃない、など。`try...catch`ブロックで通信例外を捕捉するのはもちろん、APIのレスポンスをちゃんとチェックして（`empty($data["items"])`）、想定外のデータが来てもエラーにならないようにする。ユーザーに『書籍が見つかりませんでした』とか『通信エラーです』とか、何が起きたかちゃんと伝えることが重要だ。」

## 4. 実装

それでは、上記の思考プロセスを元に、ISBN検索機能を実装していきましょう。

### 4.1. APIキーの設定

まず、Google Books APIを利用するためのAPIキーを取得し、Laravelプロジェクトに設定します。

1.  **Google Cloud Platform (GCP) でAPIキーを取得します。**（詳細な手順は割愛します。Google Cloudのドキュメントを参照してください。）
2.  取得したAPIキーを、プロジェクトの`.env`ファイルに追記します。

    ```dotenv
    // .env
    GOOGLE_BOOKS_API_KEY=あなたのAPIキーをここに貼り付け
    ```

3.  APIキーをアプリケーション内で安全に利用するために、`config/services.php`に設定を追加します。これにより、`config('services.google.books_api_key')`という形でキーを呼び出せるようになります。

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

フロントエンドのJavaScriptから呼び出されるAPIエンドポイントを`routes/web.php`に追加します。

```php
// routes/web.php

// ...
Route::middleware('auth')->group(function () {
    // ...
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.searchByIsbn');
    // ...
});
```

### 4.3. BookControllerの実装

`BookController`に、ISBNを受け取ってGoogle Books APIを呼び出し、結果をJSONで返す`searchByIsbn`メソッドを追加します。

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
      public function searchByIsbn(string $isbn): JsonResponse
    {

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

            if (empty($data["items"])) {
                return response()->json(["error" => "書籍が見つかりませんでした。"], 404);
            }

            $volumeInfo = $data['items'][0]['volumeInfo'];

            return response()->json([
                'title' => data_get($volumeInfo, 'title', ''),
                'author' => implode(', ', data_get($volumeInfo, 'authors', [])),
                'published_date' => data_get($volumeInfo, 'publishedDate', ''),
                'description' => data_get($volumeInfo, 'description', ''),
                'image_url' => data_get($volumeInfo, 'imageLinks.thumbnail', ''),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'API通信エラーが発生しました。'], 500);
        }
    }
}
```

### 4.4. コードの詳細解説

#### 1. ISBNのバリデーション

```php
if (!$isbn || strlen($isbn) !== 13) {
    return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
}
```

- APIを呼び出す前に、渡されたISBNが13桁であるかを確認しています。無効な値で無駄なAPIリクエストを送信しないための基本的なチェックです。
- `response()->json()`は、連想配列をJSON形式に変換し、適切なヘッダーを付けてレスポンスを返します。第二引数はHTTPステータスコードです。`400`は「Bad Request（不正なリクエスト）」を意味します。

#### 2. APIリクエストの組み立てと送信

```php
$apiKey = config('services.google.books_api_key');
$url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";
if ($apiKey) {
    $url .= "&key={$apiKey}";
}

$response = Http::get($url);
$data = $response->json();
```

- **`config('services.google.books_api_key')`**: `config/services.php`に設定したAPIキーを安全に取得します。
- **`$url = ...`**: Google Books APIのエンドポイントURLを組み立てています。`q=isbn:{$isbn}`の部分で、ISBNを指定して検索しています。
- **`Http::get($url)`**: LaravelのHTTPクライアントを使って、指定したURLにGETリクエストを送信します。
- **`$response->json()`**: APIからのレスポンス（JSON形式の文字列）をPHPの連想配列に変換します。

#### 3. エラーハンドリング

```php
// 通信エラーの捕捉
try {
    // ... API通信 ...
} catch (\Exception $e) {
    return response()->json(['error' => 'API通信エラーが発生しました。'], 500);
}

// APIからのレスポンス内容のチェック
if (empty($data["items"])) {
    return response()->json(["error" => "書籍が見つかりませんでした。"], 404);
}
```

- **`try...catch`**: `Http::get()`がネットワークの問題などで失敗した場合、例外（Exception）が発生します。これを`catch`ブロックで捕捉し、サーバー内部のエラーを示す`500`ステータスコードと共にエラーメッセージを返します。
- **`empty($data[
items"]))`**: API通信が成功しても、該当する書籍が見つからない場合があります。その場合、レスポンスの`items`配列が空になるので、これをチェックして「見つかりませんでした」というエラーを返します。ステータスコード`404`は「Not Found」を意味します。

#### 4. 成功レスポンスの整形
```php
$volumeInfo = $data['items'][0]['volumeInfo'];

return response()->json([
    'title' => data_get($volumeInfo, 'title', ''),
    'author' => implode(', ', data_get($volumeInfo, 'authors', [])),
    'published_date' => data_get($volumeInfo, 'publishedDate', ''),
    'description' => data_get($volumeInfo, 'description', ''),
    'image_url' => data_get($volumeInfo, 'imageLinks.thumbnail', ''),
]);
```
- **`$volumeInfo = ...`**: 必要な書籍情報は、レスポンスの深い階層（`items`配列の最初の要素の`volumeInfo`キー）にあります。
- **`data_get($volumeInfo, 'key', 'default')`**: `data_get`は、配列やオブジェクトから安全に値を取得するためのヘルパー関数です。例えば、`imageLinks.thumbnail`のようにドット記法で深い階層のデータにアクセスでき、もしキーが存在しなくてもエラーにならず、第三引数で指定したデフォルト値（この場合は空文字`''`）を返してくれます。APIレスポンスのように構造が不確実なデータを扱う際に非常に便利です。
- **`implode(', ', ...)`**: 著者は配列（`authors`）で返ってくることがあるため、`implode`関数を使ってカンマ区切りの文字列に変換しています。

### 4.5. 提供されているBladeファイルの確認

このプロジェクトでは、書籍登録・編集画面のBladeファイル（`resources/views/books/create.blade.php`と`edit.blade.php`）が事前に提供されています。提供されているBladeファイルには、既にISBN検索機能のUIとJavaScriptが実装されており、コントローラーの実装により機能するようになります。

**提供されているBladeファイルのポイント:**

- **ISBN検索フォーム**: ISBN入力欄と検索ボタンが配置されています。
- **JavaScriptによる非同期通信**: `fetch` APIを使用して`/books/isbn/{isbn}`エンドポイントにGETリクエストを送信します。
- **フォームへの自動入力**: APIから取得した書籍情報（タイトル、著者、出版日、説明、書影URL）を、対応するフォームフィールドに自動でセットします。
- **エラーハンドリング**: 書籍が見つからない場合や通信エラーが発生した場合に、ユーザーに適切なエラーメッセージを表示します。
- **ローディング状態の表示**: 検索中はボタンを無効化し、「検索中...」と表示することで、ユーザーに処理中であることを伝えます。

## 5. How to: この実装にたどり着くための調べ方

もし自力でこの実装にたどり着くとしたら、どのように調べれば良いでしょうか？初心者が辿るであろう検索経路を紹介します。

### 調べ方1：「外部のAPIを使いたい」場合

**最初の検索（日本語で素朴に）:**
```
Laravel 外部API 連携
```

**検索結果から得られる情報:**
- Laravelには`Http`クライアントという便利な機能があることがわかる。
- `Http::get()`で簡単にGETリクエストが送れるらしい。

**次の疑問と検索:**
```
Laravel Http client 使い方
```

**最終的にたどり着く答え:**
- 公式ドキュメントにたどり着き、`Http::get()`でリクエストを送り、`$response->json()`で結果を配列として受け取るという基本的な使い方がわかる。

### 調べ方2：「フロントエンドと連携したい」場合

**最初の検索（やりたいことをそのまま）:**
```
JavaScript ボタンをクリックしたらAPIを叩く
```

**検索結果から得られる情報:**
- `fetch`というブラウザ標準の機能を使えば、非同期通信ができることがわかる。
- `async/await`という構文と組み合わせると、コードが書きやすそうだということがわかる。

**次の疑問と検索:**
```
fetch async await 使い方
```

**最終的にたどり着く答え:**
- `async function`の中で`await fetch(...)`を実行し、`try...catch`でエラーを処理するという、モダンな非同期処理の書き方を習得できる。

## 6. まとめ

このChapterでは、外部APIと連携する機能の実装を通して、多くの実践的な知識を学びました。

- **APIキーの安全な管理**: `.env`ファイルと`config`ヘルパの活用
- **役割分担**: JSONを返すバックエンドAPIと、それを利用するフロントエンドという疎結合な設計
- **HTTPクライアント**: `Http`ファサードによる簡単で可読性の高いAPI通信
- **堅牢なエラーハンドリング**: `try...catch`とレスポンス内容のチェック
- **モダンなフロントエンド**: `fetch`と`async/await`による非同期処理

これらはすべて、実務で即戦力となるための重要なスキルセットです。
