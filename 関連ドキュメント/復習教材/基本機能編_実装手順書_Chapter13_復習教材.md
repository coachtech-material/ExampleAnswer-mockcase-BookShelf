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

- `fake()->numberBetween(1, 5)`: 1から5までのランダムな整数（評価）を生成します。

---

## 13.3. Unitテスト：モデルのリレーションシップ

最初に、アプリケーションの心臓部であるモデルのリレーションシップが正しく定義されているかを確認するUnitテストを作成します。

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

#### 📖 詳細解説: UserModelTest

> **💡 先輩エンジニアの視点**
> なぜモデルのリレーションシップをテストするのでしょうか？それは、リレーションシップがアプリケーションのデータの整合性を保つための「骨格」だからです。例えば、`User`モデルの`books`リレーションが正しく定義されていなければ、ユーザーが投稿した書籍一覧を取得する、といった基本的な機能が全て動作しなくなります。リレーションの定義ミスは、`null`エラーや予期せぬ動作の温床です。このテストは、その「骨格」が正しく組まれていることを保証する、非常に重要で基本的なテストなのです。

**コードリーディング**

- **`test_user_relationships_are_defined`**: このメソッドは、`User`モデルに定義された各リレーション（`books`, `reviews`, `favoriteBooks`, `likedReviews`）が期待通りに機能するかを検証します。

- **Arrange (準備)**
    - `User::factory()->create()`: テスト対象のユーザーを1人作成します。
    - `Book::factory()->for($user)->create()`: 作成した`$user`に紐づく書籍（`ownedBook`）を作成します。`for()`メソッドでリレーション先の親モデルを指定できます。
    - `Review::factory()->for($ownedBook)->for($user)->create()`: `$user`が`$ownedBook`に対して投稿したレビューを作成します。
    - `$user->favoriteBooks()->attach($favoriteBook->id)`: 多対多リレーションであるお気に入りを設定します。`attach()`メソッドで中間テーブルにレコードを追加します。
    - `$user->likedReviews()->attach($likedReview->id)`: 同様に、レビューへのいいねを中間テーブルに記録します。

- **Assert (検証)**
    - `$this->assertTrue($user->books->contains($ownedBook))`: `$user->books`（ユーザーが所有する書籍のコレクション）に、先ほど作成した`$ownedBook`が含まれていることを確認します。`contains()`は、コレクション内に指定したモデルインスタンスが存在するかを判定するメソッドです。
    - 他の`assertTrue`も同様に、各リレーションのコレクションに、準備段階で作成・紐付けしたモデルが含まれていることを一つずつ検証しています。

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

#### 📖 詳細解説: BookModelTest

> **💡 先輩エンジニアの視点**
> `Book`モデルは、このアプリケーションの中心的な存在です。ユーザー、レビュー、ジャンルなど、多くのモデルと関連しています。このテストは、その中心的なモデルが、関連する全ての情報（誰が投稿したか、どんなレビューが付いているか、どのジャンルに属しているか）を正しく取得できることを保証します。これが壊れると、書籍詳細ページが正しく表示されないなど、致命的な問題につながります。

**コードリーディング**

- **`test_book_relationships_are_defined`**: `Book`モデルの各リレーション（`user`, `reviews`, `genres`, `favoritedByUsers`）を検証します。

- **Arrange (準備)**
    - `Book`モデルを軸に、関連する`User`, `Genre`, `Review`をファクトリで作成し、`attach()`で関連付けを行っています。

