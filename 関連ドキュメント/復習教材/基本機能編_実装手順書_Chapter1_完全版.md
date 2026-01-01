# Chapter 1: 環境構築

このChapterでは、Laravelの開発環境を構築します。DockerとLaravel Sailを使い、指定されたバージョンのPHPとLaravelでプロジェクトをセットアップします。

## 1-1. 要件の確認

まず、今回のプロジェクトで求められている技術的な要件を確認します。

- **PHPバージョン**: 8.2
- **Laravelバージョン**: 10.x
- **データベース**: MySQL 8.0
- **開発環境**: Docker, Laravel Sail

**思考プロセス:**
実務では、プロジェクトを開始する前に必ずバージョン要件を確認します。これにより、開発環境と本番環境の差異による問題を未然に防ぐことができます。

## 1-2. Laravelプロジェクトの作成

指定されたバージョンのLaravelプロジェクトを作成します。

### Step 1: Dockerコンテナの準備

Dockerがインストールされていることを確認してください。

### Step 2: Laravelプロジェクトの作成

以下のコマンドを実行して、PHP 8.2環境でLaravel 10.xのプロジェクトを作成します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 book-review-app
```

**コードリーディング:**
- `docker run --rm`: Dockerコンテナを一時的に起動し、コマンド終了後に自動でコンテナを削除します。クリーンな環境で一度きりのコマンドを実行する際に便利です。
- `-u "$(id -u):$(id -g)"`: コンテナ内のプロセスを、現在操作しているユーザーのIDとグループIDで実行します。これにより、コンテナ内で作成されたファイルの所有者が現在のユーザーになり、パーミッションの問題を防ぎます。
- `-v "$(pwd):/var/www/html"`: 現在のディレクトリ（`pwd`）を、コンテナ内の `/var/www/html` ディレクトリにマウント（同期）します。これにより、ホストマシンとコンテナでファイルを共有できます。
- `-w /var/www/html`: コンテナ内での作業ディレクトリを指定します。このディレクトリで後続のコマンドが実行されます。
- `laravelsail/php82-composer:latest`: PHP 8.2とComposerがプリインストールされた公式のDockerイメージです。バージョン要件に合わせてイメージを選択します。
- `composer create-project laravel/laravel:^10.0 book-review-app`: Composerを使い、`laravel/laravel` パッケージのバージョン `10.0` 以上 `11.0` 未満を `book-review-app` という名前のディレクトリにインストールします。

### Step 3: プロジェクトディレクトリへの移動

作成されたプロジェクトのディレクトリに移動します。

```bash
cd book-review-app
```

これ以降のコマンドは、すべてこの `book-review-app` ディレクトリ内で実行します。

## 1-3. Laravel Sailのセットアップ

Laravel Sailは、Dockerを利用したLaravelのローカル開発環境を簡単に構築・操作するためのツールです。

### Step 1: Laravel Sailのインストール

Composerを使って、開発環境用の依存パッケージとしてLaravel Sailをプロジェクトに追加します。

```bash
composer require laravel/sail --dev
```

### Step 2: Sailの設定ファイルを生成

Artisanコマンドを使い、Sailの設定ファイル（`docker-compose.yml`）を生成します。`--with=mysql` オプションを付けることで、MySQLサービスも一緒にセットアップされます。

```bash
php artisan sail:install --with=mysql
```

### Step 3: Sailエイリアスの設定

毎回 `./vendor/bin/sail` と入力するのは手間がかかるため、`sail` という短いコマンドで実行できるようにエイリアス（別名）を設定しておくと便利です。

```bash
alias sail=\'bash vendor/bin/sail\'
```

**思考プロセス:**
このエイリアス設定は、ターミナルのセッションが終了すると消えてしまいます。毎回設定するのが面倒な場合は、お使いのシェルの設定ファイル（例: `~/.bashrc`, `~/.zshrc`）にこの行を追記しておくと、ターミナルを起動するたびに自動でエイリアスが設定されます。

### Step 4: Dockerコンテナの起動

いよいよSailを使ってDockerコンテナを起動します。

```bash
sail up -d
```

**コードリーディング:**
- `sail up`: `docker-compose.yml` ファイルを元に、定義されたサービス（Webサーバー、データベース等）のコンテナを起動します。
- `-d`: デタッチモード（バックグラウンド）でコンテナを起動します。これを付けないと、コンテナのログがターミナルに表示され続け、他のコマンドが入力できなくなります。

### Step 5: 動作確認

コンテナが正常に起動したら、ブラウザで `http://localhost` にアクセスしてください。Laravelの初期ウェルカムページが表示されれば、環境構築は成功です。

## 1-4. データベースマイグレーション

アプリケーションの基本的なテーブルをデータベースに作成します。

### Step 1: マイグレーションの実行

以下のコマンドで、Laravelにデフォルトで用意されているマイグレーションファイルを実行し、`users`テーブルなどを作成します。

```bash
sail artisan migrate
```

**思考プロセス:**
`.env` ファイルには、データベース接続情報（`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`）が定義されています。`sail artisan migrate` コマンドは、この設定を元にDockerネットワーク内のMySQLコンテナに接続し、テーブルを作成します。

---

これで基本的な環境構築は完了です。次のChapterでは、認証機能のセットアップに進みます。
