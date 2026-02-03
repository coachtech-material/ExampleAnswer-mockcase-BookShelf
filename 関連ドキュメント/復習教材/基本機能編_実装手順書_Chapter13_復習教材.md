# Chapter 13: 基本機能のテストと最終確認

---

## 🎯 このセクションで学ぶこと

- PHPUnitを使った**Unitテスト**と**Featureテスト**の基本
- Laravelのテストヘルパーとアサーションメソッドの活用
- **モデルのリレーションシップ**を検証するUnitテスト
- **CRUD操作**（作成、読み取り、更新、削除）を網羅するFeatureテスト
- **認証**・**認可**（ポリシー）が必要な機能のテスト
- **バリデーション**ルールを検証するテスト
- テスト用のダミーデータを生成する**ファクトリ**の作り方と使い方

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

## 13.1. テスト環境の準備

### テスト用データベースの設定

Laravelは、テスト実行時に本番のデータベースを汚さないよう、別のデータベースを使用する仕組みがデフォルトで備わっています。

まず、プロジェクトのルートにある`phpunit.xml`ファイルを確認してください。このファイルはPHPUnitの設定ファイルです。

```xml
<!-- phpunit.xml -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_DRIVER" value="array"/>
    <!-- ↓ この2行に注目！ -->
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
</php>
```

> **💡 ポイント：インメモリデータベース**
> `DB_CONNECTION` を `sqlite` に、`DB_DATABASE` を `:memory:` に設定することで、テスト実行時に**インメモリデータベース**が使用されます。これは、実際のファイルではなく、コンピュータのメモリ上に一時的にデータベースを構築する方式です。ディスクI/Oが発生しないため、**テストが非常に高速に実行できる**という大きなメリットがあります。

### RefreshDatabase トレイト

各テストクラスで使用する `use RefreshDatabase;` は、Laravelが提供する非常に便利なトレイトです。これを使用すると、**各テストメソッドが実行される前に、データベースが自動的にリセット**されます。具体的には、マイグレーションが実行され、テーブルが再作成されます。これにより、前のテストで作成されたデータが後のテストに影響を与えることを防ぎ、各テストをクリーンな状態で実行できます。

---

## 13.2. ファクトリの準備

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

- `fake()->unique()->words(2, true)`: Fakerライブラリを使い、ユニーク（重複しない）な2つの単語からなるジャンル名を生成します。

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

- `User::factory()`: この書籍を所有する`User`モデルも同時に作成します。
- `fake()->unique()->numerify('#############')`: ユニークな13桁の数字（ISBN）を生成します。

### ReviewFactory.php

`database/factories/ReviewFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\
Database\Eloquent\Factories\Factory;

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

- `fake()->numberBetween(1, 5)`: 1から5までのランダムな整数（評価）を生成します。

---

## 13.3. Unitテスト：モデルのリレーションシップ

最初に、アプリケーションの心臓部であるモデルのリレーションシップが正しく定義されているかを確認するUnitテストを作成します。

### UserModelTest

ユーザーが書籍、レビュー、お気に入り、いいねを正しく関連付けられるかテストします。

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
        // 1. Arrange (準備)
        $user = User::factory()->create();
        $ownedBook = Book::factory()->for($user)->create();
        $ownedReview = Review::factory()->for($ownedBook)->for($user)->create();
        $favoriteBook = Book::factory()->create();
        $user->favoriteBooks()->attach($favoriteBook->id);
        $likedReview = Review::factory()->create();
        $user->likedReviews()->attach($likedReview->id);

        // 2. Assert (検証)
        $this->assertTrue($user->books->contains($ownedBook));
        $this->assertTrue($user->reviews->contains($ownedReview));
        $this->assertTrue($user->favoriteBooks->contains($favoriteBook));
        $this->assertTrue($user->likedReviews->contains($likedReview));
    }
}
```

### BookModelTest

書籍がユーザー、レビュー、ジャンル、お気に入りを正しく関連付けられるかテストします。

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
        // 1. Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->for($user)->create();
        $review = Review::factory()->for($book)->for($user)->create();
        $book->genres()->attach($genre);
        $book->favoritedByUsers()->attach($user->id);

        // 2. Assert (検証)
        $this->assertTrue($book->user->is($user));
        $this->assertTrue($book->reviews->contains($review));
        $this->assertTrue($book->genres->contains($genre));
        $this->assertTrue($book->favoritedByUsers->contains($user));
    }
}
```

### ReviewModelTest

レビューがユーザー、書籍、いいねを正しく関連付けられるかテストします。

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
        // 1. Arrange (準備)
        $author = User::factory()->create();
        $book = Book::factory()->for($author)->create();
        $review = Review::factory()->for($book)->for($author)->create();
        $liker = User::factory()->create();
        $review->likedByUsers()->attach($liker->id);

        // 2. Assert (検証)
        $this->assertTrue($review->user->is($author));
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->likedByUsers->contains($liker));
    }
}
```

