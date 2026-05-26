# Chapter 01: 「完璧なキッチン」 - 開発環境のセットアップ

## 🎯 このChapterの目標

このChapterでは、書籍レビューアプリ「BookShelf」を開発するための環境をゼロから構築します。いわば**「開発という料理を作るための、完璧なキッチンを準備する」**工程です。最高のキッチンがあれば、後の調理（コーディング）がスムーズに進みます。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| DockerとLaravel Sail | なぜDockerを使うのか、Laravel Sailがいかに開発環境構築を簡単にするのかを理解 |
| Laravel 10.xプロジェクトの作成 | 特定のバージョンのLaravelプロジェクトを作成する方法 |
| フロントエンドのセットアップ | Alpine.js を導入し、フロントエンドの準備を完了 |
| 日本語バリデーションメッセージ | `lang/ja/` ファイルを配置し、エラーメッセージを日本語化 |
| phpMyAdminの導入 | データベースを視覚的に操作するツールの導入 |

---

## 📖 背景知識：なぜDockerから始めるのか？

実務では、開発を始める前にまず「全員が同じ環境で動くこと」を保証する必要があります。もし、AさんのPCでは動くけど、BさんのPCでは動かない、という状況が発生すると、その原因調査だけで膨大な時間が溶けてしまいます。これを「環境差異」と呼び、開発チームの生産性を著しく下げる要因となります。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 開発者ごとにOSやPHP/MySQLのバージョンが違う | **Docker**で環境をコンテナ化し、全員が同じ実行環境を共有する | 全ての開発の土台。最初に固めることで、後の手戻りをなくす。 |
| Dockerのコマンドは複雑で覚えるのが大変 | **Laravel Sail**を使い、Docker操作をシンプルにする | Laravelプロジェクト専用のツール。Dockerの学習コストを下げ、すぐに開発に着手できる。 |
| プロジェクトごとに必要なツールが異なる | `compose.yaml`でphpMyAdminなどの追加ツールを定義する | データベースの中身を視覚的に確認できるツールはデバッグに必須。最初に入れておくと効率が上がる。 |

---

## 📋 実装の手順

### 1.1. Laravelプロジェクトの作成 (Laravel 10.x)

まずは、このプロジェクトの土台となるLaravelの骨組みを作成します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 book-review-app
```

### 1.2. Laravel Sailのインストール

次に、Docker操作を簡単にするためのツール「Laravel Sail」をプロジェクトに導入します。

```bash
cd book-review-app

docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer require laravel/sail --dev

docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    php artisan sail:install --with=mysql
```

### 1.3. .env ファイルの設定

`.env` ファイルのデータベース接続情報を確認・修正します。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bookshelf
DB_USERNAME=sail
DB_PASSWORD=password
```

**重要:** `DB_HOST` は `localhost` や `127.0.0.1` ではなく、Dockerコンテナ名である `mysql` を指定します。

末尾に Google Books API キーを追加します（応用機能 ISBN検索で使用）。

```dotenv
GOOGLE_BOOKS_API_KEY=
```

### 1.4. compose.yaml の設定

phpMyAdminを追加する場合、`compose.yaml` の `services:` 配下に以下を追加します。

```yaml
    phpmyadmin:
        image: 'phpmyadmin:latest'
        ports:
            - '${FORWARD_PHPMYADMIN_PORT:-8080}:80'
        environment:
            PMA_HOST: mysql
            PMA_USER: '${DB_USERNAME}'
            PMA_PASSWORD: '${DB_PASSWORD}'
        networks:
            - sail
        depends_on:
            - mysql
```

### 1.5. Sailの起動とAPP_KEY生成

```bash
./vendor/bin/sail up -d
sail artisan key:generate
```

エイリアスの設定（長いコマンドを `sail` で実行できるようにする）:

```bash
echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc
source ~/.zshrc
```

### 1.6. フロントエンドのセットアップ

```bash
sail npm install
sail npm install alpinejs
sail npm install -D tailwindcss@^3.4.0 @tailwindcss/forms postcss autoprefixer
sail npx tailwindcss init -p
```

> **重要:** Alpine.js は `resources/js/app.js` で使用するため、ここで必ずインストールしてください。Tailwind CSS とプラグイン (`@tailwindcss/forms`) もここでまとめてインストールします。

