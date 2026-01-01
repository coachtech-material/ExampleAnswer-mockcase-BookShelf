# Chapter 3: 認証機能の実装

このChapterでは、Laravel Fortifyを使用してユーザー登録・ログイン・ログアウト機能を実装します。認証機能はほとんどのWebアプリケーションで必要となる基本機能であり、Laravelには複数の認証パッケージが用意されています。

## 3-1. 認証パッケージの選定

### 思考プロセス: なぜFortifyを選ぶのか

Laravelには主に以下の認証パッケージがあります。

| パッケージ | 特徴 | 適したケース |
|---|---|---|
| Laravel Breeze | シンプル、Blade/Inertia対応、UIあり | 素早く認証を実装したい場合 |
| Laravel Fortify | ヘッドレス（UIなし）、柔軟性が高い | 独自のUIを使いたい場合 |
| Laravel Jetstream | 高機能、チーム管理、2FA対応 | 本格的なSaaSを構築する場合 |

今回はPMからBladeテンプレートが提供されるため、UIを自分で用意する必要があります。そのため、**Laravel Fortify**（ヘッドレス認証）を選択します。

## 3-2. Laravel Fortifyのインストールと設定

### Step 1: Fortifyのインストール

```bash
sail composer require laravel/fortify
```

### Step 2: Fortifyの設定ファイルとマイグレーションを公開

```bash
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

このコマンドにより、以下のファイルが生成されます。

- `config/fortify.php`: Fortifyの設定ファイル
- `app/Actions/Fortify/`: 認証アクションクラス
- `database/migrations/xxxx_xx_xx_xxxxxx_add_two_factor_columns_to_users_table.php`: 2FA用のマイグレーション

### Step 3: マイグレーションの実行

```bash
sail artisan migrate
```

### Step 4: FortifyServiceProviderの登録

**`config/app.php`** の `providers` 配列に以下を追加します。

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\FortifyServiceProvider::class,
])->toArray(),
```

### Step 5: Fortifyの設定

**`config/fortify.php`:**

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'home' => '/books',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'views' => true,
    'features' => [
        // Features::emailVerification(),
        // Features::updatePasswords(),
        // Features::updateProfileInformation(),
        // Features::twoFactorAuthentication(),
    ],
];
```

**コードリーディング:**
- `'home' => '/books'`: ログイン後のリダイレクト先を `/books`（書籍一覧）に設定します。
- `'views' => true`: Fortifyがビューを返すようにします（`false` にするとJSON APIとして動作）。
- `'features'`: 使用する機能を指定します。今回は基本的な認証のみなので、すべてコメントアウトしています。

## 3-3. FortifyServiceProviderの設定

**`app/Providers/FortifyServiceProvider.php`:**

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
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // ログインビューの設定
        Fortify::loginView(function () {
            return view('auth.login');
        });

        // ユーザー登録ビューの設定
        Fortify::registerView(function () {
            return view('auth.register');
        });
    }
}
```

**コードリーディング:**
- `Fortify::createUsersUsing(CreateNewUser::class);`: ユーザー作成時に `CreateNewUser` クラスを使用します。
- `RateLimiter::for('login', ...)`: ログイン試行のレート制限を設定します（1分間に5回まで）。これにより、ブルートフォース攻撃を防ぎます。
- `Fortify::loginView(...)`: ログイン画面のビューを指定します。
- `Fortify::registerView(...)`: ユーザー登録画面のビューを指定します。

## 3-4. ユーザー作成アクションの設定

