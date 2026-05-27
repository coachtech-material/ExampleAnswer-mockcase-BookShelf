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

> **重要:** 提供された Blade テンプレート（navigation.blade.php 等）は、Chapter 15~17 で追加するルート（`reports.index` 等）を参照しています。テストファイルはこの Chapter で作成しますが、**`sail artisan test` の実行は Chapter 17 完了後に行ってください。** Chapter 15~17 のコントローラ・ルートが未定義の状態でテストを実行すると、ビューのレンダリングでエラーが発生します。

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

最初に、アプリケーションの心臓部であるモデルのリレーションシップが正しく定義されているかを確認するUnitテストを作成します。

```bash
sail artisan make:test UserModelTest --unit
sail artisan make:test BookModelTest --unit
sail artisan make:test ReviewModelTest --unit
```

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

次に、ユーザーの操作を模倣して、各機能が全体として正しく動作するかを検証するFeatureテストを作成します。

```bash
sail artisan make:test BookTest
sail artisan make:test ReviewTest
sail artisan make:test FavoriteTest
sail artisan make:test ReviewLikeTest
sail artisan make:test GenreTest
```

### BookTest (書籍管理機能)

`tests/Feature/BookTest.php`

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
            'genres' => $newGenres->pluck('id')->toArray(),
        ]);

        $response = $this->actingAs($user)->put(route('books.update', $book), $payload);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
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
            'rating' => 6, // 不正な値
        ]);

        $response->assertSessionHasErrors(['rating']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_only_owner_can_update_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create(['rating' => 3]);
        $otherUser = User::factory()->create();

        // オーナーは更新できる
        $this->actingAs($owner)
            ->put(route('reviews.update', $review), [
                'rating' => 4,
                'comment' => 'Updated comment',
            ])
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 4]);

        // 他のユーザーは更新できない
        $this->actingAs($otherUser)
            ->put(route('reviews.update', $review), ['rating' => 2])
            ->assertForbidden();
    }

    public function test_only_owner_can_delete_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        // 他のユーザーは削除できない
        $this->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);

        // オーナーは削除できる
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

    public function test_user_can_favorite_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $from = route('books.show', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('books.favorite', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_user_can_unfavorite_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);
        $from = route('books.show', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route('books.favorite', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_guest_cannot_favorite_book(): void
    {
        $book = Book::factory()->create();

        $this->post(route('books.favorite', $book))
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

    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('genres.store'), ['name' => 'Science Fiction'])
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['name' => 'Science Fiction']);
    }

    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'History']);

        $this->actingAs($user)
            ->put(route('genres.update', $genre), ['name' => 'World History'])
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => 'World History']);
    }

    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre);

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success');

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

---

## 14.5. テストの実行

> **重要:** 提供された Blade テンプレート（navigation.blade.php 等）は、応用機能編 Chapter 19 マイレポート機能で追加する `reports.index` ルートを参照しています。テストファイルはこの Chapter で作成しますが、**`sail artisan test` の実行は Chapter 19 完了後に行ってください。** `reports.index` ルートが未定義の状態でテストを実行すると、ビューのレンダリングで `Route [reports.index] not defined` エラーが発生します（実際の実行は Chapter 21 で応用機能のテストを追加した後に行うのが自然）。

全てのテストコードを書き終えたら、以下のコマンドでテストを実行します（Chapter 19 完了後）。

```bash
# 全てのテストを実行
sail artisan test

# 特定のテストファイルを実行
sail artisan test tests/Feature/BookTest.php

# 特定のテストメソッドを実行
sail artisan test --filter=test_authenticated_user_can_create_book
```

コマンドを実行すると、PHPUnitがテストを一つずつ実行し、結果をコンソールに表示します。全てのテストが緑色の `PASS` で表示されれば、アプリケーションの基本機能が正しく動作していることの証明になります。

---

これで、テストに裏付けされた堅牢な基本機能が完成しました。お疲れ様でした！
