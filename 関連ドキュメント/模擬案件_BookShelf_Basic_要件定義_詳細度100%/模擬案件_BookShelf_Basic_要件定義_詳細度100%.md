# 模擬案件\_BookShelf\_Basic\_要件定義\_詳細度100%

> 本ドキュメントは、模擬案件「BookShelf（書籍レビューアプリ）」の要件定義書です。
> 確認テストのスプレッドシートフォーマット（12シート構成）に準拠し、API仕様書シートを除いた11シート構成で作成しています。
> 各シートの内容はスプレッドシートへのコピペ転記を想定した構造です。
> **対象ブランチ: basic（基本機能のみ）**

---

## シート1: ターム内容

本模擬案件における全体概要です。
赤字の項目については、入力が必要な項目になります。担当コーチと確認しながら進めてください。
こちらは模擬案件になりますので、コーチの方は提出時にお間違えの無いように提出してください。

| 項目 | 内容 |
|---|---|
| タイトル | 模擬案件\_書籍レビューアプリ BookShelf |
| 目的 | 模擬案件を通して、実務を想定したWebアプリケーション開発を経験すること |
| 期間 | コーチと相談の上決めてください。 |
| やること | バックエンド開発（DB設計、認証/認可、CRUD実装、テスト作成）※Bladeテンプレートは完成品として提供されるため、フロントエンド実装は不要です |
| 作成物 | BookShelf 書籍レビューアプリ |
| ルール | （下記参照） |
| 提出方法 | LMSのテスト一覧画面から提出してください。 |
| 注意点 | （下記参照） |

**作成物 システム概要:**

本システムは、書籍レビューアプリケーション「BookShelf」です。
ユーザーは書籍を登録・閲覧し、レビューの投稿やお気に入り登録ができます。
ジャンルによる分類やレビューへのいいね機能、平均評価に基づくランキング機能も備えています。

**ルール:**

原則質問チャットサポートの利用は禁止です。

留意点：
1. 教材やブラウザで検索した記事を参考にすることは可能です。
2. GitHubでのエラーが解決できず、模擬案件の提出が困難な場合はコーチに相談してください。
3. 質問の利用数はCOACHTECH Proの合格基準とさせていただきますので、できるだけ質問対応は利用しないようにしましょう！

**注意点:**

教材学習の集大成で、少し難易度が高いです。
焦らず、一歩一歩開発を進めていきましょう。
また基本機能の開発が終了次第、応用機能の開発に着手してください。

---

## シート2: 開発プロセス

こちらは環境構築、コード品質、テスト要件の詳細資料です。
採点において重要な要件やアプリケーション全体を跨ぐ要件が記載していますので、十分に確認してください。

| 項目 | カテゴリ | 詳細仕様 | 留意点 |
|---|---|---|---|
| 仕様理解 | アーキテクチャ | 【重要】本プロジェクトのアーキテクチャについて（Traditional Web）本課題では、Traditional Web（Blade + セッション認証）アーキテクチャを採用します。Bladeテンプレートを使用し、セッション認証（Cookie）で動作する従来のWebアプリケーション機能を実装します。routes/web.php と通常のコントローラーを使用します。※API実装は不要です。 | |
| 環境 | 技術スタック | OS（Dockerが動作する任意のOS）: - / PHP: 8.2 / Laravel: 10.x / DB: MySQL 8.0 / フロントエンド: Vite, Tailwind CSS ^3.4.0 / 開発ツール: Docker, Laravel Sail, phpMyAdmin | |
| | 構成管理 | ・DockerとDocker Composeを使用して環境をコンテナ化する ・COACHTECH側が提示した環境構築手順を遵守する | |
| | 初期設定手順 | 「環境構築手順」シートを参考の上初期設定を行うこと | |
| README.md 記載必須項目 | プロジェクト名 | 「BookShelf 書籍レビューアプリ」など、内容がわかるタイトル | READMEが不十分で、採点者が環境構築、機能確認ができない場合は再提出、または大きく減点される可能性があるので、十分に気をつけること |
| | 概要 | プロジェクトの目的と、実装した機能の概要説明 | |
| | ER図 | 自分で設計したER図の画像またはMermaid記法でのテキスト | |
| | 環境構築手順 | 上記「初期設定手順」を参考に、誰でも環境構築ができるように詳細に記載 | |
| | 使用技術 | Laravel 10, MySQL 8.0, Dockerなど、使用した技術スタック一覧 | |
| | 作成者 | 自分の名前 | |
| コード品質担保のための指示 | 命名規則 | ・Laravelの標準命名規則（PSR-12準拠）に従うこと - 変数/メソッド: `camelCase` - クラス: `PascalCase` - DBテーブル: `snake_case`（複数形） - DBカラム: `snake_case`（単数形） - モデル名：アッパーキャメル - コントローラー名：アッパーキャメル - フォームリクエスト名：アッパーキャメル - マイグレーションファイル名：スネークケース - シーディングファイル名：アッパーキャメル | |
| | コードフォーマット | ・Laravel Pintを使用してコードを自動整形すること ・コミット前に `vendor/bin/pint` を実行し、整形されたコードをコミットする | |
| | Eloquent ORM | ・DB操作にはEloquentを最大限活用し、クエリビルダや生SQLは原則使用しない ・N+1問題を避けるため、`with()`メソッドによるEager Loadingを適切に使用する | |
| | コントローラーの責務 | ・コントローラーはリクエストの受付とレスポンスの返却に専念させる ・複雑なビジネスロジックはモデルやサービスクラス（任意）に記述する | |
| | FormRequest | ・バリデーションロジックは必ずFormRequestクラスに分離する | |
| | Policy | ・認可処理（リソースの所有者チェック等）は必ずPolicyクラスで実装する ・コントローラーで `$this->authorize()` を使用してPolicyを適用する | |
| | 設定のハードコーディング禁止 | ・DB接続情報やAPIキーなどの設定値は、必ず`.env`ファイルで管理する ・コード内に直接設定値を書き込まない | |
| | Git運用 | ・コミットメッセージは「何をしたか」が明確にわかるように記述する（例: `feat: 書籍一覧表示を実装`） ・機能ごとにブランチを作成し、`main`ブランチにマージする | |
| 要件遵守 | 開発言語 | 開発言語はCOACHTECの教材内の言語を使用すること | |
| | 各種設計 | 開発については、案件シート内の設計に沿って作成すること | - ルーティングは「画面設計」の画面定義に従って作成すること - システムは「機能要件」の要件に従って作成すること |
| | 機能要件の使用技術を遵守しているか | 認証やバリデーションなど、指定した技術以外で実装されていないか | |

