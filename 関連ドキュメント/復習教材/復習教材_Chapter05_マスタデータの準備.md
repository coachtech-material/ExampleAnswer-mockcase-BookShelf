# Chapter 05: 「開店前の仕込み」 - マスタデータの準備

## 🎯 このChapterの目標

このChapterでは、アプリケーションの開発やテストを効率的に進めるための「初期データ（マスタデータ）」をデータベースに投入する方法を学びます。Seederは、レストランを開店する前の「仕込み」作業に似ています。お客様（ユーザー）が来店したときにすぐに料理（機能）を提供できるよう、あらかじめ野菜を切ったり（ユーザー登録）、スープのベースを作ったり（ジャンル登録）しておくことで、本番の作業がスムーズに進むのです。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| Seederの役割 | なぜ手動でデータを登録するのではなく、Seederを使うのか |
| 複数Seederの作成 | `make:seeder` コマンドを使い、リソースごとにSeederファイルを作成 |
| Seederの実装 | `run` メソッド内にデータ作成ロジックを記述 |
| Seederの実行順序と依存関係 | `DatabaseSeeder` で複数Seederを正しい順序で実行する方法 |
| migrate:fresh --seed | テーブル再作成 + データ投入を一発で実行するコマンド |

---

## 📖 背景知識：なぜSeederが重要なのか？

開発の初期段階では、機能が正しく動くかを確認するために、たくさんのテストデータが必要になります。例えば、ランキング機能を実装するには、複数の書籍とそれに対する複数のレビューがなければ、ランキングが正しく表示されるか確認できません。毎回手動でこれらのデータを登録するのは非常に手間がかかり、非効率です。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 機能テストのたびに手動でデータを登録するのが面倒 | **Seeder**を使って、コマンド一つで必要な初期データを全て投入できるようにする | `migrate:fresh --seed`を実行するだけで、いつでもクリーンな状態からテストを開始できる。 |
| 複数人で開発する際に、各自のローカル環境のデータがバラバラになる | 全員が同じSeederを共有する | 開発者全員が同じデータセットを元に開発でき、「自分の環境では動いた」問題を減らせる。 |
| どんなデータが必要だったか忘れてしまう | Seederファイルを見れば必要なデータ構造が一目瞭然 | Seederは「動く仕様書」としての役割も果たす。 |

---

## 📋 実装の手順

### 5.1. Seederファイルの作成

```bash
sail artisan make:seeder GenreSeeder
sail artisan make:seeder UserSeeder
sail artisan make:seeder BookSeeder
sail artisan make:seeder ReviewSeeder
sail artisan make:seeder FavoriteSeeder
sail artisan make:seeder ReviewLikeSeeder
```

これにより、`database/seeders` ディレクトリに6つのファイルが作成されます。

---

## 💭 なぜこう作るのか？

### Seeder の実行順序が重要な理由

Seeder にはデータの依存関係があります。例えば `BookSeeder` は `UserSeeder`（登録者のユーザー）と `GenreSeeder`（紐付けるジャンル）が完了している必要があります。依存関係を無視して実行すると、外部キー制約違反のエラーが発生します。

> **重要：応用版（Advanced）の実行順序**
> 応用版の `DatabaseSeeder` では **`GenreSeeder` を最初に、次に `UserSeeder`** の順で実行します。これは `BookSeeder` がジャンル情報も使用するため、ジャンルデータが先に存在している必要があるためです。

```
GenreSeeder → UserSeeder → BookSeeder → ReviewSeeder → FavoriteSeeder → ReviewLikeSeeder
```

### create() vs firstOrCreate()

完全手順書のSeederでは `Model::create()` を使用しています。これはSeederを `migrate:fresh --seed` で実行することを前提としているため、テーブルは常に空の状態です。一方、既存データがある状態で追加したい場合は `firstOrCreate()` が安全です。

### ランダムデータの意義

`ReviewSeeder` では `$users->random()` を使って各書籍にランダムなユーザーのレビューを割り当てています。これにより、ランキング機能やレポート機能のテストで、より現実に近いデータ分布を再現できます。

---

## 🚀 コードの実装

### 5.2.1. GenreSeeder

`database/seeders/GenreSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            '文学・小説',
            '��ジネス・経済',
            '自己啓発',
            'コンピュータ・IT',
            '科学・テクノロジー',
            '歴史・地理',
            '芸術・エンターテインメント',
            '健康・医学',
            '料理・グルメ',
            '旅行・ガイド',
        ];

        foreach ($genres as $name) {
            Genre::create(['name' => $name]);
        }
    }
}
```

### 5.2.2. UserSeeder