**`app/Actions/Fortify/CreateNewUser.php`:**

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
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
```

**コードリーディング:**
- `Validator::make($input, [...])`: 入力データのバリデーションを行います。
- `Rule::unique(User::class)`: `users` テーブルでメールアドレスが一意であることを確認します。
- `$this->passwordRules()`: `PasswordValidationRules` トレイトで定義されたパスワードのバリデーションルールを使用します。
- `Hash::make($input['password'])`: パスワードをハッシュ化して保存します。平文で保存することは絶対にしてはいけません。

## 3-5. 認証ビューの作成

PMから提供されたBladeテンプレートを配置します。

### レイアウトファイルの作成

**`resources/views/layouts/app.blade.php`:**

```blade
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '書籍レビューアプリ')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('books.index') }}" class="text-xl font-bold text-gray-800">
                        書籍レビューアプリ
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('books.create') }}" class="text-gray-600 hover:text-gray-900">書籍登録</a>
                        <a href="{{ route('favorites.index') }}" class="text-gray-600 hover:text-gray-900">お気に入り</a>
                        <a href="{{ route('genres.index') }}" class="text-gray-600 hover:text-gray-900">ジャンル管理</a>
                        <a href="{{ route('ranking.index') }}" class="text-gray-600 hover:text-gray-900">ランキング</a>
                        <span class="text-gray-600">{{ Auth::user()->name }}</span>
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-gray-600 hover:text-gray-900">ログアウト</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">ログイン</a>
                        <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900">ユーザー登録</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4">
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
```

**コードリーディング:**
- `@vite([...])`: Viteでビルドされたアセットを読み込みます。
- `@auth ... @else ... @endauth`: 認証状態に応じて表示を切り替えます。
- `{{ csrf_token() }}`: CSRF対策用のトークンをメタタグに埋め込みます。
- `session('success')`, `session('error')`: フラッシュメッセージを表示します。

### ログインビューの作成

**`resources/views/auth/login.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'ログイン')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold text-center mb-6">ログイン</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="block text-gray-700 font-medium mb-2">メールアドレス</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror">
            @error('email')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="password" class="block text-gray-700 font-medium mb-2">パスワード</label>
            <input type="password" name="password" id="password"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-500 @enderror">
            @error('password')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label class="flex items-center">
                <input type="checkbox" name="remember" class="mr-2">
                <span class="text-gray-700">ログイン状態を保持する</span>
            </label>
        </div>

        <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
            ログイン
        </button>
    </form>

    <p class="text-center mt-4 text-gray-600">
        アカウントをお持ちでない方は<a href="{{ route('register') }}" class="text-blue-500 hover:underline">こちら</a>
    </p>
</div>
@endsection
```

**コードリーディング:**
- `@csrf`: CSRF対策用のトークンを埋め込みます。フォームには必ず含める必要があります。
- `old('email')`: バリデーションエラー時に入力値を保持します。
- `@error('email') ... @enderror`: バリデーションエラーがある場合にエラーメッセージを表示します。
- `name="remember"`: 「ログイン状態を保持する」チェックボックス。Fortifyが自動的に処理します。

### ユーザー登録ビューの作成

**`resources/views/auth/register.blade.php`:**

```blade
@extends('layouts.app')

@section('title', 'ユーザー登録')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold text-center mb-6">ユーザー登録</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-4">
            <label for="name" class="block text-gray-700 font-medium mb-2">名前</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="email" class="block text-gray-700 font-medium mb-2">メールアドレス</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror">
            @error('email')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password" class="block text-gray-700 font-medium mb-2">パスワード</label>
            <input type="password" name="password" id="password"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-500 @enderror">
            @error('password')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="password_confirmation" class="block text-gray-700 font-medium mb-2">パスワード（確認）</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
            登録
        </button>
    </form>

    <p class="text-center mt-4 text-gray-600">
        既にアカウントをお持ちの方は<a href="{{ route('login') }}" class="text-blue-500 hover:underline">こちら</a>
    </p>
</div>
@endsection
```

**コードリーディング:**
- `name="password_confirmation"`: パスワード確認フィールド。Laravelの `confirmed` バリデーションルールと連携します。

## 3-6. ルーティングの確認

Fortifyは自動的に以下のルートを登録します。

```bash
sail artisan route:list --name=login
sail artisan route:list --name=register
sail artisan route:list --name=logout
```

| メソッド | URI | 名前 | アクション |
|---|---|---|---|
| GET | /login | login | ログインフォーム表示 |
| POST | /login | - | ログイン処理 |
| GET | /register | register | ユーザー登録フォーム表示 |
| POST | /register | - | ユーザー登録処理 |
| POST | /logout | logout | ログアウト処理 |

## 3-7. 動作確認

1. `http://localhost/register` にアクセスし、ユーザー登録ができることを確認
2. `http://localhost/login` にアクセスし、ログインができることを確認
3. ログイン後、`http://localhost/books` にリダイレクトされることを確認
4. ログアウトができることを確認

---

これで認証機能の実装が完了しました。次のChapterでは、書籍管理機能を実装していきます。
