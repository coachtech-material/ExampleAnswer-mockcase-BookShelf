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

## 3. 実装

### 3.1. ルートの定義

まず、CSVダウンロード用のエンドポイントを`routes/web.php`に追加します。

```php
// routes/web.php

// ...
Route::middleware(\'auth\')->group(function () {
    // ...
    Route::get(\'/books/export/csv\', [BookController::class, \'exportCsv\'])->name(\'books.export\');
    // ...
});
```

### 3.2. BookControllerの実装

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
        // indexメソッドの検索ロジックを再利用
        $query = Book::with(\'genres\');

        if ($keyword = $request->input(\'keyword\')) {
            $query->where(function ($q) use ($keyword): void {
                $q->where(\'title\
', \'like\
', "%{$keyword}%")
                  ->orWhere(\'author\
', \'like\
', "%{$keyword}%");
            });
        }

        if ($genreId = $request->input(\'genre\')) {
            $query->whereHas(\'genres\
', function ($q) use ($genreId): void {
                $q->where(\'genres.id\
', $genreId);
            });
        }

        // ヘッダー情報
        $headers = [
            \'Content-Type\' => \'text/csv; charset=UTF-8\
',
            \'Content-Disposition\' => \'attachment; filename="books_\' . date(\'Ymd_His\') . \'.csv\"\
',
        ];

        // StreamedResponseを使用して、データを少しずつ生成・送信
        return response()->stream(function () use ($query): void {
            $handle = fopen(\'php://output\
', \'w\
');

            // BOM（バイトオーダーマーク）を追記して文字化けを防ぐ
            fwrite($handle, "\xEF\xBB\xBF");

            // ヘッダー行を書き込む
            fputcsv($handle, [\'ID\
', \'タイトル\
', \'著者\
', \'ISBN\
', \'出版日\
', \'ジャンル\
', \'登録日\
']);

            // データをチャンク（塊）ごとに取得して書き込む
            $query->chunk(1000, function ($books) use ($handle): void {
                foreach ($books as $book) {
                    fputcsv($handle, [
                        $book->id,
                        $book->title,
                        $book->author,
                        $book->isbn ?? \'\
',
                        $book->published_date?->format(\'Y-m-d\
') ?? \'\
',
                        $book->genres->pluck(\'name\
')->implode(\, \
'),
                        $book->created_at->format(\'Y-m-d H:i:s\
'),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    // ... 他のメソッド ...
}
```

### 3.3. ビューの実装

書籍一覧画面（`resources/views/books/index.blade.php`）に、CSVダウンロードボタンを追加します。このとき、現在の検索条件をクエリパラメータとして引き継ぐようにします。

```html
<!-- resources/views/books/index.blade.php の一部 -->

<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold">書籍一覧</h1>
    <div>
        <a href="{{ route(\'books.create\
') }}" class="bg-green-500 text-white px-4 py-2 rounded">新規登録</a>
        <a href="{{ route(\'books.export\
', request()->query()) }}" class="bg-gray-500 text-white px-4 py-2 rounded">CSVエクスポート</a>
    </div>
</div>
```

## 4. 先輩エンジニアの思考プロセス

### 思考1：検索ロジックはDRYに保つ

> 「CSVエクスポートの対象データは、一覧表示の検索結果と完全に同じだよね。ということは、`index`メソッドとほぼ同じ検索ロジックが必要になる。ここでコピペするのはDRY（Don\'t Repeat Yourself）原則に反する。本当は`Book`モデルに**ローカルスコープ**を定義してロジックを共通化するのがベストプラクティスだけど、今回は学習のためにコントローラー内にロジックを再記述してみよう。リファクタリングの機会があれば、スコープ化は最優先候補だね。」

### 思考2：大量データを扱うなら`StreamedResponse`一択

> 「もし`$books = $query->get();`のように全件取得してからCSVを生成すると、データが10万件あったらメモリを大量に消費して、最悪サーバーがダウンする。これを避けるために`StreamedResponse`を使う。これは、レスポンスを少しずつ、段階的にブラウザに送信（ストリーミング）するための仕組み。クロージャの中に書いた処理が、少しずつ実行されてデータが送られるイメージだ。」

### 思考3：`chunk()`でデータベースへの負荷も軽減

> 「`StreamedResponse`だけだと、結局`$query->get()`で全件取得する際にDBに負荷がかかる。そこで`chunk()`メソッドを組み合わせる。`chunk(1000, function($books) { ... })`と書くと、データベースから一度に1000件ずつデータを取得し、クロージャ内の処理を繰り返してくれる。これで、DBへの負荷も、PHPのメモリ消費も劇的に抑えられる。大量データ処理の鉄板パターンだよ。」

### 思考4：CSVの文字化け対策は必須

> 「CSVをExcelで開いたときに日本語が文字化けする、というのは『あるある』な問題。これはExcelがCSVファイルをUTF-8と正しく認識できないことが原因。対策として、ファイルの先頭に**BOM（バイトオーダーマーク）**という特殊な3バイト（`\xEF\xBB\xBF`）を書き込んであげる。`fwrite($handle, "\xEF\xBB\xBF");`の1行があるだけで、ユーザーからの『文字化けしてる！』という問い合わせを防げるんだ。」

### 思考5：ダウンロードリンクには検索条件を引き継がせる

> 「CSVダウンロードボタンを押したとき、現在の検索条件（キーワードやジャンル）がエクスポート処理に引き継がれないと意味がない。`index`画面のビューで、`route(\'books.export\
', request()->query())`のように、`request()->query()`をルートの第二引数に渡すのが簡単で確実。これで現在表示されているページのURLのクエリパラメータ（`?keyword=...`など）が、そのままエクスポート用のURLにも引き継がれる。」

## 5. まとめ

このChapterでは、CSVエクスポート機能の実装を通じて、実務で非常に重要な**パフォーマンスへの配慮**を学びました。`StreamedResponse`と`chunk()`の組み合わせは、大量データを扱う際の強力な武器になります。また、BOMによる文字化け対策など、ユーザー体験を損なわないための細やかな配慮も、プロのエンジニアとして大切なスキルです。