`database/seeders/UserSeeder.php`

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
            ['name' => '山田太郎', 'email' => 'yamada@example.com'],
            ['name' => '鈴木花子', 'email' => 'suzuki@example.com'],
            ['name' => '田中一郎', 'email' => 'tanaka@example.com'],
            ['name' => '佐藤美咲', 'email' => 'sato@example.com'],
            ['name' => '高橋健太', 'email' => 'takahashi@example.com'],
        ];

        foreach ($users as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
            ]);
        }
    }
}
```

### 5.2.3. BookSeeder

`database/seeders/BookSeeder.php`

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
        $users = User::all();
        $genres = Genre::all();

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である珍野苦沙弥先生の家に飼われている猫の視点から、人間社会を風刺的に描いた作品。',
                'image_url' => 'https://cover.openbd.jp/9784101010014.jpg',
                'genres' => ['文学・小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の古典として、あらゆる自己啓発本の原点となったベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784422100524.jpg',
                'genres' => ['ビジネス・経済', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニックを紹介。',
                'image_url' => 'https://cover.openbd.jp/9784873115658.jpg',
                'genres' => ['コンピュータ・IT'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '人格主義の回復を訴え、真の成功を得るための7つの習慣を説く。',
                'image_url' => 'https://cover.openbd.jp/9784863940246.jpg',
                'genres' => ['ビジネス・経済', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '四国の中学校に赴任した江戸っ子の数学教師「坊っちゃん」の物語。',
                'image_url' => 'https://cover.openbd.jp/9784101010021.jpg',
                'genres' => ['文学・小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => 'なぜ人類だけが文明を築けたのか？その謎を解き明かす世界的ベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784309226712.jpg',
                'genres' => ['歴史・地理', '科学・テクノロジー'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => 'アジャイルソフトウェア達人の技。クリーンなコードを書くための実践的ガイド。',
                'image_url' => 'https://cover.openbd.jp/9784048930598.jpg',
                'genres' => ['コンピュータ・IT'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式でわかりや���く解説した自己啓発書。',
                'image_url' => 'https://cover.openbd.jp/9784478025819.jpg',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '芥川賞受賞作。売れない芸人の青春と友情を描いた純文学。',
                'image_url' => 'https://cover.openbd.jp/9784163902302.jpg',
                'genres' => ['文学・小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => '10の思い込みを乗り越え、データを基に世界を正しく見る習慣。',
                'image_url' => 'https://cover.openbd.jp/9784822289607.jpg',
                'genres' => ['ビジネス・経済', '科学・テクノロジー'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822245566',
                'published_date' => '2007-01-18',
                'description' => '世界を変えたのは「箱」の発明だった。物流革命の歴史。',
                'image_url' => 'https://cover.openbd.jp/9784822245566.jpg',
                'genres' => ['ビジネス・経済', '歴史・地理'],
            ],
        ];

        foreach ($books as $bookData) {
            $book = Book::create([
                'user_id' => $users->random()->id,
                'title' => $bookData['title'],
                'author' => $bookData['author'],
                'isbn' => $bookData['isbn'],
                'published_date' => $bookData['published_date'],
                'description' => $bookData['description'],
                'image_url' => $bookData['image_url'],
            ]);

            $genreIds = $genres->whereIn('name', $bookData['genres'])->pluck('id');
            $book->genres()->attach($genreIds);
        }
    }
}
```

### 5.2.4. ReviewSeeder

`database/seeders/ReviewSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        $comments = [
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
        ];

        foreach ($books as $book) {
            $reviewCount = rand(2, 4);
            $reviewers = $users->random($reviewCount);

            foreach ($reviewers as $user) {
                $rating = rand(1, 5);
                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => $comments[$rating][array_rand($comments[$rating])],
                ]);
            }
        }
    }
}
```

### 5.2.5. FavoriteSeeder

`database/seeders/FavoriteSeeder.php`

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

        foreach ($users as $user) {
            $favoriteCount = rand(3, 5);
            $favoriteBooks = $books->random($favoriteCount);
            $user->favoriteBooks()->attach($favoriteBooks->pluck('id'));
        }
    }
}
```

### 5.2.6. ReviewLikeSeeder

`database/seeders/ReviewLikeSeeder.php`

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

        foreach ($reviews as $review) {
            $candidates = $users->where('id', '!=', $review->user_id);
            $maxLikes = min(3, $candidates->count());
            $likeCount = rand(0, $maxLikes);
            if ($likeCount > 0) {
                $likers = $candidates->random($likeCount);
                $review->likedByUsers()->attach($likers->pluck('id'));
            }
        }
    }
}
```

### 5.3. DatabaseSeeder への登録

`database/seeders/DatabaseSeeder.php`