- **Assert (検証)**
    - `$this->assertTrue($book->user->is($user))`: `$book->user`（1対多の逆リレーション）で取得したユーザーが、最初に作成した`$user`と同一のインスタンスであることを`is()`メソッドで確認します。`is()`は、2つのモデルが同じIDとテーブルを持つかを比較します。
    - 他の`contains()`は`UserModelTest`と同様に、コレクション内に期待するモデルが含まれているかを確認しています。

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
        $author = User::factory()->create();
        $book = Book::factory()->for($author)->create();
        $review = Review::factory()->for($book)->for($author)->create();
        $liker = User::factory()->create();
        $review->likedByUsers()->attach($liker->id);

        $this->assertTrue($review->user->is($author));
        $this->assertTrue($review->book->is($book));
        $this->assertTrue($review->likedByUsers->contains($liker));
    }
}
```

#### 📖 詳細解説: ReviewModelTest

> **💡 先輩エンジニアの視点**
> レビューは「誰が」「どの本に」対して投稿したかが明確でなければ意味がありません。このテストは、レビューがその所有者（ユーザー）と対象（書籍）を正しく指し示していることを保証します。また、「誰がいいねしたか」という情報も、今後の機能拡張（例えば、いいねしたユーザー一覧など）に備えて、正しく関連付けられていることを確認しておくことが重要です。

**コードリーディング**

- **`test_review_relationships_are_defined`**: `Review`モデルの各リレーション（`user`, `book`, `likedByUsers`）を検証します。

- **Arrange (準備)**
    - レビューの投稿者（`$author`）と、いいねをしたユーザー（`$liker`）を別々に作成し、`Review`モデルとの関連付けをテストする準備をしています。

- **Assert (検証)**
    - `$this->assertTrue($review->user->is($author))`: レビューの所有者が`$author`であることを確認します。
    - `$this->assertTrue($review->book->is($book))`: レビューの対象書籍が`$book`であることを確認します。
    - `$this->assertTrue($review->likedByUsers->contains($liker))`: レビューにいいねをしたユーザーのコレクションに`$liker`が含まれていることを確認します。

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

        $this->get(route(\'books.index\'))
            ->assertOk();
    }

    public function test_book_search_returns_matching_results(): void
    {
        Book::factory()->create([\'title\' => \'Laravel Testing Guide\']);
        Book::factory()->create([\'title\' => \'Another Book\']);

        $this->get(route(\'books.search\', [\'query\' => \'Laravel\']))
            ->assertOk()
            ->assertSee(\'Laravel Testing Guide\')
            ->assertDontSee(\'Another Book\');
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route(\'books.create\'))
            ->assertOk();
    }

    public function test_guest_cannot_view_create_form(): void
    {
        $this->get(route(\'books.create\'))
            ->assertRedirect(route(\'login\'));
    }

    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            \'title\' => \'My Test Book\',
            \'isbn\' => \'1111111111111\',
            \'genres\' => $genres->pluck(\'id\')->toArray(),
        ]);

        $response = $this->actingAs($user)->post(route(\'books.store\'), $payload);

        $book = Book::where(\'title\', \'My Test Book\')->first();
        $this->assertNotNull($book);

        $response->assertRedirect(route(\'books.show\', $book));

        $this->assertDatabaseHas(\'books\', [
            \'id\' => $book->id,
            \'user_id\' => $user->id,
            \'title\' => \'My Test Book\',
        ]);

        foreach ($genres as $genre) {
            $this->assertDatabaseHas(\'book_genre\', [
                \'book_id\' => $book->id,
                \'genre_id\' => $genre->id,
            ]);
        }
    }

    public function test_book_store_validation_errors(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route(\'books.store\'), [
            \'title\' => \'\',
            \'author\' => \'\',
            \'isbn\' => \'123\',
            \'published_date\' => \'invalid-date\',
            \'genres\' => [],
        ]);

        $response->assertSessionHasErrors([\'title\', \'isbn\', \'genres\']);
        $this->assertDatabaseCount(\'books\', 0);
    }

    public function test_book_show_page_can_be_rendered(): void
    {
        $book = Book::factory()->create([\'title\' => \'Detail Book\']);

        $this->get(route(\'books.show\', $book))
            ->assertOk()
            ->assertSee(\'Detail Book\');
    }

    public function test_authenticated_user_can_update_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $originalGenre = Genre::factory()->create();
        $book->genres()->attach($originalGenre);
        $newGenres = Genre::factory()->count(2)->create();

        $payload = $this->validBookData([
            \'title\' => \'Updated Title\',
            \'isbn\' => \'9876543210123\',
            \'genres\' => $newGenres->pluck(\'id\')->toArray(),
        ]);

        $response = $this->actingAs($user)->put(route(\'books.update\', $book), $payload);

        $response->assertRedirect(route(\'books.show\', $book));

        $this->assertDatabaseHas(\'books\', [
            \'id\' => $book->id,
            \'title\' => \'Updated Title\',
            \'isbn\' => \'9876543210123\',
        ]);

        foreach ($newGenres as $genre) {
            $this->assertDatabaseHas(\'book_genre\', [
                \'book_id\' => $book->id,
                \'genre_id\' => $genre->id,
            ]);
        }

        $this->assertDatabaseMissing(\'book_genre\', [
            \'book_id\' => $book->id,
            \'genre_id\' => $originalGenre->id,
        ]);
    }

    public function test_authenticated_user_can_delete_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route(\'books.destroy\', $book))
            ->assertRedirect(route(\'books.index\'));

        $this->assertDatabaseMissing(\'books\', [\'id\' => $book->id]);
    }

    public function test_only_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)
            ->get(route(\'books.edit\', $book))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route(\'books.edit\', $book))
            ->assertForbidden();
    }

    private function validBookData(array $overrides = []): array
    {
        $genres = $overrides[\'genres\'] ?? Genre::factory()->count(2)->create()->pluck(\'id\')->toArray();

        return array_merge([
            \'title\' => \'Sample Book\',
            \'author\' => \'Sample Author\',
            \'isbn\' => \'1234567890123\',
            \'published_date\' => \'2024-01-01\',
            \'description\' => \'Sample description\',
            \'image_url\' => \'https://example.com/image.jpg\',
            \'genres\' => $genres,
        ], $overrides);
    }
}
```

