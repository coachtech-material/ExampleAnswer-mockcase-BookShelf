# Chapter 13: 基本機能のテスト

---

## 🎯 このセクションで学ぶこと

- PHPUnitを使ったFeatureテストの基本
- Laravelのテストヘルパーの使い方
- 認証が必要な機能のテスト方法
- データベースを使ったテストの書き方

---

## 🧠 先輩エンジニアの思考プロセス

### なぜテストを書くのか

実装が完了したら、次に考えるべきは「この機能が正しく動作することをどう保証するか」です。手動でブラウザを操作して確認することもできますが、機能が増えるたびに確認作業が膨大になります。

テストコードを書くことで、以下のメリットが得られます：

| メリット | 説明 |
|:---|:---|
| **回帰テストの自動化** | 新機能を追加しても、既存機能が壊れていないことを自動で確認できる |
| **リファクタリングの安心感** | コードを改善しても、テストが通れば動作が保証される |
| **仕様のドキュメント化** | テストコードを読めば、その機能が何をすべきかが分かる |
| **バグの早期発見** | 開発中にバグを発見でき、本番環境でのトラブルを防げる |

### テストの種類

Laravelでは主に2種類のテストを書きます：

| 種類 | 説明 | 配置場所 |
|:---|:---|:---|
| **Unitテスト** | 個々のクラスやメソッドを単体でテスト | `tests/Unit/` |
| **Featureテスト** | HTTPリクエストを通じて機能全体をテスト | `tests/Feature/` |

今回は、実際のユーザー操作に近い**Featureテスト**を中心に学びます。

---

## 12.1. テスト環境の確認

### テスト用データベースの設定

Laravelでは、テスト実行時に本番データベースとは別のデータベースを使用します。`phpunit.xml`に以下の設定があることを確認してください：

```xml
<!-- phpunit.xml -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="DB_DATABASE" value="testing"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
</php>
```

> **💡 ポイント**
> 
> `DB_DATABASE` が `testing` に設定されているため、テスト実行時は `testing` データベースが使用されます。これにより、本番データが影響を受けることはありません。

### テストの実行方法

テストは以下のコマンドで実行します：

```bash
# 全てのテストを実行
sail artisan test

# 特定のテストファイルを実行
sail artisan test tests/Feature/BookTest.php

# 特定のテストメソッドを実行
sail artisan test --filter=test_user_can_create_book
```

---

## 12.2. 書籍機能のテスト

### テストファイルの作成

```bash
sail artisan make:test BookTest
```

このコマンドで `tests/Feature/BookTest.php` が作成されます。

### BookTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍一覧ページが表示できることをテスト
     */
    public function test_book_index_page_can_be_rendered(): void
    {
        $response = $this->get(route('books.index'));

        $response->assertStatus(200);
    }

    /**
     * 認証済みユーザーが書籍を登録できることをテスト
     */
    public function test_authenticated_user_can_create_book(): void
    {
        // テスト用のユーザーとジャンルを作成
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // ユーザーとしてログイン
        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'description' => 'これはテスト用の書籍です。',
            'genres' => [$genre->id],
        ]);

        // リダイレクトされることを確認
        $response->assertRedirect(route('books.index'));

        // データベースに書籍が登録されていることを確認
        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'user_id' => $user->id,
        ]);
    }

    /**
     * 未認証ユーザーは書籍を登録できないことをテスト
     */
    public function test_guest_cannot_create_book(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'description' => 'これはテスト用の書籍です。',
            'genres' => [$genre->id],
        ]);

        // ログインページにリダイレクトされることを確認
        $response->assertRedirect(route('login'));
    }

    /**
     * 書籍の所有者のみが編集できることをテスト
     */
    public function test_only_owner_can_edit_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 所有者は編集ページにアクセスできる
        $response = $this->actingAs($owner)->get(route('books.edit', $book));
        $response->assertStatus(200);

        // 他のユーザーは編集ページにアクセスできない（403 Forbidden）
        $response = $this->actingAs($otherUser)->get(route('books.edit', $book));
        $response->assertStatus(403);
    }

    /**
     * 書籍の所有者のみが削除できることをテスト
     */
    public function test_only_owner_can_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 他のユーザーは削除できない
        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));
        $response->assertStatus(403);

        // 所有者は削除できる
        $response = $this->actingAs($owner)->delete(route('books.destroy', $book));
        $response->assertRedirect(route('books.index'));

        // データベースから削除されていることを確認
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }
}
```

### 📖 コードリーディング：テストメソッドの解説

| コード | 説明 | 役割 |
|:---|:---|:---|
| `use RefreshDatabase` | トレイトの使用 | 各テスト実行前にデータベースをリセットし、マイグレーションを実行する |
| `User::factory()->create()` | ファクトリの使用 | テスト用のユーザーをデータベースに作成する |
| `$this->actingAs($user)` | 認証のシミュレート | 指定したユーザーとしてログインした状態でリクエストを送信する |
| `$this->get(route('...'))` | GETリクエスト | 指定したルートにGETリクエストを送信する |
| `$this->post(route('...'), [...])` | POSTリクエスト | 指定したルートにPOSTリクエストとデータを送信する |
| `$response->assertStatus(200)` | ステータスコードの検証 | レスポンスのHTTPステータスコードが200であることを確認する |
| `$response->assertRedirect(...)` | リダイレクトの検証 | 指定したURLにリダイレクトされることを確認する |
| `$this->assertDatabaseHas(...)` | データベースの検証 | 指定したテーブルに指定したデータが存在することを確認する |
| `$this->assertDatabaseMissing(...)` | データベースの検証 | 指定したテーブルに指定したデータが存在しないことを確認する |

---

## 12.3. レビュー機能のテスト

### テストファイルの作成

```bash
sail artisan make:test ReviewTest
```

### ReviewTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証済みユーザーがレビューを投稿できることをテスト
     */
    public function test_authenticated_user_can_create_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('books.reviews.store', $book), [
            'rating' => 5,
            'comment' => 'とても良い本でした！',
        ]);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'とても良い本でした！',
        ]);
    }

    /**
     * レビューの所有者のみが編集できることをテスト
     */
    public function test_only_owner_can_update_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $owner->id,
        ]);

        // 他のユーザーは更新できない
        $response = $this->actingAs($otherUser)->put(
            route('books.reviews.update', [$book, $review]),
            ['rating' => 3, 'comment' => '変更されたコメント']
        );
        $response->assertStatus(403);

        // 所有者は更新できる
        $response = $this->actingAs($owner)->put(
            route('books.reviews.update', [$book, $review]),
            ['rating' => 3, 'comment' => '変更されたコメント']
        );
        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 3,
            'comment' => '変更されたコメント',
        ]);
    }

    /**
     * バリデーションエラーのテスト
     */
    public function test_review_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // ratingが範囲外の場合
        $response = $this->actingAs($user)->post(route('books.reviews.store', $book), [
            'rating' => 6, // 1-5の範囲外
            'comment' => 'コメント',
        ]);

        $response->assertSessionHasErrors('rating');
    }
}
```

