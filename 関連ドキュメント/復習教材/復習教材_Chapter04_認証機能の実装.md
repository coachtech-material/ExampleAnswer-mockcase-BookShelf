# Chapter 04: 「エンジンとボディ」 - 認証機能の実装

## 🎯 このセクションで学ぶこと

このChapterでは、アプリケーションの「入り口」となる認証機能を実装します。Fortifyは認証機能の「エンジン」部分だけを提供してくれるパッケージです。車のボディ（UI）は自分たちで自由にデザインし、そこに強力なエンジン（Fortify）を搭載する、というイメージで進めます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| Laravel Fortifyの役割 | なぜUI付きパッケージ（Breeze等）ではなく、バックエンド特化のFortifyを使うのか |
| 認証ルートの構築と分割 | ログイン・登録のルートを `auth.php` に分離する「関心の分離」 |
| Fortifyのカスタマイズ | 設定ファイルやサービスプロバイダの調整方法 |
| RouteServiceProviderの役割 | 分離したルートファイルをアプリケーションに認識させる「配線役」 |
| CreateNewUser のカスタマイズ | ユーザー登録時のバリデーションや保存処理のカスタマイズ |

---

## 1. はじめに 📖

LaravelにはBreezeやJetstreamといった、UIまで含めて認証機能を一瞬で実装できる便利なパッケージがあります。しかし、実務では必ずしもそれらの提供するUIが要件に合うとは限りません。むしろ、デザイナーが作成した独自のUIに、Laravelの認証機能を「接続する」場面の方が圧倒的に多いのです。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| Breeze等が提供するUIは、今回のデザインと異なる | **Laravel Fortify**を使い、認証のバックエンド処理のみを導入する | UI（Bladeファイル）は自前で用意したものを使い、機能だけをLaravelに任せることで、要件通りの画面と機能を両立できる。 |
| 認証の仕組みがブラックボックス化しやすい | 認証ルートや設定を**自分で制御・カスタマイズ**する | ログイン画面がどう表示され、ログイン処理がどう実行されるのか、一連の流れをコードレベルで追うことで理解が深まる。 |
| ログイン後の遷移先などを柔軟に変更したい | **ServiceProvider**や設定ファイルをカスタマイズする | アプリケーションの仕様に合わせて、認証に関する様々な動作を自由に変更できる。 |

---

## 2. 要件の確認 📋

### 4.1. Laravel Fortifyのインストール