**`tailwind.config.js`:**

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
```

**`postcss.config.js`:**

```js
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

**`resources/css/app.css`:**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

**`vite.config.js`:**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

**`resources/js/app.js`:**

```js
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
```

> **重要:** この `app.js` は Alpine.js をインポートし起動しています。Blade テンプレート内の `x-data` や `@click` といった Alpine.js ディレクティブが動作するために必須です。模範解答および要件 100% と整合させるため、Laravel デフォルトの `import './bootstrap';` は使わない（削除する）。

Bladeファイルの配置後、フロントエンドをビルドします。

```bash
sail npm run build
```

### 1.7. 日本語バリデーションメッセージの準備

`config/app.php` で `'locale' => 'ja'` に変更します。

`lang/ja/` ディレクトリを作成し、以下3ファイルを配置します。

```bash
mkdir -p lang/ja
```

**`lang/ja/validation.php`:**

```php
<?php

return [
    'required' => ':attributeを入力してください。',
    'email' => ':attributeはメール形式で入力してください。',
    'confirmed' => ':attributeと一致しません。',
    'unique' => 'その:attributeは既に使用されています。',
    'min' => [
        'string' => ':attributeは:min文字以上で入力してください。',
    ],

    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
    ],
];
```

**`lang/ja/auth.php`:**

```php
<?php

return [
    'failed' => 'ログイン情報が登録されていません。',
    'password' => '入力されたパスワードが正しくありません。',
    'throttle' => 'ログインの試行回数が多すぎます。:seconds 秒後にお試しください。',
];
```

**`lang/ja/passwords.php`:**

```php
<?php

return [
    'reset' => 'パスワードをリセットしました。',
    'sent' => 'パスワードリセット用のリンクをメールで送信しました。',
    'throttled' => '時間をおいてから再度お試しください。',
    'token' => 'このパスワードリセットトークンは無効です。',
    'user' => 'このメールアドレスに一致するユーザーが見つかりません。',
];
```

---

## 💭 なぜこう作るのか？

### docker run コマンドの分解

この一見複雑なコマンドも、一つ一つのオプションに分解すれば理解できます。

| 部分 | 説明 | ポイント |
|:---|:---|:---|
| `docker run` | 新しいコンテナを起動するコマンド | Dockerの最も基本的なコマンドです。 |
| `--rm` | コンテナ停止時に自動的にコンテナを削除する | 一時的なコマンド実行に便利。不要なコンテナが残りません。 |
| `-u "$(id -u):$(id -g)"` | 現在のユーザーのIDとグループIDでコンテナを実行する | これにより、コンテナ内で作成されたファイルの所有者が現在のユーザーになり、パーミッションの問題を防ぎます。 |
| `-v "$(pwd):/var/www/html"` | 現在のディレクトリをコンテナの`/var/www/html`にマウントする | ローカルのファイルをコンテナ内で直接編集できるようになります。 |
| `-w /var/www/html` | コンテナ内の作業ディレクトリを指定する | この後のコマンドが、このディレクトリで実行されます。 |
| `laravelsail/php82-composer:latest` | 使用するDockerイメージを指定 | PHP 8.2とComposerがプリインストールされたLaravel Sail公式イメージです。 |
| `composer create-project ...` | Composerを使ってLaravelプロジェクトを作成するコマンド | `laravel/laravel:^10.0`でバージョン10を指定しています。 |

よくある間違い: `composer create-project laravel/laravel book-review-app` のようにローカル環境で実行しようとすると、ローカルのPHPやComposerのバージョンに依存してしまい、環境差異の原因になります。

### sail:install コマンド

| 部分 | 説明 | ポイント |
|:---|:---|:---|
| `php artisan sail:install` | Laravel SailをプロジェクトにセットアップするArtisanコマンド | `compose.yaml`というDockerの設定ファイルを生成します。 |
| `--with=mysql` | 使用するサービスとしてMySQLを選択するオプション | 他にも`redis`や`pgsql`などを選択できます。 |

### Alpine.js の役割

Alpine.js は、Bladeテンプレート内で `x-data`、`@click`、`x-show` などのディレクティブを使って、JavaScript をHTMLに直接記述するための軽量フレームワークです。お気に入りのトグルボタンやドロップダウンメニューなど、ちょっとしたインタラクションに使用します。

---

## 🚀 コードの実装

