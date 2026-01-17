# Chapter 5: マスタデータの準備

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの開発やテストを効率的に進めるための「初期データ（マスタデータ）」をデータベースに投入する方法を学びます。LaravelのSeeder機能を使って、ユーザー、書籍、レビューなどのまとまったデータを一度に作成します。

- **Seederの役割**: なぜ手動でデータを登録するのではなく、Seederを使うのか、そのメリットを理解します。
- **複数Seederの作成**: `make:seeder`コマンドを使い、リソースごとにSeederファイルを作成します。
- **Seederの実装**: `run`メソッド内に、`User`や`Book`モデルを使って実際にデータを作成するロジックを記述します。
- **Seederの実行と依存関係**: `DatabaseSeeder`を使って複数のSeederを正しい順序で実行する方法と、`migrate:fresh --seed`コマンドの便利な使い方を学びます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜSeederが重要なのか？

開発の初期段階では、機能が正しく動くかを確認するために、たくさんのテストデータが必要になります。例えば、ランキング機能を実装するには、複数の書籍とそれに対する複数のレビューがなければ、ランキングが正しく表示されるか確認できません。毎回手動でこれらのデータを登録するのは非常に手間がかかり、非効率です。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 機能テストのたびに手動でデータを登録するのが面倒 | **Seeder**を使って、コマンド一つで必要な初期データを全て投入できるようにする | `migrate:fresh --seed`を実行するだけで、いつでも「まっさら」で「データが揃った」状態からテストを開始できる。開発効率が劇的に向上する。 |
| 複数人で開発する際に、各自のローカル環境のデータがバラバラになる | 全員が同じSeederを共有する | 開発者全員が同じデータセットを元に開発を進めることができ、「自分の環境では動いたのに」といった問題を減らせる。 |
| どんなデータが必要だったか忘れてしまう | Seederファイルを見れば、どのような初期データが使われているか一目瞭然 | Seederは「動く仕様書」としての役割も果たし、アプリケーションが必要とするデータの構造を明確にする。 |

Seederは、レストランを開店する前の「仕込み」作業に似ています。お客様（ユーザー）が来店したときにすぐに料理（機能）を提供できるよう、あらかじめ野菜を切ったり（ユーザー登録）、スープのベースを作ったり（ジャンル登録）しておくことで、本番の作業がスムーズに進むのです。

---

## 5.1. Seederファイルの作成

まず、今回のプロジェクトで必要となるすべてのSeederファイルを`make:seeder`コマンドで一括作成します。

```bash
sail artisan make:seeder UserSeeder
sail artisan make:seeder GenreSeeder
sail artisan make:seeder BookSeeder
sail artisan make:seeder ReviewSeeder
sail artisan make:seeder FavoriteSeeder
sail artisan make:seeder ReviewLikeSeeder
```

これにより、`database/seeders`ディレクトリに6つのファイルが作成されます。

---

## 5.2. Seederの実装

作成した各Seederファイルに、初期データを登録するための処理を記述していきます。

### 5.2.1. UserSeeder

`database/seeders/UserSeeder.php`を開き、5人のダミーユーザーを作成する処理を記述します。

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => '山田太郎',
                'email' => 'yamada@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => '鈴木花子',
                'email' => 'suzuki@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => '田中一郎',
                'email' => 'tanaka@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => '佐藤美咲',
                'email' => 'sato@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => '高橋健太',
                'email' => 'takahashi@example.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