#### 📖 詳細解説: BookTest

> **💡 先輩エンジニアの視点**
> `BookTest`は、アプリケーションの根幹である書籍管理機能の「振る舞い」を保証するテスト群です。一覧表示、検索、作成、更新、削除といった一連のライフサイクルをテストすることで、ユーザーが最も頻繁に利用する機能の安定性を担保します。特に重要なのは、**正常系**（正しく動作するケース）だけでなく、**異常系**（ゲストユーザーのアクセス、バリデーションエラー、権限のない操作）もテストすることです。これにより、予期せぬ操作によるシステムの脆弱性を未然に防ぐことができます。

**コードリーディング**

- **`test_book_index_page_can_be_rendered`**: 書籍一覧ページが正常に表示されるか（HTTPステータスコード200が返ってくるか）をテストします。
    - `$this->get(route('books.index'))`: 指定したルートにGETリクエストを送信します。
    - `->assertOk()`: レスポンスのステータスコードが200であることを表明（assert）します。

- **`test_book_search_returns_matching_results`**: 検索機能が正しく動作するかをテストします。
    - `->assertSee('Laravel Testing Guide')`: レスポンスのHTML内に指定した文字列が含まれていることを確認します。
    - `->assertDontSee('Another Book')`: 逆に、含まれていないことを確認します。

- **`test_authenticated_user_can_create_book`**: 認証済みユーザーが書籍を登録できることをテストします。
    - `$this->actingAs($user)`: 指定したユーザーでログインしている状態をシミュレートします。
    - `->post(route('books.store'), $payload)`: POSTリクエストを送信します。第2引数にはリクエストボディ（入力データ）を配列で渡します。
    - `$response->assertRedirect(route('books.show', $book))`: 指定したルートにリダイレクトされたことを確認します。
    - `$this->assertDatabaseHas('books', [...])`: `books`テーブルに、指定した条件のレコードが存在することを確認します。

