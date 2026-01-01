# Chapter 1: プロジェクトの開始 - 環境構築

おめでとうございます！あなたは今日からこの「書籍レビューアプリ」開発プロジェクトに参加するエンジニアです。あなたの最初のタスクは、クライアントのプロダクトマネージャー（PM）から渡された「詳細度50%」の要件定義書と、画面の骨格となるBladeテンプレートを元に、アプリケーションを開発することです。

この教材では、ゼロからプロジェクトを立ち上げ、現場のエンジニアがどのように考え、どのようにプロジェクトを進めていくのかを、手順を一切省略せずに追体験していきます。

## 1-1. プロジェクトのゴールと進め方

- **ゴール**: 書籍レビューアプリの基本機能を完成させる。
- **進め方**: 
    1. **要件の読解**: 詳細度50%の要件定義書から、実装すべき機能と不明点を洗い出す。
    2. **設計**: 不明点をPMにヒアリングし、詳細な仕様（DB設計、画面遷移、バリデーション等）を固める。
    3. **実装**: 設計に基づき、コードを書いていく。

## 1-2. 環境構築：ゼロからプロジェクトを立ち上げる

実務では、既存のリポジトリをクローンするのではなく、新しいプロジェクトをゼロから作成する場面が多々あります。今回は、クライアントから指定された厳密な手順で環境を構築します。

### Step 1: Laravelプロジェクトの作成 (Laravel 10.x)

**思考プロセス**: なぜこの長い `docker run` コマンドを使うのか？
- このコマンドは、ローカル環境にPHPやComposerがなくても、指定したバージョンのLaravelプロジェクトを正確に作成するための定石です。
- `laravelsail/php82-composer:latest` という「PHP 8.2とComposerが入ったDockerイメージ」を一度だけ起動し、その中で `composer create-project laravel/laravel:^10.0` を実行しています。
- これにより、チームメンバー全員が全く同じバージョンの環境で開発をスタートできます。

```bash
# Laravel 10.x を指定してプロジェクトを作成します。
# "book-review-app" は作成するディレクトリ名です。任意の名前で構いません。
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
# 作成したプロジェクトディレクトリに移動します。
cd book-review-app

# Laravel Sailをインストールします。
# --dev オプションは、このパッケージが開発時にのみ必要であることを示します。
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer require laravel/sail --dev

# Sailの設定ファイルを生成します。
# --with=mysql オプションで、データベースとしてMySQLを使用することを指定します。
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
# Sailをバックグラウンドで起動します。
# -d は "detached" モードを意味し、ターミナルを占有しません。
./vendor/bin/sail up -d

# エイリアスを設定して 'sail' だけでコマンドを実行できるようにします。
# これで毎回 './vendor/bin/sail' と入力する手間が省けます。
# お使いのシェルに合わせて .zshrc または .bashrc に追記してください。
echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc

# シェルを再起動してエイリアスを有効にします。
exec $SHELL
```

### Step 4: アプリケーションキーの生成

Laravelアプリケーションの暗号化（セッション情報やCookieなど）に使われる重要なキーを生成します。`.env` ファイルの `APP_KEY` が空の状態から、このコマンドで設定されます。

```bash
sail artisan key:generate
```

### Step 5: フロントエンドのセットアップ (Vite & Tailwind CSS)

PMから提供されたBladeテンプレートはTailwind CSSを利用しているため、Laravelの標準的な手順でセットアップします。

```bash
# 1. Node.jsの依存パッケージをインストールします。
# package.json ファイルに記載されているライブラリが node_modules ディレクトリにインストールされます。
sail npm install

# 2. Tailwind CSSと関連ライブラリをインストールします。
# postcss: CSSをJSで変換するためのツール
# autoprefixer: ベンダープレフィックスを自動で付与してくれるツール
sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer

# 3. Tailwind CSSとPostCSSの設定ファイルを生成します。
sail npx tailwindcss init -p
```

次に、生成された設定ファイルを編集します。

**`tailwind.config.js` の編集:**

**思考プロセス**: なぜこの設定が必要か？
- `content` に指定されたファイル（BladeやJSファイル）を監視し、その中で使われているTailwindのクラス名（例: `bg-blue-500`, `text-lg`）を検出します。
- ビルド時に、検出されたクラス名に対応するCSSだけを抽出して、最終的なCSSファイルを生成します。これにより、不要なCSSを含まない軽量なファイルを作成できます。

```javascript
// tailwind.config.js

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

**`resources/css/app.css` の編集:**

**思考プロセス**: この3行は何をしているのか？
- これらはTailwind CSSの「ディレクティブ」と呼ばれます。
- `@tailwind base;`: ブラウザ間の表示差異をなくすための基本的なスタイル（リセットCSS）を注入します。
- `@tailwind components;`: Tailwindが提供するコンポーネントクラス（例: `container`）を注入します。
- `@tailwind utilities;`: `bg-blue-500` や `text-lg` のような、最もよく使うユーティリティクラスを注入します。

```css
/* resources/css/app.css */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

最後にVite開発サーバーを起動します。このターミナルは開発中、常に起動したままにしておきます。ファイルの変更を検知して、自動的にブラウザに反映してくれます。

```bash
# 新しいターミナルを開いて実行してください。
sail npm run dev
```

### Step 6: phpMyAdminの追加

データベースの中身をGUIで簡単に確認するために、phpMyAdminを導入します。

`compose.yaml` ファイルに以下の `phpmyadmin` の設定を追記してください。

```yaml
# compose.yaml
services:
    # ... 既存の laravel.test, mysql, mailpit の設定 ...

    phpmyadmin:
        image: phpmyadmin/phpmyadmin
        ports:
            - "${FORWARD_PHPMYADMIN_PORT:-8080}:80"
        environment:
            PMA_HOST: mysql
            PMA_PORT: 3306
            MYSQL_ROOT_PASSWORD: "${DB_PASSWORD}"
        networks:
            - sail
```

設定を反映させるために、一度コンテナを完全に停止し、再起動します。

```bash
# コンテナを停止し、関連するボリューム（データ）も削除します。
sail down -v

# 再度コンテナを起動します。
sail up -d
```

これで、ブラウザで `http://localhost:8080` にアクセスするとphpMyAdminが開きます。
- **サーバー**: `mysql`
- **ユーザー名**: `root`
- **パスワード**: `.env` ファイルの `DB_PASSWORD` の値（デフォルトは `password`）

でログインできます。

### Step 7: Bladeテンプレートの配置

PMから提供されたBladeテンプレート一式を、`resources/views` ディレクトリに配置します。これにより、後の実装で `view('books.index')` のように呼び出せるようになります。

---

これで、アプリケーションを開発するための土台がすべて整いました。次のChapterでは、この環境を元に、DB設計とモデルの作成を進めていきます。
