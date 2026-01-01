# Chapter 1: プロジェクトの開始 - 要件読解、環境構築、DB設計

おめでとうございます！あなたは今日からこの「書籍レビューアプリ」開発プロジェクトに参加するエンジニアです。あなたの最初のタスクは、クライアントのプロダクトマネージャー（PM）から渡された要件定義書を元に、アプリケーションを開発することです。

しかし、渡されたのは「詳細度50%」の要件定義書。ここから、現場のエンジニアがどのように考え、どのようにプロジェクトを進めていくのかを追体験していきましょう。

## 1-1. あなたの最初のタスク：50%の要件定義書を読み解く

まずは、PMから渡された資料を確認します。

> **【要件定義書（詳細度50%）より抜粋】**
> 
> **3. 機能要件**
> 
> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | 書籍管理 | 書籍一覧表示 | 登録されている書籍を一覧で表示する |
> | | 書籍詳細表示 | 特定の書籍の詳細情報を表示する |
> | | 書籍登録 | 新しい書籍を登録する |
> | | 書籍編集 | 登録した書籍の情報を編集する |
> | | 書籍削除 | 登録した書籍を削除する |
> 
> **バリデーション・認可**
> - 書籍の登録・編集・削除、レビューの投稿・編集・削除など、ユーザーの入力や操作が伴う箇所には適切なバリデーションと認可（ポリシー）を実装してください。

### 思考プロセス：この情報から何を考えるか？

現場のエンジニアは、この情報を見て次のように考えます。

1.  **何が「決まっていて」、何が「決まっていない」のか？**
    *   **決まっていること**: どんな機能が必要か（書籍のCRUDなど）、URI、テストが必要なこと。
    *   **決まっていないこと**: 
        *   「書籍」って具体的にどんな情報（カラム）を持つ？（タイトル、著者、...？）
        *   バリデーションの「適切」って具体的にどういうルール？（必須？文字数は？)
        *   登録が成功したらどこに移動する？失敗したらどうなる？
        *   「認可」って誰が何をできるの？（他人の書籍は編集できない、とか？）

2.  **「正解」を知るためのヒントはどこにあるか？**
    *   **クライアントPMへのヒアリング**: 決まっていないことは、PMに聞くのが一番確実です。
    *   **与えられたUI（Bladeテンプレート）**: 今回、UIはすでに用意されています。これは最大のヒントです。フォームの入力欄を見れば、DBに必要なカラムがわかりますし、リンクのURLを見れば、必要なルーティングがわかります。

### Bladeテンプレートの読み解き方

エンジニアは、まずUIから実装に必要な情報を逆算します。`resources/views/books/create.blade.php`（書籍登録画面）を見てみましょう。

```html
<form action="{{ route('books.store') }}" method="POST">
    @csrf
    <!-- タイトル -->
    <div>
        <label for="title">タイトル</label>
        <input type="text" name="title" id="title" value="{{ old('title') }}">
        @error('title') <div>{{ $message }}</div> @enderror
    </div>
    <!-- 著者 -->
    <div>
        <label for="author">著者</label>
        <input type="text" name="author" id="author" value="{{ old('author') }}">
        @error('author') <div>{{ $message }}</div> @enderror
    </div>
    <!-- ISBN -->
    <div>
        <label for="isbn">ISBN</label>
        <input type="text" name="isbn" id="isbn" value="{{ old('isbn') }}">
        @error('isbn') <div>{{ $message }}</div> @enderror
    </div>
    <!-- ... description, published_date, genres ... -->
</form>
```

このBladeファイルから、以下の重要な情報が読み取れます。

-   **必要なDBカラム**: `name`属性から、`books`テーブルには少なくとも`title`, `author`, `isbn`, `description`, `published_date`が必要だと推測できます。また、`genres`という名前から、ジャンル機能とのリレーションも必要だとわかります。
-   **必要なルーティング**: `action`属性の`route('books.store')`から、`books.store`という名前のルートが必要だとわかります。
-   **バリデーション**: `@error`ディレクティブがあるので、`title`や`author`などの各フィールドにバリデーションが必要だと確定できます。
-   **UX**: `value="{{ old('title') }}"`があるので、バリデーションエラー時にユーザーの入力内容を維持する必要がある、とわかります。

## 1-2. DB設計：アプリケーションの骨格を作る

要件とBladeファイルを分析した結果、私たちはこのアプリケーションに必要なデータの全体像を設計できます。これを**DB設計（ER図）**と呼びます。

-   **users**: ユーザー情報を格納。Laravel Breezeが自動で作成。
-   **books**: 書籍情報を格納。`user_id`を持ち、誰が登録したかわかるようにする。
-   **reviews**: レビュー情報を格納。`user_id`と`book_id`を持ち、誰がどの本にレビューしたかわかるようにする。
-   **genres**: ジャンル情報を格納。（「小説」「ビジネス」など）
-   **book_genre** (中間テーブル): `books`と`genres`の多対多リレーションを表現。
-   **favorites** (中間テーブル): `users`と`books`の多対多リレーション（お気に入り）を表現。
-   **review_likes** (中間テーブル): `users`と`reviews`の多対多リレーション（いいね）を表現。