---

## シート3: 環境構築手順

こちらは初期プロジェクトのセッティングにおいて必要な環境構築手順を記載したものです。
詳細な内容は、coachtech-prepared-blade-list/Preparedblade-mockcase-BookShelf リポジトリの環境構築.mdを参照して下さい。
採点時の環境はこちらで行いますので、違う手順によって環境構築された場合は採点を致しかねます。ご注意してください。

| 手順 | カテゴリ |
|---|---|
| 1. Laravelプロジェクトの作成 (Laravel 10.x) | 注意: `curl -s "https://laravel.build/..."` は最新版のLaravelをインストールするため、今回は使用しません。以下のDockerコマンドを実行して、Laravel 10.xを明示的に指定してプロジェクトを作成します。`docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest composer create-project laravel/laravel:^10.0 bookshelf-app` |
| 2. Laravel Sailのインストール | プロジェクト作成後、bookshelf-app ディレクトリに移動し、Laravel Sailをインストールします。`cd bookshelf-app` → `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest composer require laravel/sail --dev` → `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest php artisan sail:install --with=mysql` ※M1/M2/M3 Mac（Apple Silicon）をお使いの方: `sail up -d`実行時に `no matching manifest for linux/arm64/v8` エラーが発生した場合、compose.yaml の mysql サービスに `platform: 'linux/amd64'` を追加してください。 |
| 3. .env ファイルの設定 | .env ファイルを開き、データベース接続情報が以下と一致していることを確認します。DB_CONNECTION=mysql / DB_HOST=mysql / DB_PORT=3306 / DB_DATABASE=laravel / DB_USERNAME=sail / DB_PASSWORD=password 重要: DB_HOST は localhost や 127.0.0.1 ではなく、Dockerコンテナ名である mysql を指定します。 |
| 4. フロントエンドのセットアップ (Vite & Tailwind CSS) | 本プロジェクトでは、フロントエンドのスタイリングにTailwind CSSを使用します。1. NPM依存パッケージのインストール: `sail npm install`（※Sailコンテナが起動していることを確認。起動していない場合は `./vendor/bin/sail up -d` を実行） 2. Alpinejs のインストール: `sail npm install alpinejs` 3. Tailwind CSSのインストール: `sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer` 4. 設定ファイルの生成: `sail npx tailwindcss init -p` 5. tailwind.config.js のテンプレートパス設定（content に `"./resources/**/*.blade.php"`, `"./resources/**/*.js"`, `"./resources/**/*.vue"` を指定） 6. 本プロジェクトのresourcesファイルをcoachtech-prepared-blade-list/Preparedblade-mockcase-BookShelf リポジトリのresourcesファイルと入れ替え 7. Vite開発サーバーの起動: `sail npm run dev`（開発中は常に実行状態にしておく） |
| 5. phpMyAdminの追加 | compose.yaml を開き、mysql サービスの後に以下の設定を追加: `phpmyadmin: image: 'phpmyadmin:latest' ports: - '${FORWARD_PHPMYADMIN_PORT:-8080}:80' environment: PMA_HOST: mysql PMA_USER: '${DB_USERNAME}' PMA_PASSWORD: '${DB_PASSWORD}' networks: - sail depends_on: - mysql` |
| 6. Sailの起動とエイリアス設定 | `./vendor/bin/sail up -d` でSailをバックグラウンド起動。エイリアス設定: `echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc` → `exec $SHELL` |
| 7. アプリケーションキーの生成 | `sail artisan key:generate` |
| 8. Laravel Fortifyのインストール | 認証機能に Laravel Fortify を使用します。`sail composer require laravel/fortify` → `sail artisan fortify:install` → `sail artisan migrate` FortifyServiceProvider にログイン/登録ビューを設定してください。 |

---

## シート4: 画面設計

各画面の仕様とUIデザイン要件の詳細資料です。
アプリケーションの実装に入る前に確認し、これらの要件を満たすように実装しましょう。

### 画面定義

