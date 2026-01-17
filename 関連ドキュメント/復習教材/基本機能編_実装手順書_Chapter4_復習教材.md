# Chapter 4: 認証機能の実装

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの「入り口」となる認証機能を実装します。Laravelが提供する柔軟な認証バックエンド「Fortify」を利用して、ログイン・ログアウト・新規登録といった基本的な機能を構築します。

- **Laravel Fortifyの役割**: なぜBreezeやJetstreamのようなUI付きのパッケージではなく、バックエンド処理に特化したFortifyを使うのかを理解します。
- **認証ルートの構築と分割**: ログイン、登録などの認証関連ルートを`auth.php`に分離する理由と、そのメリットを学びます。
- **Fortifyのカスタマイズ**: 設定ファイルやサービスプロバイダを調整し、自作のビューを使って認証機能を提供する方法を学びます。
- **RouteServiceProviderの役割**: 分離したルートファイルをアプリケーションに認識させる「配線役」の重要性を理解します。

---

## 🧠 先輩エンジニアの思考プロセス：なぜUI付きパッケージを使わないのか？

LaravelにはBreezeやJetstreamといった、UIまで含めて認証機能を一瞬で実装できる便利なパッケージがあります。しかし、実務では必ずしもそれらの提供するUIが要件に合うとは限りません。むしろ、デザイナーが作成した独自のUIに、Laravelの認証機能を「接続する」場面の方が圧倒的に多いのです。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| Breeze等が提供するUIは、今回のデザインと異なる | **Laravel Fortify**を使い、認証のバックエンド処理のみを導入する | UI（Bladeファイル）は自前で用意したもの（`Preparedblade-mockcase-BookShelf`）を使い、機能だけをLaravelに任せることで、要件通りの画面と機能を両立できる。 |
| 認証の仕組みがブラックボックス化しやすい | 認証ルートや設定を**手動で構築**する | ログイン画面がどう表示され、ログイン処理がどう実行されるのか、一連の流れをコードレベルで追うことで、認証の仕組みを深く理解できる。 |
| ログイン後の遷移先などを柔軟に変更したい | **ServiceProvider**や設定ファイルをカスタマイズする | アプリケーションの仕様に合わせて、認証に関する様々な動作を自由に変更できるようになる。 |

Fortifyは、認証機能の「エンジン」部分だけを提供してくれるパッケージです。車のボディ（UI）は自分たちで自由にデザインし、そこに強力なエンジン（Fortify）を搭載する、というイメージを持つと分かりやすいでしょう。

---

## 4.1. Laravel Fortifyのインストール

まず、Composerを使ってLaravel Fortifyをプロジェクトにインストールします。その後、`vendor:publish`コマンドで設定ファイルをプロジェクト内にコピーします。

```bash
# Laravel Fortifyをインストール
sail composer require laravel/fortify

# Fortifyのサービスプロバイダと設定ファイルを公開
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

このコマンドにより、`config/fortify.php`という設定ファイルと、`app/Providers/FortifyServiceProvider.php`が作成されます。

---

## 4.2. 認証ルートの作成と分離

次に、ログイン、新規登録、ログアウトなどの認証関連のルートを定義します。今回は`routes/web.php`が煩雑になるのを避けるため、`routes/auth.php`というファイルを新規に作成して、そこに認証ルートをまとめて記述します。

### なぜ`auth.php`に分離するのか？ - 関心の分離という考え方

なぜ、すべてのルートを`web.php`にまとめず、わざわざ`auth.php`という別のファイルを作成するのでしょうか？これは、ソフトウェア設計における非常に重要な原則である「**関心の分離 (Separation of Concerns)**」に基づいています。

| 目的 | 解説 |
|:---|:---|
| **可読性の向上** | `web.php`にはアプリケーションの主要機能（書籍、レビュー、検索など）のルートが、`auth.php`には認証関連のルートだけが存在することになります。これにより、ファイルの見通しが良くなり、「認証のルートを変更したい」と思ったときに、迷わず`auth.php`を開くことができます。 |
| **メンテナンス性の向上** | アプリケーションが大規模になると、`web.php`は数百行、数千行に膨れ上がる可能性があります。機能ごとにファイルが適切に分割されていれば、コードの修正や追加が容易になり、修正による影響範囲も特定しやすくなります。これは、バグの発生を防ぎ、将来の機能拡張をスムーズに進める上で不可欠です。 |
| **責務の明確化** | `web.php`は「アプリケーションの通常機能の交通整理」、`auth.php`は「ユーザーの出入りを管理する受付」というように、それぞれのファイルが持つ役割（責務）が明確になります。 |
| **Laravelの標準への準拠** | Laravelの標準的なスターターキットであるBreezeなどでも、認証ルートは`auth.php`に分離されています。この「お作法」に従うことで、チームに新しい開発者が加わった際にも、コードの構造をすぐに理解してもらえます。 |

料理に例えるなら、`web.php`が「メインディッシュのレシピブック」、`auth.php`が「ドリンクメニュー」です。両方を一つのノートに書いても機能しますが、別々にまとめておいた方が、探しやすく、管理しやすいのは明らかでしょう。

### `auth.php`の作成

それでは、`routes/auth.php`を新規に作成し、以下の内容を記述します。

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// 未ログインユーザー向けのルート
Route::middleware("guest")->group(function () {
    // ログイン画面表示
    Route::get("/login", function () {
        return view("auth.login");
    })->name("login");

    // 新規登録画面表示
    Route::get("/register", function () {
        return view("auth.register");
    })->name("register");
});

// ログイン済みユーザー向けのルート
Route::middleware("auth")->group(function () {
    // ログアウト処理
    Route::post("/logout", [AuthenticatedSessionController::class, "destroy"])
        ->name("logout");
});
```

