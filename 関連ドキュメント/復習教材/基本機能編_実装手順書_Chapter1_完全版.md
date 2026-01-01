# Chapter 1: 環境構築

このChapterでは、書籍レビューアプリケーションを開発するための環境を構築します。DockerとLaravel Sailを使い、再現性の高いモダンな開発環境をゼロから立ち上げる手順を学びます。

## 1-1. なぜDockerとLaravel Sailを使うのか？

> **思考プロセス:**
> プロジェクトを始める際、開発環境の選定は非常に重要です。かつてはローカルマシンに直接PHPやMySQLをインストールする方法（例: MAMP, XAMPP）が主流でしたが、これにはいくつかの問題がありました。
> 
> - **「私の環境では動くのに…」問題**: 開発者それぞれのPC環境（OSのバージョン、インストールされているライブラリなど）の違いにより、AさんのPCでは動くがBさんのPCでは動かない、といった問題が頻発しました。
> - **環境構築の複雑化**: プロジェクトが大規模になるほど、必要なソフトウェア（PHP, MySQL, Redis, Node.jsなど）が増え、それらのバージョン管理も煩雑になります。
> - **本番環境との差異**: ローカル環境と本番サーバーの環境が異なると、デプロイ時に予期せぬエラーが発生する原因となります。
> 
> これらの問題を解決するのが**Docker**です。Dockerは「コンテナ」という技術を使い、アプリケーションとそれが動くために必要な環境（OS、ライブラリ、ミドルウェアなど）をひとまとめにして隔離します。これにより、誰がどこで動かしても同じように動作する、軽量で再現性の高い環境を実現できます。
> 
> そして**Laravel Sail**は、そのDocker環境をLaravelで簡単に利用できるようにした公式ツールです。本来であれば複雑なDockerの設定ファイル（`compose.yaml`）を手で書く必要がありますが、Sailはいくつかの簡単なコマンドを実行するだけで、Laravel開発に必要なコンテナ（Webサーバー、データベース、キャッシュなど）を自動で構築・起動してくれます。
> 
> 現場のチーム開発では、このように環境差異をなくし、新メンバーが迅速に開発に参加できる体制を整えることが極めて重要です。Sailの導入は、そのためのベストプラクティスと言えるでしょう。

## 1-2. Laravelプロジェクトの作成

まず、Dockerを使ってLaravelのプロジェクトを作成します。ローカルにPHPやComposerがインストールされていなくても問題ありません。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 book-review-app
```

**コマンド解説:**
- `docker run`: Dockerコンテナを起動します。
- `--rm`: コマンド実行後にコンテナを自動で削除します。一時的な利用に便利です。
- `-u "$(id -u):$(id -g)"`: コンテナ内での操作を、現在のローカルユーザーの権限で実行します。これにより、作成されたファイルの所有者が`root`になってしまい、後から編集できなくなる問題を回避します。
- `-v "$(pwd):/var/www/html"`: ローカルのカレントディレクトリ（`pwd`）を、コンテナ内の`/var/www/html`にマウント（同期）します。これにより、コンテナ内でのファイル操作がローカルのファイルシステムに反映されます。
- `-w /var/www/html`: コンテナ内での作業ディレクトリを指定します。
- `laravelsail/php82-composer:latest`: 使用するDockerイメージを指定します。PHP 8.2とComposerがプリインストールされています。
- `composer create-project ...`: 実際に実行するコマンドです。Laravelのバージョン10を指定して`book-review-app`という名前のプロジェクトを作成します。

## 1-3. Laravel Sailのセットアップ

次に、作成したプロジェクトにLaravel Sailを導入します。

```bash
# プロジェクトディレクトリに移動
cd book-review-app

# Sailをインストール
composer require laravel/sail --dev

