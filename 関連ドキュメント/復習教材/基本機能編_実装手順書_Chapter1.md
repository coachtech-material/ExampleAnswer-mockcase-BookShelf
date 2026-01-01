# 書籍レビューアプリ開発講座 - 基本機能編

## Chapter 1: プロジェクトの始まり - 環境構築と計画

### はじめに

皆さん、こんにちは！これから一緒に、書籍レビューアプリ「BookShelf」をゼロから構築していきます。この教材は、単にコードを書き写すだけでなく、「なぜこうするのか？」「現場のエンジニアはどう考えるのか？」という思考プロセスを体験することに重点を置いています。

完成形をただなぞるのではなく、一つ一つの要件をどう解釈し、どう実装計画を立て、そしてどのようにコードに落とし込んでいくのか、手取り足取り解説していきます。一緒に楽しみながら、実践的な開発スキルを身につけていきましょう！

---

### Section 1: プロジェクトの全体像を掴む - エンジニアの思考を覗いてみよう

実際の開発現場では、いきなりコードを書き始めることはありません。まず、顧客から提示された「要件定義書」を読み解き、プロジェクトの全体像を把握することから始めます。

#### 1.1. 要件定義書から機能を洗い出す

まずは「基本機能編_要件定義書_基本設計書.md」の「3. 機能要件」を見てみましょう。

> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | **認証** | 会員登録 | メールアドレス、パスワード、名前でユーザー登録できる。 |
> | | ログイン | メールアドレスとパスワードでログインできる。 |
> | | ログアウト | ログイン状態からログアウトできる。 |
> | **書籍管理** | 書籍一覧表示 | 登録されている書籍を10件ずつのページネーションで一覧表示する。 |
> | | ... | ... |

ここから、このアプリケーションに必要な「登場人物」と「できること」が見えてきます。

- **登場人物（データ）**: ユーザー、書籍、レビュー、ジャンル、お気に入り、いいね
- **できること（機能）**: 登録、ログイン、ログアウト、一覧表示、詳細表示、登録、編集、削除...

この時点で、エンジニアの頭の中では「ユーザー認証機能が必要だな」「書籍のCRUD（Create, Read, Update, Delete）が必要だな」といった具体的な機能ブロックが組み立てられていきます。

#### 1.2. ER図でデータの関係性を理解する

次に、「5.1. データベース設計 (ER図)」を見てみましょう。

```mermaid
erDiagram
    USERS ||--o{ BOOKS : "registers"
    USERS ||--o{ REVIEWS : "writes"
    BOOKS ||--o{ REVIEWS : "has"
    ...
```

この図は、データの「関係性」を示しています。

- `USERS ||--o{ BOOKS` : 1人のユーザー(USER)は、たくさんの書籍(BOOKS)を登録できる（1対多の関係）。
- `BOOKS }|--|{ BOOK_GENRE` と `GENRES }|--|{ BOOK_GENRE`: 1冊の書籍(BOOK)は、複数のジャンル(GENRE)に属することができ、1つのジャンルは複数の書籍を持つことができる（多対多の関係）。`BOOK_GENRE` はそのための「中間テーブル」です。

このER図を見ることで、「`books`テーブルには、誰が登録したかを示すために`user_id`が必要だな」「書籍とジャンルは多対多だから、`book_genre`テーブルが必要だな」といった、データベースの具体的な構造を理解することができます。

#### 1.3. 実装の順番を考える

全体像が見えたら、次は何をどの順番で実装していくかを考えます。闇雲に作ると、後で手戻りが多く発生してしまいます。基本的には、**他の機能から依存されている機能**から作るのがセオリーです。

1.  **土台作り（環境構築）**: まずはアプリケーションが動くための土台を固めます。これがこのChapterの内容です。
2.  **認証機能**: 多くの機能は「ログインしているユーザー」が使う前提なので、最初に認証機能を実装します。
3.  **マスターデータ管理**: 他のデータから参照される「ジャンル」のようなマスターデータを先に作ります。
4.  **メイン機能（書籍管理）**: アプリケーションの核となる書籍のCRUD機能を実装します。
5.  **サブ機能（レビュー、お気に入りなど）**: メイン機能に紐づく、レビューやお気に入りなどの機能を実装していきます。

このように、大きな流れを最初に決めておくことで、手戻りなく効率的に開発を進めることができるのです。

---

### Section 2: 開発環境の構築 - アプリが動く土台を作る

それでは、実際に手を動かして、アプリケーションの土台となる開発環境を構築していきましょう。

#### 2.1. なぜDockerを使うのか？

