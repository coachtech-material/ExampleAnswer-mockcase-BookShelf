# Chapter 1: 開発環境のセットアップ

## 🎯 このセクションで学ぶこと

このセクションでは、書籍レビューアプリ「BookShelf」を開発するための環境をゼロから構築します。具体的には、以下の技術要素を学び、セットアップします。

- **DockerとLaravel Sail**: なぜDockerを使うのか、そしてLaravel Sailがいかに開発環境構築を簡単にするのかを理解します。
- **Laravel 10.xプロジェクトの作成**: 特定のバージョンのLaravelプロジェクトを作成する方法を学びます。
- **フロントエンドのセットアップ**: ViteとTailwind CSSを導入し、モダンなフロントエンド開発の準備をします。
- **phpMyAdminの導入**: データベースを視覚的に操作するツールを導入します。

---

## 🧠 先輩エンジニアの思考プロセス：なぜDockerから始めるのか？

実務では、開発を始める前にまず「全員が同じ環境で動くこと」を保証する必要があります。もし、AさんのPCでは動くけど、BさんのPCでは動かない、という状況が発生すると、その原因調査だけで膨大な時間が溶けてしまいます。これを「環境差異」と呼び、開発チームの生産性を著しく下げる要因となります。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 開発者ごとにOSやPHP/MySQLのバージョンが違う | **Docker**で環境をコンテナ化し、全員が同じ実行環境を共有する | 全ての開発の土台。最初に固めることで、後の手戻りをなくす。 |
| Dockerのコマンドは複雑で覚えるのが大変 | **Laravel Sail**を使い、Docker操作をシンプルにする | Laravelプロジェクト専用のツール。Dockerの学習コストを下げ、すぐに開発に着手できる。 |
| プロジェクトごとに必要なツールが異なる | `compose.yaml`でphpMyAdminなどの追加ツールを定義する | データベースの中身を視覚的に確認できるツールはデバッグに必須。最初に入れておくと効率が上がる。 |

このChapterは、いわば**「開発という料理を作るための、完璧なキッチンを準備する」**工程です。最高のキッチンがあれば、後の調理（コーディング）がスムーズに進みます。

---

## 1.1. Laravelプロジェクトの作成 (Laravel 10.x)

まずは、このプロジェクトの土台となるLaravelの骨組みを作成します。

### 1.1.1. コマンドの実行

```bash
# Laravel 10.x を指定してプロジェクトを作成
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 book-review-app
```

### 1.1.2. コードリーディング：`docker run`コマンドの分解

この一見複雑なコマンドも、一つ一つのオプションに分解すれば理解できます。

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `docker run` | 新しいコンテナを起動するコマンド | (コンテナの実行結果) | Dockerの最も基本的なコマンドです。 |
| `--rm` | コンテナ停止時に自動的にコンテナを削除する | なし | 一時的なコマンド実行に便利。不要なコンテナが残りません。 |
| `-u "$(id -u):$(id -g)"` | 現在のユーザーのIDとグループIDでコンテナを実行する | なし | ✅ これにより、コンテナ内で作成されたファイルの所有者が現在のユーザーになり、パーミッションの問題を防ぎます。 |
| `-v "$(pwd):/var/www/html"` | 現在のディレクトリをコンテナの`/var/www/html`にマウントする | なし | ローカルのファイルをコンテナ内で直接編集できるようになります。 |
| `-w /var/www/html` | コンテナ内の作業ディレクトリを指定する | なし | この後のコマンドが、このディレクトリで実行されます。 |
| `laravelsail/php82-composer:latest` | 使用するDockerイメージを指定 | なし | PHP 8.2とComposerがプリインストールされたLaravel Sail公式イメージです。 |
| `composer create-project ...` | Composerを使ってLaravelプロジェクトを作成するコマンド | (プロジェクトファイル) | `laravel/laravel:^10.0`でバージョン10を指定しています。 |

