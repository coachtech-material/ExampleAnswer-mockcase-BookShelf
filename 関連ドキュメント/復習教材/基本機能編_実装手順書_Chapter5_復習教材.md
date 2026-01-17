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
            // ... 他4名分のデータ
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
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = ['小説', 'ビジネス', '技術書', '自己啓発', 'エッセイ', '歴史', '科学', '芸術', '料理', '旅行'];

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
        $user = User::first(); // 最初のユーザーを取得
        $books = [
            // ... 書籍データの配列 ...
        ];

        foreach ($books as $bookData) {
            $genreNames = $bookData['genres'];
            unset($bookData['genres']);

            $book = Book::firstOrCreate(
                ['isbn' => $bookData['isbn']],
                array_merge($bookData, ['user_id' => $user->id])
            );

            $genreIds = Genre::whereIn('name', $genreNames)->pluck('id')->toArray();
            $book->genres()->sync($genreIds);
        }
    }
}
```

(他の`ReviewSeeder`, `FavoriteSeeder`, `ReviewLikeSeeder`も同様に、完全手順書.mdの内容を参考に実装します)

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