このChapterで作成・編集するファイルの一覧です。

| ファイル | 操作 | 説明 |
|:---|:---|:---|
| `book-review-app/` | 新規作成 | Laravel 10.xプロジェクト一式 |
| `.env` | 編集 | DB接続情報 + Google Books APIキー |
| `compose.yaml` | 編集 | phpMyAdmin追加 |
| `tailwind.config.js` | 編集 | テンプレートパス設定 |
| `postcss.config.js` | 確認 | PostCSS設定 |
| `vite.config.js` | 確認 | Vite設定 |
| `resources/css/app.css` | 編集 | Tailwindディレクティブ |
| `resources/js/app.js` | 編集 | Alpine.js インポート |
| `config/app.php` | 編集 | `locale` を `ja` に変更 |
| `lang/ja/validation.php` | 新規作成 | 日本語バリデーションメッセージ |
| `lang/ja/auth.php` | 新規作成 | 日本語認証メッセージ |
| `lang/ja/passwords.php` | 新規作成 | 日本語パスワードメッセージ |

---

## 🔍 コードリーディング

### resources/js/app.js

```js
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
```

| コード | 解説 |
|:---|:---|
| `import Alpine from 'alpinejs'` | `npm install alpinejs` でインストールした Alpine.js パッケージをインポートします。 |
| `window.Alpine = Alpine` | グローバルオブジェクトに Alpine を設定し、Blade テンプレートからアクセス可能にします。 |
| `Alpine.start()` | Alpine.js を起動し、HTML内の `x-data` などのディレクティブを処理開始します。 |

### lang/ja/validation.php

| キー | 解説 |
|:---|:---|
| `'min.string'` | パスワードなどの最小文字数バリデーション失敗時のメッセージ。`:attribute` と `:min` はLaravelが自動置換します。 |
| `'attributes'` | フォームフィールド名を日本語に変換するマッピング。`name` → `お名前` のように、エラーメッセージ中のフィールド名が日本語表示されます。 |

---

## 🧐 調べ方のヒント

分からないことがあった時、AIに聞くプロンプト例を紹介します。

| 疑問 | プロンプト例 |
|:---|:---|
| Docker のコマンドが分からない | 「docker run の -v オプションと -w オプションの違いを教えてください。」 |
| Sail のコマンド体系 | 「Laravel Sail でよく使うコマンドの一覧と、それぞれの用途を教えてください。」 |
| Alpine.js の基本 | 「Alpine.js の x-data, @click, x-show の基本的な使い方を、具体的なHTML例で教えてください。」 |
| バリデーションの日本語化 | 「Laravel でバリデーションメッセージを日本語にする方法を教えてください。lang/ja/ ファイルの構造も含めて。」 |
| Vite と Tailwind の設定 | 「Laravel 10 で Vite と Tailwind CSS を使うための最小限の設定ファイルの構成を教えてください。」 |

---

## ✅ 動作確認

環境構築が正しく完了したか、以下のポイントを確認しましょう。

| 確認項目 | 確認方法 |
|:---|:---|
| コンテナの起動 | `sail ps` で `laravel.test`、`mysql`、`phpmyadmin` が `running` であること |
| Laravelの初期画面 | ブラウザで `http://localhost` にアクセスし、Laravelのウェルカムページが表示されること |
| phpMyAdmin | ブラウザで `http://localhost:8080` にアクセスし、phpMyAdminの画面が表示されること |
| APP_KEY | `.env` ファイルに `APP_KEY=base64:...` が設定されていること |

---

## ✨ このChapterのまとめ

このChapterでは、開発環境を構成する全てのピースを組み立てました。

| 構成要素 | 役割 |
|:---|:---|
| Docker + Laravel Sail | チーム全員が同一の実行環境を共有 |
| `.env` | データベース接続情報とAPIキーの管理 |
| compose.yaml | phpMyAdmin等の追加サービス定義 |
| Alpine.js (`resources/js/app.js`) | Blade内のインタラクティブUIの基盤 |
| `lang/ja/*.php` | バリデーション・認証エラーメッセージの日本語化 |
| Vite + Tailwind CSS | モダンなフロントエンドビルド環境 |

これで、開発を始めるための環境がすべて整いました。次のChapterでは、この環境を使ってデータベースの設計図である「マイグレーション」を作成していきます。
