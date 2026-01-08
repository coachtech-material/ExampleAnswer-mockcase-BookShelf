# 書籍レビューシステム (基本編) - BookShelf

このリポジトリは、書籍レビューシステムの基本的な機能（CRUD、レビュー、お気に入り、いいね、ランキング）を実装したLaravelプロジェクトです。

## 動作環境

- Docker
- Docker Compose

※ Windowsの場合はWSL2の利用を推奨します。

## 環境構築手順

1. **リポジトリをクローン**

   ```bash
   git clone https://github.com/coachtech-material/ExampleAnswer-mockcase-BookShelf.git
   cd ExampleAnswer-mockcase-BookShelf
   git checkout basic
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

3. **Composer依存パッケージのインストール**

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

4. **Laravel Sailの起動**

   以下のコマンドでDockerコンテナを起動します。

   ```bash
   ./vendor/bin/sail up -d
   ```

   > **エイリアスの設定（推奨）**
   > 
   > 毎回 `./vendor/bin/sail` と入力するのは手間なので、エイリアスを設定すると便利です。
   > 
   > ```bash
   > alias sail=\'[ -f sail ] && bash sail || bash vendor/bin/sail\'
   > ```

5. **アプリケーションキーの生成**

   ```bash
   sail artisan key:generate
   ```

6. **データベースのマイグレーションと初期データ投入**

   以下のコマンドでテーブルを作成し、ダミーデータを投入します。

   ```bash
   sail artisan migrate:fresh --seed
   ```

7. **フロントエンドのビルド**

   ```bash
   sail npm install
   sail npm run dev
   ```

   `npm run dev` は開発中は起動したままにしてください。

8. **アプリケーションへのアクセス**

   ブラウザで [http://localhost](http://localhost) にアクセスします。

## 機能一覧

- ユーザー認証（登録、ログイン、ログアウト）
- 書籍のCRUD（登録、一覧、詳細、更新、削除）
- 書籍レビュー投稿・編集・削除
- お気に入り登録・解除
- レビューへのいいね登録・解除
- ランキング表示（レビュー数、平均評価）


<img width="1920" height="1080" alt="CleanShot 2026-01-08 at 14 59 10" src="https://github.com/user-attachments/assets/572b726e-344e-4826-b06d-d5e0913d9465" />
