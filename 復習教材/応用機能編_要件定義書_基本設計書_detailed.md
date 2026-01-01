# 書籍レビューアプリ 要件定義書・基本設計書（応用機能編）- 詳細版

## 1. はじめに

本書は、書籍レビューアプリケーションの「応用機能編」開発における要件と設計を、実際のコードと1対1で対応付ける形で詳細に定義するものである。学習者は本書を参照することで、各機能がどのファイル、どのメソッドで実装されているかを正確に把握し、実装とレビューを効率的に進めることができる。

## 2. 機能要件（応用）

### 2.1. 高度な検索機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| F-1 | キーワード検索 | 書籍一覧ページで、キーワード（タイトル・著者）による部分一致検索ができる。 | - `app/Http/Controllers/BookController.php` @ `index` <br> - `app/Models/Book.php` (scopeSearch) |
| F-2 | ジャンル絞り込み | 検索条件としてジャンルを指定して絞り込みができる。 | - `app/Http/Controllers/BookController.php` @ `index` <br> - `app/Models/Book.php` (scopeGenreFilter) |
| F-3 | 並び順変更 | 検索結果を「登録日」「評価」の昇順・降順で並び替えできる。 | - `app/Http/Controllers/BookController.php` @ `index` <br> - `app/Models/Book.php` (scopeSort) |

### 2.2. CSVエクスポート機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| F-4 | CSVダウンロード | 検索結果をCSV形式でダウンロードできる。 | - `routes/web.php` (`books.csv`) <br> - `app/Http/Controllers/BookController.php` @ `csvExport` |
| F-5 | ストリーミング処理 | 大量データでもメモリを圧迫しないよう、`StreamedResponse` を使用してストリーミングダウンロードされる。 | - `app/Http/Controllers/BookController.php` @ `csvExport` |

### 2.3. ISBN書籍検索機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| F-6 | ISBN検索 | 書籍登録・編集ページで、ISBNを入力してGoogle Books APIから書籍情報を取得し、フォームに自動入力できる。 | - `routes/web.php` (`books.searchByIsbn`) <br> - `app/Http/Controllers/BookController.php` @ `searchByIsbn` <br> - `resources/views/books/create.blade.php` (JavaScript) <br> - `resources/views/books/edit.blade.php` (JavaScript) |

### 2.4. マイ読書レポート機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| F-7 | 読書統計表示 | 自身の読書活動に関する統計（総読了冊数、レビュー投稿日数、読書継続日数、月別統計）が表示される。 | - `routes/web.php` (`reports.index`) <br> - `app/Http/Controllers/ReportController.php` @ `index` |

### 2.5. 公開API機能

| No. | 機能 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| F-8 | 書籍情報提供API | 書籍の一覧と詳細をJSON形式で提供するAPIが動作する。 | - `routes/api.php` <br> - `app/Http/Controllers/Api/V1/BookController.php` @ `index`, `show` <br> - `app/Http/Resources/V1/BookResource.php`, `BookCollection.php` |

## 3. 非機能要件（応用）

| No. | 項目 | 評価基準 | 関連ファイル・メソッド |
|---|---|---|---|
| G-1 | 型宣言の徹底 | すべてのメソッドに引数と戻り値の型が明示的に宣言されているか。 | - プロジェクト全体のPHPファイル |
| G-2 | Collectionメソッド活用 | `ReportController`などで、`map`, `filter`, `groupBy`等のCollectionメソッドを効果的に使用し、宣言的なコードが書けているか。 | - `app/Http/Controllers/ReportController.php` |
| G-3 | 外部連携の実装 | LaravelのHTTPクライアントを正しく使用し、外部APIとの連携が実装されているか。APIキーは`.env`で管理されているか。 | - `app/Http/Controllers/BookController.php` @ `searchByIsbn` <br> - `.env` (`GOOGLE_BOOKS_API_KEY`) |
| G-4 | テスト（応用） | 応用機能（検索、CSV、API等）に対して、正常系・異常系のFeatureテストが記述されているか。 | - `tests/Feature/` ディレクトリ配下の各テストファイル |
| G-5 | テスト（カバレッジ） | テストカバレッジが80%以上に達しているか。 | - `sail artisan test --coverage` で確認 |
