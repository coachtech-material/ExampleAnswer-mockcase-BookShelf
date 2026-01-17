# Chapter 4: 認証機能の実装

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの「入り口」となる認証機能を実装します。Laravelが提供する柔軟な認証バックエンド「Fortify」を利用して、ログイン・ログアウト・新規登録といった基本的な機能を構築します。

- **Laravel Fortifyの役割**: なぜBreezeやJetstreamのようなUI付きのパッケージではなく、バックエンド処理に特化したFortifyを使うのかを理解します。
- **認証ルートの構築**: ログイン画面や登録画面の表示、ログイン・ログアウト処理の実行に必要なルートを手動で設定します。
- **Fortifyのカスタマイズ**: 設定ファイルやサービスプロバイダを調整し、自作のビューを使って認証機能を提供する方法を学びます。

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

## 4.2. 認証ルートの作成

次に、ログイン、新規登録、ログアウトなどの認証関連のルートを定義します。今回は`routes/web.php`が煩雑になるのを避けるため、`routes/auth.php`というファイルを新規に作成して、そこに認証ルートをまとめて記述します。

**`routes/auth.php` を新規作成:**

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
> この`auth.php`ファイルは、このままではアプリケーションに認識されません。後のステップで`routes/web.php`から読み込む設定を追加しますが、この時点ではファイルを作成するだけで問題ありません。

---

## 4.3. Fortifyの設定

インストールしたFortifyが、私たちが用意したビューやルート定義を使うように設定を調整していきます。

### 4.3.1. Fortifyのデフォルトビューを無効化

Fortifyはデフォルトで自身の持つビューを使おうとします。今回は自前のビューを使うため、この機能を無効化します。また、ログイン後のリダイレクト先をルートパス(`/`)に設定します。

`config/fortify.php` を開き、以下の2箇所を修正してください。

```php
// config/fortify.php

// 変更前
// 'views' => true,
// 変更後
'views' => false,

// ...

// 変更前
// 'home' => '/home',
// 変更後
'home' => '/',
```

### 4.3.2. Fortifyのサービスプロバイダを登録

Fortifyをアプリケーションに正式に登録するため、`config/app.php`の`providers`配列に`FortifyServiceProvider`を追加します。

```php
// config/app.php

'providers' => ServiceProvider::defaultProviders()->merge([
    /*
     * Package Service Providers...
     */

    /*
     * Application Service Providers...
     */
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // App\Providers\BroadcastServiceProvider::class,
    App\Providers\EventServiceProvider::class,
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

    // ... (他のビュー設定は必要に応じて追加)
}
```

---

## 4.4. RouteServiceProviderの設定

最後に、ユーザーがログインした後にどこへ遷移するかを設定します。`app/Providers/RouteServiceProvider.php`を開き、`HOME`定数の値を修正します。

```php
// app/Providers/RouteServiceProvider.php

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/'; // 変更

    // ...
}
```

これで、認証機能のバックエンド側の設定は完了です。次のChapterでは、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
