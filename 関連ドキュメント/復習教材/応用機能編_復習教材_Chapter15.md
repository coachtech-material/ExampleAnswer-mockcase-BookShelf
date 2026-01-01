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
Route::middleware('auth')->group(function () {
    // ...
    Route::get('/books/export/csv', [BookController::class, 'exportCsv'])->name('books.export');
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
        $keyword = $request->input('keyword');
        $genreId = $request->input('genre');

        $query = Book::with('genres');

        if ($keyword) {
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($genreId) {
            $query->whereHas('genres', function ($q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
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
                    $book->published_date?->format('Y-m-d') ?? '',
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

### 3.3. ビューの実装

書籍一覧画面（`resources/views/books/index.blade.php`）に、CSVダウンロードボタンを追加します。このとき、現在の検索条件をクエリパラメータとして引き継ぐようにします。

```html
<!-- resources/views/books/index.blade.php の一部 -->

<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold">書籍一覧</h1>
    <div>
        <a href="{{ route('books.create') }}" class="bg-green-500 text-white px-4 py-2 rounded">新規登録</a>
        <a href="{{ route('books.export', request()->query()) }}" class="bg-gray-500 text-white px-4 py-2 rounded">CSVエクスポート</a>
    </div>
</div>
```

## 4. 先輩エンジニアの思考プロセス

### 思考1：検索ロジックはDRYに保つ

> 「CSVエクスポートの対象データは、一覧表示の検索結果と完全に同じだよね。ということは、`index`メソッドとほぼ同じ検索ロジックが必要になる。ここでコピペするのはDRY（Don't Repeat Yourself）原則に反する。本当は`Book`モデルに**ローカルスコープ**を定義してロジックを共通化するのがベストプラクティスだけど、今回は学習のためにコントローラー内にロジックを再記述してみよう。リファクタリングの機会があれば、スコープ化は最優先候補だね。」

### 思考2：大量データを扱うなら`StreamedResponse`と`chunk()`を検討する

> 「模範解答では`$books = $query->get();`と全件取得している。これはデータ件数が少ないうちは問題ないけど、実務で数万、数十万件のデータを扱う場合はメモリを大量に消費して、最悪サーバーがダウンする可能性がある。その対策として`StreamedResponse`と`chunk()`メソッドの組み合わせがあるんだ。`StreamedResponse`はレスポンスを少しずつ送信する仕組みで、`chunk()`はDBからデータを少しずつ取得する仕組み。この2つを組み合わせることで、メモリ消費を劇的に抑えられる。今回は模範解答に合わせて`get()`を使っているけど、大量データを扱う可能性がある機能では`chunk()`の利用を必ず検討しよう。」

### 思考3：CSVの文字化け対策は必須

> 「CSVをExcelで開いたときに日本語が文字化けする、というのは『あるある』な問題。これはExcelがCSVファイルをUTF-8と正しく認識できないことが原因。対策として、ファイルの先頭に**BOM（バイトオーダーマーク）**という特殊な3バイト（`\xEF\xBB\xBF`）を書き込んであげる。`fwrite($handle, "\xEF\xBB\xBF");`の1行があるだけで、ユーザーからの『文字化けしてる！』という問い合わせを防げるんだ。」

### 思考4：ダウンロードリンクには検索条件を引き継がせる

> 「CSVダウンロードボタンを押したとき、現在の検索条件（キーワードやジャンル）がエクスポート処理に引き継がれないと意味がない。`index`画面のビューで、`route('books.export', request()->query())`のように、`request()->query()`をルートの第二引数に渡すのが簡単で確実。これで現在表示されているページのURLのクエリパラメータ（`?keyword=...`など）が、そのままエクスポート用のURLにも引き継がれる。」

## 5. まとめ

このChapterでは、CSVエクスポート機能の実装方法を学びました。今回は模範解答に合わせて`get()`でデータを取得しましたが、実務における大量データ処理の重要性と、その解決策である`StreamedResponse`と`chunk()`の組み合わせについても理解を深めました。常にパフォーマンスを意識することは、プロのエンジニアとして非常に大切なスキルです。
