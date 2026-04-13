# Chapter 14: 品質の番人 - 基本機能のテスト

## 🎯 このChapterの目標

ここまでに実装した全機能に対して、テストコードを作成します。テストは「コードでコードをテストする」仕組みであり、一度書いてしまえば、コマンド一つで何度でもアプリケーション全体の動作を検証できます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| PHPUnit の基本 | `Unitテスト` と `Featureテスト` の違いと使い分け |
| ファクトリ | テスト用のダミーデータを生成する仕組み |
| CRUD操作のテスト | 作成・読み取り・更新・削除を網羅するFeatureテスト |
| 認証・認可のテスト | `actingAs()` でログインユーザーを模擬する方法 |
| APIテスト | `getJson()`, `postJson()` でAPIエンドポイントをテストする方法 |

---

## 📖 背景知識

### なぜテストを書くのか？

| メリット | 説明 |
|:---|:---|
| **品質の保証** | 新機能追加時に既存機能が壊れていないことを自動で確認できます。 |
| **リファクタリングの安心感** | テストがあれば、コード修正後も動作が保証されます。 |
| **生きたドキュメント** | テストコードを読めば、機能の仕様が分かります。 |

### テストの種類

| 種類 | テスト対象 | 配置場所 |
|:---|:---|:---|
| **Unitテスト** | モデルのリレーションなど個々のクラス | `tests/Unit/` |
| **Featureテスト** | HTTPリクエストを通じた機能全体 | `tests/Feature/` |

---

## 📋 テスト環境の準備

### テスト用データベース（`phpunit.xml`）

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

インメモリSQLiteを使うことで、テストが高速に実行できます。

### ファクトリの準備

```bash
sail artisan make:factory GenreFactory --model=Genre
sail artisan make:factory BookFactory --model=Book
sail artisan make:factory ReviewFactory --model=Review
```

#### `database/factories/GenreFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
```

#### `database/factories/BookFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->numerify(str_repeat('#', 13)),
            'published_date' => fake()->date(),
            'description' => fake()->paragraph(),
            'image_url' => fake()->url(),
        ];
    }
}
```

#### `database/factories/ReviewFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
```

---

## 🚀 コードの実装

### Unit テスト

#### `tests/Unit/BookModelTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_relationships_are_defined(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create();
        $review = Review::factory()->for($book)->for($user)->create();
        $book->genres()->attach($genre);
        $book->favoritedByUsers()->attach($user->id);

        $this->assertTrue($book->user->is($user));
        $this->assertTrue($book->reviews->contains($review));
        $this->assertTrue($book->genres->contains($genre));
        $this->assertTrue($book->favoritedByUsers->contains($user));
    }
}
```

#### `tests/Unit/UserModelTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relationships_are_defined(): void
    {
        $user = User::factory()->create();
        $ownedBook = Book::factory()->for($user)->create();
        $ownedReview = Review::factory()->for($ownedBook)->for($user)->create();

        $favoriteBook = Book::factory()->create();
        $user->favoriteBooks()->attach($favoriteBook->id);

        $likedReview = Review::factory()->create();
        $user->likedReviews()->attach($likedReview->id);

        $this->assertTrue($user->books->contains($ownedBook));
        $this->assertTrue($user->reviews->contains($ownedReview));
        $this->assertTrue($user->favoriteBooks->contains($favoriteBook));
        $this->assertTrue($user->likedReviews->contains($likedReview));
    }
}
```

### Feature テスト（主要なもの抜粋）

#### `tests/Feature/BookControllerTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_index_page_can_be_rendered(): void
    {
        Book::factory()->count(2)->create();

        $this->get(route('books.index'))
            ->assertOk();
    }

    public function test_guest_cannot_view_create_form(): void
    {
        $this->get(route('books.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $payload = [
            'title' => 'My Test Book',
            'author' => 'Test Author',
            'isbn' => '1111111111111',
            'published_date' => '2024-01-01',
            'description' => 'Test description',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $book = Book::where('title', 'My Test Book')->first();
        $this->assertNotNull($book);
        $response->assertRedirect(route('books.show', $book));
    }

    public function test_only_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('books.edit', $book))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route('books.edit', $book))
            ->assertForbidden();
    }
}
```

#### `tests/Feature/FavoriteTest.php`

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

    public function test_user_can_add_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $from = route('books.show', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_toggle_works_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $from = route('books.show', $book);

        // Add favorite
        $this->actingAs($user)->from($from)->post(route('favorites.toggle', $book));
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);

        // Remove favorite
        $this->actingAs($user)->from($from)->post(route('favorites.toggle', $book));
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
    }
}
```

