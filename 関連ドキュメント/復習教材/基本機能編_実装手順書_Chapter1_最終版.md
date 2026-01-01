# Chapter 1: プロジェクトの開始 - 環境構築とDB設計

おめでとうございます！あなたは今日からこの「書籍レビューアプリ」開発プロジェクトに参加するエンジニアです。あなたの最初のタスクは、クライアントのプロダクトマネージャー（PM）から渡された要件定義書を元に、アプリケーションを開発することです。

しかし、渡されたのは「詳細度50%」の要件定義書。ここから、現場のエンジニアがどのように考え、どのようにプロジェクトを進めていくのかを追体験していきましょう。

## 1-1. 要件の読み解きとBladeテンプレートの分析

(このセクションは前回の「完全版」と同様のため、内容は省略しませんが、ここでは記載を割愛します。実際のファイルには含まれています。)

## 1-2. 環境構築：ゼロからプロジェクトを立ち上げる

前回の手順では `curl` を使いましたが、今回はクライアントから指定された、より厳密な手順で環境を構築します。この方法は、特定のPHPやLaravelのバージョンを正確に指定したい場合に有効です。

### Step 1: Laravelプロジェクトの作成 (Laravel 10.x)

**思考プロセス**: なぜこの長い `docker run` コマンドを使うのか？
- `curl` を使う方法は手軽ですが、常に最新版のLaravelをインストールします。実務では「このプロジェクトはLaravel 10.x系で」のようにバージョンが指定されることが多々あります。
- このコマンドは、`laravelsail/php82-composer:latest` という「PHP 8.2とComposerが入ったDockerイメージ」を一度だけ起動し、その中で `composer create-project laravel/laravel:^10.0` を実行しています。
- これにより、ローカル環境にPHPやComposerがなくても、指定したバージョンのLaravelプロジェクトを正確に作成できます。

```bash
# Laravel 10.x を指定してプロジェクトを作成
# "book-review-app" は作成するディレクトリ名
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 book-review-app
```

### Step 2: Laravel Sailのインストール

**思考プロセス**: なぜSailを後から入れるのか？
- `create-project` で作成されたばかりのLaravelプロジェクトには、まだSail（Docker環境を簡単に操作するツール）が含まれていません。
- そのため、再度Dockerコンテナを一時的に起動して、今作成したプロジェクトの中に `laravel/sail` パッケージをインストールします。

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

# Sailの設定ファイルを生成（--with=mysql でMySQLを選択）
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    php artisan sail:install --with=mysql
```

### Step 3: Sailの起動とエイリアス設定

これでプロジェクトにSailが導入されたので、今後は `sail` コマンドでコンテナを操作できます。

```bash
# Sailをバックグラウンドで起動
./vendor/bin/sail up -d

# エイリアスを設定して 'sail' だけでコマンドを実行できるようにする
# お使いのシェルに合わせて .zshrc または .bashrc に追記
echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc

# シェルを再起動してエイリアスを有効にする
exec $SHELL
```

### Step 4: アプリケーションキーの生成

Laravelアプリケーションの暗号化などに使われる重要なキーを生成します。

```bash
sail artisan key:generate
```

### Step 5: フロントエンドのセットアップ (Vite & Tailwind CSS)

添付の手順書通りに、Tailwind CSSをセットアップします。

```bash
# 1. NPM依存パッケージのインストール
sail npm install

# 2. Tailwind CSSのインストール
sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer

# 3. 設定ファイルの生成
sail npx tailwindcss init -p
```

`tailwind.config.js` と `resources/css/app.css` を手順書通りに編集してください。

```javascript
// tailwind.config.js
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
  ],
  // ...
}
```

```css
/* resources/css/app.css */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

最後にVite開発サーバーを起動します。このターミナルは開発中、常に起動したままにしておきます。

```bash
# 新しいターミナルを開いて実行
sail npm run dev
```

### Step 6: phpMyAdminの追加とDB設計

添付手順書通りに `compose.yaml` にphpMyAdminの設定を追記し、`sail down -v && sail up -d` でコンテナを再起動してください。

その後、添付手順書「Step 2: データベース設計とマイグレーション」の通り、リポジトリにあるマイグレーションファイルの内容を確認し、`sail artisan migrate` を実行してテーブルを作成します。

**思考プロセス**: なぜ `make:migration` しないのか？
- この教材では、DB設計（マイグレーションファイル）もPMから与えられた「仕様」の一部と見なします。そのため、自分で作成するのではなく、提供されたものを読み解き、実行することが求められます。
- `sail artisan migrate` を実行することで、`database/migrations` ディレクトリ内のファイルが実行され、設計通りのテーブルがDB内に作成されます。

これで、添付手順書に沿った、より実践的な環境構築が完了しました。次のChapterから、この環境を前提に機能実装を進めていきます。
