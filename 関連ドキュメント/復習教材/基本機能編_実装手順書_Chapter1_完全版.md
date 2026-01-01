# Chapter 1: 環境構築とプロジェクト作成

このChapterでは、Laravel 10.xを使用した開発環境をゼロから構築します。Docker（Laravel Sail）を使用することで、OSに依存しない統一された開発環境を実現します。

---

## 1.1. Laravelプロジェクトの作成 (Laravel 10.x)

**注意:** `curl -s "https://laravel.build/..."` は最新版のLaravelをインストールするため、今回は使用しません。

以下のDockerコマンドを実行して、Laravel 10.xを明示的に指定してプロジェクトを作成します。

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

## 1.2. Laravel Sailのインストール

プロジェクト作成後、`book-review-app` ディレクトリに移動し、Laravel Sailをインストールします。

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

## 1.4. フロントエンドのセットアップ (Vite & Tailwind CSS)

本プロジェクトでは、フロントエンドのスタイリングにTailwind CSSを使用します。以下の手順でセットアップを行ってください。

### 1. NPM依存パッケージのインストール

> **重要:** `sail npm install` を実行する前に、必ずSailコンテナが起動していることを確認してください。
> コンテナが起動していない場合は、先に `./vendor/bin/sail up -d` を実行してください。

```bash
sail npm install
```

> **トラブルシューティング:**
> 以下のエラーが発生した場合:
> ```
> OCI runtime exec failed: exec failed: unable to start container process: current working directory is outside of container mount namespace root
> ```
> 
> **原因:** Sailコンテナが起動していません。
> 
> **解決方法:**
> 1. Sailコンテナを起動: `./vendor/bin/sail up -d`
> 2. 再度実行: `sail npm install`

### 2. Tailwind CSSのインストール

```bash
sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer
```

### 3. 設定ファイルの生成

```bash
sail npx tailwindcss init -p
```

### 4. Tailwind CSSのテンプレートパス設定

`tailwind.config.js` を開き、TailwindがCSSを適用するテンプレートファイル（Bladeファイルなど）のパスを指定します。

**`tailwind.config.js`**
```javascript
/** @type {import('tailwindcss').Config} */
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

### 5. CSSファイルにTailwindディレクティブを追加

`resources/css/app.css` を開き、中身を以下の3行に置き換えます。

**`resources/css/app.css`**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### 6. Vite開発サーバーの起動

```bash
# 新しいターミナルを開いて実行
sail npm run dev
```

> **注意:** `sail npm run dev` は実行したままにしておく必要があります。開発中は常にこのコマンドを実行した状態にしておいてください。

## 1.5. phpMyAdminの追加

`compose.yaml` を開き、`mysql` サービスの後に以下の設定を追加してください。

**`compose.yaml` に追加する内容:**

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

## 1.6. Sailの起動とエイリアス設定

```bash
# Sailをバックグラウンドで起動
./vendor/bin/sail up -d
```

> **注意：Dockerボリュームについて**
> 以前に同じプロジェクト名やポートでDockerを使用したことがある場合、MySQLのデータボリュームに古いデータが残っている可能性があります。後のステップでマイグレーションを実行した際に「Table already exists」エラーが発生した場合は、以下のコマンドでボリュームをクリアしてください：
> ```bash
> sail down -v
> sail up -d
> sail artisan migrate
> ```

```bash
# エイリアスを設定して 'sail' だけでコマンドを実行できるようにする
echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc
# または bash の場合
# echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.bashrc

# シェルを再起動するか、新しいターミナルを開いてエイリアスを有効にする
exec $SHELL
```

## 1.7. アプリケーションキーの生成

```bash
sail artisan key:generate
```
