# Chapter 19: 応用機能のテスト

---

## 🎯 このセクションで学ぶこと

- 応用機能（高度な検索、CSVエクスポート、外部API連携、公開API）のテスト方法
- `Http::fake()` を使った外部APIのモック化
- ファイルダウンロードのテスト方法
- JSONレスポンスのアサーション
- テストカバレッジの確認方法

---

## 🧠 先輩エンジニアの思考プロセス

### 応用機能のテストで考慮すべきこと

応用機能のテストでは、基本機能のテストに加えて以下の点を考慮する必要があります：

| 機能 | テストの考慮点 |
|:---|:---|
| **高度な検索機能** | 複数の検索条件の組み合わせ、ソート順の検証 |
| **CSVエクスポート** | ファイルダウンロードの検証、文字コードの確認 |
| **外部API連携** | `Http::fake()`によるモック化、エラーハンドリング |
| **公開API** | JSONレスポンスの構造検証、ステータスコード |

### 外部APIのテストにおける重要な考え方

外部API（Google Books APIなど）に依存するテストは、以下の理由からモック化が必須です：

1. **テストの安定性**: 外部APIがダウンしていてもテストが実行できる
2. **テストの速度**: 実際のHTTPリクエストを送信しないため高速
3. **テストの再現性**: 常に同じレスポンスを返すため結果が安定

---

## 19.1. 高度な検索機能のテスト

### テストファイルの作成

```bash
sail artisan make:test SearchTest
```

### SearchTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * キーワード検索が正しく動作することをテスト
     */
    public function test_keyword_search_returns_matching_books(): void
    {
        $user = User::factory()->create();
        
        // テスト用の書籍を作成
        Book::factory()->create(['title' => 'Laravel入門', 'user_id' => $user->id]);
        Book::factory()->create(['title' => 'PHP基礎', 'user_id' => $user->id]);
        Book::factory()->create(['title' => 'JavaScript入門', 'user_id' => $user->id]);

        // 「Laravel」で検索
        $response = $this->get(route('books.index', ['keyword' => 'Laravel']));

        $response->assertStatus(200);
        $response->assertSee('Laravel入門');
        $response->assertDontSee('PHP基礎');
    }

    /**
     * ジャンル絞り込みが正しく動作することをテスト
     */
    public function test_genre_filter_returns_books_in_genre(): void
    {
        $user = User::factory()->create();
        $genre1 = Genre::factory()->create(['name' => 'プログラミング']);
        $genre2 = Genre::factory()->create(['name' => '小説']);

        $book1 = Book::factory()->create(['title' => 'Laravel本', 'user_id' => $user->id]);
        $book2 = Book::factory()->create(['title' => '小説本', 'user_id' => $user->id]);

        $book1->genres()->attach($genre1->id);
        $book2->genres()->attach($genre2->id);

        // プログラミングジャンルで絞り込み
        $response = $this->get(route('books.index', ['genre' => $genre1->id]));

        $response->assertStatus(200);
        $response->assertSee('Laravel本');
        $response->assertDontSee('小説本');
    }

    /**
     * ソート機能が正しく動作することをテスト
     */
    public function test_sort_by_created_at_works(): void
    {
        $user = User::factory()->create();
        
        // 異なる日時で書籍を作成
        $oldBook = Book::factory()->create([
            'title' => '古い本',
            'user_id' => $user->id,
            'created_at' => now()->subDays(10),
        ]);
        $newBook = Book::factory()->create([
            'title' => '新しい本',
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        // 新しい順でソート
        $response = $this->get(route('books.index', ['sort' => 'newest']));

        $response->assertStatus(200);
        // 新しい本が先に表示されることを確認
        $response->assertSeeInOrder(['新しい本', '古い本']);
    }
}
```

### 📖 コードリーディング：検索テストの解説

| コード | 説明 | 役割 |
|:---|:---|:---|
| `$response->assertSee('...')` | 文字列の存在確認 | レスポンスに指定した文字列が含まれることを確認 |
| `$response->assertDontSee('...')` | 文字列の非存在確認 | レスポンスに指定した文字列が含まれないことを確認 |
| `$response->assertSeeInOrder([...])` | 順序の確認 | 指定した文字列が指定した順序で表示されることを確認 |
| `$book->genres()->attach(...)` | リレーションの設定 | 多対多リレーションのデータを追加 |

---

## 19.2. CSVエクスポート機能のテスト

### テストファイルの作成

```bash
sail artisan make:test CsvExportTest
```

### CsvExportTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CSVエクスポートが正しく動作することをテスト
     */
    public function test_csv_export_returns_csv_file(): void
    {
        $user = User::factory()->create();
        
        // テスト用の書籍を作成
        Book::factory()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('books.export'));

        // ステータスコードが200であることを確認
        $response->assertStatus(200);

        // Content-Typeがtext/csvであることを確認
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Content-Dispositionヘッダーが設定されていることを確認
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition')
        );
    }

    /**
     * CSVの内容が正しいことをテスト
     */
    public function test_csv_contains_correct_data(): void
    {
        $user = User::factory()->create();
        
        Book::factory()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('books.export'));

        // CSVの内容を確認
        $content = $response->streamedContent();
        
        $this->assertStringContainsString('テスト書籍', $content);
        $this->assertStringContainsString('テスト著者', $content);
    }

    /**
     * 未認証ユーザーはCSVエクスポートできないことをテスト
     */
    public function test_guest_cannot_export_csv(): void
    {
        $response = $this->get(route('books.export'));

        $response->assertRedirect(route('login'));
    }
}
```

### 📖 コードリーディング：CSVテストの解説

| コード | 説明 | 役割 |
|:---|:---|:---|
| `$response->assertHeader(...)` | ヘッダーの検証 | レスポンスヘッダーが期待値と一致することを確認 |
| `$response->streamedContent()` | ストリームの取得 | StreamedResponseの内容を文字列として取得 |
| `$this->assertStringContainsString(...)` | 文字列の部分一致 | 文字列に指定した部分文字列が含まれることを確認 |

---

## 19.3. ISBN書籍検索機能のテスト（外部API連携）

### テストファイルの作成

```bash
sail artisan make:test IsbnSearchTest
```

### IsbnSearchTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ISBN検索が正しく動作することをテスト（正常系）
     */
    public function test_isbn_search_returns_book_info(): void
    {
        // Google Books APIのレスポンスをモック化
        Http::fake([
            'www.googleapis.com/books/v1/*' => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'テスト書籍タイトル',
                            'authors' => ['テスト著者'],
                            'description' => 'テスト説明文',
                            'industryIdentifiers' => [
                                ['type' => 'ISBN_13', 'identifier' => '9784123456789'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('isbn.fetch', ['isbn' => '9784123456789']));

        $response->assertStatus(200);
        $response->assertJson([
            'title' => 'テスト書籍タイトル',
            'author' => 'テスト著者',
            'description' => 'テスト説明文',
        ]);
    }

    /**
     * 存在しないISBNの場合のテスト
     */
    public function test_isbn_search_returns_404_when_not_found(): void
    {
        // 書籍が見つからない場合のレスポンスをモック化
        Http::fake([
            'www.googleapis.com/books/v1/*' => Http::response([
                'totalItems' => 0,
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('isbn.fetch', ['isbn' => '0000000000000']));

        $response->assertStatus(404);
        $response->assertJson([
            'message' => '書籍が見つかりませんでした',
        ]);
    }

    /**
     * 外部APIがエラーを返した場合のテスト
     */
    public function test_isbn_search_handles_api_error(): void
    {
        // APIエラーをモック化
        Http::fake([
            'www.googleapis.com/books/v1/*' => Http::response([], 500),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('isbn.fetch', ['isbn' => '9784123456789']));

        $response->assertStatus(503);
        $response->assertJson([
            'message' => '外部APIとの通信に失敗しました',
        ]);
    }

    /**
     * 無効なISBN形式の場合のテスト
     */
    public function test_isbn_search_validates_isbn_format(): void
    {
        $user = User::factory()->create();

        // 無効なISBN形式
        $response = $this->actingAs($user)->get(route('isbn.fetch', ['isbn' => 'invalid']));

        $response->assertStatus(422);
    }
}
```

### 📖 コードリーディング：Http::fake() の解説

| コード | 説明 | 役割 |
|:---|:---|:---|
| `Http::fake([...])` | HTTPリクエストのモック化 | 指定したURLパターンに対するレスポンスを偽装する |
| `Http::response([...], 200)` | モックレスポンスの作成 | 指定したボディとステータスコードのレスポンスを返す |
| `'www.googleapis.com/books/v1/*'` | URLパターン | ワイルドカード（*）を使ってURLパターンを指定 |
| `$response->assertJson([...])` | JSONの検証 | レスポンスのJSONが指定した構造を含むことを確認 |

> **💡 Http::fake() の重要性**
> 
> `Http::fake()` を使用することで、外部APIに実際にリクエストを送信せずにテストを実行できます。これにより：
> - 外部APIがダウンしていてもテストが実行できる
> - テストの実行速度が向上する
> - 様々なエラーケース（404、500など）を簡単にテストできる

---

## 19.4. 公開APIのテスト

### テストファイルの作成

```bash
sail artisan make:test PublicApiTest
```

### PublicApiTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍一覧APIが正しいJSON構造を返すことをテスト
     */
    public function test_books_api_returns_correct_json_structure(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'author',
                    'description',
                    'genres' => [
                        '*' => ['id', 'name'],
                    ],
                    'created_at',
                ],
            ],
            'links',
            'meta',
        ]);
    }

    /**
     * 書籍詳細APIが正しいデータを返すことをテスト
     */
    public function test_book_show_api_returns_correct_data(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $book->id,
                'title' => 'テスト書籍',
                'author' => 'テスト著者',
            ],
        ]);
    }

    /**
     * 存在しない書籍のAPIアクセスで404が返ることをテスト
     */
    public function test_book_show_api_returns_404_for_nonexistent_book(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404);
    }

    /**
     * APIのページネーションが正しく動作することをテスト
     */
    public function test_books_api_pagination_works(): void
    {
        $user = User::factory()->create();
        
        // 15件の書籍を作成（1ページ10件の場合、2ページ目が必要）
        Book::factory()->count(15)->create(['user_id' => $user->id]);

        // 1ページ目
        $response = $this->getJson('/api/v1/books?page=1');
        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');

        // 2ページ目
        $response = $this->getJson('/api/v1/books?page=2');
        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }
}
```

### 📖 コードリーディング：APIテストの解説

| コード | 説明 | 役割 |
|:---|:---|:---|
| `$this->getJson(...)` | JSON APIリクエスト | Accept: application/json ヘッダー付きでGETリクエストを送信 |
| `$response->assertJsonStructure([...])` | JSON構造の検証 | レスポンスのJSONが指定した構造を持つことを確認 |
| `$response->assertJsonCount(10, 'data')` | 配列の件数検証 | 指定したキーの配列が指定した件数であることを確認 |
| `'*' => [...]` | 配列要素の構造 | 配列の各要素が指定した構造を持つことを確認 |

---

## 19.5. テストカバレッジの確認

### カバレッジレポートの生成

テストカバレッジを確認するには、以下のコマンドを実行します：

```bash
sail artisan test --coverage
```

### カバレッジ目標

| 項目 | 目標値 |
|:---|:---|
| **全体カバレッジ** | 80%以上 |
| **コントローラー** | 90%以上 |
| **モデル** | 80%以上 |

### カバレッジを上げるためのポイント

1. **正常系と異常系の両方をテストする**
2. **条件分岐（if文）の全てのパスを通す**
3. **バリデーションエラーのケースもテストする**
4. **認証・認可のテストを含める**

---

## 19.6. 全テストの実行

全てのテストを実行して、結果を確認します。

```bash
sail artisan test
```

成功すると以下のような出力が表示されます：

```
   PASS  Tests\Feature\BookTest
  ✓ book index page can be rendered
  ✓ authenticated user can create book
  ...

   PASS  Tests\Feature\SearchTest
  ✓ keyword search returns matching books
  ✓ genre filter returns books in genre
  ✓ sort by created at works

   PASS  Tests\Feature\CsvExportTest
  ✓ csv export returns csv file
  ✓ csv contains correct data
  ✓ guest cannot export csv

   PASS  Tests\Feature\IsbnSearchTest
  ✓ isbn search returns book info
  ✓ isbn search returns 404 when not found
  ✓ isbn search handles api error
  ✓ isbn search validates isbn format

   PASS  Tests\Feature\PublicApiTest
  ✓ books api returns correct json structure
  ✓ book show api returns correct data
  ✓ book show api returns 404 for nonexistent book
  ✓ books api pagination works

  Tests:  XX passed
  Time:   X.XXs
```

---

## 📝 まとめ

このChapterでは、応用機能のテストを実装しました。

### 学んだこと

| 項目 | 内容 |
|:---|:---|
| **Http::fake()** | 外部APIへのリクエストをモック化し、テストを安定させる |
| **assertHeader()** | レスポンスヘッダーを検証する |
| **assertJson()** | JSONレスポンスの内容を検証する |
| **assertJsonStructure()** | JSONレスポンスの構造を検証する |
| **assertJsonCount()** | JSON配列の件数を検証する |
| **getJson()** | JSON APIにリクエストを送信する |
| **streamedContent()** | StreamedResponseの内容を取得する |

### テストカバレッジの重要性

テストカバレッジ80%以上を目標とすることで、コードの品質を一定以上に保つことができます。ただし、カバレッジの数値だけでなく、**意味のあるテスト**を書くことが重要です。

### 外部API連携テストのベストプラクティス

1. **必ず`Http::fake()`を使用する**: 外部APIに依存しないテストを書く
2. **正常系・異常系の両方をテストする**: APIエラー時の挙動も確認する
3. **レスポンスの構造を検証する**: 期待するデータ形式が返されることを確認する

これで、応用機能編のテストが完了しました。全てのテストが通ることを確認してください。
