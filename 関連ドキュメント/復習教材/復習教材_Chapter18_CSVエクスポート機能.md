# Chapter 15: CSVエクスポート機能の実装

## 1. はじめに

Webアプリケーション開発では、画面に表示されているデータをファイルとしてダウンロードさせたい、という要件が頻繁に登場します。特に、ビジネス系のシステムではCSV形式でのエクスポート機能は定番です。

このChapterでは、書籍一覧の検索結果をCSVファイルとしてダウンロードする機能を実装します。さらに、大量のデータを扱う際に問題となる**メモリ消費**を抑えるための実践的なテクニックも学びます。

## 2. 要件の確認

| 機能 | 詳細仕様 |
|:---|:---|
| **CSVダウンロード** | 書籍一覧をCSV形式でダウンロードできる。 |
| **検索条件の維持** | 検索画面で絞り込んだ結果が、そのままCSVに出力される。 |
| **パフォーマンス** | 大量のデータ（数万件以上）をエクスポートする場合でも、サーバーのメモリを使い果たさないように実装する。 |

## 3. 先輩エンジニアの思考プロセス：実装の設計

これからCSVエクスポート機能を実装していきますが、その前に、どのような考え方で実装を進めていくのか、設計段階の思考プロセスを覗いてみましょう。

### 思考1：どうやってCSVをダウンロードさせるか？

> 「まず、サーバー上のPHPでCSVファイルの中身を動的に生成し、それをユーザーのブラウザに『ファイルとしてダウンロードしてね』と伝える必要がある。Laravelでこれを実現するには、`Response`オブジェクトをカスタマイズする方法が考えられる。特に、レスポンスを少しずつ送信できる`StreamedResponse`（ストリームレスポンス）が良さそうだ。これなら、巨大なCSVファイルでもサーバーのメモリを圧迫せずに済む。」

### 思考2：検索ロジックはどうする？

> 「CSVエクスポートの対象データは、一覧表示の検索結果と完全に同じであるべき。ということは、Chapter 14で実装した`index`メソッドとほぼ同じ検索ロジックが必要になる。ここでロジックをコピペするのはDRY（Don't Repeat Yourself）原則に反する。本当は`Book`モデルに**ローカルスコープ**を定義してロジックを共通化するのがベストプラクティスだけど、今回は学習のためにコントローラー内にロジックを再記述してみよう。リファクタリングの機会があれば、スコープ化は最優先候補だね。」

### 思考3：大量データを扱う場合の注意点は？

> 「実務では数万、数十万件のデータを扱う可能性がある。その場合、`$query->get()`で全件を一度に取得すると、メモリを大量に消費して最悪サーバーがダウンする。この問題は、DBからデータを少しずつ（例えば1000件ずつ）取得する`chunk()`メソッドと、レスポンスを少しずつ送信する`StreamedResponse`を組み合わせることで解決できる。今回は模範解答に合わせて`get()`を使うけど、大量データを扱う機能では`chunk()`の利用を必ず検討すべきだ。」

### 思考4：CSV特有の問題（文字化け）はどうする？

> 「CSVをExcelで開いたときに日本語が文字化けする、というのは『あるある』な問題。これはExcelがCSVファイルをUTF-8と正しく認識できないことが原因。対策として、ファイルの先頭に**BOM（バイトオーダーマーク）**という特殊な3バイト（`\xEF\xBB\xBF`）を書き込んであげる。`fwrite($handle, "\xEF\xBB\xBF");`の1行があるだけで、ユーザーからの『文字化けしてる！』という問い合わせを防げるんだ。」

## 4. 実装

それでは、上記の思考プロセスを元に、CSVエクスポート機能を実装していきましょう。コード全体を掲載した後に、各ブロックの詳細な解説を追記します。

### 4.1. ルートの定義

まず、CSVダウンロード用のエンドポイントを`routes/web.php`に追加します。一覧表示と同様に、認証されたユーザーのみがアクセスできるように`auth`ミドルウェアグループの中に定義します。

```php
// routes/web.php

// ...
Route::middleware('auth')->group(function () {
    // ...
    Route::get('/books/export/csv', [BookController::class, 'exportCsv'])->name('books.export');
    // ...
});
```

### 4.2. BookControllerの実装

次に、`BookController`に`exportCsv`メソッドを追加します。

