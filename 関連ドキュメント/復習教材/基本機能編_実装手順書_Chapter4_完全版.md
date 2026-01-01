'''
# Chapter 4: 認証機能とマスタデータの準備

このChapterでは、アプリケーションの「門」となる認証機能を実装し、書籍登録で必要になる「ジャンル」の初期データ（マスタデータ）を準備します。

---

## 4-1. 先輩エンジニアの思考プロセス：なぜこのタイミングで認証とSeederなのか？

### なぜCRUD機能の前に「認証」を実装するのか？

データベースの骨組み（モデルとリレーション）ができた今、次はいよいよ機能実装です。しかし、書籍の登録（Create）やレビュー投稿（Create）の前に、なぜ「認証」を実装するのでしょうか？

> **先輩エンジニアの思考:**
> 「このアプリケーションのほとんどの機能は、『誰が』行うかが重要だ。『誰が書籍を登録したか』『誰がレビューを書いたか』。これらの操作はすべて、ログインしている特定のユーザーに紐づく。認証機能がなければ、`Auth::id()` のようなコードが使えず、機能の根幹を実装・テストすることができない。だから、具体的なデータ操作（CRUD）の前に、まずユーザーを特定するための認証機能を実装するのが最も効率的なんだ。」

開発の順序はパズルのようなものです。依存関係を考え、最もスムーズに進むルートを選択することが、手戻りのない開発に繋がります。

| 開発ステップ | 目的 |
|:---|:---|
| 1. DB設計 (Chapter 2) | データの保管場所を決める（基礎工事） |
| 2. モデル定義 (Chapter 3) | データ同士の関係性を定義する（骨組み） |
| 3. **認証機能 (Chapter 4)** | **誰が操作するのかを確定させる（門の設置）** |
| 4. CRUD機能 (Chapter 5以降) | 具体的な機能を実装する（内装工事） |

### なぜLaravel Fortifyを選ぶのか？

Laravelには、BreezeやJetstreamといった便利な認証スターターキットがあります。これらはコマンド一つで認証画面（ビュー）まで自動生成してくれます。では、なぜ今回はバックエンド機能のみを提供するFortifyをあえて使うのでしょうか？

> **先輩エンジニアの思考:**
> 「Breezeは便利だが、裏側で何が起きているかが見えにくい。今回は学習が目的だ。Fortifyを使って、認証用のルート、コントローラー、ビューを自分で一つずつ作ることで、『ログイン処理がどのように実行され、どのビューが表示されるのか』という一連の流れを深く理解できる。この経験は、将来複雑な認証要件に直面したときに必ず役立つ。」

学習目的であるため、あえて手間のかかる方法を選び、認証の仕組みそのものを理解することに重きを置いています。

### なぜ「Seeder」でマスタデータを用意するのか？

書籍を登録するには、予め「ジャンル」がデータベースに存在している必要があります。このようなアプリケーションの基本動作に必須の初期データを**マスタデータ**と呼びます。

> **先輩エンジニアの思考:**
> 「開発環境を再構築するたびに、手動で`INSERT`文を実行してジャンルを登録するのは非効率的で、ミスも起きやすい。Seederファイルに初期データを定義しておけば、`sail artisan migrate:fresh --seed`コマンド一発で、誰が実行しても同じ状態のデータベースを再現できる。これにより、開発チーム内での環境差異がなくなり、開発効率が大幅に向上するんだ。」

Seederは、開発の再現性と効率性を担保するための重要な仕組みです。

---

## 4.2. Laravel Fortifyのインストール

まず、Composerを使ってFortifyをインストールし、設定ファイルを公開（publish）します。

```bash
sail composer require laravel/fortify
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

---

## 4.3. 認証関連ファイルの作成

Fortifyはバックエンドのロジックのみを提供するため、画面（ビュー）やレイアウトは全て手動で作成します。これは認証の仕組みを理解するための重要なステップです。

### 1. 認証ルートの作成 (`routes/auth.php`)

ログイン画面や登録画面へのルートを定義します。このファイルは後ほど`routes/web.php`から読み込まれます。

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// 未ログインユーザーのみアクセス可能
Route::middleware("guest")->group(function () {
    Route::get("/login", function () {
        return view("auth.login");
    })->name("login");

    Route::get("/register", function () {
        return view("auth.register");
    })->name("register");
});

// ログイン済みユーザーのみアクセス可能
Route::middleware("auth")->group(function () {
    Route::post("/logout", [AuthenticatedSessionController::class, "destroy"])
        ->name("logout");
});
```

### 2. レイアウトとコンポーネントの作成

BreezeやJetstreamと違い、Fortifyには`<x-app-layout>`のような共通レイアウトコンポーネントがありません。そのため、手動で作成します。

- **`resources/views/layouts/app.blade.php`**: ログイン後の画面で使用するメインレイアウト
- **`resources/views/layouts/guest.blade.php`**: ログイン画面や登録画面で使用するゲスト用レイアウト
- **`app/View/Components/AppLayout.php`**: `app.blade.php`を呼び出すコンポーネントクラス
- **`app/View/Components/GuestLayout.php`**: `guest.blade.php`を呼び出すコンポーネントクラス
- **`resources/views/components/`以下の各種ファイル**: ボタンや入力フォームなどのUI部品

*各ファイルのコードは、実装手順書に記載されている通りに作成してください。ここでは詳細を省略します。*

---

## 4.4. Fortifyの設定

Fortifyに、自作したビューを使うように教える設定を行います。

### 1. `config/fortify.php` の設定

Fortifyがデフォルトで提供するビューを無効化します。

```php
// 'views' => true,
'views' => false,
```

### 2. `config/app.php` の設定

Fortifyのサービスプロバイダをアプリケーションに登録します。

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\FortifyServiceProvider::class, // ← これを追加
])->toArray(),
```

### 3. `app/Providers/FortifyServiceProvider.php` の設定

ログイン画面や登録画面として、どのビューファイルを使用するかをFortifyに明示的に伝えます。

```php
use Laravel\Fortify\Fortify;

public function boot(): void
{
    Fortify::loginView(fn () => view("auth.login"));
    Fortify::registerView(fn () => view("auth.register"));
}
```

---

## 4.5. 認証ビューの作成

実際にユーザーが目にするログイン画面と新規登録画面を作成します。

- **`resources/views/auth/login.blade.php`**
- **`resources/views/auth/register.blade.php`**

これらのファイルには、`x-guest-layout`コンポーネントや、`x-input-label`、`x-text-input`といったUIコンポーネントを組み合わせてフォームを構築します。

*各ファイルのコードは、実装手順書に記載されている通りに作成してください。*

---

## 4.6. マスタデータの準備 (GenreSeeder)

書籍登録時に選択肢として表示するための、ジャンルの初期データをSeederで作成します。

### 1. Seederファイルの作成

```bash
sail artisan make:seeder GenreSeeder
```

### 2. Seederの実装 (`database/seeders/GenreSeeder.php`)

`run`メソッドに、データベースに投入したい初期データを配列で定義します。

```php
use App\Models\Genre;

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
```

### 3. DatabaseSeederへの登録

`db:seed`コマンドが実行されたときに`GenreSeeder`が呼び出されるように、`DatabaseSeeder.php`に登録します。

```php
public function run(): void
{
    $this->call([
        GenreSeeder::class,
    ]);
}
```

### 4. データベースへのデータ投入

以下のコマンドで、全てのテーブルを一度削除・再作成（`migrate:fresh`）し、その後Seederを実行（`--seed`）します。

```bash
sail artisan migrate:fresh --seed
```

これで、クリーンなデータベースとマスタデータが準備できました。

---

## 4.7. 動作確認

最後に、実装した認証機能が正しく動作するかを確認します。

1.  `/register`にアクセスして新規ユーザー登録ができるか。
2.  登録後に自動でログインされ、トップページにリダイレクトされるか。
3.  一度ログアウトし、`/login`から再度ログインできるか。

これらの確認が取れれば、認証機能の実装は完了です。
'''
