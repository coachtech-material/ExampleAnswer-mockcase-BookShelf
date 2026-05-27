# Chapter 14: 基本機能のテスト

---

## 🎯 このセクションで学ぶこと

- PHPUnitを使った**Unitテスト**と**Featureテスト**の基本
- Laravelのテストヘルパーとアサーションメソッドの活用
- **モデルのリレーションシップ**を検証するUnitテスト
- **CRUD操作**（作成、読み取り、更新、削除）を網羅するFeatureテスト
- **認証**・**認可**（ポリシー）が必要な機能のテスト
- **バリデーション**ルールを検証するテスト
- テスト用のダミーデータを生成する**ファクトリ**の作り方と使い方

> **重要:** 提供された Blade テンプレート（navigation.blade.php 等）は、Chapter 15~17 で追加するルート（`reports.index` / `reading-plans.index` / `notifications.index` 等）を参照しています。テストファイルはこの Chapter で作成しますが、**`sail artisan test` の実行は Chapter 17 完了後に行ってください。** Chapter 15~17 のコントローラ・ルートが未定義の状態でテストを実行すると、ビューのレンダリングでエラーが発生します。

以下のテストファイルを一括作成します:

```bash
mkdir -p tests/Feature/Api/V1
touch tests/Unit/UserModelTest.php tests/Unit/BookModelTest.php tests/Unit/ReviewModelTest.php \
      tests/Feature/BookRequestTest.php tests/Feature/BookControllerTest.php tests/Feature/ReviewTest.php \
      tests/Feature/FavoriteTest.php tests/Feature/ReviewLikeTest.php tests/Feature/GenreTest.php \
      tests/Feature/RankingTest.php tests/Feature/RedirectIfAuthenticatedTest.php tests/Feature/ReviewPolicyTest.php \
      tests/Feature/Api/V1/BookApiTest.php
```

---

## 🧠 先輩エンジニアの思考プロセス

### なぜテストを書くのか？

実装が完了したら、次に考えるべきは「**このアプリケーションが仕様通りに、そして安定して動作することをどう保証するか**」です。手動でブラウザをポチポチとクリックして確認することもできますが、機能が増え、アプリケーションが複雑になるにつれて、その確認作業は膨大かつ非効率になります。一度確認した機能が、新しい機能を追加したことで壊れてしまう「**デグレード**」も頻繁に発生します。

そこで「**テストコード**」の出番です。テストコードは「コードでコードをテストする」仕組みであり、一度書いてしまえば、コマンド一つで何度でも一瞬でアプリケーション全体の動作を検証できます。

| メリット | 説明 |
|:---|:---|
| **品質の保証と回帰テストの自動化** | 新機能を追加したり、既存のコードを修正（リファクタリング）したりしても、既存の機能が壊れていないこと（デグレードしていないこと）を自動で確認できます。これにより、常に安定した品質を保つことができます。 |
| **リファクタリングの安心感** | 「このコード、もっと綺麗に書けるな」と思っても、変更による影響範囲が分からず、修正をためらうことがあります。テストがあれば、修正後もテストが通ることを確認するだけで、動作が保証されるという安心感が得られます。 |
| **生きたドキュメント** | テストコードを読めば、その機能が「何をすべきか」「どのような入力に対して、どのような出力を期待しているか」という**仕様**が明確に分かります。仕様書が古くなることはあっても、テストコードは常に最新の仕様を反映した「生きたドキュメント」になります。 |
| **バグの早期発見** | 開発の早い段階でバグを発見できます。本番環境でユーザーがバグに遭遇してから慌てて修正するよりも、開発中に発見して修正する方が、はるかにコストもリスクも低くなります。 |

### テストの種類：Unitテスト vs Featureテスト

Laravelでは主に2種類のテストを書きます。今回はその両方を実装します。

| 種類 | テスト対象 | 説明 | 配置場所 |
|:---|:---|:---|:---|
| **Unitテスト (単体テスト)** | 個々のクラスやメソッド | アプリケーションの小さな部品（例えば、モデルのリレーションシップ定義など）が正しく機能するかを個別にテストします。他の部品から隔離してテストするため、高速に実行できます。 | `tests/Unit/` |
| **Featureテスト (機能テスト)** | HTTPリクエストを通じた機能全体 | 実際のユーザー操作を模倣し、HTTPリクエストを送信してからレスポンスが返ってくるまでの一連の流れをテストします。複数のコンポーネント（コントローラー、モデル、ビューなど）が連携して正しく動作するかを確認します。 | `tests/Feature/` |