❌ **よくある間違い**: `composer create-project laravel/laravel book-review-app` のようにローカル環境で実行しようとすると、ローカルのPHPやComposerのバージョンに依存してしまい、環境差異の原因になります。

---

## 1.2. Laravel Sailのインストール

次に、Docker操作を簡単にするためのツール「Laravel Sail」をプロジェクトに導入します。

### 1.2.1. コマンドの実行

```bash
# プロジェクトディレクトリに移動
cd book-review-app

# Laravel Sailをインストール
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer require laravel/sail --dev

# Sailの設定ファイルをパブリッシュ（MySQLを選択）
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    php artisan sail:install --with=mysql
```

### 1.2.2. コードリーディング：`sail:install`コマンド

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `php artisan sail:install` | Laravel SailをプロジェクトにセットアップするArtisanコマンド | (設定ファイル) | `compose.yaml`というDockerの設定ファイルを生成します。 |
| `--with=mysql` | 使用するサービスとしてMySQLを選択するオプション | なし | 他にも`redis`や`pgsql`などを選択できます。 |

このコマンドを実行すると、プロジェクトのルートに`compose.yaml`ファイルが作成されます。これが、私たちの「開発環境の設計図」となります。

---

## 1.3. .env ファイルの設定

`.env` ファイルを開き、データベース接続情報が以下と一致していることを確認します。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

**重要:** `DB_HOST` は `localhost` や `127.0.0.1` ではなく、Dockerコンテナ名である `mysql` を指定します。

---

## 1.4. フロントエンドとツールのセットアップ

最後に、開発を効率化するための周辺ツールを導入します。

### 1.4.1. フロントエンドのセットアップ (Vite & Tailwind CSS)

**1. NPM依存パッケージのインストール**

> **重要:** `sail npm install` を実行する前に、必ずSailコンテナが起動していることを確認してください。
> コンテナが起動していない場合は、先に `./vendor/bin/sail up -d` を実行してください。

```bash
sail npm install
```

**2. Tailwind CSSのインストール**

```bash
sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer
```

**3. 設定ファイルの生成**

```bash
sail npx tailwindcss init -p
```

**4. Tailwind CSSのテンプレートパス設定**

`tailwind.config.js` を開き、TailwindがCSSを適用するテンプレートファイル（Bladeファイルなど）のパスを指定します。

**`tailwind.config.js`**
```javascript
/** @type {import(\'tailwindcss\').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

**5. Vite開発サーバーの起動**

```bash
# 新しいターミナルを開いて実行
sail npm run dev
```

### 1.4.2. phpMyAdminの追加

`compose.yaml` を開き、`mysql` サービスの後に以下の設定を追加してください。

**`compose.yaml` に追加する内容:**

```yaml
    phpmyadmin:
        image: \'phpmyadmin:latest\'
        ports:
            - \'${FORWARD_PHPMYADMIN_PORT:-8080}:80\'
        environment:
            PMA_HOST: mysql
            PMA_USER: \'${DB_USERNAME}\'
            PMA_PASSWORD: \'${DB_PASSWORD}\'
        networks:
            - sail
        depends_on:
            - mysql
```

### 1.4.3. Sailの起動とエイリアス設定

以下のコマンドで、`compose.yaml`の設計図を元に、定義された全てのコンテナ（Webサーバー、MySQL、phpMyAdmin）をバックグラウンドで起動します。
```bash
./vendor/bin/sail up -d
```

以下のコマンドで、長いコマンドを`sail`という短いエイリアスで実行できるように設定します。これにより、以降は`sail artisan migrate`のようにシンプルにコマンドを実行できます。
```bash
alias sail=\'...\[ -f sail ] && bash sail || bash vendor/bin/sail\''
```

これで、開発を始めるための環境がすべて整いました。次のChapterでは、この環境を使ってデータベースの設計図である「マイグレーション」を作成していきます。