```bash
sail composer require laravel/fortify
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

このコマンドにより、`config/fortify.php` 設定ファイルと `app/Providers/FortifyServiceProvider.php` が作成されます。

### 4.2. config/fortify.php の設定

`home` の値を `/` に変更します（デフォルトは `/home`）。

```php
// config/fortify.php
'home' => '/',
```

> **重要:** この設定がないと、ログイン後に `/home` にリダイレクトされ、「404 Not Found」エラーになります。BookShelfのトップページは `/` であるため、必ずこの変更が必要です。

> **補足:** `'home' => '/'` はログイン後のリダイレクト先です。一方、ログアウト後のリダイレクト先はデフォルトで `/`（トップページ）ですが、BookShelfではログアウト後に `/login` に遷移させたいため、`FortifyServiceProvider` で `LogoutResponse` をカスタマイズします（コードは 4.5 節で掲載）。

### 4.3. FortifyServiceProvider を config/app.php に登録

```php
// config/app.php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\RouteServiceProvider::class,
    App\Providers\FortifyServiceProvider::class, // これを追加
])->toArray(),
```

### 4.4. auth.php の作成（認証ルートの分離）

### 4.5. FortifyServiceProvider の編集

### 4.6. RouteServiceProvider の編集

### 4.7. CreateNewUser.php の更新

---

## 3. 先輩エンジニアの思考プロセス 💭

### なぜ auth.php に分離するのか？ -- 関心の分離

ソフトウェア設計における「**関心の分離 (Separation of Concerns)**」という重要な原則に基づいています。

| 目的 | 解説 |
|:---|:---|
| **可読性の向上** | `web.php`には主要機能のルートが、`auth.php`には認証関連のルートだけが存在し、見通しが良くなります。 |
| **メンテナンス性の向上** | 機能ごとにファイルが分割されていれば、修正による影響範囲を特定しやすくなります。 |
| **責務の明確化** | `web.php`は「アプリの通常機能の交通整理」、`auth.php`は「ユーザーの出入りを管理する受付」と役割が明確になります。 |
| **Laravelの標準への準拠** | Breezeなどでも認証ルートは `auth.php` に分離されています。チームの新メンバーもすぐに構造を理解できます。 |

料理に例えるなら、`web.php`が「メインディッシュのレシピブック」、`auth.php`が「ドリンクメニュー」です。別々にまとめておいた方が、探しやすく管理しやすいのは明らかでしょう。

### RouteServiceProvider の HOME 定数

`public const HOME = '/'` は、ログイン成功後のリダイレクト先を定義します。Fortify や Laravelの認証ミドルウェアがこの定数を参照して、ログイン後の遷移先を決定します。`config/fortify.php` の `'home' => '/'` と合わせて設定することで、確実にトップページに遷移するようにします。

### CreateNewUser のバリデーションメッセージ

Fortify のデフォルトバリデーションメッセージは英語です。`CreateNewUser.php` の `Validator::make()` 第3引数にメッセージ配列を渡すことで、フォームのエラーメッセージを日本語にカスタマイズしています。

---

## 4. 実装 🚀

### 4.4. `routes/auth.php`（新規作成）

```bash
touch routes/auth.php
```

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

> **注意:** この `auth.php` ファイルは、作成しただけではアプリケーションに認識されません。次の `RouteServiceProvider` で読み込む設定が必要です。

### 4.5. `app/Providers/FortifyServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
            public function toResponse($request)
            {
                return redirect('/login');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
```

### 4.6. `app/Providers/RouteServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        });
    }
}
```

### 4.7. `app/Actions/Fortify/CreateNewUser.php`

日本語バリデーションメッセージを追加します。

```php
<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ], [
            'name.required' => 'お名前を入力してください。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスはメール形式で入力してください。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
            'password.required' => 'パスワードを入力してください。',
            'password.confirmed' => 'パスワードと一致しません。',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
```

---

## 5. コードの詳細解説 🔍

### routes/auth.php

| コード | 解説 |
|:---|:---|
| `Route::middleware('guest')->group(...)` | 未ログインユーザーのみがアクセスできるルートグループ。ログイン済みの場合は HOME にリダイレクトされます。 |
| `Route::middleware('auth')->group(...)` | ログイン済みユーザーのみがアクセスできるルートグループ。未ログインの場合は `/login` にリダイレクトされます。 |
| `[AuthenticatedSessionController::class, 'destroy']` | Fortifyが提供するログアウト処理。セッションを破棄し、ユーザーをログアウトさせます。 |

### FortifyServiceProvider

| コード | 解説 |
|:---|:---|
| `$this->app->instance(LogoutResponse::class, ...)` | ログアウト後のリダイレクト先を `/login` に変更。Fortifyの `LogoutResponse` インターフェースの実装をサービスコンテナに登録しています。 |
| `Fortify::createUsersUsing(CreateNewUser::class)` | ユーザー登録時に使用するアクションクラスを指定します。 |
| `Fortify::loginView(fn () => view('auth.login'))` | Fortifyがログインビューを要求した際に、`auth.login` ビューを返すアロー関数を定義しています。 |
| `Fortify::registerView(fn () => view('auth.register'))` | 同様に、新規登録ビューを指定しています。 |
| `RateLimiter::for('login', ...)` | ログイン試行の回数制限（1分間に5回まで）を設定し、ブルートフォース攻撃を防ぎます。 |

### RouteServiceProvider

| コード | 解説 |
|:---|:---|
| `public const HOME = '/'` | ログイン成功後のリダイレクト先。デフォルトの `/home` からトップページの `/` に変更しています。 |
| `Route::middleware('web')->group(base_path('routes/auth.php'))` | **配線の核心部分。** `auth.php` を読み込み、`web` ミドルウェア（セッション管理、CSRF保護など）を適用します。 |

### CreateNewUser

| コード | 解説 |
|:---|:---|
| `Validator::make($input, [...], [...])` | 第1引数がバリデーション対象データ、第2引数がルール、第3引数が日本語カスタムメッセージです。 |
| `Rule::unique(User::class)` | `users` テーブルの `email` カラムでユニーク制約をチェックします。 |
| `$this->passwordRules()` | `PasswordValidationRules` トレイトが提供するパスワードバリデーションルール（確認フィールドとの一致チェックなど）を使用しています。 |

---

## 6. この実装にたどり着くための調べ方 🧐

分からないことがあった時、AIに聞くプロンプト例を紹介します。

| 疑問 | プロンプト例 |
|:---|:---|
| Fortify と Breeze の違い | 「Laravel Fortify と Laravel Breeze の違いを教えてください。それぞれどのような場面で使うべきですか？」 |
| ルートファイルの分離方法 | 「Laravel で routes/auth.php のようにルートファイルを分離する方法を教えてください。RouteServiceProvider での読み込み方法も含めて。」 |
| ミドルウェアの仕組み | 「Laravel の guest ミドルウェアと auth ミドルウェアの違いと動作を教えてください。」 |
| バリデーションメッセージのカスタマイズ | 「Laravel の Validator::make() で日本語のカスタムエラーメッセージを設定する方法を教えてください。」 |
| RateLimiter の仕組み | 「Laravel の RateLimiter でログイン試行回数を制限する仕組みを教えてください。」 |

---

## 7. 動作確認 ✅

認証機能が正しく動作するか確認しましょう。

| 確認項目 | 確認方法 |
|:---|:---|
| ログイン画面の表示 | ブラウザで `http://localhost/login` にアクセスし、ログインフォームが表示されること |
| 新規登録画面の表示 | ブラウザで `http://localhost/register` にアクセスし、登録フォームが表示されること |
| 新規登録 | フォームに情報を入力して送信し、ユーザーが作成されること（phpMyAdmin で確認） |
| ログイン後のリダイレクト | ログイン成功後、`/`（トップページ）にリダイレクトされること（`/home` ではないこと） |
| ログアウト後のリダイレクト | ログアウト後、`/login`（ログイン画面）にリダイレクトされること |
| バリデーションエラー | 空のフォームを送信し、日本語のエラーメッセージが表示されること |

---

## 8. まとめ ✨

このChapterでは、Laravel Fortify を使って認証のバックエンド処理を構築し、自前のUIと接続しました。

| 構成要素 | ファイル | 役割 |
|:---|:---|:---|
| 認証ルート | `routes/auth.php` | ログイン・登録・ログアウトのURLを定義 |
| Fortify設定 | `config/fortify.php` | `home => '/'` でログイン後の遷移先を設定 |
| Fortifyプロバイダ | `app/Providers/FortifyServiceProvider.php` | ビュー指定・ユーザー作成ロジック・レート制限 |
| ルートプロバイダ | `app/Providers/RouteServiceProvider.php` | `auth.php` の読み込み + HOME定数 |
| ユーザー作成 | `app/Actions/Fortify/CreateNewUser.php` | 日本語バリデーション付きのユーザー登録処理 |

これで、認証機能のバックエンド側の設定は完了です。次のChapterでは、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
