# Chapter 05: 「開店前の仕込み」 - マスタデータの準備

## 🎯 このChapterの目標

このChapterでは、アプリケーションの開発やテストを効率的に進めるための「初期データ（マスタデータ）」をデータベースに投入する方法を学びます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| Seederの役割 | なぜ手動でデータを登録するのではなく、Seederを使うのか |
| 複数Seederの作成 | `make:seeder` コマンドを使い、リソースごとにSeederファイルを作成 |
| Seederの実行順序と依存関係 | `DatabaseSeeder` で複数Seederを正しい順序で実行する方法 |
| migrate:fresh --seed | テーブル再作成 + データ投入を一発で実行するコマンド |

---

## 📖 背景知識：なぜSeederが重要なのか？

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 機能テストのたびに手動でデータを登録するのが面倒 | **Seeder**を使って、コマンド一つで必要な初期データを全て投入 | `migrate:fresh --seed`を実行するだけで、いつでもクリーンな状態からテストを開始できる。 |
| 複数人で開発する際に、各自のローカル環境のデータがバラバラ | 全員が同じSeederを共有する | 開発者全員が同じデータセットを元に開発できる。 |
| どんなデータが必要だったか忘れてしまう | Seederファイルを見れば必要なデータ構造が一目瞭然 | Seederは「動く仕様書」としての役割も果たす。 |

---

## 📋 実装の手順

### 5.1. Seederファイルの作成

```bash
sail artisan make:seeder UserSeeder
sail artisan make:seeder GenreSeeder
sail artisan make:seeder BookSeeder
sail artisan make:seeder ReviewSeeder
sail artisan make:seeder FavoriteSeeder
sail artisan make:seeder ReviewLikeSeeder
```

---

## 💭 なぜこう作るのか？

### Seeder の実行順序が重要な理由

基本版の `DatabaseSeeder` では **`UserSeeder` を最初に、次に `GenreSeeder`** の順で実行します。`BookSeeder` はユーザーデータとジャンルデータの両方に依存するためです。

```
UserSeeder → GenreSeeder → BookSeeder → ReviewSeeder → FavoriteSeeder → ReviewLikeSeeder
```

### firstOrCreate() の利点

基本版のSeederでは `firstOrCreate()` を使用しています。これにより、Seederを2回実行しても重複エラーが発生しません。`migrate:fresh --seed` でテーブルを再作成する前提であれば `create()` でも問題ありませんが、`firstOrCreate()` はより安全な選択です。

### syncWithoutDetaching() の利点

`FavoriteSeeder` と `ReviewLikeSeeder` では `syncWithoutDetaching()` を使用しています。これは既存のレコードを削除せずに、新しいレコードだけを追加するメソッドです。

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
        $user = User::first();

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である珍野苦沙弥の家に飼われている猫である「吾輩」の視点から...',
                'image_url' => 'https://cover.openbd.jp/9784101010014.jpg',
                'genres' => ['小説'],
            ],
            // ... 他10冊も同様の構造で定義
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

> **ポイント:** `User::first()` で最初のユーザー（山田太郎）を全書籍の登録者として使用し、`firstOrCreate()` でISBNの重複を防いでいます。ジャンル紐付けには `sync()` を使用しています。

### 5.2.4. ReviewSeeder

基本版では、各書籍に対して固定のレビューデータを `firstOrCreate()` で投入します。評価は3~5の範囲で設定されています。

### 5.2.5. FavoriteSeeder / ReviewLikeSeeder

お気に入りといいねの紐付けには `syncWithoutDetaching()` を使用します。

### 5.3. DatabaseSeeder への登録

`database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            ReviewLikeSeeder::class,
        ]);
    }
}
```

> **注意:** 基本版では **UserSeeder を最初に** 実行します。

### 5.4. データベースへのデータ投入

```bash
sail artisan migrate:fresh --seed
```

---

## 🔍 コードリーディング

### BookSeeder の主要ロジック

| コード | 解説 |
|:---|:---|
| `$user = User::first()` | 最初のユーザー（山田太郎）を取得。全書籍の登録者として使用します。 |
| `Book::firstOrCreate(['isbn' => ...], ...)` | ISBNが一致するレコードがなければ新規作成、あればそのレコードを返します。 |
| `Genre::whereIn('name', $genreNames)->pluck('id')->toArray()` | ジャンル名の配列から対応するIDの配列を取得します。 |
| `$book->genres()->sync($genreIds)` | 中間テーブルのジャンル紐付けを同期します。 |

### DatabaseSeeder の実行順序

```
UserSeeder       → ユーザー（依存なし）
  ↓
GenreSeeder      → ジャンルマスタ（依存なし）
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

| 疑問 | プロンプト例 |
|:---|:---|
| Seeder の基本 | 「Laravel のシーダーの基本的な使い方を教えてください。」 |
| firstOrCreate と create の違い | 「Laravel の firstOrCreate と create の違いを教えてください。Seeder ではどちらを使うべきですか？」 |
| attach と sync の違い | 「Laravel の belongsToMany で attach, sync, syncWithoutDetaching の違いを教えてください。」 |
| migrate:fresh --seed | 「Laravel の migrate:fresh と migrate:refresh の違いを教えてください。」 |

---

## ✅ 動作確認

| 確認項目 | 確認方法 |
|:---|:---|
| コマンド成功 | `sail artisan migrate:fresh --seed` がエラーなく完了すること |
| ユーザー | `users` テーブルに5件のレコードがあること |
| ジャンル | `genres` テーブルに10件のレコードがあること |
| 書籍 | `books` テーブルに11件のレコードがあること |
| ログイン確認 | `yamada@example.com` / `password` でログインできること |

---

## ✨ このChapterのまとめ

| Seeder | 投入データ | 依存先 |
|:---|:---|:---|
| `UserSeeder` | ダミーユーザー5人 | なし |
| `GenreSeeder` | ジャンル10種類 | なし |
| `BookSeeder` | 書籍11冊 + ジャンル紐付け | User, Genre |
| `ReviewSeeder` | 固定レビュー | User, Book |
| `FavoriteSeeder` | お気に入り | User, Book |
| `ReviewLikeSeeder` | いいね | User, Review |

**コマンド一発でクリーンな状態に:**

```bash
sail artisan migrate:fresh --seed
```

次のChapterからは、いよいよメイン機能である書籍のCRUD（作成・読み取り・更新・削除）を実装していきます。