---

## 12.4. お気に入り機能のテスト

### テストファイルの作成

```bash
sail artisan make:test FavoriteTest
```

### FavoriteTest.php の実装

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * お気に入りの追加と解除ができることをテスト
     */
    public function test_user_can_toggle_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // お気に入りに追加
        $response = $this->actingAs($user)->post(route('favorites.toggle', $book));
        $response->assertRedirect();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // お気に入りから解除
        $response = $this->actingAs($user)->post(route('favorites.toggle', $book));
        $response->assertRedirect();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * お気に入り一覧ページが表示できることをテスト
     */
    public function test_favorite_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('favorites.index'));

        $response->assertStatus(200);
    }
}
```

---

## 12.5. ファクトリの準備

テストで使用するファクトリが必要です。以下のファクトリを作成・更新してください。

### GenreFactory.php

```bash
sail artisan make:factory GenreFactory
```

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GenreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
```

### BookFactory.php

```bash
sail artisan make:factory BookFactory
```

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'description' => fake()->paragraph(),
            'user_id' => User::factory(),
        ];
    }
}
```

### ReviewFactory.php

```bash
sail artisan make:factory ReviewFactory
```

```php
<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(),
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
        ];
    }
}
```

---

## 12.6. テストの実行と確認

全てのテストを実行して、結果を確認します。

```bash
sail artisan test
```

成功すると以下のような出力が表示されます：

```
   PASS  Tests\Feature\BookTest
  ✓ book index page can be rendered
  ✓ authenticated user can create book
  ✓ guest cannot create book
  ✓ only owner can edit book
  ✓ only owner can delete book

   PASS  Tests\Feature\ReviewTest
  ✓ authenticated user can create review
  ✓ only owner can update review
  ✓ review validation errors

   PASS  Tests\Feature\FavoriteTest
  ✓ user can toggle favorite
  ✓ favorite index page can be rendered

  Tests:  10 passed
  Time:   1.23s
```

---

## 📝 まとめ

このChapterでは、Laravelのテスト機能を使って基本機能のテストを実装しました。

### 学んだこと

| 項目 | 内容 |
|:---|:---|
| **RefreshDatabase** | 各テスト実行前にデータベースをリセットする |
| **Factory** | テスト用のダミーデータを簡単に作成する |
| **actingAs()** | 認証済みユーザーとしてリクエストを送信する |
| **assertStatus()** | HTTPステータスコードを検証する |
| **assertRedirect()** | リダイレクト先を検証する |
| **assertDatabaseHas()** | データベースにデータが存在することを検証する |
| **assertDatabaseMissing()** | データベースにデータが存在しないことを検証する |
| **assertSessionHasErrors()** | バリデーションエラーを検証する |

### テストを書く際のポイント

1. **1つのテストメソッドでは1つのことだけをテストする**
2. **テストメソッド名は何をテストしているか分かるように命名する**
3. **Arrange（準備）→ Act（実行）→ Assert（検証）の流れを意識する**
4. **正常系だけでなく、異常系（エラーケース）もテストする**

テストを書くことで、コードの品質を保ちながら安心して開発を進めることができます。
