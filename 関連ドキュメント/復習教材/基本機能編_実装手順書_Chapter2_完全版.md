# Chapter 2: 認証機能 (Laravel Fortify)

このChapterでは、Laravel Fortifyを導入して、アプリケーションに必須の認証機能（ユーザー登録、ログイン、ログアウト）を実装します。

## 2-1. Laravel Fortifyのインストール

Fortifyは、Laravel公式の認証バックエンド実装です。UIは提供せず、認証ロジックのみを提供するため、フロントエンドを自由に構築したい場合に適しています。

### Step 1: ComposerでFortifyをインストール

```bash
sail composer require laravel/fortify
```

### Step 2: Fortifyのリソースを公開

以下のコマンドで、Fortifyの設定ファイル、マイグレーション、ビューなどをプロジェクトにコピーします。

```bash
sail artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

### Step 3: データベースマイグレーション

Fortifyをインストールすると、`password_resets`テーブルなど、認証に必要な新しいマイグレーションファイルが追加されます。再度マイグレーションを実行して、これらのテーブルをデータベースに作成します。

```bash
sail artisan migrate
```

## 2-2. Fortifyのサービスプロバイダ登録

Fortifyをアプリケーションに認識させるために、サービスプロバイダを登録します。

### Step 1: `config/app.php` の編集

`config/app.php` ファイルを開き、`providers` 配列に `App\Providers\FortifyServiceProvider::class` を追加します。

**`config/app.php`**
```php
'providers' => [
    // ... 他のプロバイダ

    /*
     * Application Service Providers...
     */
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // App\Providers\BroadcastServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Providers\FortifyServiceProvider::class, // この行を追加
],
```

## 2-3. 認証ビューのセットアップ

Fortifyはバックエンドのみを提供するため、ログイン画面や登録画面などのUI（ビュー）は自分たちで用意する必要があります。

### Step 1: Tailwind CSSのインストール

認証画面のスタイリングのために、Tailwind CSSをセットアップします。

```bash
sail npm install -D tailwindcss postcss autoprefixer @tailwindcss/forms
sail npx tailwindcss init -p
```

### Step 2: `tailwind.config.js` の編集

TailwindがBladeファイル内のクラスを認識できるように、設定ファイルを編集します。

**`tailwind.config.js`**
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
    "./storage/framework/views/*.php",
    "./resources/views/**/*.blade.php",
  ],

  theme: {
    extend: {},
  },

  plugins: [require('@tailwindcss/forms')],
};
```

**思考プロセス:**
`@tailwindcss/forms` プラグインを追加することで、基本的なフォーム要素にきれいなスタイルが適用されます。認証フォームを素早く整えるのに役立ちます。

### Step 3: `resources/css/app.css` の編集

TailwindのディレクティブをCSSファイルに追加します。

**`resources/css/app.css`**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### Step 4: Viteの開発サーバーを起動

CSSの変更をリアルタイムで反映させるため、Viteを起動しておきます。

```bash
sail npm run dev
```

### Step 5: 認証関連のビューを作成

Fortifyは、`config/fortify.php` の `views` 設定で指定されたビューを返します。今回は、これらのビューを手動で作成します。

- `resources/views/auth/login.blade.php` (ログイン画面)
- `resources/views/auth/register.blade.php` (ユーザー登録画面)

(※詳細なBladeコードは、提供された教材ファイルを参照してください)

### Step 6: ルート定義 (`routes/web.php`)

Fortifyが提供する認証ルートをインクルードします。

**`routes/web.php`**
```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 認証関連のルート
require __DIR__.'/auth.php';
```

**思考プロセス:**
`routes/web.php`に`require __DIR__.'/auth.php';`を記述することで、Laravel Fortifyが提供する認証関連のルート（ログイン、ログアウト、ユーザー登録、パスワードリセットなど）がアプリケーションに組み込まれます。これにより、自前でこれらのルートを一つ一つ定義する手間が省けます。

## 2-4. 動作確認

1.  ブラウザで `http://localhost/register` にアクセスし、ユーザー登録画面が表示されることを確認します。
2.  フォームに情報を入力してユーザー登録を行い、ログイン後の画面（通常は `/` にリダイレクトされる）が表示されることを確認します。
3.  一度ログアウトし、`http://localhost/login` から再度ログインできることを確認します。

---

これで認証機能の基本的なセットアップが完了しました。次のChapterでは、アプリケーションの核となるDB設計とモデル作成に進みます。