#### `tests/Feature/Api/V1/BookApiTest.php`（抜粋）

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_data_and_meta_structure(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->count(3)->for($user)->create()->each(function ($book) use ($genre) {
            $book->genres()->attach($genre);
        });

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'author', 'isbn', 'genres', 'average_rating', 'review_count'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_store_creates_book_and_attaches_genres(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $payload = [
            'user_id' => $user->id,
            'title' => 'New API Book',
            'author' => 'API Author',
            'isbn' => '9784000000000',
            'published_date' => '2024-01-01',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        $response = $this->postJson('/api/v1/books', $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('books', ['title' => 'New API Book']);
    }

    public function test_show_returns_custom_404_json_when_not_found(): void
    {
        $response = $this->getJson('/api/v1/books/99999');
        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }
}
```

---

## 🔍 コードリーディング

### テストで頻出するメソッド

| メソッド | 解説 |
|:---|:---|
| `User::factory()->create()` | ファクトリでダミーユーザーを作成しDBに保存。 |
| `Book::factory()->for($user)->create()` | 指定ユーザーに紐づく書籍を作成。 |
| `$this->actingAs($user)` | 指定ユーザーとしてログインした状態を模擬。 |
| `$this->get(route('books.index'))` | GETリクエストを送信。 |
| `->assertOk()` | HTTPステータスコード200を確認。 |
| `->assertRedirect(route('login'))` | ログインページへのリダイレクトを確認。 |
| `->assertForbidden()` | HTTPステータスコード403を確認。 |
| `$this->assertDatabaseHas('books', [...])` | DBに指定レコードが存在することを確認。 |
| `$this->assertDatabaseMissing('books', [...])` | DBに指定レコードが存在しないことを確認。 |
| `$this->getJson('/api/v1/books')` | JSON形式のGETリクエスト（API用）。 |
| `->assertJsonStructure([...])` | JSONレスポンスの構造を確認。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| テストの書き方 | 「Laravel でFeatureテストを書く基本的な方法を教えてください。actingAs の使い方も含めて。」 |
| ファクトリの使い方 | 「Laravel のファクトリで for() メソッドを使ってリレーション先を指定する方法を教えてください。」 |
| アサーションメソッド | 「Laravel テストで使える assertOk, assertForbidden, assertDatabaseHas 等のアサーションメソッド一覧を教えてください。」 |

---

## ✅ テストの実行

基本版では全てのルートが定義済みのため、テストは即座に実行できます。

```bash
sail artisan test
```

カバレッジ目標は **60%** です。

### 期待される出力例

```
Tests:    XX passed
Time:     X.XXs
```

全テストがパスすれば、アプリケーションの基本機能が正しく動作していることが保証されます。

---

## ✨ このChapterのまとめ

### テストファイル一覧

| テストファイル | テスト内容 |
|:---|:---|
| `tests/Unit/UserModelTest.php` | Userモデルのリレーション検証 |
| `tests/Unit/BookModelTest.php` | Bookモデルのリレーション検証 |
| `tests/Unit/ReviewModelTest.php` | Reviewモデルのリレーション検証 |
| `tests/Feature/BookControllerTest.php` | 書籍CRUD + 認可テスト |
| `tests/Feature/BookRequestTest.php` | 書籍バリデーションテスト |
| `tests/Feature/ReviewTest.php` | レビューCRUD + 認可テスト |
| `tests/Feature/ReviewPolicyTest.php` | レビューポリシーのユニットテスト |
| `tests/Feature/FavoriteTest.php` | お気に入りトグルテスト |
| `tests/Feature/ReviewLikeTest.php` | いいねトグルテスト |
| `tests/Feature/GenreTest.php` | ジャンルCRUD + 削除制約テスト |
| `tests/Feature/RankingTest.php` | ランキング表示テスト |
| `tests/Feature/RedirectIfAuthenticatedTest.php` | 認証済みユーザーリダイレクトテスト |
| `tests/Feature/Api/V1/BookApiTest.php` | 公開API全エンドポイントテスト |

これで、BookShelfアプリケーションの基本機能が全て実装・テスト完了です。