| 画面ID | 画面名称 | HTTPメソッド | パス | 備考 |
|---|---|---|---|---|
| PG01 | 書籍一覧（トップ） | GET | / または /books | Blade提供済み。公開ページ。全書籍をページネーション（10件/ページ）で最新順に表示。ジャンル情報をEager Loading。routes/web.php / app/Http/Controllers/BookController@index / resources/views/books/index.blade.php |
| PG02 | 書籍詳細 | GET | /books/{book} | Blade提供済み。公開ページ。書籍詳細とレビュー・ジャンル・お気に入り・いいね機能を表示。routes/web.php / app/Http/Controllers/BookController@show / resources/views/books/show.blade.php |
| PG03 | 書籍登録 | GET | /books/create | Blade提供済み。認証必須。全ジャンル一覧をセレクトボックスで表示。routes/web.php / app/Http/Controllers/BookController@create / resources/views/books/create.blade.php |
| PG04 | 書籍編集 | GET | /books/{book}/edit | Blade提供済み。認証+認可（BookPolicy@update）必須。作成者のみ閲覧可。routes/web.php / app/Http/Controllers/BookController@edit / resources/views/books/edit.blade.php |
| PG05 | ジャンル一覧 | GET | /genres | Blade提供済み。認証必須。各ジャンルの書籍数を表示。routes/web.php / app/Http/Controllers/GenreController@index / resources/views/genres/index.blade.php |
| PG06 | ジャンル詳細 | GET | /genres/{genre} | Blade提供済み。公開ページ。ジャンルに紐づく書籍をページネーション（10件/ページ）で表示。routes/web.php / app/Http/Controllers/GenreController@show / resources/views/genres/show.blade.php |
| PG07 | ジャンル登録 | GET | /genres/create | Blade提供済み。認証必須。routes/web.php / app/Http/Controllers/GenreController@create / resources/views/genres/create.blade.php |
| PG08 | ジャンル編集 | GET | /genres/{genre}/edit | Blade提供済み。認証必須。routes/web.php / app/Http/Controllers/GenreController@edit / resources/views/genres/edit.blade.php |
| PG09 | レビュー編集 | GET | /reviews/{review}/edit | Blade提供済み。認証+認可（ReviewPolicy@update）必須。投稿者のみ閲覧可。routes/web.php / app/Http/Controllers/ReviewController@edit / resources/views/reviews/edit.blade.php |
| PG10 | お気に入り一覧 | GET | /favorites | Blade提供済み。認証必須。ユーザーのお気に入り書籍をページネーション（10件/ページ）で表示。routes/web.php / app/Http/Controllers/FavoriteController@index / resources/views/favorites/index.blade.php |
| PG11 | ランキング | GET | /ranking | Blade提供済み。公開ページ。レビュー平均評価TOP10を表示。routes/web.php / app/Http/Controllers/RankingController@index / resources/views/ranking/index.blade.php |
| PG12 | ログイン | GET | /login | Blade提供済み。Fortifyが提供するログインビュー。メール・パスワード入力と送信。resources/views/auth/login.blade.php |
| PG13 | 会員登録 | GET | /register | Blade提供済み。Fortifyが提供する登録ビュー。氏名・メール・パスワードを登録。resources/views/auth/register.blade.php |

### Bladeファイルの提供

| 手順 | 詳細 |
|---|---|
| 1 | Bladeファイルはcoachtech-prepared-blade-list/Preparedblade-mockcase-BookShelf リポジトリに内包されている。 |
| 2 | 対象リポジトリは基本機能と応用機能がブランチごとに分かれているので、環境構築手順シートを参照しつつプロジェクトに移入する。 |
| 3 | 環境構築手順シートを参照しつつフロントエンドの環境構築を行う。 |

---

## シート5: デザインUI

各画面のUI画像を添付してあります。
模擬案件の実装に入る前に確認し、こちらを参考にレイアウトを完成させましょう。

### 書籍管理

| 書籍一覧画面 | 書籍詳細画面 | 書籍登録画面 |
|---|---|---|
| （画像を添付） | （画像を添付） | （画像を添付） |

| 書籍編集画面 | | |
|---|---|---|
| （画像を添付） | | |

### ジャンル管理

| ジャンル一覧画面 | ジャンル詳細画面 | ジャンル登録画面 |
|---|---|---|
| （画像を添付） | （画像を添付） | （画像を添付） |

| ジャンル編集画面 | | |
|---|---|---|
| （画像を添付） | | |

### レビュー・お気に入り・ランキング

| レビュー編集画面 | お気に入り一覧画面 | ランキング画面 |
|---|---|---|
| （画像を添付） | （画像を添付） | （画像を添付） |

### 認証

| 会員登録画面 | ログイン画面 |
|---|---|
| （画像を添付） | （画像を添付） |

---

## シート6: 機能要件一覧

本模擬案件で実装する機能の詳細仕様です。
Bladeは提供済みのため、バックエンドの実装に集中してください。