「私のPCでは動いたのに、他の人のPCでは動かない...」というのは、開発現場でよくある問題です。これは、OSやインストールされているソフトウェアのバージョンが人によって違うために起こります。

**Docker**は、こうした環境の違いを吸収するための技術です。「コンテナ」と呼ばれる隔離された環境の中に、アプリケーションが動くのに必要なもの（OS、ライブラリ、ミドルウェアなど）をすべてパッケージングしてしまいます。これにより、「誰のPCでも」「本番環境でも」全く同じようにアプリケーションを動かすことができるのです。

今回は、このDockerをLaravelで簡単に使えるようにした**Laravel Sail**というツールを使って環境を構築します。

#### 2.2. Laravelプロジェクトの作成

まず、プロジェクトを置きたいディレクトリに移動して、以下のコマンドを実行してください。これが、私たちの書籍レビューアプリの元となるLaravelプロジェクトを作成するコマンドです。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 book-review-app
```

長くて難しそうに見えますが、一つ一つ分解すれば怖くありません。

<details>
<summary>コマンド1行ずつ解説</summary>

- `docker run --rm \`: Dockerコンテナを実行するコマンドです。`--rm`は、コマンドが終了したらコンテナを自動的に削除するオプションです。一時的なコマンド実行に便利です。
- `-u "$(id -u):$(id -g)" \`: コンテナ内でのコマンド実行ユーザーを、現在操作しているユーザーのIDとグループIDに合わせる設定です。これをしないと、コンテナが作ったファイルの所有者が`root`になってしまい、後で編集できなくなる問題を回避できます。
- `-v "$(pwd):/var/www/html" \`: **ボリュームマウント**の設定です。`:`の左側（`$(pwd)`=現在のディレクトリ）と右側（コンテナ内の`/var/www/html`ディレクトリ）を同期させます。これにより、ホストPC（あなたのPC）でコードを編集すると、即座にコンテナ内に反映されます。
- `-w /var/www/html \`: コンテナ内での作業ディレクトリを指定します。今回はマウント先の`/var/www/html`です。
- `-e COMPOSER_CACHE_DIR=/tmp/composer_cache \`: Composer（PHPのパッケージ管理ツール）のキャッシュディレクトリをコンテナ内の一時的な場所に変更しています。パフォーマンス向上のためのおまじないです。
- `laravelsail/php82-composer:latest \`: 実行するコンテナのイメージ（設計図のようなもの）を指定しています。「PHP 8.2とComposerがインストール済みのLaravel Sail公式イメージ」という意味です。
- `composer create-project laravel/laravel:^10.0 book-review-app`: 実際にプロジェクトを作成している部分です。Composerを使い、「`laravel/laravel`というパッケージ（Laravel本体）のバージョン10系を使って、`book-review-app`という名前のディレクトリにプロジェクトを作成してください」という命令です。

</details>

コマンドが成功すると、`book-review-app`というディレクトリが作成されているはずです。これが私たちの戦場です！

---

### Section 3: Laravel Sailのセットアップ - Dockerを飼いならす

プロジェクトができたので、次はこのプロジェクト専用のDocker環境（Laravel Sail）をセットアップします。

まず、作成されたプロジェクトディレクトリに移動してください。

```bash
cd book-review-app
```

#### 3.1. Sailのインストール

次に、Composerを使ってLaravel Sailをプロジェクトにインストールします。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer require laravel/sail --dev
```

> **【なぜ`--dev`？】**
> `--dev`は「開発時(development)にのみ必要なパッケージ」という意味です。Laravel Sailは開発環境を便利にするためのツールであり、アプリケーションが本番環境で動くために必須ではありません。そのため、`--dev`オプションを付けて、開発用の依存関係としてインストールするのが一般的です。

#### 3.2. Sailの設定ファイルを作成

