# Chapter 4: 認証機能とマスタデータの準備

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの「入り口」を整備します。具体的には以下の点を学びます。

- **認証機能の実装**: ユーザー登録（会員登録）とログイン機能を、Laravelの仕組みを活用して実装します。
- **レイアウトとコンポーネント**: 全ページ共通のヘッダーやフッターを「レイアウト」として定義し、再利用可能な「コンポーネント」を作成します。
- **マスタデータのシーディング**: 開発やテストを効率化するために、ジャンルなどの初期データをデータベースに投入する方法を学びます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜ認証を先に実装するのか？

多くのWebアプリケーションでは、「誰がこの操作を行っているか」を特定することが重要です。書籍の登録やレビューの投稿は、ログインしているユーザーに紐づけて記録する必要があります。認証機能を先に整備しておくことで、後続の機能開発がスムーズに進みます。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 誰が書籍を登録したか分からない | **認証機能**でログインユーザーを特定し、`user_id`を記録する | 書籍やレビューの「所有者」を明確にし、編集・削除の権限管理を可能にする。 |
| 全ページでヘッダーやフッターを毎回書くのは非効率 | **レイアウト**と**コンポーネント**で共通部分を一元管理する | DRY原則（Don\'t Repeat Yourself）に従い、保守性を高める。 |
| 開発中にテストデータを手動で入力するのは面倒 | **Seeder**で初期データを自動投入する | `sail artisan migrate:fresh --seed`一発で、いつでもクリーンな状態から開発を再開できる。 |

---

## 4.1. Laravel Breezeのインストール

Laravel Breezeは、認証機能の雛形（ルート、コントローラー、ビュー）を自動で生成してくれる便利なパッケージです。

```bash
# Laravel Breezeをインストール
sail composer require laravel/breeze --dev

# Breezeをインストール（Reactオプション付き）
sail artisan breeze:install react
```

> **💡 ポイント**
> `breeze:install`を実行すると、`routes/auth.php`や認証関連のコントローラー、ビューが自動で作成されます。これにより、手動で作成する手間が大幅に省けます。

---

## 4.2. レイアウトとコンポーネントの作成

Breezeによって生成されたファイルに加えて、本アプリケーションで必要なレイアウトとコンポーネントを作成します。

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/layouts
mkdir -p resources/views/components
mkdir -p app/View/Components

touch resources/views/layouts/app.blade.php
touch resources/views/layouts/guest.blade.php
touch app/View/Components/AppLayout.php
touch app/View/Components/GuestLayout.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 4.3. 認証ビューの作成

ログイン画面と会員登録画面のビューを作成します。

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/auth
touch resources/views/auth/login.blade.php
touch resources/views/auth/register.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 4.4. マスタデータの準備（ジャンルSeeder）

開発を効率化するために、ジャンルの初期データをデータベースに投入します。

### 4.4.1. Seederファイルの作成

```bash
sail artisan make:seeder GenreSeeder
```

### 4.4.2. Seederの実装

```php
// database/seeders/GenreSeeder.php

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
            \'小説\',
            \'ビジネス\',
            \'技術書\',
            \'自己啓発\',
            \'エッセイ\',
            \'歴史\',
            \'科学\',
            \'芸術\',
            \'料理\',
            \'旅行\',
        ];

        foreach ($genres as $genre) {
            Genre::firstOrCreate([\'name\' => $genre]);
        }
    }
}
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `Genre::firstOrCreate([...])` | 指定した条件のレコードが存在すれば取得し、なければ作成します。 | `Genre` | Seederを複数回実行しても、重複データが作成されません。 |

### 4.4.3. DatabaseSeederへの登録

```php
// database/seeders/DatabaseSeeder.php

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GenreSeeder::class,
        ]);
    }
}
```

### 4.4.4. データベースへのデータ投入

```bash
# テーブルを再作成し、Seederを実行
sail artisan migrate:fresh --seed
```

> **🧠 先輩エンジニアの思考プロセス**
> `migrate:fresh --seed`は、開発中に「データベースをまっさらな状態に戻して、初期データを入れ直す」ときに非常に便利です。本番環境では絶対に実行しないでください（全データが消えます）。

これで、認証機能と初期データの準備が整いました。次のChapterでは、いよいよ書籍管理機能（CRUD）を実装していきます。