| No. | EPIC名 | 機能ID | 機能名 | 概要（ビジネスロジック） | 入力条件/制約 | 期待結果 | バックエンド挙動 | 関連ソース/責務 | 基本/応用 |
|---|---|---|---|---|---|---|---|---|---|
| 1 | 認証機能 | AF01 | ユーザー登録 | ユーザーがFortifyの登録画面から新規アカウントを作成できる。 | URL: GET/POST /register バリデーション: name(required/string/max:255), email(required/email/max:255/unique:users), password(Fortify標準 8文字以上・確認用一致) | 登録成功後 / にリダイレクト。 | Fortify CreateNewUser で入力検証・ハッシュ化保存。ユーザー作成後 config/fortify.php の home に従ってリダイレクト。 | app/Providers/FortifyServiceProvider, app/Actions/Fortify/CreateNewUser, resources/views/auth/register.blade.php | 基本 |
| | | AF02 | ログイン/ログアウト | ユーザーがログインし、保護された機能を利用し、ログアウトできる。 | URL: GET/POST /login, POST /logout | ログイン成功で / にリダイレクト、ログアウトでセッション破棄しログイン画面へ。 | Fortifyログインビューを提供。auth ミドルウェアで保護されたルートへのアクセス制御。/logout POST でセッション破棄。 | app/Providers/FortifyServiceProvider, resources/views/auth/login.blade.php, routes/web.php | 基本 |
| 2 | 書籍管理 | BK01 | 書籍一覧表示 | 全書籍をページネーション付きで一覧表示する。 | URL: GET / または GET /books 認証: 不要 | 書籍一覧が10件/ページでページネーション表示される。各書籍にジャンルが表示される。 | Book::with("genres")->latest()->paginate(10) で書籍を取得し books.index ビューを返す。N+1対策としてgenresをEager Loading。 | routes/web.php, app/Http/Controllers/BookController@index, resources/views/books/index.blade.php | 基本 |
| | | BK02 | 書籍詳細表示 | 書籍の詳細情報をレビュー・ジャンルと共に表示する。 | URL: GET /books/{book} 認証: 不要 | 書籍詳細画面にタイトル・著者・ISBN・出版日・説明・画像・ジャンル・レビュー一覧が表示される。お気に入り・いいね操作が可能（認証時）。 | ルートモデルバインディングで Book を取得。$book->load(["reviews.user", "genres"]) でリレーションを読み込み。books.show ビューを返す。 | routes/web.php, app/Http/Controllers/BookController@show, resources/views/books/show.blade.php | 基本 |
| | | BK03 | 書籍登録 | 認証ユーザーが新しい書籍を登録し、ジャンルを紐付けられる。 | URL: GET /books/create（フォーム）, POST /books（登録） 認証: 必須 バリデーション: StoreBookRequest | 登録成功時、書籍詳細画面にリダイレクトし「書籍を登録しました。」と表示。バリデーションエラー時はフォームに戻りエラー表示。 | StoreBookRequest でバリデーション。$request->user()->books()->create($bookData) で書籍作成（user_id自動設定）。$book->genres()->attach($genres) でジャンル紐付け。 | routes/web.php, app/Http/Controllers/BookController@create,store, app/Http/Requests/StoreBookRequest, resources/views/books/create.blade.php | 基本 |
| | | BK04 | 書籍編集 | 書籍の作成者のみが書籍情報を編集できる。 | URL: GET /books/{book}/edit（フォーム）, PUT /books/{book}（更新） 認証: 必須 認可: BookPolicy@update（作成者のみ） バリデーション: UpdateBookRequest | 更新成功時、書籍詳細画面にリダイレクトし「書籍情報を更新しました。」と表示。他ユーザーは403エラー。 | $this->authorize("update", $book) で認可チェック。$book->update($request->validated()) で更新。$book->genres()->sync($request->genres) でジャンル同期。 | routes/web.php, app/Http/Controllers/BookController@edit,update, app/Http/Requests/UpdateBookRequest, app/Policies/BookPolicy, resources/views/books/edit.blade.php | 基本 |
| | | BK05 | 書籍削除 | 書籍の作成者のみが書籍を削除できる。 | URL: DELETE /books/{book} 認証: 必須 認可: BookPolicy@delete（作成者のみ） | 削除成功時、書籍一覧にリダイレクトし「書籍を削除しました。」と表示。関連レビュー・お気に入り・ジャンル紐付けはカスケード削除。他ユーザーは403エラー。 | $this->authorize("delete", $book) で認可チェック。$book->delete() で削除。外部キーのON DELETE CASCADEにより関連データも削除。 | routes/web.php, app/Http/Controllers/BookController@destroy, app/Policies/BookPolicy | 基本 |
| 3 | レビュー管理 | RV01 | レビュー投稿 | 認証ユーザーが書籍にレビュー（評価+コメント）を投稿できる。 | URL: POST /books/{book}/reviews 認証: 必須 バリデーション: StoreReviewRequest | 投稿成功時、書籍詳細画面にリダイレクトし「レビューを投稿しました。」と表示。ゲストはログイン画面にリダイレクト。 | StoreReviewRequest でバリデーション。$book->reviews()->create(['user_id' => Auth::id(), 'rating' => ..., 'comment' => ...]) でレビュー作成。 | routes/web.php, app/Http/Controllers/ReviewController@store, app/Http/Requests/StoreReviewRequest | 基本 |
| | | RV02 | レビュー編集 | レビューの投稿者のみがレビューを編集できる。 | URL: GET /reviews/{review}/edit（フォーム）, PUT /reviews/{review}（更新） 認証: 必須 認可: ReviewPolicy@update（投稿者のみ） バリデーション: UpdateReviewRequest | 更新成功時、書籍詳細画面にリダイレクトし「レビューを更新しました。」と表示。他ユーザーは403エラー。 | $this->authorize('update', $review) で認可チェック。$review->update(['rating' => ..., 'comment' => ...]) で更新。 | routes/web.php, app/Http/Controllers/ReviewController@edit,update, app/Http/Requests/UpdateReviewRequest, app/Policies/ReviewPolicy, resources/views/reviews/edit.blade.php | 基本 |
| | | RV03 | レビュー削除 | レビューの投稿者のみがレビューを削除できる。 | URL: DELETE /reviews/{review} 認証: 必須 認可: ReviewPolicy@delete（投稿者のみ） | 削除成功時、書籍詳細画面にリダイレクトし「レビューを削除しました。」と表示。関連いいねはカスケード削除。他ユーザーは403エラー。 | $this->authorize('delete', $review) で認可チェック。$review->delete() で削除。外部キーのON DELETE CASCADEにより関連いいねも削除。 | routes/web.php, app/Http/Controllers/ReviewController@destroy, app/Policies/ReviewPolicy | 基本 |
| 4 | お気に入り | FV01 | お気に入り登録/解除（トグル） | 認証ユーザーが書籍をお気に入りに追加/解除できる。同一操作でトグル動作する。 | URL: POST /books/{book}/favorites 認証: 必須 | お気に入り追加時はfavoritesテーブルにレコード追加、解除時は削除。操作後、元のページにリダイレクト。ゲストはログイン画面にリダイレクト。 | Auth::user()->favoriteBooks()->toggle($book->id) でトグル処理。return back() で元のページに戻る。 | routes/web.php, app/Http/Controllers/FavoriteController@toggle | 基本 |
| | | FV02 | お気に入り一覧表示 | 認証ユーザーの全お気に入り書籍を一覧表示する。 | URL: GET /favorites 認証: 必須 | ユーザーのお気に入り書籍がページネーション（10件/ページ）で表示される。 | Auth::user()->favoriteBooks()->paginate(10) でお気に入り書籍を取得。favorites.index ビューを返す。 | routes/web.php, app/Http/Controllers/FavoriteController@index, resources/views/favorites/index.blade.php | 基本 |
| 5 | いいね | LK01 | レビューいいね登録/解除（トグル） | 認証ユーザーがレビューにいいねを追加/解除できる。同一操作でトグル動作する。 | URL: POST /reviews/{review}/like 認証: 必須 | いいね追加時はreview_likesテーブルにレコード追加、解除時は削除。操作後、元のページにリダイレクト。ゲストはログイン画面にリダイレクト。 | Auth::user()->likedReviews()->toggle($review->id) でトグル処理。return back() で元のページに戻る。 | routes/web.php, app/Http/Controllers/ReviewLikeController@toggle | 基本 |
| 6 | ジャンル管理 | GN01 | ジャンル一覧表示 | 認証ユーザーが全ジャンルを書籍数付きで一覧表示する。 | URL: GET /genres 認証: 必須 | ジャンル一覧が各ジャンルの書籍数と共に表示される。 | Genre::withCount('books')->get() でジャンルを書籍数付きで取得。genres.index ビューを返す。 | routes/web.php, app/Http/Controllers/GenreController@index, resources/views/genres/index.blade.php | 基本 |
| | | GN02 | ジャンル詳細（ジャンル内書籍一覧） | ジャンルに紐づく書籍をページネーション付きで表示する。 | URL: GET /genres/{genre} 認証: 不要 | ジャンル名と紐づく書籍がページネーション（10件/ページ）で表示される。 | ルートモデルバインディングで Genre を取得。$genre->books()->with('genres')->paginate(10) で書籍を取得。genres.show ビューを返す。 | routes/web.php, app/Http/Controllers/GenreController@show, resources/views/genres/show.blade.php | 基本 |
| | | GN03 | ジャンル登録 | 認証ユーザーが新しいジャンルを登録できる。 | URL: GET /genres/create（フォーム）, POST /genres（登録） 認証: 必須 バリデーション: StoreGenreRequest | 登録成功時、ジャンル一覧にリダイレクトし「ジャンルを作成しました。」と表示。名前重複時はバリデーションエラー。 | StoreGenreRequest でバリデーション。Genre::create($request->validated()) でジャンル作成。 | routes/web.php, app/Http/Controllers/GenreController@create,store, app/Http/Requests/StoreGenreRequest, resources/views/genres/create.blade.php | 基本 |
| | | GN04 | ジャンル編集 | 認証ユーザーがジャンル名を編集できる。 | URL: GET /genres/{genre}/edit（フォーム）, PUT /genres/{genre}（更新） 認証: 必須 バリデーション: UpdateGenreRequest | 更新成功時、ジャンル一覧にリダイレクトし「ジャンルを更新しました。」と表示。名前重複時はバリデーションエラー（自身を除外）。 | UpdateGenreRequest でバリデーション（unique制約で自身を除外）。$genre->update($request->validated()) で更新。 | routes/web.php, app/Http/Controllers/GenreController@edit,update, app/Http/Requests/UpdateGenreRequest, resources/views/genres/edit.blade.php | 基本 |
| | | GN05 | ジャンル削除 | 認証ユーザーがジャンルを削除できる。ただし書籍が紐付いている場合は削除不可。 | URL: DELETE /genres/{genre} 認証: 必須 制約: 書籍が紐付いている場合は削除を拒否 | 書籍紐付きなしの場合、ジャンル一覧にリダイレクトし「ジャンルを削除しました。」と表示。書籍紐付きありの場合、「このジャンルには書籍が紐付いているため削除できません。」とエラー表示。 | $genre->books()->count() > 0 のチェック。紐付きあり: redirect with error。紐付きなし: $genre->delete() 後 redirect with success。 | routes/web.php, app/Http/Controllers/GenreController@destroy | 基本 |
| 7 | ランキング | RK01 | 書籍ランキング表示 | レビュー平均評価のTOP10書籍をランキング表示する。 | URL: GET /ranking 認証: 不要 | レビューが存在する書籍が平均評価の降順でTOP10表示される。レビューがない書籍は表示されない。 | Book::select('books.*', DB::raw('AVG(reviews.rating) as average_rating'))->join('reviews', 'books.id', '=', 'reviews.book_id')->groupBy('books.id')->orderByDesc('average_rating')->take(10)->get() でランキング取得。 | routes/web.php, app/Http/Controllers/RankingController@index, resources/views/ranking/index.blade.php | 基本 |