```php
// app/Http/Controllers/BookController.php

// ...
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookController extends Controller
{
    // ... index, create, storeなどのメソッド ...

    /**
     * 書籍一覧をCSVでエクスポート（応用機能）
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $keyword = $request->input("keyword");
        $genreId = $request->input("genre");
        $sort = $request->input("sort", "newest");

        $query = Book::with("genres");

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                return $q->where("title", "like", "%{$keyword}%")
                         ->orWhere("author", "like", "%{$keyword}%");
            });
        }

        if ($genreId) {
            $query->whereHas("genres", function ($q) use ($genreId) {
                return $q->where("genres.id", $genreId);
            });
        }

        switch ($sort) {
            case "oldest":
                $query->orderBy("created_at", "asc");
                break;
            case "rating":
                $query->withAvg("reviews", "rating")->orderByDesc("reviews_avg_rating");
                break;
            case "title":
                $query->orderBy("title", "asc");
                break;
            default:
                $query->orderBy("created_at", "desc");
                break;
        }

        $books = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="books_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($books): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'タイトル', '著者', 'ISBN', '出版日', 'ジャンル', '登録日']);

            foreach ($books as $book) {
                fputcsv($handle, [
                    $book->id,
                    $book->title,
                    $book->author,
                    $book->isbn ?? '',
                    $book->published_date ?? '',
                    $book->genres->pluck('name')->implode(', '),
                    $book->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    // ... 他のメソッド ...
}
```

### 4.3. コードの詳細解説

ここからは、上記のコードをブロックごとに分解し、何をしているのかを詳しく見ていきましょう。

#### 1. 検索条件の取得

```php
$keyword = $request->input("keyword");
$genreId = $request->input("genre");
$sort = $request->input("sort", "newest");

$query = Book::with("genres");
```

- **`$request->input("keyword")`**: リクエストから`keyword`パラメータを取得します。ユーザーが検索フォームに入力したキーワードが入っています。
- **`$request->input("sort", "newest")`**: `sort`パラメータを取得します。第二引数の`"newest"`はデフォルト値で、パラメータが指定されていない場合に使用されます。
- **`Book::with("genres")`**: 書籍データを取得する際に、関連するジャンル情報も一緒に取得するよう指示しています（Eager Loading）。

#### 2. キーワード検索

```php
if ($keyword) {
    $query->where(function ($q) use ($keyword) {
        return $q->where("title", "like", "%{$keyword}%")
                 ->orWhere("author", "like", "%{$keyword}%");
    });
}
```

- **`if ($keyword)`**: キーワードが入力されている場合のみ、この検索条件を適用します。
- **クロージャによるグループ化**: `where(function ($q) { ... })`の形式でクロージャを渡すことで、SQLの`WHERE (title LIKE ... OR author LIKE ...)`というグループ化された条件を作成しています。これはChapter 14で学んだテクニックと同じです。

#### 3. ジャンル絞り込み

```php
if ($genreId) {
    $query->whereHas("genres", function ($q) use ($genreId) {
        return $q->where("genres.id", $genreId);
    });
}
```

- **`whereHas`**: リレーション先のテーブル（`genres`）の条件で絞り込むためのメソッドです。「指定したジャンルIDを持つ書籍のみ」を取得します。

#### 4. 並び替え

```php
switch ($sort) {
    case "oldest":
        $query->orderBy("created_at", "asc");
        break;
    case "rating":
        $query->withAvg("reviews", "rating")->orderByDesc("reviews_avg_rating");
        break;
    case "title":
        $query->orderBy("title", "asc");
        break;
    default:
        $query->orderBy("created_at", "desc");
        break;
}
```

- **`switch`文による分岐**: ユーザーが選択した並び順に応じて、クエリに`orderBy`を追加しています。
- **`withAvg("reviews", "rating")`**: 評価の高い順で並び替える場合、`reviews`リレーションの`rating`カラムの平均値を計算し、`reviews_avg_rating`という名前で取得します。

#### 5. データの取得

```php
$books = $query->get();
```

- **`get()`**: 組み立てたクエリを実行し、結果を取得します。この時点で、検索条件に合致するすべての書籍データがメモリに読み込まれます。

#### 6. レスポンスヘッダの設定

```php
$headers = [
    'Content-Type' => 'text/csv; charset=UTF-8',
    'Content-Disposition' => 'attachment; filename="books_' . date('Ymd_His') . '.csv"',
];
```

- **`Content-Type`**: ブラウザに「これはUTF-8でエンコードされたCSVファイルですよ」と伝えています。
- **`Content-Disposition`**: ブラウザに「このレスポンスを画面に表示するのではなく、ファイルとしてダウンロードさせてください」と指示しています。
    - `attachment`: ファイルとしてダウンロードさせるための指定です。
    - `filename=...`: ダウンロード時のファイル名を指定しています。`date('Ymd_His')`を使って、ダウンロードした日時がファイル名に含まれるようにしています。

#### 7. StreamedResponseの生成

```php
return response()->stream(function () use ($books): void {
    // ... 書き込み処理 ...
}, 200, $headers);
```

- **`response()->stream(...)`**: これが`StreamedResponse`を生成する部分です。第一引数のクロージャ（無名関数）の中で、実際にCSVファイルに書き込む処理を記述します。このクロージャ内の処理は、レスポンスがクライアントに送信される過程で実行されます。
- `200`: HTTPステータスコード「OK」です。
- `$headers`: 先ほど設定したレスポンスヘッダです。