これをER図で表すと以下のようになります。

```
[users] --1対多-- [books]
  |
  `--1対多-- [reviews]

[books] --多対多-- [genres] (via book_genre)
  |
  `--多対多-- [users] (via favorites)

[reviews] --多対多-- [users] (via review_likes)
```

この設計に基づいて、次のステップでマイグレーションファイルを作成していきます。

## 1-3. 環境構築とマイグレーション (完全版)

それでは、実際に手を動かして開発環境を構築し、DB設計をコードに落とし込んでいきましょう。

### 1. プロジェクトのセットアップ

```bash
# 1. リポジトリをクローン
git clone https://github.com/coachtech-material/ExampleAnswer-mockcase-BookShelf.git

# 2. プロジェクトディレクトリに移動
cd ExampleAnswer-mockcase-BookShelf

# 3. .envファイルの準備
cp .env.example .env

# 4. Laravel Sailのエイリアスを設定 (任意)
alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'

# 5. Dockerコンテナを起動
sail up -d

# 6. PHPの依存関係をインストール
sail composer install

# 7. .envのAPP_KEYを生成
sail artisan key:generate

# 8. npmの依存関係をインストール
sail npm install

# 9. フロントエンドのアセットをビルド
sail npm run dev
```

### 2. データベースのマイグレーション

次に、先ほど設計したDBをLaravelのマイグレーション機能を使って作成します。`database/migrations`ディレクトリに以下のファイルを作成・編集していきます。

**思考プロセス**: なぜこの順番で作成するのか？
Laravelのマイグレーションはファイル名のタイムスタンプ順に実行されます。外部キー制約を設定する場合、参照先のテーブル（例: `users`）が参照元のテーブル（例: `books`）より先に作成されている必要があります。そのため、依存関係のないテーブルから順に作成するのが基本です。

#### 2-1. `genres`テーブル

```bash
# genresテーブルのマイグレーションファイルを作成
sail artisan make:migration create_genres_table
```

`database/migrations/xxxx_xx_xx_xxxxxx_create_genres_table.php`を開き、`up`メソッドを編集します。

```php
// database/migrations/xxxx_xx_xx_xxxxxx_create_genres_table.php
public function up(): void
{
    Schema::create('genres', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique(); // ジャンル名はユニーク
        $table->timestamps();
    });
}
```

#### 2-2. `books`テーブル

```bash
sail artisan make:migration create_books_table
```

```php
// database/migrations/xxxx_xx_xx_xxxxxx_create_books_table.php
public function up(): void
{
    Schema::create('books', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 外部キー制約
        $table->string('title');
        $table->string('author');
        $table->string('isbn', 13)->unique();
        $table->date('published_date');
        $table->text('description')->nullable();
        $table->string('image_url')->nullable();
        $table->timestamps();
    });
}
```

#### 2-3. `reviews`テーブル

```bash
sail artisan make:migration create_reviews_table
```

```php
// database/migrations/xxxx_xx_xx_xxxxxx_create_reviews_table.php
public function up(): void
{
    Schema::create('reviews', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('book_id')->constrained()->onDelete('cascade');
        $table->unsignedTinyInteger('rating');
        $table->text('comment');
        $table->timestamps();
    });
}
```

#### 2-4. 中間テーブル (`book_genre`, `favorites`, `review_likes`)

```bash
sail artisan make:migration create_book_genre_table
sail artisan make:migration create_favorites_table
sail artisan make:migration create_review_likes_table
```

各ファイルの中身を以下のように編集します。

```php
// create_book_genre_table.php
Schema::create('book_genre', function (Blueprint $table) {
    $table->foreignId('book_id')->constrained()->onDelete('cascade');
    $table->foreignId('genre_id')->constrained()->onDelete('cascade');
    $table->primary(['book_id', 'genre_id']); // 複合主キー
});

// create_favorites_table.php
Schema::create('favorites', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('book_id')->constrained()->onDelete('cascade');
    $table->primary(['user_id', 'book_id']);
});

// create_review_likes_table.php
Schema::create('review_likes', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('review_id')->constrained()->onDelete('cascade');
    $table->primary(['user_id', 'review_id']);
});
```

### 3. マイグレーションの実行

すべてのマイグレーションファイルが準備できたら、以下のコマンドでデータベースにテーブルを作成します。

```bash
# データベースのテーブルを一度リセットし、再作成して、シーダーを実行する
sail artisan migrate:fresh --seed
```

`--seed`オプションを付けることで、`database/seeders`に定義されたテストデータも同時に投入されます。

これで、アプリケーションを動かすための土台がすべて整いました。次のChapterから、いよいよ機能実装に入っていきます。