---

## シート7: バリデーションルール

本模擬案件で実装する各種バリデーションの詳細仕様です。

| 対象機能 | 入力項目 | ルール |
|---|---|---|
| 書籍登録 (StoreBookRequest) | title | required / string / max:255 |
| | author | required / string / max:255 |
| | isbn | required / string / size:13 / unique:books,isbn |
| | published_date | required / date |
| | description | nullable / string |
| | image_url | nullable / url |
| | genres | required / array |
| | genres.* | exists:genres,id |
| 書籍編集 (UpdateBookRequest) | title | required / string / max:255 |
| | author | required / string / max:255 |
| | isbn | required / string / size:13 / unique:books,isbn（自身を除外: Rule::unique('books')->ignore($this->book)） |
| | published_date | required / date |
| | description | nullable / string |
| | image_url | nullable / url |
| | genres | required / array |
| | genres.* | exists:genres,id |
| レビュー投稿 (StoreReviewRequest) | rating | required / integer / min:1 / max:5 |
| | comment | nullable / string / max:1000 |
| レビュー編集 (UpdateReviewRequest) | rating | required / integer / min:1 / max:5 |
| | comment | nullable / string / max:1000 |
| ジャンル登録 (StoreGenreRequest) | name | required / string / max:255 / unique:genres,name |
| ジャンル編集 (UpdateGenreRequest) | name | required / string / max:255 / unique:genres,name（自身を除外: Rule::unique('genres')->ignore($this->genre)） |
| ユーザー登録 | name | required / string / max:255 |
| | email | required / email / max:255 / unique:users,email |
| | password | Fortify標準（8文字以上・確認用一致） |
| ログイン | email | required / email |
| | password | required |

