# Chapter 04: 「エンジンとボディ」 - 認証機能の実装

## 🎯 このChapterの目標

このChapterでは、アプリケーションの「入り口」となる認証機能を実装します。Fortifyは認証機能の「エンジン」部分だけを提供してくれるパッケージです。車のボディ（UI）は自分たちで自由にデザインし、そこに強力なエンジン（Fortify）を搭載する、というイメージで進めます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| Laravel Fortifyの役割 | なぜUI付きパッケージ（Breeze等）ではなく、バックエンド特化のFortifyを使うのか |
| 認証ルートの構築と分割 | ログイン・登録のルートを `auth.php` に分離する「関心の分離」 |
| Fortifyのカスタマイズ | 設定ファイルやサービスプロバイダの調整方法 |
| RouteServiceProviderの役割 | 分離したルートファイルをアプリケーションに認識させる「配線役」 |
| CreateNewUser のカスタマイズ | 日本語バリデーションメッセージの追加 |

---

## 📖 背景知識：なぜUI付きパッケージを使わないのか？

LaravelにはBreezeやJetstreamといった、UIまで含めて認証機能を一瞬で実装できる便利なパッケージがあります。しかし、実務では必ずしもそれらの提供するUIが要件に合うとは限りません。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| Breeze等が提供するUIは、今回のデザインと異なる | **Laravel Fortify**を使い、認証のバックエンド処理のみを導入する | UI（Bladeファイル）は自前で用意したものを使い、機能だけをLaravelに任せる。 |
| 認証の仕組みがブラックボックス化しやすい | 認証ルートや設定を**手動で構築**する | 一連の流れをコードレベルで追うことで理解が深まる。 |
| ログイン後の遷移先などを柔軟に変更したい | **ServiceProvider**や設定ファイルをカスタマイズする | アプリケーションの仕様に合わせて、認証に関する動作を自由に変更できる。 |

---

## 📋 実装の手順

### 4.1. Laravel Fortifyのインストール

```bash
sail composer require laravel/fortify
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

### 4.2. config/fortify.php の設定

```php
// config/fortify.php
'home' => '/',
```

### 4.3. ログアウト後のリダイレクト先を `/login` にする

Fortifyのデフォルトでは、ログアウト後に `/` へリダイレクトされます。ログアウト後は `/login` に遷移させたいため、`FortifyServiceProvider` の `register()` メソッドでカスタム `LogoutResponse` を登録します。

```php
use Laravel\Fortify\Contracts\LogoutResponse;

public function register(): void
{
    $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
        public function toResponse($request)
        {
            return redirect('/login');
        }
    });
}
```

> **ポイント:** `LogoutResponse` はFortifyが提供するコントラクト（インターフェース）です。サービスコンテナに独自の実装を登録することで、ログアウト時のレスポンスを自由にカスタマイズできます。

### 4.4. FortifyServiceProvider を config/app.php に登録

```php
// config/app.php
'providers' => ServiceProvider::defaultProviders()->merge([
    App\Providers\RouteServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
])->toArray(),
```

---

## 💭 なぜこう作るのか？

### なぜ auth.php に分離するのか？

| 目的 | 解説 |
|:---|:---|
| **可読性の向上** | `web.php`には主要機能のルートが、`auth.php`には認証関連のルートだけが存在し、見通しが良くなります。 |
| **メンテナンス性の向上** | 機能ごとにファイルが分割されていれば、修正による影響範囲を特定しやすくなります。 |
| **責務の明確化** | `web.php`は「アプリの通常機能の交通整理」、`auth.php`は「ユーザーの出入りを管理する受付」。 |

### RouteServiceProvider の HOME 定数

`public const HOME = '/'` は、ログイン成功後のリダイレクト先を定義します。

---

## 🚀 コードの実装

### 4.5. `routes/auth.php`（新規作成）

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

### 4.6. `app/Providers/FortifyServiceProvider.php`

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
    public function register(): void
    {
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
            public function toResponse($request)
            {
                return redirect('/login');
            }
        });
    }

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

### 4.7. `app/Providers/RouteServiceProvider.php`

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
    public const HOME = '/';

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

### 4.8. `app/Actions/Fortify/CreateNewUser.php`

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

## 🔍 コードリーディング

### routes/auth.php

| コード | 解説 |
|:---|:---|
| `Route::middleware('guest')->group(...)` | 未ログインユーザーのみがアクセスできるルートグループ。 |
| `Route::middleware('auth')->group(...)` | ログイン済みユーザーのみがアクセスできるルートグループ。 |
| `[AuthenticatedSessionController::class, 'destroy']` | Fortifyが提供するログアウト処理。 |

### RouteServiceProvider

| コード | 解説 |
|:---|:---|
| `public const HOME = '/'` | ログイン成功後のリダイレクト先。 |
| `Route::middleware('web')->group(base_path('routes/auth.php'))` | `auth.php` を読み込み、`web` ミドルウェアを適用。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| Fortify と Breeze の違い | 「Laravel Fortify と Laravel Breeze の違いを教えてください。」 |
| ルートファイルの分離方法 | 「Laravel で routes/auth.php のようにルートファイルを分離する方法を教えてください。」 |
| ミドルウェアの仕組み | 「Laravel の guest ミドルウェアと auth ミドルウェアの違いを教えてください。」 |

---

## ✅ 動作確認

| 確認項目 | 確認方法 |
|:---|:---|
| ログイン画面の表示 | `http://localhost/login` でフォームが表示されること |
| 新規登録画面の表示 | `http://localhost/register` でフォームが表示されること |
| ログイン後のリダイレクト | ログイン成功後、`/`（トップページ）にリダイレクトされること |
| ログアウト後のリダイレクト | ログアウト後、`/login`（ログイン画面）にリダイレクトされること |
| バリデーションエラー | 空のフォームを送信し、日本語のエラーメッセージが表示されること |

---

## ✨ このChapterのまとめ

| 構成要素 | ファイル | 役割 |
|:---|:---|:---|
| 認証ルート | `routes/auth.php` | ログイン・登録・ログアウトのURLを定義 |
| Fortify設定 | `config/fortify.php` | `home => '/'` でログイン後の遷移先を設定 |
| Fortifyプロバイダ | `app/Providers/FortifyServiceProvider.php` | ビュー指定・ユーザー作成ロジック・レート制限・ログアウト後リダイレクト |
| ルートプロバイダ | `app/Providers/RouteServiceProvider.php` | `auth.php` の読み込み + HOME定数 |
| ユーザー作成 | `app/Actions/Fortify/CreateNewUser.php` | 日本語バリデーション付きのユーザー登録処理 |

次のChapterでは、開発を効率化するための初期データ（マスタデータ）の準備を進めていきます。