#### 8. CSVファイルへの書き込み処理

```php
$handle = fopen('php://output', 'w');
fwrite($handle, "\xEF\xBB\xBF");
fputcsv($handle, ['ID', 'タイトル', '著者', 'ISBN', '出版日', 'ジャンル', '登録日']);

foreach ($books as $book) {
    fputcsv($handle, [
        $book->id,
        $book->title,
        $book->author,
        $book->isbn ?? '',
        $book->published_date ?? '',
        $book->genres->pluck('name')->implode(', '),
        $book->created_at->format('Y-m-d H:i:s'),
    ]);
}

fclose($handle);
```

- **`fopen('php://output', 'w')`**: `php://output`は書き込み可能なストリームで、プログラムの出力バッファに直接アクセスします。つまり、「画面に出力するのと同じ要領でファイルに書き込む」というイメージです。これを`$handle`（ファイルポインタ）に格納します。
- **`fwrite($handle, "\xEF\xBB\xBF");`**: **BOM（バイトオーダーマーク）**をファイルの先頭に書き込みます。これはExcelでCSVを開いた際の文字化けを防ぐためのおまじないです。
- **`fputcsv($handle, [...])`**: 配列をCSV形式の1行としてファイルに書き込みます。最初の呼び出しでヘッダ行を書き込んでいます。
- **`foreach ($books as $book)`**: 取得した書籍データを1件ずつループ処理し、`fputcsv`でデータ行を書き込んでいきます。
- **`$book->genres->pluck('name')->implode(', ')`**: 書籍に紐づくジャンル名を取得し、カンマ区切りの文字列に変換しています。
- **`fclose($handle)`**: ファイルポインタを閉じます。

### 4.4. 提供されているBladeファイルの確認

このプロジェクトでは、書籍一覧画面のBladeファイル（`resources/views/books/index.blade.php`）が事前に提供されています。提供されているBladeファイルには、既にCSVダウンロードボタンが実装されており、コントローラーの実装により機能するようになります。

**提供されているBladeファイルのポイント:**

- CSVダウンロードボタンは`{{ route('books.export') }}?{{ http_build_query(request()->query()) }}`のように、現在の検索条件をクエリパラメータとして引き継いでいます。
- これにより、ユーザーが検索結果を絞り込んだ状態でCSVエクスポートを実行すると、その検索条件が反映されたCSVファイルがダウンロードされます。

## 5. How to: この実装にたどり着くための調べ方

もし自力でこの実装にたどり着くとしたら、どのように調べれば良いでしょうか？初心者が辿るであろう検索経路を紹介します。

### 調べ方1：「CSVをダウンロードさせたい」場合

**最初の検索（日本語で素朴に）:**
```
Laravel CSV ダウンロード
```

**検索結果から得られる情報:**
- `response()->download()`という簡単な方法が見つかるが、これは既存のファイルをダウンロードさせる方法だとわかる。
- 動的に生成するには`response()->streamDownload()`や`response()->stream()`が良いという情報が見つかる。

**次の疑問と検索:**
```
Laravel streamDownload 使い方
```

**最終的にたどり着く答え:**
- `streamDownload`のクロージャ内で`fopen('php://output', 'w')`や`fputcsv`を使ってCSVを生成する方法がわかる。

### 調べ方2：「大量のデータをCSVエクスポートしたい」場合

**最初の検索（困っていることをそのまま）:**
```
Laravel CSV 大量データ メモリ不足
```

**検索結果から得られる情報:**
- `get()`で全件取得するのが原因だとわかる。
- 対策として`chunk()`や`cursor()`メソッドがあることがわかる。

**次の疑問と検索:**
```
Laravel chunk 使い方
```

**最終的にたどり着く答え:**
- `chunk()`メソッドが、指定した件数ごとにDBからデータを取得し、クロージャを実行してくれることがわかる。
- `StreamedResponse`と組み合わせることで、メモリ消費を抑えながら巨大なファイルを出力できることがわかる。

### 調べ方3：「CSVが文字化けする」場合

**最初の検索（実際に困った状況から）:**
```
Laravel CSV Excel 文字化け
```

**検索結果から得られる情報:**
- 原因がExcelの文字コード認識の問題であることがわかる。
- 解決策として「BOM（バイトオーダーマーク）を付ける」という方法が多数見つかる。

**最終的にたどり着く答え:**
- `fwrite($handle, "\xEF\xBB\xBF");`という具体的なコードをファイルの先頭に記述すれば解決できることがわかる。

## 6. まとめ

このChapterでは、CSVエクスポート機能の実装方法を学びました。今回は模範解答に合わせて`get()`でデータを取得しましたが、実務における大量データ処理の重要性と、その解決策である`StreamedResponse`と`chunk()`の組み合わせについても理解を深めました。常にパフォーマンスを意識することは、プロのエンジニアとして非常に大切なスキルです。
