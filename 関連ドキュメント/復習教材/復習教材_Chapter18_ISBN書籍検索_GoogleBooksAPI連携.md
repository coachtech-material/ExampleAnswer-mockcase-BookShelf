# Chapter 18: ISBN書籍検索 (Google Books API連携)

## 🎯 このセクションで学ぶこと

書籍登録フォームに ISBN 入力欄を追加し、Google Books API から書籍情報（タイトル / 著者 / 出版日 / 説明 / 書影 URL）を自動取得してフォームへ反映させる機能を実装します。外部 API 連携の典型パターンを学べる Chapter です。

- **API キーの安全な管理**: `.env` に `GOOGLE_BOOKS_API_KEY` を保管し、`config/services.php` 経由で参照する
- **Laravel HTTP クライアント (`Http::get`)**: Guzzle ベースの HTTP ファサードで外部 API へリクエスト、`->json()` で JSON ボディを取り出す
- **3 段階のエラー分岐**: 422（バリデーション）/ 404（該当書籍なし）/ 500（通信エラー）を JSON で返す
- **非同期通信 (fetch API)**: ブラウザ側で JavaScript の `async/await` + `fetch` でエンドポイントを叩き、結果をフォームに自動入力する
- **`config()` 経由のキー取得**: `env()` 直呼び出しではなく `config('services.google_books.api_key')` を使い、`config:cache` 互換にする

---


## 1. はじめに 📖

現代のWebアプリケーション開発において、外部のサービスと連携する機能は不可欠です。このような連携は、一般的に**API（Application Programming Interface）**を介して行われます。

このChapterでは、書籍登録の手間を大幅に削減するため、**Google Books API**と連携し、ISBN（国際標準図書番号）を入力するだけで書籍情報を自動で取得・入力する機能を実装します。Laravelの便利なHTTPクライアントと、フロントエンド（JavaScript）との連携方法を学びます。

## 2. 要件の確認 📋

| 機能 | 詳細仕様 |
|:---|:---|
| **ISBN検索** | 書籍の登録・編集画面でISBN（13桁）を入力し、検索ボタンを押すと、Google Books APIから書籍情報を取得する。 |
| **情報自動入力** | 取得した書籍情報（タイトル、著者、出版日、説明、書影URL）を、フォームの各入力欄に自動でセットする。 |
| **エラーハンドリング** | 書籍が見つからない場合や、API通信に失敗した場合には、ユーザーに適切なエラーメッセージを表示する。 |

## 3. 先輩エンジニアの思考プロセス 💭

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

> 「外部API連携は『失敗する可能性』を常に考慮しないといけない。通信エラー、APIからのエラーレスポンス、期待したデータ形式じゃない、など。`try...catch`ブロックで通信例外を捕捉するのはもちろん、APIのレスポンスをちゃんとチェックして（`! isset($data['items'][0])`）、想定外のデータが来てもエラーにならないようにする。さらに、APIキーなしだとクォータ制限（429エラー）に引っかかることもあるから、そのチェックも忘れずに。ユーザーに『書籍が見つかりませんでした』とか『通信エラーです』とか、何が起きたかちゃんと伝えることが重要だ。」

