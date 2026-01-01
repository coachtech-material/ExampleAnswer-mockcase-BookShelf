# Chapter 4: 認証機能の実装 (Fortify)

このChapterでは、Laravelの公式認証パッケージであるFortifyを導入し、アプリケーションに必須のログイン、新規登録、ログアウトといった認証機能を実装します。また、マスタデータとしてジャンルの初期データをSeederで投入します。

---

## 4-1. 先輩エンジニアの思考プロセス：なぜ認証機能を先に実装するのか？

### 認証機能の位置づけ

データベース設計（Chapter 2）とモデル定義（Chapter 3）が完了した今、次に何を実装すべきでしょうか？

先輩エンジニアは以下のように考えます。

> 「書籍管理アプリでは、『誰が書籍を登録したか』『誰がレビューを書いたか』という情報が必要だ。つまり、ほぼ全ての機能が『ログインしているユーザー』を前提としている。認証機能がなければ、書籍登録もレビュー投稿もテストできない。だから、CRUD機能より先に認証を実装するのが合理的だ。」

### なぜFortifyを選ぶのか？

Laravelには複数の認証パッケージがあります。

| パッケージ | 特徴 |
|:---|:---|
| **Laravel Breeze** | シンプルなスターターキット。Bladeテンプレートが自動生成される |
| **Laravel Jetstream** | 高機能なスターターキット。チーム管理、2FA、APIトークンなどを含む |
| **Laravel Fortify** | バックエンドのみ。フロントエンドは自分で作成する必要がある |

今回Fortifyを選ぶ理由は以下の通りです。

1. **学習目的**: Bladeテンプレートを自分で書くことで、認証の仕組みを深く理解できる
2. **柔軟性**: 自分でUIを完全にコントロールできる
3. **軽量**: 不要な機能が含まれていない

---

## 4.1. Laravel Fortifyのインストール

まず、Composerを使ってFortifyをインストールします。

```bash
sail composer require laravel/fortify
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

---

## 4.2. 認証ルートの作成

レイアウトファイルで `route('login')` や `route('register')` を使用するため、先に認証ルートを定義します。

**`routes/auth.php` を新規作成:**

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware("guest")->group(function () {
    Route::get("/login", function () {
        return view("auth.login");
    })->name("login");

    Route::get("/register", function () {
        return view("auth.register");
    })->name("register");
});

Route::middleware("auth")->group(function () {
    Route::post("/logout", [AuthenticatedSessionController::class, "destroy"])
        ->name("logout");
});
```

> **重要:** この `auth.php` ファイルは、後のステップで `routes/web.php` から読み込みます。この時点ではファイルを作成するだけで問題ありません。

---

## 4.3. レイアウトファイルとコンポーネントの作成

Fortifyは認証のバックエンドロジックのみを提供するため、ログイン画面や登録画面などのビューは手動で作成する必要があります。BreezeやJetstreamと異なり、`<x-app-layout>` のようなコンポーネントも存在しないため、自作します。

### 1. レイアウトファイルの作成 (`resources/views/layouts/app.blade.php`)

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config("app.name", "Laravel") }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(["resources/css/app.css", "resources/js/app.js"])

        <!-- Alpine.js -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include("layouts.navigation")

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
```

### 2. ナビゲーションファイルの作成 (`resources/views/layouts/navigation.blade.php`)

```html
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
</nav>
```

*中身は後のステップで実装します。*

### 3. ゲストレイアウトの作成 (`resources/views/layouts/guest.blade.php`)

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
```

### 4. レイアウトコンポーネントクラスの作成

`app/View/Components` ディレクトリを作成し、以下のファイルを作成してください。

```bash
mkdir -p app/View/Components
```

**`app/View/Components/AppLayout.php`**
```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function render(): View
    {
        return view('layouts.app');
    }
}
```

**`app/View/Components/GuestLayout.php`**
```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function render(): View
    {
        return view('layouts.guest');
    }
}
```

### 5. Bladeコンポーネントの作成

認証画面で使用するBladeコンポーネントを手動で作成します。`resources/views/components` ディレクトリに以下のファイルを作成してください。

- `application-logo.blade.php`
- `dropdown.blade.php`
- `dropdown-link.blade.php`
- `nav-link.blade.php`
- `responsive-nav-link.blade.php`
- `text-input.blade.php`
- `input-label.blade.php`
- `input-error.blade.php`
- `primary-button.blade.php`

*各コンポーネントのコードは元の教材からコピーしてください。*

---

## 4.4. Fortifyの設定

### 1. Fortifyのビューを無効化

`config/fortify.php` を開き、`views` の値を `false` に変更します。

```php
'views' => false,
```

### 2. Fortifyのサービスプロバイダを登録

`config/app.php` を開き、`providers` 配列に `FortifyServiceProvider` を追加します。

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\FortifyServiceProvider::class, // ← これを追加
    // ...
])->toArray(),
```

### 3. Fortifyのビューを設定

`app/Providers/FortifyServiceProvider.php` を開き、`boot` メソッド内に以下のコードを追加します。

```php
use Laravel\Fortify\Fortify;

// ...

public function boot(): void
{
    // ...

    // ビューの設定
    Fortify::loginView(fn () => view("auth.login"));
    Fortify::registerView(fn () => view("auth.register"));
    // ... (他のビュー設定は必要に応じて追加)
}
```

---

## 4.5. 認証ビューの作成

`resources/views/auth` ディレクトリに、`login.blade.php` と `register.blade.php` を作成します。

### `resources/views/auth/login.blade.php`

```html
<x-guest-layout>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="ml-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
```

### `resources/views/auth/register.blade.php`

```html
<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ml-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
```

---

## 4.6. マスタデータの準備 (ジャンルSeeder)

書籍を登録する際にジャンルを選択できるように、あらかじめデータベースにジャンルの初期データを投入しておきます。

### Seederファイルの作成

```bash
sail artisan make:seeder GenreSeeder
```

### Seederの実装

作成された `database/seeders/GenreSeeder.php` を開き、`run` メソッドに初期ジャンルを登録する処理を記述します。

**`database/seeders/GenreSeeder.php`**

```php
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

### DatabaseSeederへの登録

`database/seeders/DatabaseSeeder.php` の `run` メソッド内で `GenreSeeder` を呼び出します。

**`database/seeders/DatabaseSeeder.php`**

```php
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

### データベースへのデータ投入

以下のコマンドを実行して、データベースに初期ジャンルデータを投入します。

```bash
sail artisan migrate:fresh --seed
```

このコマンドにより、すべてのテーブルが再作成され、`DatabaseSeeder` が実行されます。phpMyAdminで `genres` テーブルにデータが登録されていることを確認してください。

---

## 4.7. 動作確認

ここまで設定が完了したら、動作確認を行いましょう。

1. `sail up -d` と `sail npm run dev` が実行されていることを確認します。
2. ブラウザで `http://localhost/register` にアクセスし、新規ユーザーを登録します。
3. 登録後、自動的にログイン状態になり、トップページにリダイレクトされれば成功です。
4. ヘッダーの「ログアウト」ボタンをクリックし、正常にログアウトできることを確認します。
5. `http://localhost/login` にアクセスし、先ほど登録した情報でログインできることを確認します。

これで、アプリケーションの基本的な認証機能とマスタデータの準備が完成しました。
