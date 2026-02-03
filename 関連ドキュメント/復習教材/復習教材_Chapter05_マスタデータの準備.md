# Chapter 5: マスタデータの準備

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの基本動作に必要な初期データ（マスタデータ）をSeederを使って準備します。

- **Seederの役割**: なぜ手動でデータを投入するのではなく、Seederを使うのかを理解します。
- **マスタデータとは**: アプリケーションの動作に必須の初期データの概念を学びます。
- **再現性のある環境構築**: `migrate:fresh --seed`で誰でも同じ状態のデータベースを再現できる仕組みを学びます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜSeederでマスタデータを用意するのか？

書籍を登録するには、予め「ジャンル」がデータベースに存在している必要があります。このようなアプリケーションの基本動作に必須の初期データを**マスタデータ**と呼びます。

> **先輩エンジニアの思考:**
> 「開発環境を再構築するたびに、手動で`INSERT`文を実行してジャンルを登録するのは非効率的で、ミスも起きやすい。Seederファイルに初期データを定義しておけば、`sail artisan migrate:fresh --seed`コマンド一発で、誰が実行しても同じ状態のデータベースを再現できる。これにより、開発チーム内での環境差異がなくなり、開発効率が大幅に向上するんだ。」

| 課題 | 解決策 | メリット |
|:---|:---|:---|
| 手動でのデータ投入は面倒でミスが起きやすい | Seederでコード化する | コマンド一発で再現可能 |
| チームメンバー間で環境が異なる | Seederをバージョン管理する | 誰でも同じ状態を再現 |
| 本番環境への初期データ投入が必要 | Seederを本番でも実行可能 | 安全で確実なデプロイ |

---

## 5.1. GenreSeederの作成

書籍登録時に選択肢として表示するための、ジャンルの初期データをSeederで作成します。

### Seederファイルの作成

```bash
sail artisan make:seeder GenreSeeder
```

このコマンドにより、`database/seeders/GenreSeeder.php`が作成されます。

---

## 5.2. GenreSeederの実装

`database/seeders/GenreSeeder.php`を開き、`run`メソッドに初期データを定義します。

```php
<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            '小説', 'ビジネス', '技術書', '自己啓発', 'エッセイ',
            '歴史', '科学', '芸術', '料理', '旅行',
        ];

        foreach ($genres as $genre) {
            // 同じ名前のジャンルが存在しない場合のみ作成する
            Genre::firstOrCreate(['name' => $genre]);
        }
    }
}
```

### 📖 コードリーディング：GenreSeeder

| コード | 構文・メソッド | 解説 |
|:---|:---|:---|
| `$genres = [...]` | 配列 | 投入したいジャンル名を配列で定義します。 |
| `foreach ($genres as $genre)` | foreach文 | 配列の各要素をループで処理します。 |
| `Genre::firstOrCreate(['name' => $genre])` | `firstOrCreate()` | 指定した条件に一致するレコードがあれば取得し、なければ新規作成します。これにより、Seederを複数回実行しても重複データが作成されません。 |

---

## 5.3. DatabaseSeederへの登録

`db:seed`コマンドが実行されたときに`GenreSeeder`が呼び出されるように、`database/seeders/DatabaseSeeder.php`に登録します。

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            GenreSeeder::class,
        ]);
    }
}
```

### 📖 コードリーディング：DatabaseSeeder

| コード | 構文・メソッド | 解説 |
|:---|:---|:---|
| `$this->call([...])` | `call()` | 指定したSeederクラスを順番に実行します。複数のSeederを配列で渡すことで、実行順序を制御できます。 |
| `GenreSeeder::class` | クラス定数 | `GenreSeeder`クラスの完全修飾名を取得します。 |

---

## 5.4. データベースへのデータ投入

以下のコマンドで、全てのテーブルを一度削除・再作成（`migrate:fresh`）し、その後Seederを実行（`--seed`）します。

```bash
sail artisan migrate:fresh --seed
```

### コマンドの解説

| オプション | 解説 |
|:---|:---|
| `migrate:fresh` | 全てのテーブルを削除し、マイグレーションを最初から実行します。 |
| `--seed` | マイグレーション完了後に`DatabaseSeeder`を実行します。 |

> **⚠️ 注意**
> `migrate:fresh`は全てのデータを削除します。本番環境では絶対に実行しないでください。

---

## 5.5. 動作確認

データベースにジャンルが正しく投入されたか確認します。

```bash
sail artisan tinker
```

Tinkerが起動したら、以下のコマンドを実行します。

```php
>>> App\Models\Genre::all();
```

10件のジャンルデータが表示されれば成功です。

---

## 📝 まとめ

このChapterでは、以下のことを学びました。

| 学んだこと | 内容 |
|:---|:---|
| **マスタデータの概念** | アプリケーションの基本動作に必須の初期データ |
| **Seederの作成** | `make:seeder`コマンドでSeederファイルを作成 |
| **firstOrCreate()** | 重複を防ぎながらデータを投入するメソッド |
| **DatabaseSeederへの登録** | `call()`メソッドで実行するSeederを指定 |
| **migrate:fresh --seed** | データベースの初期化とSeeder実行を一括で行う |

これで、認証機能とマスタデータの準備が整いました。次のChapterからは、いよいよ書籍管理機能（CRUD）の実装に入ります。