```

### 5.2.2. GenreSeeder

`database/seeders/GenreSeeder.php`を開き、書籍のジャンルを登録します。

```php
<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            '小説',
            'ビジネス',
            '技術書',
            '自己啓発',
            'エッセイ',
            '歴史',
            '科学',
            '芸術',
            '料理',
            '旅行',
        ];

        foreach ($genres as $genre) {
            Genre::firstOrCreate(['name' => $genre]);
        }
    }
}
```

### 5.2.3. BookSeeder

`database/seeders/BookSeeder.php`を開き、書籍データを登録します。ここでは、`User`と`Genre`のデータが必要になるため、それらのモデルを`use`し、リレーションを考慮した処理を記述します。

```php
<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        
        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である珍野苦沙弥の家に飼われている猫である「吾輩」の視点から、珍野一家や、そこに出入りする人々の様子を風刺的に描いた作品。',
                'image_url' => 'https://cover.openbd.jp/9784101010014.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の古典として、あらゆる自己啓発本の原点となったデール・カーネギーの名著。',
                'image_url' => 'https://cover.openbd.jp/9784422100524.jpg',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニックを紹介。',
                'image_url' => 'https://cover.openbd.jp/9784873115658.jpg',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '全世界3000万部、国内180万部を超えるベストセラー。人生を成功に導く7つの習慣を解説。',
                'image_url' => 'https://cover.openbd.jp/9784863940246.jpg',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '東京の物理学校を卒業後、四国の中学校に数学教師として赴任した主人公「坊っちゃん」の物語。',
                'image_url' => 'https://cover.openbd.jp/9784101010021.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => 'なぜ人類だけが文明を築けたのか？ホモ・サピエンスの歴史を俯瞰する世界的ベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784309226712.jpg',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => 'アジャイルソフトウェア開発の奥義として、クリーンなコードを書くための原則を解説。',
                'image_url' => 'https://cover.openbd.jp/9784048930598.jpg',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式でわかりやすく解説した自己啓発書のベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784478025819.jpg',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '芥川賞受賞作。売れない芸人の青春を描いた純文学作品。',
                'image_url' => 'https://cover.openbd.jp/9784163902302.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => 'データを基に世界を正しく見る習慣を身につける。思い込みを乗り越え、世界を正しく見る方法。',
                'image_url' => 'https://cover.openbd.jp/9784822289607.jpg',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => 'コンテナが世界を変えた。物流革命の歴史を描いたノンフィクション。',
                'image_url' => 'https://cover.openbd.jp/9784822251468.jpg',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $bookData) {
            $genreNames = $bookData['genres'];
            unset($bookData['genres']);
            
            $book = Book::firstOrCreate(
                ['isbn' => $bookData['isbn']],
                array_merge($bookData, ['user_id' => $user->id])
            );

            // ジャンルを紐付け
            $genreIds = Genre::whereIn('name', $genreNames)->pluck('id')->toArray();
            $book->genres()->sync($genreIds);
        }
    }
}