> **💡 テスト戦略**
> 一般的には、アプリケーションの安定性を確保するために、**Featureテストで主要な機能の正常系・異常系のシナリオを網羅**しつつ、**Unitテストでモデルの複雑なロジックや重要なメソッドを個別にテスト**する、という組み合わせが効果的です。

---

## 14.1. テスト環境の準備

### テスト用データベースの設定

Laravelは、テスト実行時に本番のデータベースを汚さないよう、別のデータベースを使用する仕組みがデフォルトで備わっています。

まず、プロジェクトのルートにある`phpunit.xml`ファイルを確認してください。このファイルはPHPUnitの設定ファイルです。

```xml
<!-- phpunit.xml -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_DRIVER" value="array"/>
    <!-- ↓ この行に注目！ -->
    <env name="DB_DATABASE" value="testing"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
</php>
```

> **💡 ポイント：テスト専用データベース**
> `DB_DATABASE` を `testing` に設定することで、テスト実行時に**テスト専用のデータベース（`testing`）** が使用されます。アプリケーションの開発用 DB（`bookshelf`）と分離することで、テスト実行中にアプリケーションのデータが消えてしまうのを防ぎます。`compose.yaml` 内の `mysql` サービスは初回起動時に `bookshelf` と `testing` の 2 つのデータベースを自動作成するように設定されています。

### RefreshDatabase トレイト

各テストクラスで使用する `use RefreshDatabase;` は、Laravelが提供する非常に便利なトレイトです。これを使用すると、**各テストメソッドが実行される前に、データベースが自動的にリセット**されます。具体的には、マイグレーションが実行され、テーブルが再作成されます。これにより、前のテストで作成されたデータが後のテストに影響を与えることを防ぎ、各テストをクリーンな状態で実行できます。

---

## 14.2. ファクトリの準備

テストを実行するには、前提となるデータ（ユーザー、書籍、レビューなど）が必要です。毎回手動でデータを作成するのは大変なので、Laravelでは**ファクトリ**という仕組みを使って、テスト用のダミーデータを簡単に生成できます。

以下のコマンドで、各モデルに対応するファクトリを作成します。

```bash
sail artisan make:factory GenreFactory --model=Genre
sail artisan make:factory BookFactory --model=Book
sail artisan make:factory ReviewFactory --model=Review
```

作成されたファクトリファイルを、以下のように編集します。

### GenreFactory.php

`database/factories/GenreFactory.php`

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

### BookFactory.php

`database/factories/BookFactory.php`

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

### ReviewFactory.php

`database/factories/ReviewFactory.php`

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

## 14.3. Unitテスト：モデルのリレーションシップ

最初に、アプリケーションの心臓部であるモデルのリレーションシップが正しく定義されているかを確認するUnitテストを作成します（テストファイルは本 Chapter 冒頭で一括 `touch` 済みのため、ここでは内容を埋めるだけです）。

### UserModelTest

`tests/Unit/UserModelTest.php`

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

### BookModelTest

`tests/Unit/BookModelTest.php`

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

### ReviewModelTest

`tests/Unit/ReviewModelTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_relationships_are_defined(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($user)->for($book)->create();
        $review->likedByUsers()->attach($user->id);

        $this->assertTrue($review->user->is($user));
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->likedByUsers->contains($user));
    }
}
```

#### 📖 詳細解説: Unitテスト

> **💡 先輩エンジニアの視点**
> なぜモデルのリレーションシップをテストするのでしょうか？それは、リレーションシップがアプリケーションのデータの整合性を保つための「骨格」だからです。リレーションの定義ミスは、`null`エラーや予期せぬ動作の温床です。このテストは、その「骨格」が正しく組まれていることを保証する、非常に重要で基本的なテストなのです。

