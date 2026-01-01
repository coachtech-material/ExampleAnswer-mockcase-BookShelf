# Chapter 1: 環境構築

このChapterでは、Laravelの開発環境を構築します。Docker、Laravel Sail、Tailwind CSSを使用して、モダンな開発環境を整えていきます。

## 1-1. 要件定義書の確認

まず、詳細度50%の要件定義書を確認し、環境構築に必要な情報を把握します。

### 要件定義書から読み取れる情報

- **フレームワーク**: Laravel
- **データベース**: MySQL
- **フロントエンド**: Tailwind CSS
- **開発環境**: Docker（Laravel Sail）

### この段階でPMに確認すべきこと

環境構築を始める前に、以下の点をPMに確認しておくと良いでしょう。

1. **PHPのバージョン**: 特に指定がなければ最新の安定版を使用
2. **MySQLのバージョン**: 特に指定がなければ8.0を使用
3. **Node.jsのバージョン**: フロントエンドビルドに必要

## 1-2. Laravelプロジェクトの作成

### Step 1: Dockerを使用してLaravelプロジェクトを作成

以下のコマンドを実行して、Laravel Sailを含むプロジェクトを作成します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer create-project --prefer-dist laravel/laravel book-review-app
```

**コードリーディング:**
- `docker run --rm`: Dockerコンテナを一時的に起動し、終了後に自動削除します。
- `-u "$(id -u):$(id -g)"`: 現在のユーザーIDとグループIDでコンテナ内のプロセスを実行します。これにより、作成されるファイルの所有者が現在のユーザーになります。
- `-v "$(pwd):/var/www/html"`: 現在のディレクトリをコンテナ内の `/var/www/html` にマウントします。
- `-w /var/www/html`: コンテナ内の作業ディレクトリを設定します。
- `laravelsail/php83-composer:latest`: PHP 8.3とComposerがインストールされたDockerイメージを使用します。
- `composer create-project --prefer-dist laravel/laravel book-review-app`: Laravelプロジェクトを `book-review-app` ディレクトリに作成します。

### Step 2: プロジェクトディレクトリに移動

```bash
cd book-review-app
```

### Step 3: Laravel Sailのインストール

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer require laravel/sail --dev
```

### Step 4: Sailの設定ファイルを生成

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    php artisan sail:install --with=mysql
```

このコマンドを実行すると、`docker-compose.yml` ファイルが生成されます。`--with=mysql` オプションにより、MySQLコンテナも一緒にセットアップされます。

### Step 5: Sailエイリアスの設定

毎回 `./vendor/bin/sail` と入力するのは面倒なので、エイリアスを設定します。

```bash
alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
```

**思考プロセス**: このエイリアスは、現在のディレクトリに `sail` ファイルがあればそれを実行し、なければ `vendor/bin/sail` を実行します。これにより、どのディレクトリからでも `sail` コマンドを使用できます。

永続化する場合は、`~/.bashrc` または `~/.zshrc` に追加してください。

### Step 6: Dockerコンテナの起動

```bash
sail up -d
```

**コードリーディング:**
- `sail up`: Docker Composeを使用してコンテナを起動します。
- `-d`: デタッチモード（バックグラウンド）で起動します。

### Step 7: 動作確認

ブラウザで `http://localhost` にアクセスし、Laravelのウェルカムページが表示されることを確認してください。

## 1-3. Tailwind CSSのセットアップ

### Step 1: npmパッケージのインストール

```bash
sail npm install
```

### Step 2: Tailwind CSSのインストール

```bash
sail npm install -D tailwindcss postcss autoprefixer
sail npx tailwindcss init -p
```

**コードリーディング:**
- `npm install -D`: 開発依存としてパッケージをインストールします。
- `tailwindcss init -p`: `tailwind.config.js` と `postcss.config.js` を生成します。

### Step 3: Tailwind設定ファイルの編集

**`tailwind.config.js`:**

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

**コードリーディング:**
- `content`: Tailwind CSSがクラス名をスキャンするファイルのパターンを指定します。Bladeテンプレート、JavaScript、Vueファイルを対象にしています。