> **重要:** 応用版（Advanced）では **GenreSeeder を最初に** 実行します。BookSeeder がジャンルデータに依存しているためです。

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GenreSeeder::class,
            UserSeeder::class,
            BookSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            ReviewLikeSeeder::class,
        ]);
    }
}
```

### 5.4. データベースへのデータ投入

```bash
sail artisan migrate:fresh --seed
```

---

## 🔍 コードリーディング

### BookSeeder の主要ロジック

| コード | 解説 |
|:---|:---|
| `$users = User::all()` | 全ユーザーをコレクションとして取得。後で `->random()` でランダム選択に使用します。 |
| `$genres = Genre::all()` | 全ジャンルをコレクションとして取得。ジャンル名からIDを検索するために使用します。 |
| `$users->random()->id` | コレクションからランダムに1人のユーザーを選び、そのIDを返します。各書籍の登録者をランダムに割り当てます。 |
| `$genres->whereIn('name', $bookData['genres'])->pluck('id')` | ジャンル名の配列から対応するジャンルIDの配列を取得します。`whereIn` はコレクションのフィルタメソッドです。 |
| `$book->genres()->attach($genreIds)` | `book_genre` 中間テーブルにレコードを挿入し、書籍とジャンルを紐付けます。 |

### ReviewLikeSeeder の主要ロジック

| コード | 解説 |
|:---|:---|
| `$candidates = $users->where('id', '!=', $review->user_id)` | レビュー投稿者自身を除外した候補ユーザーリスト。自分のレビューに自分でいいねしないようにするためです。 |
| `$candidates->random($likeCount)` | 候補の中からランダムに `$likeCount` 人を選びます。 |
| `$review->likedByUsers()->attach($likers->pluck('id'))` | `review_likes` 中間テーブルにレコードを挿入し、いいねを記録します。 |

### DatabaseSeeder の実行順序

```
GenreSeeder      → ジャンルマスタ（依存なし）
  ↓
UserSeeder       → ユーザー（依存なし）
  ↓
BookSeeder       → 書籍（ユーザー + ジャンルに依存）
  ↓
ReviewSeeder     → レビュー（ユーザー + 書籍に依存）
  ↓
FavoriteSeeder   → お気に入り（ユーザー + 書籍に依存）
  ↓
ReviewLikeSeeder → いいね（ユーザー + レビューに依存）
```

---

## 🧐 調べ方のヒント

分からないことがあった時、AIに聞くプロンプト例を紹介します。

| 疑問 | プロンプト例 |
|:---|:---|
| Seeder の基本 | 「Laravel のシーダーの基本的な使い方を教えてください。make:seeder から db:seed までの流れを知りたいです。」 |
| attach と sync の違い | 「Laravel の belongsToMany で attach, sync, syncWithoutDetaching の違いを教えてください。」 |
| Collection メソッド | 「Laravel Collection の whereIn, pluck, random メソッドの使い方を教えてください。Seeder でよく使うパターンも知りたいです。」 |
| migrate:fresh --seed | 「Laravel の migrate:fresh と migrate:refresh の違いを教えてください。--seed オプションは何をしますか？」 |
| ランダムデータの生成 | 「Laravel Seeder で rand() と fake() を使い分ける基準を教えてください。」 |

---

## ✅ 動作確認

シーディングが正しく完了したか確認しましょう。

| 確認項目 | 確認方法 |
|:---|:---|
| コマンド成功 | `sail artisan migrate:fresh --seed` がエラーなく完了すること |
| ユーザー | phpMyAdmin で `users` テーブルに5件のレコードがあること |
| ジャンル | `genres` テーブルに10件のレコードがあること |
| 書籍 | `books` テーブルに11件のレコードがあること |
| レビュー | `reviews` テーブルにレコードが存在すること（件数はランダム） |
| ジャンル紐付け | `book_genre` テーブルにレコードが存在すること |
| お気に入り | `favorites` テーブルにレコードが存在すること |
| いいね | `review_likes` テーブルにレコードが存在すること |
| ログイン確認 | `yamada@example.com` / `password` でログインできること |

---

## ✨ このChapterのまとめ

このChapterでは、6つのSeederを作成し、アプリケーションの開発・テストに必要な初期データを一括投入する仕組みを構築しました。

| Seeder | 投入データ | 依存先 |
|:---|:---|:---|
| `GenreSeeder` | ジャンル10種類 | なし |
| `UserSeeder` | ダミーユーザー5人 | なし |
| `BookSeeder` | 書籍11冊 + ジャンル紐付け | User, Genre |
| `ReviewSeeder` | ランダムレビュー | User, Book |
| `FavoriteSeeder` | ランダムお気に入り | User, Book |
| `ReviewLikeSeeder` | ランダムいいね | User, Review |

**コマンド一発でクリーンな状態に:**

```bash
sail artisan migrate:fresh --seed
```

これで、アプリケーションの「仕込み」は完了です。次のChapterからは、いよいよメイン機能である書籍のCRUD（作成・読み取り・更新・削除）を実装していきます。