---

## 13.4. Featureテスト：各機能の振る舞い

次に、ユーザーの操作を模倣して、各機能が全体として正しく動作するかを検証するFeatureテストを作成します。

### 認可（Policy）の有効化

Featureテストで認可（Policy）のテストを行う前に、コメントアウトされている`authorize`メソッドを有効化します。

`app/Http/Controllers/BookController.php`

```php
// ...
    public function edit(Book $book): View
    {
        $this->authorize("update", $book); // コメントを解除
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize("update", $book); // コメントを解除
        // ...
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize("delete", $book); // コメントを解除
        // ...
    }
// ...
```

また、`AuthServiceProvider`にPolicyが正しく登録されていることを確認します。

`app/Providers/AuthServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Review;
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
```

### BookTest (書籍管理機能)

書籍のCRUD操作、検索、認可などをテストします。

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

    // 書籍一覧ページが表示される
    public function test_book_index_page_can_be_rendered(): void
    {
        Book::factory()->count(2)->create();
        $this->get(route('books.index'))->assertOk();
    }

    // 書籍検索が機能する
    public function test_book_search_returns_matching_results(): void
    {
        Book::factory()->create(['title' => 'Laravel Testing Guide']);
        Book::factory()->create(['title' => 'Another Book']);

        $this->get(route('books.search', ['query' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravel Testing Guide')
            ->assertDontSee('Another Book');
    }

    // 認証済みユーザーは登録フォームを表示できる
    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('books.create'))->assertOk();
    }

    // 未認証ユーザーは登録フォームを表示できない
    public function test_guest_cannot_view_create_form(): void
    {
        $this->get(route('books.create'))->assertRedirect(route('login'));
    }

    // 認証済みユーザーは書籍を登録できる
    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();
        $payload = $this->validBookData(['genres' => $genres->pluck('id')->toArray()]);

        $response = $this->actingAs($user)->post(route('books.store'), $payload);

        $book = Book::first();
        $this->assertNotNull($book);
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', ['title' => $payload['title']]);
        $this->assertTrue($book->genres->contains($genres[0]));
    }

    // 書籍登録時のバリデーションが機能する
    public function test_book_store_validation_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('books.store'), ['title' => ''])
            ->assertSessionHasErrors(['title', 'author', 'isbn', 'published_date', 'genres']);
    }

    // 書籍詳細ページが表示される
    public function test_book_show_page_can_be_rendered(): void
    {
        $book = Book::factory()->create(['title' => 'Detail Book']);
        $this->get(route('books.show', $book))->assertOk()->assertSee('Detail Book');
    }

    // 認証済みユーザーは書籍を更新できる
    public function test_authenticated_user_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $newGenres = Genre::factory()->count(2)->create();
        $payload = $this->validBookData(['title' => 'Updated Title', 'genres' => $newGenres->pluck('id')->toArray()]);

        $this->actingAs($user)->put(route('books.update', $book), $payload)
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', ['title' => 'Updated Title']);
        $this->assertTrue($book->fresh()->genres->contains($newGenres[0]));
    }

    // 認証済みユーザーは書籍を削除できる
    public function test_authenticated_user_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    // 所有者のみが編集フォームを表示できる（認可テスト）
    public function test_only_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)->get(route('books.edit', $book))->assertOk();
        $this->actingAs($otherUser)->get(route('books.edit', $book))->assertForbidden();
    }

    // テストデータ生成用のヘルパーメソッド
    private function validBookData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sample Book',
            'author' => 'Sample Author',
            'isbn' => fake()->unique()->numerify(str_repeat('#', 13)),
            'published_date' => '2024-01-01',
            'description' => 'Sample description',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => Genre::factory()->create()->pluck('id')->toArray(),
        ], $overrides);
    }
}
```

### ReviewTest (レビュー機能)

レビューの投稿、更新、削除、認可などをテストします。

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

    // 認証済みユーザーはレビューを投稿できる
    public function test_authenticated_user_can_create_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 5, 'comment' => 'Great read'])
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'book_id' => $book->id, 'rating' => 5]);
    }

    // 未認証ユーザーはレビューを投稿できない
    public function test_guest_cannot_create_review(): void
    {
        $book = Book::factory()->create();
        $this->post(route('reviews.store', $book), ['rating' => 4])->assertRedirect(route('login'));
    }

    // レビュー投稿時のバリデーションが機能する
    public function test_review_store_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $book), ['rating' => 6])
            ->assertSessionHasErrors(['rating']);
    }

    // 所有者のみが編集フォームを表示できる
    public function test_only_owner_can_edit_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)->get(route('reviews.edit', $review))->assertOk();
        $this->actingAs($otherUser)->get(route('reviews.edit', $review))->assertForbidden();
    }

    // 所有者のみがレビューを更新できる
    public function test_only_owner_can_update_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create(['rating' => 3]);
        $otherUser = User::factory()->create();

        $this->actingAs($owner)->put(route('reviews.update', $review), ['rating' => 4, 'comment' => 'Updated'])
            ->assertRedirect(route('books.show', $review->book));
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 4]);

        $this->actingAs($otherUser)->put(route('reviews.update', $review), ['rating' => 2])->assertForbidden();
    }

    // 所有者のみがレビューを削除できる
    public function test_only_owner_can_delete_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->delete(route('reviews.destroy', $review))->assertForbidden();
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);

        $this->actingAs($owner)->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book));
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
```