---

## シート8: シーディング要件

本模擬案件で実装するシーディングの詳細仕様です。
本要件を忘れてしまいますと、採点において差し戻しが発生する場合があるのでご注意ください。

| 対象 | 仕様 |
|---|---|
| UserSeeder | users テーブルに初期ユーザーを5件登録する。 |
| | name: 山田太郎, email: yamada@example.com, password: password |
| | name: 鈴木花子, email: suzuki@example.com, password: password |
| | name: 田中一郎, email: tanaka@example.com, password: password |
| | name: 佐藤美咲, email: sato@example.com, password: password |
| | name: 高橋健太, email: takahashi@example.com, password: password |
| | firstOrCreate を使用し、email の重複を防ぐこと。 |
| GenreSeeder | genres テーブルにジャンルを固定で10件投入する。 |
| | 内容: 「小説」「ビジネス」「技術書」「自己啓発」「エッセイ」「歴史」「科学」「芸術」「料理」「旅行」 |
| | firstOrCreate を使用し、name の重複を防ぐこと。 |
| BookSeeder | books テーブルに書籍データを11件投入する。登録者は User::first()（山田太郎）とする。 |
| | 1. 吾輩は猫である / 夏目漱石 / ISBN:9784101010014 / 1905-01-01 / ジャンル: 小説 |
| | 2. 人を動かす / D・カーネギー / ISBN:9784422100524 / 1936-10-01 / ジャンル: ビジネス, 自己啓発 |
| | 3. リーダブルコード / Dustin Boswell / ISBN:9784873115658 / 2012-06-23 / ジャンル: 技術書 |
| | 4. 7つの習慣 / スティーブン・R・コヴィー / ISBN:9784863940246 / 2013-08-30 / ジャンル: ビジネス, 自己啓発 |
| | 5. 坊っちゃん / 夏目漱石 / ISBN:9784101010021 / 1906-04-01 / ジャンル: 小説 |
| | 6. サピエンス全史 / ユヴァル・ノア・ハラリ / ISBN:9784309226712 / 2016-09-08 / ジャンル: 歴史, 科学 |
| | 7. Clean Code / Robert C. Martin / ISBN:9784048930598 / 2017-12-18 / ジャンル: 技術書 |
| | 8. 嫌われる勇気 / 岸見一郎・古賀史健 / ISBN:9784478025819 / 2013-12-13 / ジャンル: 自己啓発 |
| | 9. 火花 / 又吉直樹 / ISBN:9784163902302 / 2015-03-11 / ジャンル: 小説 |
| | 10. FACTFULNESS / ハンス・ロスリング / ISBN:9784822289607 / 2019-01-11 / ジャンル: ビジネス, 科学 |
| | 11. コンテナ物語 / マルク・レビンソン / ISBN:9784822251468 / 2007-01-18 / ジャンル: ビジネス, 歴史 |
| | 各書籍に description と image_url も設定すること。firstOrCreate（ISBN重複防止）と genres()->sync() を使用。 |
| ReviewSeeder | reviews テーブルにレビューデータを32件投入する。 |
| | 5人のユーザーが11冊の書籍に対してレビューを投稿。rating は 3〜5 の範囲。 |
| | 各書籍に2〜4件のレビューを配分。具体的なコメント内容を設定すること。 |
| | firstOrCreate（book_id + user_id の重複防止）を使用。 |
| FavoriteSeeder | favorites テーブルにお気に入りデータを投入する。 |
| | 各ユーザーに3〜5冊のお気に入りを設定。syncWithoutDetaching を使用。 |
| ReviewLikeSeeder | review_likes テーブルにいいねデータを投入する。 |
| | 各レビューに0〜3人のユーザーがいいね（自分のレビューを除く）。syncWithoutDetaching を使用。 |
| DatabaseSeeder | 上記 Seeder を DatabaseSeeder の run() で依存関係を考慮した順番に呼び出す。 |
| | 実行順: UserSeeder → GenreSeeder → BookSeeder → ReviewSeeder → FavoriteSeeder → ReviewLikeSeeder |
| | `php artisan db:seed` でまとめて投入できるようにする。 |

---

## シート9: テスト要件

