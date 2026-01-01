# Chapter 2: 認証機能の実装 - Fortifyでバックエンドを学ぶ

環境構築が完了しました。次に実装するのは、Webアプリケーションの基本である「認証機能」です。詳細度50%の要件定義書には「ログイン・ログアウト・ユーザー登録機能が必要」としか書かれていません。ここからどうやって具体的な実装に進めるのでしょうか。

## 2-1. 認証パッケージの選定（思考プロセス）

Laravelには認証機能を実装するための便利なパッケージがいくつかあります。

- **Laravel Breeze**: シンプルな認証機能の雛形（バックエンド＋UI）を数コマンドで生成してくれる。手早く始めたい場合に最適。
- **Laravel Jetstream**: より高機能な認証機能（チーム管理、二要素認証など）を提供。
- **Laravel Fortify**: 認証の**バックエンドロジックのみ**を提供。UI（見た目）は提供されないため、自分で実装する必要がある。

**なぜ今回はFortifyを選ぶのか？**
PMから提供されたBladeテンプレート（UI）を活かす必要があるため、UIまで自動生成されてしまうBreezeやJetstreamは不適切です。また、Fortifyを使うことで、認証の裏側でどのようなリクエストが送られ、どのような処理が行われているのかを深く理解できるため、学習効果が非常に高いというメリットがあります。今回はこのFortifyを採用します。

## 2-2. Fortifyのインストールと設定

添付された手順書に従い、Fortifyをインストールします。

```bash
# Fortifyパッケージをインストール
sail composer require laravel/fortify

# Fortifyの設定ファイルやマイグレーションファイルをプロジェクトにコピー
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

`vendor:publish` を実行すると、`config/fortify.php` や `app/Providers/FortifyServiceProvider.php` などが生成され、Fortifyの動作をカスタマイズできるようになります。

## 2-3. 認証ルートの定義

Fortifyはログイン処理などのPOSTリクエストは自動で処理してくれますが、ログイン画面を表示するGETリクエストのルートは自分で定義する必要があります。

**`routes/auth.php` を新規作成:**

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// ログインしていないユーザー（ゲスト）向けのルート
Route::middleware("guest")->group(function () {
    // ログイン画面表示
    Route::get("/login", function () {
        return view("auth.login");
    })->name("login");

    // ユーザー登録画面表示
    Route::get("/register", function () {
        return view("auth.register");
    })->name("register");
});

// ログイン済みのユーザー向けのルート
Route::middleware("auth")->group(function () {
    // ログアウト処理
    Route::post("/logout", [AuthenticatedSessionController::class, "destroy"])
        ->name("logout");
});
```

**思考プロセス**: なぜこのファイルを作るのか？
- 認証関連のルートを `routes/web.php` から分離することで、コードの見通しが良くなります。
- `middleware("guest")` は「ログインしていない時だけアクセスできる」という制御です。ログインしているのにログインページにアクセスするのはおかしいですよね。
- 逆に `middleware("auth")` は「ログインしている時だけアクセスできる」制御です。

このファイルは後ほど `routes/web.php` から `require __DIR__."/auth.php";` のように読み込みます。

## 2-4. レイアウトとコンポーネントの構築

FortifyはUIを提供しないため、PMから提供されたBladeテンプレートを使って画面の骨格（レイアウト）や部品（コンポーネント）を自作します。

### 1. Bladeコンポーネントの配置

まず、PMから提供された以下のBladeコンポーネントファイルを `resources/views/components` ディレクトリに配置します。これらのファイルは、テキスト入力欄やボタンなどのUI部品です。

- `application-logo.blade.php`
- `dropdown.blade.php`
- `dropdown-link.blade.php`
- `nav-link.blade.php`
- `responsive-nav-link.blade.php`
- `text-input.blade.php`
- `input-label.blade.php`
- `input-error.blade.php`
- `primary-button.blade.php`

### 2. レイアウトファイルの作成

次に、これらの部品を組み合わせてページ全体のレイアウトを作成します。添付手順書通りに、`resources/views/layouts` ディレクトリに `app.blade.php` と `guest.blade.php` を作成します。

**Bladeの読み解き方**: `app.blade.php` の中身を見てみましょう。
- `<x-app-layout>` のように、HTMLタグとは異なる `<x-...>` というタグがあります。これがBladeコンポーネントを呼び出す記述です。
- `@include("layouts.navigation")` は、別のBladeファイル（ナビゲーションバー）をその場所に読み込んでいます。
- `{{ $slot }}` は、このレイアウトを使う側のBladeファイルで定義されたメインコンテンツが挿入される場所です。これにより、ヘッダーやフッターを共通化できます。

### 3. レイアウトコンポーネントクラスの作成

`<x-app-layout>` のようなタグがどのBladeファイルを指すのかをLaravelに教えるため、対応するクラスを作成します。添付手順書通りに、`app/View/Components` ディレクトリに `AppLayout.php` と `GuestLayout.php` を作成します。

```php
// app/View/Components/AppLayout.php
class AppLayout extends Component
{
    public function render(): View
    {
        // <x-app-layout> が呼ばれたら、layouts.app.blade.php を表示する
        return view("layouts.app");
    }
}
```

## 2-5. Fortifyの設定とビューの作成

最後に、Fortifyに「認証画面としては自作のビューを使ってください」と教え、そのビューを作成します。

1.  **Fortifyのビューを無効化**: `config/fortify.php` で `"views" => false,` に変更します。
2.  **サービスプロバイダを登録**: `config/app.php` の `providers` 配列に `App\Providers\FortifyServiceProvider::class` を追加します。
3.  **Fortifyのビューを設定**: `app/Providers/FortifyServiceProvider.php` の `boot` メソッドで、`Fortify::loginView(...)` などを使い、どのルートがどのビューに対応するかを定義します。
4.  **認証ビューの作成**: 添付手順書通りに `resources/views/auth/login.blade.php` と `register.blade.php` を作成します。

**Bladeの読み解き方**: `login.blade.php` を見てみましょう。
- `<form method="POST" action="{{ route("login") }}">`: このフォームの送信先が、Fortifyが用意しているログイン処理用のURL (`/login` へのPOST) になっています。
- `<x-input-label for="email" ... />`: 先ほど配置したコンポーネントを使い、ラベルを表示しています。
- `<x-text-input id="email" type="email" name="email" ... />`: `name="email"` と `name="password"` が重要です。Fortifyはこの `name` 属性を見て、メールアドレスとパスワードを受け取ります。
- `<x-input-error :messages="$errors->get("email")" ... />`: バリデーションエラーがあった場合に、メッセージを表示するためのコンポーネントです。

これで認証機能の骨格が完成しました。次のChapterから、いよいよアプリケーションのメイン機能である書籍管理を実装していきます。