### その他のFeatureテスト

同様に、お気に入り、レビューいいね、ランキング、ジャンル管理のテストも作成します。これらのテストは、各機能が仕様通りに動作することを保証するために不可欠です。

- **FavoriteTest**: お気に入りの追加・解除（toggle）、一覧表示、未認証ユーザーのアクセス制限をテストします。
- **ReviewLikeTest**: レビューへのいいねの追加・解除（toggle）、未認証ユーザーのアクセス制限をテストします。
- **RankingTest**: ランキングページが表示され、書籍がレビューの平均評価順に正しく並んでいることをテストします。
- **GenreTest**: ジャンルのCRUD操作、バリデーション、そして「書籍が紐付いているジャンルは削除できない」という特殊なビジネスロジックをテストします。

---

## 13.5. テストの実行

全てのテストコードを書き終えたら、以下のコマンドでテストを実行します。

```bash
# 全てのテストを実行
sail artisan test

# 特定のテストファイルを実行
sail artisan test tests/Feature/BookTest.php

# 特定のテストメソッドを実行
sail artisan test --filter=test_authenticated_user_can_create_book
```

コマンドを実行すると、PHPUnitがテストを一つずつ実行し、結果をコンソールに表示します。全てのテストが緑色の `PASS` で表示されれば、アプリケーションの基本機能が正しく動作していることの証明になります。

```
   PASS  Tests/Unit/BookModelTest
   PASS  Tests/Unit/ReviewModelTest
   PASS  Tests/Unit/UserModelTest
   PASS  Tests/Feature/BookTest
   PASS  Tests/Feature/FavoriteTest
   PASS  Tests/Feature/GenreTest
   PASS  Tests/Feature/RankingTest
   PASS  Tests/Feature/RedirectIfAuthenticatedTest
   PASS  Tests/Feature/ReviewLikeTest
   PASS  Tests/Feature/ReviewTest

  Tests:  33 passed
  Time:   ...s
```

---

## 13.6. 最終確認

### 動作確認チェックリスト

全ての機能実装とテストが完了しました。以下のチェックリストを用いて、手動でも最終的な動作確認を行ってください。

| 確認項目 | チェック |
|:---|:---:|
| ユーザー登録ができる | [ ] |
| ログイン・ログアウトができる | [ ] |
| 書籍の登録・編集・削除ができる（権限のないユーザーは編集・削除不可） | [ ] |
| ジャンルを選択して書籍を登録できる | [ ] |
| レビューの投稿・編集・削除ができる（権限のないユーザーは編集・削除不可） | [ ] |
| お気に入りの追加・解除ができる | [ ] |
| レビューにいいねができる | [ ] |
| ランキングが表示される | [ ] |
| ジャンル別一覧が表示される | [ ] |
| ジャンルの作成・編集・削除ができる（書籍が紐付いている場合は削除不可） | [ ] |
| テストが全て通る（`sail artisan test`） | [ ] |

### このチュートリアルで実装した機能

このチュートリアルでは、書籍レビューサイトの基本的な機能を網羅的に実装しました。

| Chapter | 機能 | 学んだこと |
|:---|:---|:---|
| 0-5 | 設計・準備 | 要件定義、DB設計、モデル、認証、マスタデータ |
| 6 | 書籍管理 | CRUD、FormRequest、Policy、リソースルート |
| 7 | レビュー機能 | ネストしたリソース、認可 |
| 8 | お気に入り機能 | 多対多リレーション、`toggle`メソッド |
| 9 | いいね機能 | パターンの再利用（お気に入り機能との類似性） |
| 10 | ランキング機能 | 集計クエリ (`AVG`)、`DB::raw`、`join` |
| 11 | ジャンル別一覧 | リレーションを活用した絞り込み表示 |
| 12 | ジャンル管理 | CRUDパターンの再実践、特殊な削除ロジック |
| 13 | **テスト** | **Unitテスト、Featureテスト、ファクトリ、AAAパターン、各種アサーション** |

---

これで、テストに裏付けされた堅牢な基本機能が完成しました。お疲れ様でした！