### Step 4: CSSファイルの編集

**`resources/css/app.css`:**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

**コードリーディング:**
- `@tailwind base`: ブラウザ間の差異を吸収するリセットCSSを読み込みます。
- `@tailwind components`: Tailwindのコンポーネントクラスを読み込みます。
- `@tailwind utilities`: Tailwindのユーティリティクラス（`flex`, `mt-4` など）を読み込みます。

### Step 5: Vite設定の確認

**`vite.config.js`:**

```javascript
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

### Step 6: フロントエンドのビルド

開発中は以下のコマンドでViteの開発サーバーを起動します。

```bash
sail npm run dev
```

本番用にビルドする場合は以下のコマンドを使用します。

```bash
sail npm run build
```

## 1-4. phpMyAdminのセットアップ（任意）

データベースを視覚的に確認できるよう、phpMyAdminを追加します。

### Step 1: docker-compose.ymlの編集

**`docker-compose.yml`** に以下を追加します（`services:` の下に追加）:

```yaml
    phpmyadmin:
        image: phpmyadmin/phpmyadmin
        links:
            - mysql:mysql
        ports:
            - 8080:80
        environment:
            MYSQL_USERNAME: '${DB_USERNAME}'
            MYSQL_ROOT_PASSWORD: '${DB_PASSWORD}'
            PMA_HOST: mysql
        networks:
            - sail
```

### Step 2: コンテナの再起動

```bash
sail down
sail up -d
```

### Step 3: phpMyAdminへのアクセス

ブラウザで `http://localhost:8080` にアクセスし、以下の情報でログインします。

- **ユーザー名**: `sail`（`.env` の `DB_USERNAME`）
- **パスワード**: `password`（`.env` の `DB_PASSWORD`）

## 1-5. Bladeテンプレートの配置

PMから提供されたBladeテンプレートを配置します。

### 思考プロセス: Bladeテンプレートの読み解き方

PMからBladeテンプレートが提供された場合、以下の観点で内容を確認します。

1. **ディレクトリ構成**: どのような画面があるか
2. **変数の使用**: `$books`, `$book`, `$reviews` など、コントローラから渡される変数
3. **フォームの構成**: `name` 属性、`action` 属性、HTTPメソッド
4. **リンク先**: `route()` ヘルパーで指定されているルート名
5. **認証関連**: `@auth`, `@guest`, `Auth::user()` の使用箇所

### Bladeテンプレートの配置先

```
resources/views/
├── layouts/
│   └── app.blade.php        # 共通レイアウト
├── books/
│   ├── index.blade.php      # 書籍一覧
│   ├── show.blade.php       # 書籍詳細
│   ├── create.blade.php     # 書籍登録フォーム
│   └── edit.blade.php       # 書籍編集フォーム
├── reviews/
│   ├── create.blade.php     # レビュー投稿フォーム
│   └── edit.blade.php       # レビュー編集フォーム
├── genres/
│   ├── index.blade.php      # ジャンル一覧
│   ├── create.blade.php     # ジャンル登録フォーム
│   └── edit.blade.php       # ジャンル編集フォーム
├── favorites/
│   └── index.blade.php      # お気に入り一覧
├── ranking/
│   └── index.blade.php      # ランキング
└── auth/
    ├── login.blade.php      # ログインフォーム
    └── register.blade.php   # ユーザー登録フォーム
```

### Bladeテンプレートから読み取る情報の例

**`books/index.blade.php` の例:**

```blade
@foreach ($books as $book)
    <div>
        <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
        <p>{{ $book->author }}</p>
    </div>
@endforeach

{{ $books->links() }}
```

**読み取れる情報:**
- `$books`: コントローラから書籍のコレクションが渡される
- `$book->title`, `$book->author`: Bookモデルに `title`, `author` プロパティが必要
- `route('books.show', $book)`: `books.show` という名前のルートが必要
- `$books->links()`: ページネーションが使用されている → コントローラで `paginate()` を使用

---

これで環境構築が完了しました。次のChapterでは、DB設計とモデルの作成を行います。