## 4. 実装 🚀

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
        if (strlen($isbn) !== 13) {
            return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
        }

        $apiKey = config('services.google.books_api_key');
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";

        if ($apiKey) {
            $url .= "&key={$apiKey}";
        }

        try {
            $response = Http::get($url);

            if ($response->status() === 429) {
                return response()->json([
                    'error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。',
                ], 429);
            }

            $data = $response->json();

            if (! isset($data['items'][0])) {
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

### 4.4. コードの詳細解説

#### 1. ISBNのバリデーション

```php
if (strlen($isbn) !== 13) {
    return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
}
```

- APIを呼び出す前に、渡されたISBNが13桁であるかを確認しています。ルート定義で`{isbn}`パラメータは必須のため、`!$isbn`のチェックは不要で、`strlen`のチェックだけで十分です。無効な値で無駄なAPIリクエストを送信しないための基本的なチェックです。
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

// レート制限チェック
if ($response->status() === 429) {
    return response()->json([
        'error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。',
    ], 429);
}

// APIからのレスポンス内容のチェック
if (! isset($data['items'][0])) {
    return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
}
```

- **`try...catch`**: `Http::get()`がネットワークの問題などで失敗した場合、例外（Exception）が発生します。これを`catch`ブロックで捕捉し、サーバー内部のエラーを示す`500`ステータスコードと共にエラーメッセージを返します。
- **`$response->status() === 429`**: APIキーなしでリクエストを送ると、Google Books APIのクォータ制限に引っかかる場合があります。HTTPステータスコード`429`（Too Many Requests）を検出して、APIキーの設定を促すメッセージを返します。
- **`! isset($data['items'][0])`**: API通信が成功しても、該当する書籍が見つからない場合があります。その場合、レスポンスに`items`配列の最初の要素が存在しないので、`isset`でチェックして「見つかりませんでした」というエラーを返します。ステータスコード`404`は「Not Found」を意味します。

#### 4. 成功レスポンスの整形
```php
$volumeInfo = $data['items'][0]['volumeInfo'];

return response()->json([
    'title' => $volumeInfo['title'] ?? '',
    'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
    'published_date' => $volumeInfo['publishedDate'] ?? '',
    'description' => $volumeInfo['description'] ?? '',
    'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
]);
```
- **`$volumeInfo = ...`**: 必要な書籍情報は、レスポンスの深い階層（`items`配列の最初の要素の`volumeInfo`キー）にあります。
- **`$volumeInfo['title'] ?? ''`**: PHPの**Null合体演算子（`??`）**を使って、配列のキーが存在しない場合やnullの場合にデフォルト値（空文字`''`）を返します。APIレスポンスのように構造が不確実なデータを扱う際に、安全に値を取得するための標準的なテクニックです。
- **`isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : ''`**: 著者は配列（`authors`）で返ってくることがあるため、まず`isset`でキーの存在を確認し、存在する場合のみ`implode`関数を使ってカンマ区切りの文字列に変換しています。存在しない場合は空文字を返します。
- **`$volumeInfo['imageLinks']['thumbnail'] ?? ''`**: ネストした配列に対しても`??`演算子で安全にアクセスできます。`imageLinks`キーや`thumbnail`キーが存在しない場合は空文字が返ります。

### 4.5. 提供されているBladeファイルの確認

このプロジェクトでは、書籍登録・編集画面のBladeファイル（`resources/views/books/create.blade.php`と`edit.blade.php`）が事前に提供されています。提供されているBladeファイルには、既にISBN検索機能のUIとJavaScriptが実装されており、コントローラーの実装により機能するようになります。

**提供されているBladeファイルのポイント:**

- **ISBN検索フォーム**: ISBN入力欄と検索ボタンが配置されています。
- **JavaScriptによる非同期通信**: `fetch` APIを使用して`/books/isbn/{isbn}`エンドポイントにGETリクエストを送信します。
- **フォームへの自動入力**: APIから取得した書籍情報（タイトル、著者、出版日、説明、書影URL）を、対応するフォームフィールドに自動でセットします。
- **エラーハンドリング**: 書籍が見つからない場合や通信エラーが発生した場合に、ユーザーに適切なエラーメッセージを表示します。
- **ローディング状態の表示**: 検索中はボタンを無効化し、「検索中...」と表示することで、ユーザーに処理中であることを伝えます。

## 5. コードの詳細解説 🔍

### `Http::get()` の使い方

Laravel の `Http` ファサードは Guzzle ベースの HTTP クライアントで、外部 API への GET/POST を簡潔に書ける。`Http::get($url, $query)` の戻り値は `Response` オブジェクトで、`->json()` で JSON ボディを連想配列として取り出せる。`->ok()` / `->failed()` でステータス判定も可能。

### エラー分岐の 3 段階

ISBN 検索 API では結果を 3 段階に分けて処理する:
1. **422 (Validation Error)**: ISBN フォーマットが不正（13 桁でない等）。FormRequest で検証する。
2. **404 (Not Found)**: API は成功したが該当書籍が見つからない。`$response->json('totalItems') === 0` で判定。
3. **500 (Server Error)**: API 通信に失敗（ネットワークエラー / API キー不正 / Google 側障害）。try/catch で例外を捕捉して JSON エラーを返す。

### `config('services.google_books.api_key')`

`config/services.php` の `'google_books' => ['api_key' => env('GOOGLE_BOOKS_API_KEY')]` を経由して `.env` の値を取得する。直接 `env()` を呼ぶより `config()` 経由が推奨（`config:cache` でキャッシュ可能・テスト時に上書きしやすい等）。

---

## 6. この実装にたどり着くための調べ方 🧐

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

## 7. 動作確認 ✅

> **画面動作確認時の注意:** 本 Chapter の動作確認は書籍登録フォーム（`/books/create`）での ISBN 検索ボタン押下が中心ですが、`navigation.blade.php` が `reports.index` を参照しているため、Chapter 19 マイ読書レポート機能の実装前は画面表示で `Route [reports.index] not defined` の 500 エラーになります。**画面動作確認は Chapter 19 完了後に行ってください。** ISBN 検索エンドポイント自体は curl で直接叩けば確認可能です（例: `curl -s http://localhost/books/isbn/9784297124219`、ただし認証必須のため Cookie が必要）。

| 確認項目 | 確認方法 |
|:---|:---|
| 正常系: ISBN で書籍情報取得 | 書籍登録フォームで実在する ISBN（例: `9784297124219`）を入力 → 「ISBN 検索」ボタン押下 → タイトル / 著者 / 出版日等が自動入力される |
| 422: 13 桁でない ISBN | `123` のような短い値を入力 → エラーメッセージ「ISBN は 13 桁で入力してください」が表示される |
| 404: 存在しない ISBN | `9999999999999` を入力 → 「書籍が見つかりませんでした」が表示される |
| 500: API キー未設定の通信エラー | `.env` の `GOOGLE_BOOKS_API_KEY` を一時的に空にして検索 → 「API 通信エラーが発生しました」が表示される |
| ローディング表示 | 検索中はボタンが無効化され、「検索中...」表示になる |
| 未認証時のアクセス | ログアウト状態で `/books/isbn/{isbn}` を叩くとログイン画面にリダイレクト |

---

## 8. まとめ ✨

このChapterでは、外部APIと連携する機能の実装を通して、多くの実践的な知識を学びました。

- **APIキーの安全な管理**: `.env`ファイルと`config`ヘルパの活用
- **役割分担**: JSONを返すバックエンドAPIと、それを利用するフロントエンドという疎結合な設計
- **HTTPクライアント**: `Http`ファサードによる簡単で可読性の高いAPI通信
- **堅牢なエラーハンドリング**: `try...catch`とレスポンス内容のチェック
- **モダンなフロントエンド**: `fetch`と`async/await`による非同期処理

これらはすべて、実務で即戦力となるための重要なスキルセットです。

次の Chapter 19 では、ユーザーの読書傾向を可視化する**マイ読書レポート機能**を実装します。Laravel Collection の `groupBy` / `map` / `flatMap` / `filter` / `sortByDesc` を駆使した 4 統計の集計を学びます。Chapter 19 完了で `reports.index` ルートが定義され、本 Chapter までで仕込んだ画面の動作確認もここで一括できるようになります。