- **`for()`**: `Book::factory()->for($user)->create()` のように、リレーション先の親モデルを指定します。
- **`attach()`**: `$user->favoriteBooks()->attach($favoriteBook->id)` のように、多対多リレーションの中間テーブルにレコードを追加します。
- **`contains()`**: `$user->books->contains($ownedBook)` のように、コレクション内に指定したモデルインスタンスが存在するかを判定します。
- **`is()`**: `$book->user->is($user)` のように、2つのモデルが同じIDとテーブルを持つか（つまり、同じレコードか）を比較します。

---

## 14.4. Featureテスト：各機能の振る舞い

次に、ユーザーの操作を模倣して、各機能が全体として正しく動作するかを検証するFeatureテストを作成します（テストファイルは本 Chapter 冒頭で一括 `touch` 済みのため、ここでは内容を埋めるだけです）。

### BookControllerTest (書籍管理機能)

`tests/Feature/BookControllerTest.php`

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

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('books.create'))
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

        $payload = $this->validBookData([
            'title' => 'My Test Book',
            'isbn' => '1111111111111',
            'genres' => $genres->pluck('id')->toArray(),
        ]);

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $book = Book::where('title', 'My Test Book')->first();
        $this->assertNotNull($book);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'My Test Book',
        ]);

        foreach ($genres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    public function test_book_store_validation_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'invalid-date',
            'genres' => [],
        ]);

        $response->assertSessionHasErrors(['title', 'isbn', 'genres']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_show_page_can_be_rendered(): void
    {
        $book = Book::factory()->create(['title' => 'Detail Book']);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Detail Book');
    }

    public function test_authenticated_user_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $originalGenre = Genre::factory()->create();
        $book->genres()->attach($originalGenre);
        $newGenres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            'title' => 'Updated Title',
            'isbn' => '9876543210123',
            'genres' => $newGenres->pluck('id')->toArray(),
        ]);

        $response = $this->actingAs($user)->put(route('books.update', $book), $payload);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'isbn' => '9876543210123',
        ]);

        foreach ($newGenres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $originalGenre->id,
        ]);
    }

    public function test_authenticated_user_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_non_owner_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->delete(route('books.destroy', $book))
            ->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
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

    private function validBookData(array $overrides = []): array
    {
        $genres = $overrides['genres'] ?? Genre::factory()->count(2)->create()->pluck('id')->toArray();

        return array_merge([
            'title' => 'Sample Book',
            'author' => 'Sample Author',
            'isbn' => '1234567890123',
            'published_date' => '2024-01-01',
            'description' => 'Sample description',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => $genres,
        ], $overrides);
    }
}
```

### ReviewTest (レビュー機能)

`tests/Feature/ReviewTest.php`

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

    public function test_authenticated_user_can_create_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => 'Great read',
        ]);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
        ]);
    }

    public function test_guest_cannot_create_review(): void
    {
        $book = Book::factory()->create();

        $this->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => 'Guest review',
        ])->assertRedirect(route('login'));
    }

    public function test_review_store_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 6,
            'comment' => str_repeat('a', 1001),
        ]);

        $response->assertSessionHasErrors(['rating', 'comment']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_store_rating_below_min(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 0,
            'comment' => '下限違反',
        ])->assertSessionHasErrors(['rating']);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_only_owner_can_edit_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('reviews.edit', $review))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route('reviews.edit', $review))
            ->assertForbidden();
    }

    public function test_only_owner_can_update_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create(['rating' => 3]);
        $otherUser = User::factory()->create();

        $this->actingAs($owner)
            ->put(route('reviews.update', $review), [
                'rating' => 4,
                'comment' => 'Updated comment',
            ])
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => 'Updated comment',
        ]);

        $this->actingAs($otherUser)
            ->put(route('reviews.update', $review), [
                'rating' => 2,
                'comment' => 'Not allowed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => 'Updated comment',
        ]);
    }

    public function test_only_owner_can_delete_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);

        $this->actingAs($owner)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
```

### FavoriteTest (お気に入り機能)