次に、Artisanコマンドを使ってSailの設定ファイルを生成します。ArtisanはLaravelに付属する便利なコマンドラインツールで、開発の様々な場面で活躍します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    php artisan sail:install --with=mysql
```

`--with=mysql`オプションで、データベースとしてMySQLを使用することを指定しています。このコマンドを実行すると、プロジェクトのルートに`compose.yaml`というファイルが作成されます。これがDockerの構成を定義する設計図になります。

---

### Section 4: フロントエンドの準備 - 見た目を作る道具を揃える

Laravelはバックエンドのフレームワークですが、最近のWeb開発ではフロントエンド（見た目や動き）の技術も重要です。ここでは、モダンなフロントエンド開発ツールである**Vite**と**Tailwind CSS**をセットアップします。

#### 4.1. 必要なツールのインストール

ここからは、`docker run`の長いコマンドの代わりに、プロジェクト内にインストールされた`sail`コマンドが使えます。`sail`コマンドは、内部的にDockerを良しなに扱ってくれる便利なラッパーです。

1.  **NPM依存パッケージのインストール**
    Node.jsのパッケージ管理ツール`npm`を使って、必要なライブラリをインストールします。

    ```bash
    ./vendor/bin/sail npm install
    ```

2.  **Tailwind CSSのインストール**
    次に、CSSフレームワークであるTailwind CSSをインストールします。

    ```bash
    ./vendor/bin/sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer
    ```

3.  **設定ファイルの生成**
    Tailwind CSS用の設定ファイルを生成します。

    ```bash
    ./vendor/bin/sail npx tailwindcss init -p
    ```
    これにより、`tailwind.config.js`と`postcss.config.js`が作成されます。

#### 4.2. 設定ファイルの編集

1.  **`tailwind.config.js`の編集**
    Tailwind CSSに、どのファイルのデザインを監視してほしいかを教えます。`content`プロパティを以下のように編集してください。

    ```javascript
    /** @type {import(\'tailwindcss\').Config} */
    export default {
      content: [
        "./resources/**/*.blade.php", // Bladeテンプレート
        "./resources/**/*.js",      // JavaScriptファイル
        "./resources/**/*.vue",      // Vueコンポーネント（今回は使わない）
      ],
      theme: {
        extend: {},
      },
      plugins: [],
    }
    ```

2.  **`resources/css/app.css`の編集**
    このファイルにTailwind CSSの基本的なスタイルを読み込むための記述をします。ファイルの中身を以下の3行に置き換えてください。

    ```css
    @tailwind base;
    @tailwind components;
    @tailwind utilities;
    ```

#### 4.3. 開発サーバーの起動

最後に、Viteの開発サーバーを起動します。これにより、CSSやJavaScriptの変更が即座にブラウザに反映されるようになります。

```bash
./vendor/bin/sail npm run dev
```

**【重要】** このコマンドを実行したターミナルは、開発中は**常に起動したまま**にしておいてください。新しいターミナルを開いて、以降のコマンドを実行しましょう。

---

### Section 5: 最終仕上げ - アプリケーションの起動

いよいよ最終段階です。データベースの設定を行い、アプリケーションを起動します。

#### 5.1. phpMyAdminの追加

データベースの中身をブラウザで簡単に確認できるツール「phpMyAdmin」を追加しましょう。`compose.yaml`ファイルを開き、`services:`セクションの`mysql:`の後に、以下の設定を追記してください。

```yaml
# compose.yaml

services:
    # ... (laravel.test, mysqlなどの記述)
    mysql:
        # ... (mysqlの既存の設定)
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

#### 5.2. Sailの起動とエイリアス設定

1.  **Sailの起動**
    設定を変更したので、Sailを起動（または再起動）します。`-d`オプションは「デタッチモード」を意味し、コンテナをバックグラウンドで起動します。

    ```bash
    ./vendor/bin/sail up -d
    ```

2.  **エイリアス設定（推奨）**
    毎回`./vendor/bin/sail`と入力するのは大変なので、「`sail`」だけで実行できるようにエイリアス（別名）を設定しましょう。これは任意ですが、設定しておくと非常に便利です。

    ```bash
    # zshをお使いの場合
    echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc
    
    # bashをお使いの場合
    # echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.bashrc
    
    # シェルを再起動して設定を反映
    exec $SHELL
    ```
    これで、今後は`sail artisan ...`のように短いコマンドで実行できます。

#### 5.3. アプリケーションキーの生成

最後に、Laravelアプリケーションの暗号化などに使われる重要な「アプリケーションキー」を生成します。

```bash
sail artisan key:generate
```

このコマンドを実行すると、`.env`ファイルに`APP_KEY`が設定されます。

### 確認してみよう！

お疲れ様でした！これで全ての環境構築が完了です。

- **アプリケーション**: ブラウザで `http://localhost` にアクセスしてみてください。Laravelのウェルカムページが表示されれば成功です。
- **データベース**: `http://localhost:8080` にアクセスしてみてください。phpMyAdminのログイン画面が表示されます。`.env`ファイルに記載されているユーザー名（`sail`）とパスワード（`password`）でログインできます。

これで、アプリケーションを開発していくための頑丈な土台が完成しました。次のChapterから、いよいよ機能実装に入っていきます！