# Sailの設定ファイルを生成（MySQLを選択）
php artisan sail:install --with=mysql
```

> **思考プロセス:**
> - `composer require laravel/sail --dev`: Sailは開発時にのみ使用するツールなので、`--dev`オプションを付けて開発用依存パッケージとしてインストールします。これにより、本番環境には不要なパッケージが含まれなくなります。
> - `php artisan sail:install --with=mysql`: このコマンドがSailの心臓部です。`compose.yaml`というDockerの設定ファイルを自動生成します。`--with=mysql`オプションにより、Webサーバー（`laravel.test`）コンテナに加えて、`mysql`コンテナも設定に含めるよう指示しています。他にも`redis`や`pgsql`などを指定できます。

## 1-4. Sailの起動とエイリアス設定

Sailを起動し、使いやすくするためのエイリアス（ショートカット）を設定します。

```bash
# Sailをバックグラウンドで起動
./vendor/bin/sail up -d

# エイリアスを設定して `sail` だけでコマンドを実行できるようにする
alias sail=\'[ -f sail ] && bash sail || bash vendor/bin/sail\'

# Sailを使ってアプリケーションキーを生成
sail artisan key:generate
```

**コマンド解説:**
- `./vendor/bin/sail up -d`: `compose.yaml`の内容に基づいてコンテナを起動します。`-d`はバックグラウンドで起動する（デタッチモード）オプションです。
- `alias sail=...`: `sail`と入力するだけで`./vendor/bin/sail`が実行されるようにします。毎回長いパスを打つ手間が省けます。
- `sail artisan key:generate`: Sail経由でArtisanコマンドを実行しています。`sail`コマンドは、適切なコンテナ（この場合は`laravel.test`）内で後続のコマンドを実行してくれます。`key:generate`は、アプリケーションの暗号化などに使われるキーを`.env`ファイルに生成する重要なコマンドです。

## 1-5. フロントエンド環境の構築 (Vite & Tailwind CSS)

Laravelのモダンなフロントエンド開発環境をセットアップします。

```bash
# 必要なNPMパッケージをインストール
sail npm install

# Tailwind CSSとその関連パッケージをインストール
sail npm install -D tailwindcss postcss autoprefixer

# Tailwind CSSの設定ファイルを生成
sail npx tailwindcss init -p
```

> **思考プロセス:**
> - **なぜVite?**: Vite（ヴィート）は、非常に高速な開発サーバーとビルドツールです。ファイルの変更を即座にブラウザに反映させるHMR（ホットモジュールリプレイスメント）機能が優れており、開発体験を大幅に向上させます。
> - **なぜTailwind CSS?**: Tailwind CSSは「ユーティリティファースト」を掲げるCSSフレームワークです。`class="text-red-500 font-bold"`のように、あらかじめ用意された小さなクラス（ユーティリティクラス）をHTMLに直接書き込むことでデザインを構築します。これにより、CSSファイルが肥大化しにくく、コンポーネントベースの開発と非常に相性が良いというメリットがあります。

次に、生成された設定ファイルを編集します。

**`tailwind.config.js`**
```javascript
/** @type {import(\'tailwindcss\').Config} */
export default {
  content: [
    "./resources/**/*.blade.php", // Bladeファイル内のクラスを検知
    "./resources/**/*.js",
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php", // ページネーションのスタイルも対象に
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

**`resources/css/app.css`**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

> **思考プロセス:**
> - `tailwind.config.js`の`content`には、Tailwind CSSがスキャンして使用されているクラスを検出するためのファイルパスを指定します。ここにパスを追加しないと、本番用にビルドした際に未使用と判断されたクラスが削除されてしまい、スタイルが崩れる原因になります。ページネーションのBladeファイルも忘れずに追加するのがポイントです。
> - `app.css`の`@tailwind`ディレクティブは、Tailwindの基本的なスタイル、コンポーネントクラス、ユーティリティクラスを読み込むための記述です。

最後に、Vite開発サーバーを起動します。

```bash
# 新しいターミナルを開いて実行
sail npm run dev
```

これで、BladeやJSファイルを変更すると自動的にブラウザがリロードされる、快適な開発環境が整いました。

---

以上で、全ての開発の土台となる環境構築は完了です。次のChapterでは、このアプリケーションの根幹となるデータベースの設計とモデルの設計に進みます。