`tests/Feature/FavoriteTest.php`

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

    public function test_user_can_remove_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);
        $from = route('books.show', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_toggle_works_correctly(): void
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

        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->actingAs($user)
            ->from($from)
            ->post(route('favorites.toggle', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => 'Favorite Book']);
        $user->favoriteBooks()->attach($book->id);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('Favorite Book');
    }

    public function test_guest_cannot_toggle_favorite(): void
    {
        $book = Book::factory()->create();

        $this->post(route('favorites.toggle', $book))
            ->assertRedirect(route('login'));
    }
}
```

### ReviewLikeTest (いいね機能)

`tests/Feature/ReviewLikeTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_like_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $from = route('books.show', $review->book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('reviews.like', $review))
            ->assertRedirect($from);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_user_can_unlike_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);
        $from = route('books.show', $review->book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('reviews.like', $review))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_like_toggle_works_correctly(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $from = route('books.show', $review->book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('reviews.like', $review))
            ->assertRedirect($from);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->actingAs($user)
            ->from($from)
            ->post(route('reviews.like', $review))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->actingAs($user)
            ->from($from)
            ->post(route('reviews.like', $review))
            ->assertRedirect($from);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_guest_cannot_like_review(): void
    {
        $review = Review::factory()->create();

        $this->post(route('reviews.like', $review))
            ->assertRedirect(route('login'));
    }
}
```

### GenreTest (ジャンル管理機能)

`tests/Feature/GenreTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        Genre::factory()->count(3)->create();

        $this->actingAs($user)
            ->get(route('genres.index'))
            ->assertOk();
    }

    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('genres.store'), [
                'name' => 'Science Fiction',
            ])
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['name' => 'Science Fiction']);
    }

    public function test_genre_store_validation_errors(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create(['name' => 'Fantasy']);

        $this->actingAs($user)
            ->post(route('genres.store'), ['name' => 'Fantasy'])
            ->assertSessionHasErrors(['name']);
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('genres.create'))
            ->assertOk();
    }

    public function test_genre_show_page_displays_books(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'Mystery']);
        $book = Book::factory()->create(['title' => 'Mystery Book']);
        $book->genres()->attach($genre);

        $this->actingAs($user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('Mystery Book');
    }

    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'History']);

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => 'World History'])
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => 'World History',
        ]);
    }

    public function test_authenticated_user_can_view_edit_form(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'Poetry']);

        $this->actingAs($user)
            ->get(route('genres.edit', $genre))
            ->assertOk();
    }

    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'Adventure']);
        $book = Book::factory()->create();
        $book->genres()->attach($genre);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'Short Stories']);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }
}
```

#### 📖 詳細解説: Featureテスト

> **💡 先輩エンジニアの視点**
> Featureテストは、アプリケーションの根幹である各機能の「振る舞い」を保証するテスト群です。一覧表示、作成、更新、削除といった一連のライフサイクルをテストすることで、ユーザーが最も頻繁に利用する機能の安定性を担保します。特に重要なのは、**正常系**（正しく動作するケース）だけでなく、**異常系**（ゲストユーザーのアクセス、バリデーションエラー、権限のない操作）もテストすることです。これにより、予期せぬ操作によるシステムの脆弱性を未然に防ぐことができます。

- **`actingAs($user)`**: 指定したユーザーでログインしている状態をシミュレートします。
- **`get()` / `post()` / `put()` / `delete()`**: 指定したルートに各HTTPメソッドでリクエストを送信します。
- **`assertOk()`**: ステータスコードが200であることを表明します。
- **`assertRedirect($route)`**: 指定したルートにリダイレクトされたことを確認します。
- **`assertForbidden()`**: ステータスコードが403（Forbidden）であることを表明します。
- **`assertSee($text)`**: レスポンスのHTML内に指定した文字列が含まれていることを確認します。
- **`assertDatabaseHas($table, $data)`**: 指定したテーブルに、指定した条件のレコードが存在することを確認します。
- **`assertDatabaseMissing($table, $data)`**: レコードが存在しないことを確認します。
- **`assertSessionHasErrors($keys)`**: セッションに指定したキーのバリデーションエラーが含まれていることを確認します。

### BookRequestTest (書籍 FormRequest)

`tests/Feature/BookRequestTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_タイトルは必須(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => '',
            'author' => 'テスト著者',
            'genres' => [$genre->id],
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_著者は必須(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => '',
            'genres' => [$genre->id],
        ]);

        $response->assertSessionHasErrors('author');
    }

    public function test_isbnは13桁でなければならない(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '123456789',
            'genres' => [$genre->id],
        ]);

        $response->assertSessionHasErrors('isbn');
    }

    public function test_ジャンルは必須(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'genres' => [],
        ]);

        $response->assertSessionHasErrors('genres');
    }
}
```

### RankingTest (ランキング機能)

`tests/Feature/RankingTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_page_can_be_rendered(): void
    {
        $book = Book::factory()->create(['title' => 'Ranked Book']);
        Review::factory()->for($book)->count(2)->create(['rating' => 5]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('Ranked Book');
    }

    public function test_ranking_is_ordered_by_average_rating(): void
    {
        $topBook = Book::factory()->create(['title' => 'Top Book']);
        Review::factory()->for($topBook)->count(2)->create(['rating' => 5]);

        $middleBook = Book::factory()->create(['title' => 'Middle Book']);
        Review::factory()->for($middleBook)->count(2)->create(['rating' => 3]);

        $lowBook = Book::factory()->create(['title' => 'Low Book']);
        Review::factory()->for($lowBook)->count(2)->create(['rating' => 1]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Top Book',
                'Middle Book',
                'Low Book',
            ]);
    }
}
```

### RedirectIfAuthenticatedTest (認証ミドルウェア)

`tests/Feature/RedirectIfAuthenticatedTest.php`

```php
<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RedirectIfAuthenticatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_is_redirected_to_home(): void
    {
        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertEquals(url(RouteServiceProvider::HOME), $response->headers->get('Location'));

        Auth::logout();
    }

    public function test_guest_can_access_route(): void
    {
        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, fn () => response('allowed'));

        $this->assertEquals('allowed', $response->getContent());
    }
}
```

### ReviewPolicyTest (レビュー Policy)

`tests/Feature/ReviewPolicyTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_レビュー投稿者のみが編集できる(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->genres()->attach($genre->id);
        $review = Review::factory()->create(['user_id' => $owner->id, 'book_id' => $book->id]);

        $this->assertTrue($owner->can('update', $review));
        $this->assertFalse($other->can('update', $review));
    }

    public function test_レビュー投稿者のみが削除できる(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->genres()->attach($genre->id);
        $review = Review::factory()->create(['user_id' => $owner->id, 'book_id' => $book->id]);

        $this->assertTrue($owner->can('delete', $review));
        $this->assertFalse($other->can('delete', $review));
    }
}
```

### BookApiTest (公開 API)

`tests/Feature/Api/V1/BookApiTest.php`

公開 API（書籍 CRUD・認証なし）の Feature テスト。Sanctum 認証付き完全版は応用機能編 Chapter 21 で置き換えます。

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    // ===== GET /api/v1/books =====

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
                '*' => [
                    'id', 'title', 'author', 'isbn', 'published_date',
                    'description', 'image_url', 'genres',
                    'average_rating', 'review_count',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonMissing(['reviews' => []]);
    }

    public function test_index_filters_by_keyword(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $hit = Book::factory()->for($user)->create(['title' => 'Laravel入門']);
        $miss = Book::factory()->for($user)->create(['title' => 'PHP基礎']);
        $hit->genres()->attach($genre);
        $miss->genres()->attach($genre);

        $response = $this->getJson('/api/v1/books?keyword=Laravel');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Laravel入門');
    }

    public function test_index_filters_by_genre_id(): void
    {
        $user = User::factory()->create();
        $genreA = Genre::factory()->create();
        $genreB = Genre::factory()->create();
        $bookA = Book::factory()->for($user)->create(['title' => 'BookA']);
        $bookB = Book::factory()->for($user)->create(['title' => 'BookB']);
        $bookA->genres()->attach($genreA);
        $bookB->genres()->attach($genreB);

        $response = $this->getJson("/api/v1/books?genre_id={$genreA->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'BookA');
    }

    public function test_index_pagination_per_page(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->count(5)->for($user)->create()->each(function ($book) use ($genre) {
            $book->genres()->attach($genre);
        });

        $response = $this->getJson('/api/v1/books?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonPath('meta.total', 5);
    }

    public function test_index_returns_422_when_per_page_exceeds_max(): void
    {
        $response = $this->getJson('/api/v1/books?per_page=200');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    // ===== GET /api/v1/books/{book} =====

    public function test_show_returns_book_with_reviews(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create();
        $book->genres()->attach($genre);
        Review::factory()->for($book)->for($user)->create();

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id', 'title', 'author', 'isbn', 'published_date',
                'description', 'image_url', 'genres',
                'average_rating', 'review_count',
                'reviews' => [
                    '*' => ['id', 'user_name', 'rating', 'comment', 'created_at'],
                ],
            ],
        ]);
        $response->assertJsonPath('data.id', $book->id);
    }

    public function test_show_returns_custom_404_json_when_not_found(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    // ===== POST /api/v1/books =====

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
            'description' => 'desc',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => $genres->pluck('id')->toArray(),
        ];

        $response = $this->postJson('/api/v1/books', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'New API Book');

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => 'New API Book',
            'isbn' => '9784000000000',
        ]);

        $book = Book::where('isbn', '9784000000000')->first();
        foreach ($genres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    public function test_store_returns_422_with_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/books', [
            'title' => '',
            'author' => '',
            'isbn' => '123',
            'published_date' => 'invalid',
            'genres' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'user_id', 'title', 'author', 'isbn', 'published_date', 'genres',
        ]);
    }

    // ===== PUT /api/v1/books/{book} =====

    public function test_update_modifies_book_fields_and_genres(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['title' => 'Old Title']);
        $oldGenre = Genre::factory()->create();
        $newGenre = Genre::factory()->create();
        $book->genres()->attach($oldGenre);

        $payload = [
            'user_id' => $user->id,
            'title' => 'Updated Title',
            'author' => 'Updated Author',
            'isbn' => '9784000000111',
            'published_date' => '2024-02-02',
            'description' => null,
            'image_url' => null,
            'genres' => [$newGenre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);
    }

    public function test_update_returns_404_for_unknown_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->putJson('/api/v1/books/99999', [
            'user_id' => $user->id,
            'title' => 'X',
            'author' => 'Y',
            'isbn' => '9784000000222',
            'published_date' => '2024-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }

    public function test_update_returns_422_with_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '',
            'author' => '',
            'isbn' => 'short',
            'published_date' => 'no-date',
            'genres' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'user_id', 'title', 'author', 'isbn', 'published_date', 'genres',
        ]);
    }

    // ===== DELETE /api/v1/books/{book} =====

    public function test_destroy_deletes_book_and_returns_204(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_destroy_returns_404_for_unknown_book(): void
    {
        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '書籍が見つかりませんでした。']);
    }
}
```