> **💡 ポイント**
> この`auth.php`ファイルは、このままではアプリケーションに認識されません。次のステップで`RouteServiceProvider`から読み込む設定を追加します。この時点ではファイルを作成するだけで問題ありません。

---

## 4.3. Fortifyの設定

インストールしたFortifyが、私たちが用意したビューやルート定義を使うように設定を調整していきます。

### 4.3.1. Fortifyのデフォルトビューを無効化

Fortifyはデフォルトで自身の持つビューを使おうとします。今回は自前のビューを使うため、この機能を無効化します。

`config/fortify.php` を開き、`views`の値を`false`に修正してください。

```php
// config/fortify.php

// 変更前
// 'views' => true,
// 変更後
'views' => false,
```

### 4.3.2. Fortifyのサービスプロバイダを登録

Fortifyをアプリケーションに正式に登録するため、`config/app.php`の`providers`配列に`FortifyServiceProvider`を追加します。

```php
// config/app.php

'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\RouteServiceProvider::class,
    App\Providers\FortifyServiceProvider::class, // ← これを追加
])->toArray(),
```

### 4.3.3. Fortifyが使用するビューの指定

`app/Providers/FortifyServiceProvider.php` を開き、`boot` メソッド内で、Fortifyがログイン画面や新規登録画面としてどのBladeファイルを呼び出すべきかを明示的に指定します。

```php
// app/Providers/FortifyServiceProvider.php

use Laravel\Fortify\Fortify;

// ...

public function boot(): void
{
    // Fortifyに、ログイン画面として `auth.login` ビューを使うよう指示
    Fortify::loginView(fn () => view("auth.login"));

    // Fortifyに、新規登録画面として `auth.register` ビューを使うよう指示
    Fortify::registerView(fn () => view("auth.register"));
}
```

---

## 4.4. RouteServiceProviderの設定 - ルートファイルの「配線」

最後に、ここまで準備してきた`auth.php`をアプリケーションに正式に認識させるための「配線作業」を行います。また、ユーザーがログインした後にどこへ遷移するかの設定もここで行います。

### RouteServiceProviderの役割とは？

`app/Providers/RouteServiceProvider.php`は、Laravelアプリケーションの「**ルートの司令塔**」です。どのルートファイルを読み込み、それらにどのような共通設定（ミドルウェアやプレフィックスなど）を適用するかを一元管理する役割を担っています。

先ほど`auth.php`を作成しましたが、これはただファイルを作っただけではLaravelに認識されません。`RouteServiceProvider`に「`routes/auth.php`というファイルもルート定義として読み込んでください」と明示的に指示してあげる必要があります。

### 設定の解説

`app/Providers/RouteServiceProvider.php`を開き、以下の2点を修正・追記します。

```php
// app/Providers/RouteServiceProvider.php

class RouteServiceProvider extends ServiceProvider
{
    /**
     * ログイン後のリダイレクト先
     */
    public const HOME = '/'; // ① ログイン後の遷移先をルートパスに変更

    /**
     * ルートの定義
     */
    public function boot(): void
    {
        $this->routes(function () {
            // ... web.phpの読み込み設定 ...
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // ② auth.phpを読み込む処理を追記
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        });
    }
}
```

| 番号 | コード | 解説 |
|:---|:---|:---|
| **①** | `public const HOME = '/';` | ユーザーがログインに成功した後のリダイレクト先を定義します。デフォルトの`/home`から、このアプリケーションのトップページである`/`に変更します。 |
| **②** | `Route::middleware('web')->group(base_path('routes/auth.php'));` | これが「配線」の核心部分です。`routes/auth.php`ファイルを読み込み、そこに定義されているルート群に対して`web`ミドルウェアグループを適用するよう指示しています。`web`ミドルウェアには、セッション管理やCSRF保護など、Webアプリケーションに必須の機能が含まれています。 |

この設定により、`auth.php`に書かれたルート（`/login`, `/register`, `/logout`）が`web.php`のルートと同様に扱われ、アプリケーション全体で有効になります。

これで、認証機能のバックエンド側の設定は完了です。次のChapterでは、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