```
### 5.2.4. ReviewSeeder

作成された `database/seeders/ReviewSeeder.php` を開き、`run` メソッドに初期ジャンルを登録する処理を記述します。  
```php
<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        
        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である珍野苦沙弥の家に飼われている猫である「吾輩」の視点から、珍野一家や、そこに出入りする人々の様子を風刺的に描いた作品。',
                'image_url' => 'https://cover.openbd.jp/9784101010014.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の古典として、あらゆる自己啓発本の原点となったデール・カーネギーの名著。',
                'image_url' => 'https://cover.openbd.jp/9784422100524.jpg',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニックを紹介。',
                'image_url' => 'https://cover.openbd.jp/9784873115658.jpg',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '全世界3000万部、国内180万部を超えるベストセラー。人生を成功に導く7つの習慣を解説。',
                'image_url' => 'https://cover.openbd.jp/9784863940246.jpg',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '東京の物理学校を卒業後、四国の中学校に数学教師として赴任した主人公「坊っちゃん」の物語。',
                'image_url' => 'https://cover.openbd.jp/9784101010021.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => 'なぜ人類だけが文明を築けたのか？ホモ・サピエンスの歴史を俯瞰する世界的ベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784309226712.jpg',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => 'アジャイルソフトウェア開発の奥義として、クリーンなコードを書くための原則を解説。',
                'image_url' => 'https://cover.openbd.jp/9784048930598.jpg',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式でわかりやすく解説した自己啓発書のベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784478025819.jpg',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '芥川賞受賞作。売れない芸人の青春を描いた純文学作品。',
                'image_url' => 'https://cover.openbd.jp/9784163902302.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => 'データを基に世界を正しく見る習慣を身につける。思い込みを乗り越え、世界を正しく見る方法。',
                'image_url' => 'https://cover.openbd.jp/9784822289607.jpg',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => 'コンテナが世界を変えた。物流革命の歴史を描いたノンフィクション。',
                'image_url' => 'https://cover.openbd.jp/9784822251468.jpg',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $bookData) {
            $genreNames = $bookData['genres'];
            unset($bookData['genres']);
            
            $book = Book::firstOrCreate(
                ['isbn' => $bookData['isbn']],
                array_merge($bookData, ['user_id' => $user->id])
            );

            // ジャンルを紐付け
            $genreIds = Genre::whereIn('name', $genreNames)->pluck('id')->toArray();
            $book->genres()->sync($genreIds);
        }
    }
}
```

### 5.2.4. FavoriteSeeder

作成された `database/seeders/FavoriteSeeder.php` を開き、`run` メソッドに初期ジャンルを登録する処理を記述します。  
```php
<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // 各ユーザーにお気に入りを設定
        $favorites = [
            // 山田太郎のお気に入り
            ['user_index' => 0, 'book_indices' => [0, 2, 5, 9]],
            // 鈴木花子のお気に入り
            ['user_index' => 1, 'book_indices' => [1, 3, 5, 7]],
            // 田中一郎のお気に入り
            ['user_index' => 2, 'book_indices' => [0, 1, 7]],
            // 佐藤美咲のお気に入り
            ['user_index' => 3, 'book_indices' => [3, 4, 7, 8]],
            // 高橋健太のお気に入り
            ['user_index' => 4, 'book_indices' => [2, 5, 6, 9, 10]],
        ];

        foreach ($favorites as $favoriteData) {
            $user = $users[$favoriteData['user_index']];
            $bookIds = collect($favoriteData['book_indices'])->map(fn($i) => $books[$i]->id)->toArray();
            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
```

### 5.2.4. ReviewLikeSeeder

作成された `database/seeders/ReviewLikeSeeder.php` を開き、`run` メソッドに初期ジャンルを登録する処理を記述します。  
```php
<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $reviews = Review::all();

        // ランダムにいいねを付ける
        foreach ($reviews as $review) {
            // 各レビューに0〜3人のユーザーがいいねする
            $likeCount = rand(0, 3);
            $likeUsers = $users->where('id', '!=', $review->user_id)->random(min($likeCount, $users->count() - 1));
            
            foreach ($likeUsers as $user) {
                $user->likedReviews()->syncWithoutDetaching([$review->id]);
            }
        }
    }
}
```

---

## 5.3. DatabaseSeederへの登録

作成した個別のSeederを、`db:seed`コマンド実行時にまとめて呼び出せるように`database/seeders/DatabaseSeeder.php`に登録します。

**重要なのは実行順序です。** 例えば、`BookSeeder`は`UserSeeder`と`GenreSeeder`が完了している必要があります。依存関係を考慮して、以下の順序で登録します。

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,       // 1. ユーザー
            GenreSeeder::class,      // 2. ジャンル
            BookSeeder::class,       // 3. 書籍 (ユーザーとジャンルに依存)
            ReviewSeeder::class,     // 4. レビュー (ユーザーと書籍に依存)
            FavoriteSeeder::class,   // 5. お気に入り (ユーザーと書籍に依存)
            ReviewLikeSeeder::class, // 6. いいね (ユーザーとレビューに依存)
        ]);
    }
}
```

---

## 5.4. データベースへのデータ投入

準備が整ったので、以下のコマンドを実行して、データベースに全ての初期データを投入します。

```bash
# 全てのテーブルを削除 → 再作成 → 全てのSeederを実行
sail artisan migrate:fresh --seed
```

このコマンド一発で、データベースは常にクリーンで、テストデータが完全に揃った状態になります。実行後、phpMyAdminなどで各テーブルにデータが正しく登録されているか確認してみましょう。

これで、アプリケーションの「仕込み」は完了です。次のChapterからは、いよいよメイン機能である書籍のCRUD（作成・読み取り・更新・削除）を実装していきます。