- **`test_book_store_validation_errors`**: バリデーションが機能しているかをテストします。
    - わざと不正なデータ（空のタイトル、不正なISBNなど）を送信します。
    - `$response->assertSessionHasErrors(['title', 'isbn', 'genres'])`: レスポンスのセッションに、指定したキーのバリデーションエラーが含まれていることを確認します。
    - `$this->assertDatabaseCount('books', 0)`: `books`テーブルのレコード数が0であること、つまり不正なデータが登録されていないことを確認します。

- **`test_only_owner_can_view_edit_form`**: 認可（Policy）が機能しているかをテストします。
    - 書籍の所有者（`$owner`）と、別のユーザー（`$otherUser`）を作成します。
    - 所有者が編集ページにアクセスした場合は`assertOk()`（成功）することを確認します。
    - 別のユーザーがアクセスした場合は`assertForbidden()`（403 Forbiddenエラー）となることを確認します。

- **`validBookData`**: テスト用の有効な書籍データを生成するプライベートなヘルパーメソッドです。これにより、テストコードの重複を減らし、可読性を高めています。

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

        $response = $this->actingAs($user)->post(route(\'reviews.store\', $book), [
            \'rating\' => 5,
            \'comment\' => \'Great read\',
        ]);

        $response->assertRedirect(route(\'books.show\', $book));

        $this->assertDatabaseHas(\'reviews\', [
            \'user_id\' => $user->id,
            \'book_id\' => $book->id,
            \'rating\' => 5,
        ]);
    }

    public function test_guest_cannot_create_review(): void
    {
        $book = Book::factory()->create();

        $this->post(route(\'reviews.store\', $book), [
            \'rating\' => 4,
            \'comment\' => \'Guest review\',
        ])->assertRedirect(route(\'login\'));
    }

    public function test_review_store_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route(\'reviews.store\', $book), [
            \'rating\' => 6, // 不正な値
            \'comment\' => str_repeat(\'a\', 1001), // 不正な値
        ]);

        $response->assertSessionHasErrors([\'rating\']);
        $this->assertDatabaseCount(\'reviews\', 0);
    }

    public function test_only_owner_can_edit_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)
            ->get(route(\'reviews.edit\', $review))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route(\'reviews.edit\', $review))
            ->assertForbidden();
    }

    public function test_only_owner_can_update_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create([\'rating\' => 3]);
        $otherUser = User::factory()->create();

        // 成功ケース
        $this->actingAs($owner)
            ->put(route(\'reviews.update\', $review), [
                \'rating\' => 4,
                \'comment\' => \'Updated comment\',
            ])
            ->assertRedirect(route(\'books.show\', $review->book));

        $this->assertDatabaseHas(\'reviews\', [
            \'id\' => $review->id,
            \'rating\' => 4,
        ]);

        // 失敗ケース
        $this->actingAs($otherUser)
            ->put(route(\'reviews.update\', $review), [
                \'rating\' => 2,
            ])
            ->assertForbidden();
    }

    public function test_only_owner_can_delete_review(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        // 失敗ケース
        $this->actingAs($otherUser)
            ->delete(route(\'reviews.destroy\', $review))
            ->assertForbidden();
        $this->assertDatabaseHas(\'reviews\', [\'id\' => $review->id]);

        // 成功ケース
        $this->actingAs($owner)
            ->delete(route(\'reviews.destroy\', $review))
            ->assertRedirect(route(\'books.show\', $review->book));
        $this->assertDatabaseMissing(\'reviews\', [\'id\' => $review->id]);
    }
}
```

#### 📖 詳細解説: ReviewTest

> **💡 先輩エンジニアの視点**
> レビュー機能のテストは、書籍管理機能と同様にCRUDと認可が中心です。特に`ReviewPolicy`が正しく機能しているか（自分のレビューしか編集・削除できない）を厳密にテストすることが重要です。他人のレビューを勝手に書き換えられたり、削除できたりするシステムは、信頼性がありません。このテストは、そうした不正な操作からデータを守るための「番人」が正しく機能しているかを確認する役割を担います。

**コードリーディング**

- **`test_authenticated_user_can_create_review`**: ログインユーザーがレビューを投稿できることをテストします。`BookTest`の書籍作成テストと構造はほぼ同じです。

- **`test_guest_cannot_create_review`**: ゲスト（未ログインユーザー）がレビューを投稿しようとすると、ログインページにリダイレクトされることをテストします。

- **`test_review_store_validation_errors`**: レビュー投稿時のバリデーションをテストします。評価に`6`（1-5の範囲外）、コメントに1001文字（1000文字以内）といった不正な値を送信し、エラーが返ることを確認します。

- **`test_only_owner_can_...`**: 3つのテスト（`edit`, `update`, `delete`）は、いずれも認可のテストです。レビューの所有者（`$owner`）と他人（`$otherUser`）を作成し、所有者は操作に成功し（`assertOk`）、他人は拒否される（`assertForbidden`）ことを確認します。削除テストでは、`assertDatabaseMissing`を使って、レコードが実際にデータベースから消えたことも検証しています。

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
        $from = route(\'books.show\', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route(\'favorites.toggle\', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas(\'favorites\', [
            \'user_id\' => $user->id,
            \'book_id\' => $book->id,
        ]);
    }

    public function test_user_can_remove_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book->id);
        $from = route(\'books.show\', $book);

        $this->actingAs($user)
            ->from($from)
            ->post(route(\'favorites.toggle\', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing(\'favorites\', [
            \'user_id\' => $user->id,
            \'book_id\' => $book->id,
        ]);
    }

    public function test_favorite_toggle_works_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $from = route(\'books.show\', $book);

        // Add to favorites
        $this->actingAs($user)
            ->from($from)
            ->post(route(\'favorites.toggle\', $book))
            ->assertRedirect($from);

        $this->assertDatabaseHas(\'favorites\', [
            \'user_id\' => $user->id,
            \'book_id\' => $book->id,
        ]);

        // Remove from favorites
        $this->actingAs($user)
            ->from($from)
            ->post(route(\'favorites.toggle\', $book))
            ->assertRedirect($from);

        $this->assertDatabaseMissing(\'favorites\', [
            \'user_id\' => $user->id,
            \'book_id\' => $book->id,
        ]);
    }

    public function test_favorite_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([\'title\' => \'Favorite Book\']);
        $user->favoriteBooks()->attach($book->id);

        $this->actingAs($user)
            ->get(route(\'favorites.index\'))
            ->assertOk()
            ->assertSee(\'Favorite Book\');
    }

    public function test_guest_cannot_toggle_favorite(): void
    {
        $book = Book::factory()->create();

        $this->post(route(\'favorites.toggle\', $book))
            ->assertRedirect(route(\'login\'));
    }
}
```

#### 📖 詳細解説: FavoriteTest

> **💡 先輩エンジニアの視点**
> `toggle`（トグル）処理は、同じアクションで状態が反転する（ON→OFF, OFF→ON）便利な機能ですが、テストが不十分だと「お気に入りに追加したはずが、もう一度押したら消えてしまった」といった意図しない挙動を生む可能性があります。このテストでは、追加、削除、そして「追加→削除」という一連のトグル動作が正しく行われることを保証します。`from()`メソッドを使っているのは、`toggle`処理後のリダイレクト先が、元のページ（この場合は書籍詳細ページ）に戻ることを明確にテストするためです。

**コードリーディング**

- **`test_user_can_add_favorite`**: 未お気に入りの状態から、お気に入りに追加されることをテストします。
    - `$this->from($from)`: リクエストの送信元URL（リファラ）を偽装します。これにより、リダイレクト先がこのURLになることを期待できます。
    - `assertDatabaseHas('favorites', ...)`: `favorites`中間テーブルにレコードが作成されたことを確認します。

- **`test_user_can_remove_favorite`**: お気に入り済みの状態から、お気に入りが解除されることをテストします。
    - `$user->favoriteBooks()->attach($book->id)`: テストの前提として、事前にお気に入り状態にしておきます。
    - `assertDatabaseMissing('favorites', ...)`: `favorites`中間テーブルからレコードが削除されたことを確認します。

- **`test_favorite_toggle_works_correctly`**: 1回のテストで、追加と削除の両方の動作を連続してテストします。

- **`test_favorite_index_page_can_be_rendered`**: お気に入り一覧ページが正常に表示され、お気に入りした書籍のタイトルが表示されていることを確認します。

### ReviewLikeTest (レビューいいね機能)

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
        $from = route(\'books.show\', $review->book);

        $this->actingAs($user)
            ->from($from)
            ->post(route(\'reviews.like\', $review))
            ->assertRedirect($from);

        $this->assertDatabaseHas(\'review_likes\', [
            \'user_id\' => $user->id,
            \'review_id\' => $review->id,
        ]);
    }

    public function test_user_can_unlike_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);
        $from = route(\'books.show\', $review->book);

        $this->actingAs($user)
            ->from($from)
            ->post(route(\'reviews.like\', $review))
            ->assertRedirect($from);

        $this->assertDatabaseMissing(\'review_likes\', [
            \'user_id\' => $user->id,
            \'review_id\' => $review->id,
        ]);
    }

    public function test_like_toggle_works_correctly(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $from = route(\'books.show\', $review->book);

        // Like a review
        $this->actingAs($user)
            ->from($from)
            ->post(route(\'reviews.like\', $review))
            ->assertRedirect($from);

        $this->assertDatabaseHas(\'review_likes\', [
            \'user_id\' => $user->id,
            \'review_id\' => $review->id,
        ]);

        // Unlike a review
        $this->actingAs($user)
            ->from($from)
            ->post(route(\'reviews.like\', $review))
            ->assertRedirect($from);

        $this->assertDatabaseMissing(\'review_likes\', [
            \'user_id\' => $user->id,
            \'review_id\' => $review->id,
        ]);
    }

    public function test_guest_cannot_like_review(): void
    {
        $review = Review::factory()->create();

        $this->post(route(\'reviews.like\', $review))
            ->assertRedirect(route(\'login\'));
    }
}
```

#### 📖 詳細解説: ReviewLikeTest

> **💡 先輩エンジニアの視点**
> このテストは、`FavoriteTest`とほぼ同じ構造をしています。これは意図的なもので、似たような機能（トグル式の多対多リレーション）は、同じテストパターンで検証できることを示しています。実務では、このように既存のテストをコピーして少し修正するだけで、新しい機能のテストを素早く作成することがよくあります。テストコードの「再利用性」を意識することも、効率的な開発には欠かせません。

**コードリーディング**

- このテストファイルの各メソッドは、`FavoriteTest`の対応するメソッドと全く同じロジックです。対象が`Book`から`Review`に、中間テーブルが`favorites`から`review_likes`に変わっているだけです。この類似性に気づくことが重要です。

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
        $book = Book::factory()->create([\'title\' => \'Ranked Book\']);
        Review::factory()->for($book)->count(2)->create([\'rating\' => 5]);

        $this->get(route(\'ranking.index\'))
            ->assertOk()
            ->assertSee(\'Ranked Book\');
    }

    public function test_ranking_is_ordered_by_average_rating(): void
    {
        $topBook = Book::factory()->create([\'title\' => \'Top Book\']);
        Review::factory()->for($topBook)->count(2)->create([\'rating\' => 5]);

        $middleBook = Book::factory()->create([\'title\' => \'Middle Book\']);
        Review::factory()->for($middleBook)->count(2)->create([
            \'rating\' => 3,
        ]);

        $lowBook = Book::factory()->create([\'title\' => \'Low Book\']);
        Review::factory()->for($lowBook)->count(2)->create([
            \'rating\' => 1,
        ]);

        $this->get(route(\'ranking.index\'))
            ->assertOk()
            ->assertSeeInOrder([
                \'Top Book\',
                \'Middle Book\',
                \'Low Book\',
            ]);
    }
}
```

#### 📖 詳細解説: RankingTest

> **💡 先輩エンジニアの視点**
> ランキング機能の核心は「正しい順序で表示されること」です。このテストでは、意図的に評価点が異なる書籍を複数作成し、ランキングページにそれらが評価の高い順に表示されるかを検証します。`assertSeeInOrder`は、まさにこのためのアサーションです。このテストがなければ、将来誰かがランキングの集計ロジックを誤って変更してしまい、順序がバラバラになっても気づくことができません。

**コードリーディング**

- **`test_ranking_is_ordered_by_average_rating`**: ランキングの並び順が正しいかをテストします。
    - **Arrange**: 評価が5、3、1となる3冊の書籍（`$topBook`, `$middleBook`, `$lowBook`）を意図的に作成します。
    - **Assert**: `$this->get(route('ranking.index'))`でランキングページを取得し、`assertSeeInOrder([...])`を使って、レスポンスのHTML内に指定した文字列がこの順番通りに出現することを確認します。

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
            ->get(route(\'genres.index\'))
            ->assertOk();
    }

    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route(\'genres.store\'), [
                \'name\' => \'Science Fiction\',
            ])
            ->assertRedirect(route(\'genres.index\'));

        $this->assertDatabaseHas(\'genres\', [\'name\' => \'Science Fiction\']);
    }

    public function test_genre_store_validation_errors(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create([\'name\' => \'Fantasy\']);

        $this->actingAs($user)
            ->post(route(\'genres.store\'), [\'name\' => \'Fantasy\']) // 重複した名前
            ->assertSessionHasErrors([\'name\']);
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route(\'genres.create\'))
            ->assertOk();
    }

    public function test_genre_show_page_displays_books(): void
    {
        $genre = Genre::factory()->create([\'name\' => \'Mystery\']);
        $book = Book::factory()->create([\'title\' => \'Mystery Book\']);
        $book->genres()->attach($genre);

        $this->get(route(\'genres.show\', $genre))
            ->assertOk()
            ->assertSee(\'Mystery Book\');
    }

    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create([\'name\' => \'History\']);

        $this->actingAs($user)
            ->put(route(\'genres.update\', $genre), [\'name\' => \'World History\'])
            ->assertRedirect(route(\'genres.index\'));

        $this->assertDatabaseHas(\'genres\', [
            \'id\' => $genre->id,
            \'name\' => \'World History\',
        ]);
    }

    public function test_authenticated_user_can_view_edit_form(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create([\'name\' => \'Poetry\']);

        $this->actingAs($user)
            ->get(route(\'genres.edit\', $genre))
            ->assertOk();
    }

    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create([\'name\' => \'Adventure\']);
        $book = Book::factory()->create();
        $book->genres()->attach($genre);

        $this->actingAs($user)
            ->delete(route(\'genres.destroy\', $genre))
            ->assertRedirect(route(\'genres.index\'))
            ->assertSessionHas(\'error\', \'このジャンルには書籍が紐付いているため削除できません。\');

        $this->assertDatabaseHas(\'genres\', [\'id\' => $genre->id]);
    }

    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create([\'name\' => \'Short Stories\']);

        $this->actingAs($user)
            ->delete(route(\'genres.destroy\', $genre))
            ->assertRedirect(route(\'genres.index\'))
            ->assertSessionHas(\'success\', \'ジャンルを削除しました。\');

        $this->assertDatabaseMissing(\'genres\', [\'id\' => $genre->id]);
    }
}
```

#### 📖 詳細解説: GenreTest

> **💡 先輩エンジニアの視点**
> ジャンル管理機能のテストで最も特徴的なのは、削除ロジックのテストです。このシステムでは「書籍が紐付いているジャンルは削除できない」というビジネスルールがあります。このテストでは、そのルールが正しく機能することを保証します。`test_genre_with_books_cannot_be_deleted`（削除失敗ケース）と`test_genre_without_books_can_be_deleted`（削除成功ケース）の両方をテストすることで、データの整合性を守る重要なロジックが壊れていないことを確認できます。エラーメッセージをセッションで検証しているのもポイントです。

**コードリーディング**

- **CRUDテスト**: `create`, `update`, `index`, `edit`などのテストは、これまでの`BookTest`などと同様のパターンです。

- **`test_genre_with_books_cannot_be_deleted`**: 削除が失敗するケースをテストします。
    - **Arrange**: ジャンルを作成し、それに紐づく書籍も作成します。
    - **Act**: 削除リクエストを送信します。
    - **Assert**: `assertSessionHas('error', ...)`で、コントローラーが返したエラーメッセージがセッションに存在することを確認します。さらに`assertDatabaseHas`で、ジャンルが削除されていないことを念押しで確認します。

- **`test_genre_without_books_can_be_deleted`**: 削除が成功するケースをテストします。
    - **Arrange**: 書籍が紐付いていないジャンルを作成します。
    - **Act**: 削除リクエストを送信します。
    - **Assert**: `assertSessionHas('success', ...)`で成功メッセージを確認し、`assertDatabaseMissing`でジャンルがDBから削除されたことを確認します。

### RedirectIfAuthenticatedTest (認証リダイレクト)

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
        $middleware = new RedirectIfAuthenticated();
        $request = Request::create(\'/login\', \'GET\');
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $middleware->handle($request, fn () => response(\'next\'));

        $this->assertEquals(url(RouteServiceProvider::HOME), $response->headers->get(\'Location\'));

        Auth::logout();
    }

    public function test_guest_can_access_route(): void
    {
        $middleware = new RedirectIfAuthenticated();
        $request = Request::create(\'/login\', \'GET\');

        $response = $middleware->handle($request, fn () => response(\'allowed\'));

        $this->assertEquals(\'allowed\', $response->getContent());
    }
}
```

#### 📖 詳細解説: RedirectIfAuthenticatedTest

> **💡 先輩エンジニアの視点**
> このテストは、他のFeatureテストとは少し毛色が異なります。コントローラーを介さず、**ミドルウェアを直接テスト**しています。なぜなら、`RedirectIfAuthenticated`ミドルウェアは「ログイン済みのユーザーがログインページや登録ページにアクセスするのを防ぐ」という、アプリケーション全体に関わる横断的な役割を持つからです。このテストは、ユーザーが混乱するような不自然な画面遷移を防ぐための、重要な「交通整理」のルールが正しく機能していることを保証します。

**コードリーディング**

- **`test_authenticated_user_is_redirected_to_home`**: ログイン済みユーザーがリダイレクトされることをテストします。
    - `$middleware = new RedirectIfAuthenticated()`: テスト対象のミドルウェアを直接インスタンス化します。
    - `$request = Request::create('/login', 'GET')`: `/login`へのGETリクエストを模した`Request`オブジェクトを作成します。
    - `$this->actingAs($user)`: ユーザーをログイン状態にします。
    - `$response = $middleware->handle($request, fn () => response('next'))`: ミドルウェアの`handle`メソッドを直接実行します。第2引数のクロージャは、ミドルウェアがリクエストを次の処理に通した場合に実行されるダミーの処理です。
    - `$this->assertEquals(url(RouteServiceProvider::HOME), $response->headers->get('Location'))`: レスポンスヘッダーの`Location`（リダイレクト先）が、期待通りホームページのURLであることを確認します。

- **`test_guest_can_access_route`**: ゲストユーザーはリダイレクトされずに、意図した処理が実行されることをテストします。
    - ログイン状態にせず、`handle`メソッドを実行します。
    - `$this->assertEquals('allowed', $response->getContent())`: レスポンスの内容が、`handle`メソッドの第2引数で定義したクロージャが返した`'allowed'`という文字列であることを確認します。これにより、ミドルウェアがリダイレクト処理を行わず、リクエストを通過させたことがわかります。

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