このプロジェクトで実装するべきテストの一覧です。

### 全体要件

- テストが全て通過すること
- `sail artisan test --coverage` コマンドで表示されるテストカバレッジが60%超を目指すこと
  ※実装されたテストケースごとに採点が行われるため、すべてのテストケースを書かなければ点数が入らないということはありません。

### テスト要件

| テスト種別 | カテゴリ | テスト対象 | 実装要件 |
|---|---|---|---|
| 単体テスト (Unit Tests) | 環境 | Framework | フレームワークが正しく構成され、基本アサーションが動作すること。 |
| | モデル | Bookモデル関連 | Bookモデルのリレーション（user, reviews, genres, favoritedByUsers）が正しく定義されていること。 |
| | モデル | Reviewモデル関連 | Reviewモデルのリレーション（user, book, likedByUsers）が正しく定義されていること。 |
| | モデル | Userモデル関連 | Userモデルのリレーション（books, reviews, favoriteBooks, likedReviews）が正しく定義されていること。 |
| 機能テスト (Feature Tests) | 画面アクセス | 書籍一覧 | 書籍一覧ページ（/books）が正常に表示されること（200レスポンス）。 |
| | 画面アクセス | 書籍登録フォーム | 認証ユーザーのみが書籍登録フォーム（/books/create）を表示でき、ゲストはログインにリダイレクトされること。 |
| | 書籍CRUD | 書籍詳細 | 書籍詳細ページ（/books/{book}）が正常に表示され、書籍タイトルが画面に含まれること。 |
| | 書籍CRUD | 書籍登録 | 認証ユーザーが書籍を登録でき、ジャンルがbook_genreテーブルに紐付けられること。バリデーションエラー時は適切にエラーが返されること。 |
| | 書籍CRUD | 書籍編集 | 書籍所有者のみが編集でき、ジャンルの同期（sync）が正しく動作すること。他ユーザーがアクセスした場合は403 Forbiddenとなること。 |
| | 書籍CRUD | 書籍削除 | 書籍所有者のみが削除でき、削除後に書籍一覧にリダイレクトされること。DBからレコードが削除されること。 |
| | レビュー | レビュー投稿 | 認証ユーザーがレビューを投稿でき、reviewsテーブルにレコードが作成されること。ゲストはログインにリダイレクトされること。rating のバリデーション（1〜5の範囲）が動作すること。 |
| | レビュー | レビュー編集 | レビュー投稿者のみが編集フォームを表示・更新でき、他ユーザーは403 Forbiddenとなること。更新後のデータがDBに反映されること。 |
| | レビュー | レビュー削除 | レビュー投稿者のみが削除でき、他ユーザーは403 Forbiddenとなること。削除後に書籍詳細にリダイレクトされること。 |
| | ジャンル | ジャンル一覧 | 認証ユーザーがジャンル一覧ページ（/genres）を表示できること。 |
| | ジャンル | ジャンル登録 | 認証ユーザーがジャンルを作成でき、genresテーブルにレコードが作成されること。名前のユニーク制約が動作すること。 |
| | ジャンル | ジャンル登録フォーム | 認証ユーザーがジャンル登録フォーム（/genres/create）を表示できること。 |
| | ジャンル | ジャンル詳細 | ジャンル詳細ページ（/genres/{genre}）でジャンルに紐づく書籍タイトルが表示されること。 |
| | ジャンル | ジャンル編集 | 認証ユーザーがジャンル名を更新でき、genresテーブルのレコードが更新されること。編集フォームが表示できること。 |
| | ジャンル | ジャンル削除制約 | 書籍が紐付いているジャンルは削除できず、エラーメッセージが表示されること。紐付きがないジャンルは正常に削除できること。 |
| | お気に入り | お気に入り追加 | 認証ユーザーがお気に入りを追加でき、favoritesテーブルにレコードが作成されること。 |
| | お気に入り | お気に入り解除 | 認証ユーザーがお気に入りを解除でき、favoritesテーブルからレコードが削除されること。 |
| | お気に入り | お気に入りトグル | 同一操作で追加→解除が正しく動作すること。 |
| | お気に入り | お気に入り一覧 | 認証ユーザーのお気に入り一覧ページが正常に表示され、お気に入り書籍のタイトルが含まれること。 |
| | お気に入り | ゲスト制限 | ゲストがお気に入り操作を行うとログインにリダイレクトされること。 |
| | いいね | いいね追加 | 認証ユーザーがレビューにいいねを追加でき、review_likesテーブルにレコードが作成されること。 |
| | いいね | いいね解除 | 認証ユーザーがレビューのいいねを解除でき、review_likesテーブルからレコードが削除されること。 |
| | いいね | いいねトグル | 同一操作で追加→解除が正しく動作すること。 |
| | いいね | ゲスト制限 | ゲストがいいね操作を行うとログインにリダイレクトされること。 |
| | ランキング | ランキング表示 | ランキングページ（/ranking）が正常に表示され、レビューのある書籍タイトルが含まれること。 |
| | ランキング | ランキング順序 | 書籍が平均評価の降順で正しく並ぶこと。 |
| | 認証 | 認証済みリダイレクト | 認証済みユーザーがログインページにアクセスした場合、ホームにリダイレクトされること。ゲストはアクセス可能であること。 |
| | 基盤 | アプリケーション疎通 | アプリケーションのトップページ（/）が正常なレスポンス（200）を返すこと。 |

---

## シート10: データ要件

このセクションで定義された情報を管理するために、最適なテーブル構造を自分で設計し、ER図を作成してください。
【重要】ER図をコーチに提出し、承認を得てから実装に進んでください。