---

## 14.5. テストの実行

> **重要:** 提供された Blade テンプレート（navigation.blade.php 等）は、応用機能編 応用機能編で追加する `reports.index` / `reading-plans.index` / `notifications.index` ルートを参照しています。テストファイルはこの Chapter で作成しますが、**`sail artisan test` の実行は Chapter 21 完了後に行ってください。** `reports.index` / `reading-plans.index` / `notifications.index` ルートが未定義の状態でテストを実行すると、ビューのレンダリングで `Route [reports.index] not defined` エラーが発生します（実際の実行は Chapter 21 で応用機能のテストを追加した後に行うのが自然）。

全てのテストコードを書き終えたら、以下のコマンドでテストを実行します（Chapter 21 完了後）。

```bash
# 全てのテストを実行
sail artisan test

# 特定のテストファイルを実行
sail artisan test tests/Feature/BookControllerTest.php

# 特定のテストメソッドを実行
sail artisan test --filter=test_authenticated_user_can_create_book
```

コマンドを実行すると、PHPUnitがテストを一つずつ実行し、結果をコンソールに表示します。

> **注:** 本 Chapter 完了時点ではまだ `reports.index` / `reading-plans.index` / `notifications.index` ルート（応用機能編 Chapter 19-20 で実装）が未定義のため、`sail artisan test` を実行するとビューのレンダリングでエラーになります。テストファイル作成だけが本 Chapter のゴールであり、**実行は Chapter 21 完了後**（推奨は Chapter 21 で応用テストを追加した後）に行います。全テストが緑色の `PASS` で表示されれば、アプリケーション全体が正しく動作していることの証明になります。

---

これで、テストに裏付けされた堅牢な基本機能が完成しました。お疲れ様でした！
