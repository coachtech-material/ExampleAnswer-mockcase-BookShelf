# 書籍レビューシステム (応用編) - BookShelf

このリポジトリは、書籍レビューシステムの応用機能（基本機能＋高度な検索、ISBN（Google Books API）連携、マイ読書レポート、公開API への Sanctum 認証追加）を実装したLaravelプロジェクトです。

## 動作環境

- Docker
- Docker Compose

※ Windowsの場合はWSL2の利用を推奨します。

## 環境構築手順

1. **リポジトリをクローン**

   ```bash
   git clone https://github.com/coachtech-material/ExampleAnswer-mockcase-BookShelf.git
   cd ExampleAnswer-mockcase-BookShelf
   git checkout advanced
   ```

2. **.envファイルの準備**

   `.env.example` をコピーして `.env` を作成します。

   ```bash
   cp .env.example .env
   ```

   `.env` ファイル内の以下のDB接続情報を確認・設定します。デフォルトではLaravel Sailの標準設定になっています。

   ```ini
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   DB_DATABASE=bookshelf
   DB_USERNAME=sail
   DB_PASSWORD=password
   ```

3. **Google Books APIキーの設定**

   書籍のISBN検索機能を利用するために、Google Books APIのAPIキーが必要です。
   [Google Cloud Console](https://console.cloud.google.com/) でAPIキーを取得し、`.env` ファイルに追記してください。

   ```ini
   GOOGLE_BOOKS_API_KEY=YOUR_API_KEY
   ```

4. **Composer依存パッケージのインストール**

   プロジェクトの初回セットアップ時は、`vendor` ディレクトリが存在しないため `sail` コマンドを使用できません。
   以下のDockerコマンドを実行して、コンテナ内で `composer install` を実行します。

   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php82-composer:latest \
       composer install --ignore-platform-reqs
   ```

5. **Laravel Sailの起動**

   以下のコマンドでDockerコンテナを起動します。

   ```bash
   ./vendor/bin/sail up -d
   ```

   > **エイリアスの設定（推奨）**
   > 
   > 毎回 `./vendor/bin/sail` と入力するのは手間なので、エイリアスを設定すると便利です。
   > 
   > ```bash
   > alias sail=\\\\'[ -f sail ] && bash sail || bash vendor/bin/sail\\\\'
   > ```

6. **アプリケーションキーの生成**

   ```bash
   sail artisan key:generate
   ```

7. **データベースのマイグレーションと初期データ投入**

   以下のコマンドでテーブルを作成し、ダミーデータを投入します。

   ```bash
   sail artisan migrate:fresh --seed
   ```

8. **フロントエンドのビルド**

   ```bash
   sail npm install
   sail npm run dev
   ```

   `npm run dev` は開発中は起動したままにしてください。

9. **アプリケーションへのアクセス**

   ブラウザで [http://localhost](http://localhost) にアクセスします。

## 機能一覧

### 基本機能

- ユーザー認証（登録、ログイン、ログアウト）
- 書籍のCRUD（登録、一覧、詳細、更新、削除）
- 書籍レビュー投稿・編集・削除
- お気に入り登録・解除
- レビューへのいいね登録・解除
- ランキング表示（レビュー数、平均評価）

### 応用機能

- **高度な書籍検索**: キーワード、ジャンル、並び順での絞り込み
- **ISBN検索**: Google Books APIを利用して書籍情報を自動入力
- **マイ読書レポート**: ログインユーザーの読書統計を表示
- **公開API の Sanctum 認証**: 書き込み系（POST/PUT/DELETE）にトークン認証を追加