### 管理すべきデータ一覧

| No. | データ名 | 説明 | 管理すべき情報 | 備考 |
|---|---|---|---|---|
| DR01 | 書籍情報 | ユーザーが登録する書籍 | タイトル（<=255）、著者（<=255）、ISBN（13桁、ユニーク）、出版日（date）、説明（text、任意）、画像URL（任意）、作成者ID | books テーブル。app/Models/Book |
| DR02 | ジャンルマスタ | 書籍分類 | name（<=255、ユニーク） | genres テーブル。database/seeders/GenreSeeder.php |
| DR03 | 書籍×ジャンル | 多対多紐付け | book_id, genre_id（複合主キー） | book_genre テーブル。中間テーブル。 |
| DR04 | レビュー | 書籍へのレビュー | user_id, book_id, rating（1〜5）, comment（text） | reviews テーブル。app/Models/Review |
| DR05 | お気に入り | ユーザーのお気に入り書籍 | user_id, book_id（複合主キー） | favorites テーブル。中間テーブル。 |
| DR06 | いいね | レビューへのいいね | user_id, review_id（複合主キー） | review_likes テーブル。中間テーブル。 |
| DR07 | ユーザー | アプリケーション利用者 | name（<=255）, email（<=255、ユニーク）, password（ハッシュ化）, remember_token, email_verified_at | users テーブル。database/seeders/UserSeeder.php |
| DR08 | 認証補助 | パスワードリセットトークン等 | password_reset_tokens, personal_access_tokens, failed_jobs | Laravel標準マイグレーション。 |

---

## シート11: テーブル仕様書

こちらには模擬案件のテーブル仕様書を記載しています。
こちらの仕様書は評価対象になりますので、提出時にアプリケーションと仕様が一致するか必ず確認してください。
また、テーブル仕様書を参考にER図を作成し、右側のER図の箇所に添付してください。ER図はREADMEにも忘れず添付するようにしましょう。

### テーブル仕様

| No. | テーブル名 | カラム名 | 型 | PRIMARY KEY | NOT NULL | FOREIGN KEY | 補足 |
|---|---|---|---|---|---|---|---|
| 1 | usersテーブル | | | | | | |
| | | id | bigint unsigned | ○ | ○ | | $table->id() |
| | | name | varchar(255) | | ○ | | |
| | | email | varchar(255) | | ○ | | UNIQUE |
| | | email_verified_at | timestamp | | | | NULL許可（nullable） |
| | | password | varchar(255) | | ○ | | |
| | | two_factor_secret | text | | | | NULL許可（nullable）Fortify追加カラム |
| | | two_factor_recovery_codes | text | | | | NULL許可（nullable）Fortify追加カラム |
| | | two_factor_confirmed_at | timestamp | | | | NULL許可（nullable）Fortify追加カラム |
| | | remember_token | varchar(100) | | | | $table->rememberToken()（NULL許可） |
| | | created_at | timestamp | | | | $table->timestamps() |
| | | updated_at | timestamp | | | | $table->timestamps() |
| 2 | genresテーブル | | | | | | |
| | | id | bigint unsigned | ○ | ○ | | $table->id() |
| | | name | varchar(255) | | ○ | | UNIQUE |
| | | created_at | timestamp | | | | $table->timestamps() |
| | | updated_at | timestamp | | | | $table->timestamps() |
| 3 | booksテーブル | | | | | | |
| | | id | bigint unsigned | ○ | ○ | | $table->id() |
| | | user_id | bigint unsigned | | ○ | users.id | ON DELETE CASCADE。書籍の登録者。 |
| | | title | varchar(255) | | ○ | | |
| | | author | varchar(255) | | ○ | | |
| | | isbn | varchar(13) | | ○ | | UNIQUE。13桁固定。 |
| | | published_date | date | | ○ | | |
| | | description | text | | | | NULL許可（nullable） |
| | | image_url | varchar(255) | | | | NULL許可（nullable） |
| | | created_at | timestamp | | | | $table->timestamps() |
| | | updated_at | timestamp | | | | $table->timestamps() |
| 4 | reviewsテーブル | | | | | | |
| | | id | bigint unsigned | ○ | ○ | | $table->id() |
| | | user_id | bigint unsigned | | ○ | users.id | ON DELETE CASCADE |
| | | book_id | bigint unsigned | | ○ | books.id | ON DELETE CASCADE |
| | | rating | tinyint unsigned | | ○ | | 1〜5の評価値 |
| | | comment | text | | ○ | | レビューコメント |
| | | created_at | timestamp | | | | $table->timestamps() |
| | | updated_at | timestamp | | | | $table->timestamps() |
| 5 | book_genreテーブル | | | | | | |
| | | book_id | bigint unsigned | ○（複合） | ○ | books.id | ON DELETE CASCADE。複合主キー。 |
| | | genre_id | bigint unsigned | ○（複合） | ○ | genres.id | ON DELETE CASCADE。複合主キー。 |
| 6 | favoritesテーブル | | | | | | |
| | | user_id | bigint unsigned | ○（複合） | ○ | users.id | ON DELETE CASCADE。複合主キー。 |
| | | book_id | bigint unsigned | ○（複合） | ○ | books.id | ON DELETE CASCADE。複合主キー。 |
| 7 | review_likesテーブル | | | | | | |
| | | user_id | bigint unsigned | ○（複合） | ○ | users.id | ON DELETE CASCADE。複合主キー。 |
| | | review_id | bigint unsigned | ○（複合） | ○ | reviews.id | ON DELETE CASCADE。複合主キー。 |

### ER図

（ER図を添付してください）
