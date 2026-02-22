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
| やること | バックエンド開発（DB設計、認証/認可、CRUD実装、テスト作成）<br>※Bladeテンプレートは完成品として提供されるため、フロントエンド実装は不要です |
| 作成物 | BookShelf 書籍レビューアプリ<br><br>【システム概要】<br>本システムは、書籍レビューアプリケーション「BookShelf」です。<br>ユーザーは書籍を登録・閲覧し、レビューの投稿やお気に入り登録ができます。<br>ジャンルによる分類やレビューへのいいね機能、平均評価に基づくランキング機能も備えています。 |
| ルール | 原則質問チャットサポートの利用は禁止です。<br><br>留意点：<br>1. 教材やブラウザで検索した記事を参考にすることは可能です。<br>2. GitHubでのエラーが解決できず、模擬案件の提出が困難な場合はコーチに相談してください。<br>3. 質問の利用数はCOACHTECH Proの合格基準とさせていただきますので、できるだけ質問対応は利用しないようにしましょう！ |
| 提出方法 | LMSのテスト一覧画面から提出してください。 |
| 注意点 | 教材学習の集大成で、少し難易度が高いです。<br>焦らず、一歩一歩開発を進めていきましょう。<br>また基本機能の開発が終了次第、応用機能の開発に着手してください。 |

---

## シート2: 開発プロセス

こちらは環境構築、コード品質、テスト要件の詳細資料です。
採点において重要な要件やアプリケーション全体を跨ぐ要件が記載していますので、十分に確認してください。

| 項目 | カテゴリ | 詳細仕様 | 留意点 |
|---|---|---|---|
| 仕様理解 | アーキテクチャ | 【重要】本プロジェクトのアーキテクチャについて（Traditional Web）<br><br>本課題では、Traditional Web（Blade + セッション認証）アーキテクチャを採用します。<br><br>Webブラウザ向け機能（Traditional Web）:<br>Bladeテンプレートを使用し、セッション認証（Cookie）で動作する従来のWebアプリケーション機能。<br>routes/web.php と通常のコントローラーを使用します。<br><br>※API実装は不要です。 | |
| 環境 | 技術スタック | OS（Dockerが動作する任意のOS）: -<br>PHP: 8.2<br>Laravel: 10.x<br>DB: MySQL 8.0<br>フロントエンド: Vite, Tailwind CSS ^3.4.0<br>開発ツール: Docker, Laravel Sail, phpMyAdmin | |
| | 構成管理 | ・DockerとDocker Composeを使用して環境をコンテナ化する<br>・COACHTECH側が提示した環境構築手順を遵守する | |
| | 初期設定手順 | 「環境構築手順」シートを参考の上初期設定を行うこと | |
| README.md 記載必須項目 | プロジェクト名 | 「BookShelf 書籍レビューアプリ」など、内容がわかるタイトル | READMEが不十分で、採点者が環境構築、機能確認ができない場合は再提出、または大きく減点される可能性があるので、十分に気をつけること |
| | 概要 | プロジェクトの目的と、実装した機能の概要説明 | |
| | ER図 | 自分で設計したER図の画像またはMermaid記法でのテキスト | |
| | 環境構築手順 | 上記「初期設定手順」を参考に、誰でも環境構築ができるように詳細に記載 | |
| | 使用技術 | Laravel 10, MySQL 8.0, Dockerなど、使用した技術スタック一覧 | |
| | 作成者 | 自分の名前 | |
| コード品質担保のための指示 | 命名規則 | ・Laravelの標準命名規則（PSR-12準拠）に従うこと<br> - 変数/メソッド: `camelCase`<br> - クラス: `PascalCase`<br> - DBテーブル: `snake_case`（複数形）<br> - DBカラム: `snake_case`（単数形）<br> - モデル名：アッパーキャメル<br> - コントローラー名：アッパーキャメル<br> - フォームリクエスト名：アッパーキャメル<br> - マイグレーションファイル名：スネークケース<br> - シーディングファイル名：アッパーキャメル | |
| | コードフォーマット | ・Laravel Pintを使用してコードを自動整形すること<br>・コミット前に `vendor/bin/pint` を実行し、整形されたコードをコミットする | |
| | Eloquent ORM | ・DB操作にはEloquentを最大限活用し、クエリビルダや生SQLは原則使用しない<br>・N+1問題を避けるため、`with()`メソッドによるEager Loadingを適切に使用する | |
| | コントローラーの責務 | ・コントローラーはリクエストの受付とレスポンスの返却に専念させる<br>・複雑なビジネスロジックはモデルやサービスクラス（任意）に記述する | |
| | FormRequest | ・バリデーションロジックは必ずFormRequestクラスに分離する | |
| | Policy | ・認可処理（リソースの所有者チェック等）は必ずPolicyクラスで実装する<br>・コントローラーで `$this->authorize()` を使用してPolicyを適用する | |
| | 設定のハードコーディング禁止 | ・DB接続情報やAPIキーなどの設定値は、必ず`.env`ファイルで管理する<br>・コード内に直接設定値を書き込まない | |
| | Git運用 | ・コミットメッセージは「何をしたか」が明確にわかるように記述する<br>（例: `feat: 書籍一覧表示を実装`）<br>・機能ごとにブランチを作成し、`main`ブランチにマージする | |
| 要件遵守 | 開発言語 | 開発言語はCOACHTECの教材内の言語を使用すること | |
| | 各種設計 | 開発については、案件シート内の設計に沿って作成すること | - ルーティングは「画面設計」の画面定義に従って作成すること<br>- システムは「機能要件」の要件に従って作成すること |
| | 機能要件の使用技術を遵守しているか | 認証やバリデーションなど、指定した技術以外で実装されていないか | |

---

## シート3: 環境構築手順

こちらは初期プロジェクトのセッティングにおいて必要な環境構築手順を記載したものです。
詳細な内容は、coachtech-prepared-blade-list/Preparedblade-mockcase-BookShelf リポジトリの環境構築.mdを参照して下さい。
採点時の環境はこちらで行いますので、違う手順によって環境構築された場合は採点を致しかねます。ご注意してください。

| 手順 | カテゴリ |
|---|---|
| 1. Laravelプロジェクトの作成 (Laravel 10.x) | 注意: `curl -s "https://laravel.build/..."` は最新版のLaravelをインストールするため、今回は使用しません。<br><br>以下のDockerコマンドを実行して、Laravel 10.xを明示的に指定してプロジェクトを作成します。<br><br>`docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest composer create-project laravel/laravel:^10.0 bookshelf-app` |
| 2. Laravel Sailのインストール | プロジェクト作成後、bookshelf-app ディレクトリに移動し、Laravel Sailをインストールします。<br><br># プロジェクトディレクトリに移動<br>`cd bookshelf-app`<br><br># Laravel Sailをインストール<br>`docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest composer require laravel/sail --dev`<br><br># Sailの設定ファイルをパブリッシュ（MySQLを選択）<br>`docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html -e COMPOSER_CACHE_DIR=/tmp/composer_cache laravelsail/php82-composer:latest php artisan sail:install --with=mysql`<br><br>※M1/M2/M3 Mac（Apple Silicon）をお使いの方：<br>`sail up -d` 実行時に `no matching manifest for linux/arm64/v8` エラーが発生した場合、compose.yaml の mysql サービスに `platform: 'linux/amd64'` を追加してください。 |
| 3. .env ファイルの設定 | .env ファイルを開き、データベース接続情報が以下と一致していることを確認します。<br><br>DB_CONNECTION=mysql<br>DB_HOST=mysql<br>DB_PORT=3306<br>DB_DATABASE=laravel<br>DB_USERNAME=sail<br>DB_PASSWORD=password<br><br>重要: DB_HOST は localhost や 127.0.0.1 ではなく、Dockerコンテナ名である mysql を指定します。 |
| 4. フロントエンドのセットアップ (Vite & Tailwind CSS) | 本プロジェクトでは、フロントエンドのスタイリングにTailwind CSSを使用します。<br>以下の手順でセットアップを行ってください。<br><br>1. NPM依存パッケージのインストール<br>`sail npm install`<br>※Sailコンテナが起動していることを確認。起動していない場合は `./vendor/bin/sail up -d` を実行<br><br>2. Alpine.jsのインストール<br>`sail npm install alpinejs`<br><br>3. Tailwind CSSのインストール<br>`sail npm install -D tailwindcss@^3.4.0 postcss autoprefixer`<br><br>4. 設定ファイルの生成<br>`sail npx tailwindcss init -p`<br><br>5. Tailwind CSSのテンプレートパス設定<br>tailwind.config.js を開き、content に以下を指定：<br>`"./resources/**/*.blade.php"`<br>`"./resources/**/*.js"`<br>`"./resources/**/*.vue"`<br><br>6. 本プロジェクトのresourcesファイルをcoachtech-prepared-blade-list/Preparedblade-mockcase-BookShelf リポジトリのresourcesファイルと入れ替え<br><br>7. Vite開発サーバーの起動<br>`sail npm run dev`<br>注意: 開発中は常にこのコマンドを実行した状態にしておいてください。 |
| 5. phpMyAdminの追加 | compose.yaml を開き、mysql サービスの後に以下の設定を追加してください。<br><br>```yaml<br>phpmyadmin:<br>    image: 'phpmyadmin:latest'<br>    ports:<br>        - '${FORWARD_PHPMYADMIN_PORT:-8080}:80'<br>    environment:<br>        PMA_HOST: mysql<br>        PMA_USER: '${DB_USERNAME}'<br>        PMA_PASSWORD: '${DB_PASSWORD}'<br>    networks:<br>        - sail<br>    depends_on:<br>        - mysql<br>``` |
| 6. Sailの起動とエイリアス設定 | # Sailをバックグラウンドで起動<br>`./vendor/bin/sail up -d`<br><br># エイリアスを設定して 'sail' だけでコマンドを実行できるようにする<br>`echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.zshrc`<br><br># シェルを再起動するか、新しいターミナルを開いてエイリアスを有効にする<br>`exec $SHELL` |
| 7. アプリケーションキーの生成 | ルートで以下のコマンドを実行する<br>`sail artisan key:generate` |

---

## シート4: 画面設計

各画面の仕様とUIデザイン要件の詳細資料です。
アプリケーションの実装に入る前に確認し、これらの要件を満たすように実装しましょう。

### 画面定義

| 画面ID | 画面名称 | HTTPメソッド | パス | 備考 |
|---|---|---|---|---|
| PG01 | 書籍一覧（トップ） | GET | / または /books | Blade提供済み。<br>公開ページ。全書籍をページネーション（10件/ページ）で最新順に表示。ジャンル情報をEager Loading。<br>routes/web.php<br>app/Http/Controllers/BookController@index<br>resources/views/books/index.blade.php |
| PG02 | 書籍詳細 | GET | /books/{book} | Blade提供済み。<br>公開ページ。書籍詳細とレビュー・ジャンル・お気に入り・いいね機能を表示。<br>routes/web.php<br>app/Http/Controllers/BookController@show<br>resources/views/books/show.blade.php |
| PG03 | 書籍登録 | GET | /books/create | Blade提供済み。<br>認証必須。全ジャンル一覧をセレクトボックスで表示。<br>routes/web.php<br>app/Http/Controllers/BookController@create<br>resources/views/books/create.blade.php |
| PG04 | 書籍編集 | GET | /books/{book}/edit | Blade提供済み。<br>認証+認可（BookPolicy@update）必須。作成者のみ閲覧可。<br>routes/web.php<br>app/Http/Controllers/BookController@edit<br>resources/views/books/edit.blade.php |
| PG05 | ジャンル一覧 | GET | /genres | Blade提供済み。<br>認証必須。各ジャンルの書籍数を表示。<br>routes/web.php<br>app/Http/Controllers/GenreController@index<br>resources/views/genres/index.blade.php |
| PG06 | ジャンル詳細 | GET | /genres/{genre} | Blade提供済み。<br>公開ページ。ジャンルに紐づく書籍をページネーション（10件/ページ）で表示。<br>routes/web.php<br>app/Http/Controllers/GenreController@show<br>resources/views/genres/show.blade.php |
| PG07 | ジャンル登録 | GET | /genres/create | Blade提供済み。<br>認証必須。<br>routes/web.php<br>app/Http/Controllers/GenreController@create<br>resources/views/genres/create.blade.php |
| PG08 | ジャンル編集 | GET | /genres/{genre}/edit | Blade提供済み。<br>認証必須。<br>routes/web.php<br>app/Http/Controllers/GenreController@edit<br>resources/views/genres/edit.blade.php |
| PG09 | レビュー編集 | GET | /reviews/{review}/edit | Blade提供済み。<br>認証+認可（ReviewPolicy@update）必須。投稿者のみ閲覧可。<br>routes/web.php<br>app/Http/Controllers/ReviewController@edit<br>resources/views/reviews/edit.blade.php |
| PG10 | お気に入り一覧 | GET | /favorites | Blade提供済み。<br>認証必須。ユーザーのお気に入り書籍をページネーション（10件/ページ）で表示。<br>routes/web.php<br>app/Http/Controllers/FavoriteController@index<br>resources/views/favorites/index.blade.php |
| PG11 | ランキング | GET | /ranking | Blade提供済み。<br>公開ページ。レビュー平均評価TOP10を表示。<br>routes/web.php<br>app/Http/Controllers/RankingController@index<br>resources/views/ranking/index.blade.php |
| PG12 | ログイン | GET | /login | Blade提供済み。<br>Fortifyが提供するログインビュー。<br>メール・パスワード入力と送信。<br>resources/views/auth/login.blade.php |
| PG13 | 会員登録 | GET | /register | Blade提供済み。<br>Fortifyが提供する登録ビュー。<br>氏名・メール・パスワードを登録。<br>resources/views/auth/register.blade.php |

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
| 1 | 認証機能 | AF01 | ユーザー登録 | ユーザーがFortifyの登録画面から新規アカウントを作成できる。 | URL: GET/POST /register<br><br>バリデーション:<br>name(required/string/max:255)<br>email(required/email/max:255/unique:users)<br>password(Fortify標準 8文字以上・確認用一致) | 登録成功後 / にリダイレクト。 | Fortify CreateNewUser で入力検証・ハッシュ化保存。<br>ユーザー作成後 config/fortify.php の home に従ってリダイレクト。 | app/Providers/FortifyServiceProvider<br>app/Actions/Fortify/CreateNewUser<br>resources/views/auth/register.blade.php | 基本 |
| | | AF02 | ログイン/ログアウト | ユーザーがログインし、保護された機能を利用し、ログアウトできる。 | URL: GET/POST /login<br>URL: POST /logout | ログイン成功で / にリダイレクト。<br>ログアウトでセッション破棄しログイン画面へ。 | Fortifyログインビューを提供。<br>auth ミドルウェアで保護されたルートへのアクセス制御。<br>/logout POST でセッション破棄。 | app/Providers/FortifyServiceProvider<br>resources/views/auth/login.blade.php<br>routes/web.php | 基本 |
| 2 | 書籍管理 | BK01 | 書籍一覧表示 | 全書籍をページネーション付きで一覧表示する。 | URL: GET / または GET /books<br>認証: 不要 | 書籍一覧が10件/ページでページネーション表示される。<br>各書籍にジャンルが表示される。 | Book::with("genres")->latest()->paginate(10) で書籍を取得し books.index ビューを返す。<br>N+1対策としてgenresをEager Loading。 | routes/web.php<br>app/Http/Controllers/BookController@index<br>resources/views/books/index.blade.php | 基本 |
| | | BK02 | 書籍詳細表示 | 書籍の詳細情報をレビュー・ジャンルと共に表示する。 | URL: GET /books/{book}<br>認証: 不要 | 書籍詳細画面にタイトル・著者・ISBN・出版日・説明・画像・ジャンル・レビュー一覧が表示される。<br>お気に入り・いいね操作が可能（認証時）。 | ルートモデルバインディングで Book を取得。<br>$book->load(["reviews.user", "genres"]) でリレーションを読み込み。<br>books.show ビューを返す。 | routes/web.php<br>app/Http/Controllers/BookController@show<br>resources/views/books/show.blade.php | 基本 |
| | | BK03 | 書籍登録 | 認証ユーザーが新しい書籍を登録し、ジャンルを紐付けられる。 | URL: GET /books/create（フォーム）<br>URL: POST /books（登録）<br>認証: 必須<br>バリデーション: StoreBookRequest | 登録成功時、書籍詳細画面にリダイレクトし「書籍を登録しました。」と表示。<br>バリデーションエラー時はフォームに戻りエラー表示。 | StoreBookRequest でバリデーション。<br>$request->user()->books()->create($bookData) で書籍作成（user_id自動設定）。<br>$book->genres()->attach($genres) でジャンル紐付け。 | routes/web.php<br>app/Http/Controllers/BookController@create,store<br>app/Http/Requests/StoreBookRequest<br>resources/views/books/create.blade.php | 基本 |
| | | BK04 | 書籍編集 | 書籍の作成者のみが書籍情報を編集できる。 | URL: GET /books/{book}/edit（フォーム）<br>URL: PUT /books/{book}（更新）<br>認証: 必須<br>認可: BookPolicy@update（作成者のみ）<br>バリデーション: UpdateBookRequest | 更新成功時、書籍詳細画面にリダイレクトし「書籍情報を更新しました。」と表示。<br>他ユーザーは403エラー。 | $this->authorize("update", $book) で認可チェック。<br>$book->update($request->validated()) で更新。<br>$book->genres()->sync($request->genres) でジャンル同期。 | routes/web.php<br>app/Http/Controllers/BookController@edit,update<br>app/Http/Requests/UpdateBookRequest<br>app/Policies/BookPolicy<br>resources/views/books/edit.blade.php | 基本 |
| | | BK05 | 書籍削除 | 書籍の作成者のみが書籍を削除できる。 | URL: DELETE /books/{book}<br>認証: 必須<br>認可: BookPolicy@delete（作成者のみ） | 削除成功時、書籍一覧にリダイレクトし「書籍を削除しました。」と表示。<br>関連レビュー・お気に入り・ジャンル紐付けはカスケード削除。<br>他ユーザーは403エラー。 | $this->authorize("delete", $book) で認可チェック。<br>$book->delete() で削除。<br>外部キーのON DELETE CASCADEにより関連データも削除。 | routes/web.php<br>app/Http/Controllers/BookController@destroy<br>app/Policies/BookPolicy | 基本 |
| 3 | レビュー管理 | RV01 | レビュー投稿 | 認証ユーザーが書籍にレビュー（評価+コメント）を投稿できる。 | URL: POST /books/{book}/reviews<br>認証: 必須<br>バリデーション: StoreReviewRequest | 投稿成功時、書籍詳細画面にリダイレクトし「レビューを投稿しました。」と表示。<br>ゲストはログイン画面にリダイレクト。 | StoreReviewRequest でバリデーション。<br>$book->reviews()->create(['user_id' => Auth::id(), 'rating' => ..., 'comment' => ...]) でレビュー作成。 | routes/web.php<br>app/Http/Controllers/ReviewController@store<br>app/Http/Requests/StoreReviewRequest | 基本 |
| | | RV02 | レビュー編集 | レビューの投稿者のみがレビューを編集できる。 | URL: GET /reviews/{review}/edit（フォーム）<br>URL: PUT /reviews/{review}（更新）<br>認証: 必須<br>認可: ReviewPolicy@update（投稿者のみ）<br>バリデーション: UpdateReviewRequest | 更新成功時、書籍詳細画面にリダイレクトし「レビューを更新しました。」と表示。<br>他ユーザーは403エラー。 | $this->authorize('update', $review) で認可チェック。<br>$review->update(['rating' => ..., 'comment' => ...]) で更新。 | routes/web.php<br>app/Http/Controllers/ReviewController@edit,update<br>app/Http/Requests/UpdateReviewRequest<br>app/Policies/ReviewPolicy<br>resources/views/reviews/edit.blade.php | 基本 |
| | | RV03 | レビュー削除 | レビューの投稿者のみがレビューを削除できる。 | URL: DELETE /reviews/{review}<br>認証: 必須<br>認可: ReviewPolicy@delete（投稿者のみ） | 削除成功時、書籍詳細画面にリダイレクトし「レビューを削除しました。」と表示。<br>関連いいねはカスケード削除。<br>他ユーザーは403エラー。 | $this->authorize('delete', $review) で認可チェック。<br>$review->delete() で削除。<br>外部キーのON DELETE CASCADEにより関連いいねも削除。 | routes/web.php<br>app/Http/Controllers/ReviewController@destroy<br>app/Policies/ReviewPolicy | 基本 |
| 4 | お気に入り | FV01 | お気に入り登録/解除（トグル） | 認証ユーザーが書籍をお気に入りに追加/解除できる。<br>同一操作でトグル動作する。 | URL: POST /books/{book}/favorites<br>認証: 必須 | お気に入り追加時はfavoritesテーブルにレコード追加、解除時は削除。<br>操作後、元のページにリダイレクト。<br>ゲストはログイン画面にリダイレクト。 | Auth::user()->favoriteBooks()->toggle($book->id) でトグル処理。<br>return back() で元のページに戻る。 | routes/web.php<br>app/Http/Controllers/FavoriteController@toggle | 基本 |
| | | FV02 | お気に入り一覧表示 | 認証ユーザーの全お気に入り書籍を一覧表示する。 | URL: GET /favorites<br>認証: 必須 | ユーザーのお気に入り書籍がページネーション（10件/ページ）で表示される。 | Auth::user()->favoriteBooks()->paginate(10) でお気に入り書籍を取得。<br>favorites.index ビューを返す。 | routes/web.php<br>app/Http/Controllers/FavoriteController@index<br>resources/views/favorites/index.blade.php | 基本 |
| 5 | いいね | LK01 | レビューいいね登録/解除（トグル） | 認証ユーザーがレビューにいいねを追加/解除できる。<br>同一操作でトグル動作する。 | URL: POST /reviews/{review}/like<br>認証: 必須 | いいね追加時はreview_likesテーブルにレコード追加、解除時は削除。<br>操作後、元のページにリダイレクト。<br>ゲストはログイン画面にリダイレクト。 | Auth::user()->likedReviews()->toggle($review->id) でトグル処理。<br>return back() で元のページに戻る。 | routes/web.php<br>app/Http/Controllers/ReviewLikeController@toggle | 基本 |
| 6 | ジャンル管理 | GN01 | ジャンル一覧表示 | 認証ユーザーが全ジャンルを書籍数付きで一覧表示する。 | URL: GET /genres<br>認証: 必須 | ジャンル一覧が各ジャンルの書籍数と共に表示される。 | Genre::withCount('books')->get() でジャンルを書籍数付きで取得。<br>genres.index ビューを返す。 | routes/web.php<br>app/Http/Controllers/GenreController@index<br>resources/views/genres/index.blade.php | 基本 |
| | | GN02 | ジャンル詳細（ジャンル内書籍一覧） | ジャンルに紐づく書籍をページネーション付きで表示する。 | URL: GET /genres/{genre}<br>認証: 不要 | ジャンル名と紐づく書籍がページネーション（10件/ページ）で表示される。 | ルートモデルバインディングで Genre を取得。<br>$genre->books()->with('genres')->paginate(10) で書籍を取得。<br>genres.show ビューを返す。 | routes/web.php<br>app/Http/Controllers/GenreController@show<br>resources/views/genres/show.blade.php | 基本 |
| | | GN03 | ジャンル登録 | 認証ユーザーが新しいジャンルを登録できる。 | URL: GET /genres/create（フォーム）<br>URL: POST /genres（登録）<br>認証: 必須<br>バリデーション: StoreGenreRequest | 登録成功時、ジャンル一覧にリダイレクトし「ジャンルを作成しました。」と表示。<br>名前重複時はバリデーションエラー。 | StoreGenreRequest でバリデーション。<br>Genre::create($request->validated()) でジャンル作成。 | routes/web.php<br>app/Http/Controllers/GenreController@create,store<br>app/Http/Requests/StoreGenreRequest<br>resources/views/genres/create.blade.php | 基本 |
| | | GN04 | ジャンル編集 | 認証ユーザーがジャンル名を編集できる。 | URL: GET /genres/{genre}/edit（フォーム）<br>URL: PUT /genres/{genre}（更新）<br>認証: 必須<br>バリデーション: UpdateGenreRequest | 更新成功時、ジャンル一覧にリダイレクトし「ジャンルを更新しました。」と表示。<br>名前重複時はバリデーションエラー（自身を除外）。 | UpdateGenreRequest でバリデーション（unique制約で自身を除外）。<br>$genre->update($request->validated()) で更新。 | routes/web.php<br>app/Http/Controllers/GenreController@edit,update<br>app/Http/Requests/UpdateGenreRequest<br>resources/views/genres/edit.blade.php | 基本 |
| | | GN05 | ジャンル削除 | 認証ユーザーがジャンルを削除できる。<br>ただし書籍が紐付いている場合は削除不可。 | URL: DELETE /genres/{genre}<br>認証: 必須<br>制約: 書籍が紐付いている場合は削除を拒否 | 書籍紐付きなし: ジャンル一覧にリダイレクトし「ジャンルを削除しました。」と表示。<br>書籍紐付きあり: 「このジャンルには書籍が紐付いているため削除できません。」とエラー表示。 | $genre->books()->count() > 0 のチェック。<br>紐付きあり: redirect with error。<br>紐付きなし: $genre->delete() 後 redirect with success。 | routes/web.php<br>app/Http/Controllers/GenreController@destroy | 基本 |
| 7 | ランキング | RK01 | 書籍ランキング表示 | レビュー平均評価のTOP10書籍をランキング表示する。 | URL: GET /ranking<br>認証: 不要 | レビューが存在する書籍が平均評価の降順でTOP10表示される。<br>レビューがない書籍は表示されない。 | Book::select('books.*', DB::raw('AVG(reviews.rating) as average_rating'))<br>->join('reviews', 'books.id', '=', 'reviews.book_id')<br>->groupBy('books.id')<br>->orderByDesc('average_rating')<br>->take(10)->get() でランキング取得。 | routes/web.php<br>app/Http/Controllers/RankingController@index<br>resources/views/ranking/index.blade.php | 基本 |

---

## シート7: バリデーションルール

本模擬案件で実装する各種バリデーションの詳細仕様です。

| 対象機能 | 入力項目 | ルール |
|---|---|---|
| 書籍登録<br>(StoreBookRequest) | title | required / string / max:255 |
| | author | required / string / max:255 |
| | isbn | required / string / size:13 / unique:books,isbn |
| | published_date | required / date |
| | description | nullable / string |
| | image_url | nullable / url |
| | genres | required / array |
| | genres.* | exists:genres,id |
| 書籍編集<br>(UpdateBookRequest) | title | required / string / max:255 |
| | author | required / string / max:255 |
| | isbn | required / string / size:13 / unique:books,isbn<br>（自身を除外: Rule::unique('books')->ignore($this->book)） |
| | published_date | required / date |
| | description | nullable / string |
| | image_url | nullable / url |
| | genres | required / array |
| | genres.* | exists:genres,id |
| レビュー投稿<br>(StoreReviewRequest) | rating | required / integer / min:1 / max:5 |
| | comment | nullable / string / max:1000 |
| レビュー編集<br>(UpdateReviewRequest) | rating | required / integer / min:1 / max:5 |
| | comment | nullable / string / max:1000 |
| ジャンル登録<br>(StoreGenreRequest) | name | required / string / max:255 / unique:genres,name |
| ジャンル編集<br>(UpdateGenreRequest) | name | required / string / max:255 / unique:genres,name<br>（自身を除外: Rule::unique('genres')->ignore($this->genre)） |
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
| UserSeeder | users テーブルに初期ユーザーを5件登録する。<br><br>name: 山田太郎, email: yamada@example.com, password: password<br>name: 鈴木花子, email: suzuki@example.com, password: password<br>name: 田中一郎, email: tanaka@example.com, password: password<br>name: 佐藤美咲, email: sato@example.com, password: password<br>name: 高橋健太, email: takahashi@example.com, password: password<br><br>firstOrCreate を使用し、email の重複を防ぐこと。 |
| GenreSeeder | genres テーブルにジャンルを固定で10件投入する。<br><br>内容: 「小説」「ビジネス」「技術書」「自己啓発」「エッセイ」「歴史」「科学」「芸術」「料理」「旅行」<br><br>firstOrCreate を使用し、name の重複を防ぐこと。 |
| BookSeeder | books テーブルに書籍データを11件投入する。<br>登録者は User::first()（山田太郎）とする。<br><br>1. 吾輩は猫である / 夏目漱石 / ISBN:9784101010014 / 1905-01-01 / ジャンル: 小説<br>2. 人を動かす / D・カーネギー / ISBN:9784422100524 / 1936-10-01 / ジャンル: ビジネス, 自己啓発<br>3. リーダブルコード / Dustin Boswell / ISBN:9784873115658 / 2012-06-23 / ジャンル: 技術書<br>4. 7つの習慣 / スティーブン・R・コヴィー / ISBN:9784863940246 / 2013-08-30 / ジャンル: ビジネス, 自己啓発<br>5. 坊っちゃん / 夏目漱石 / ISBN:9784101010021 / 1906-04-01 / ジャンル: 小説<br>6. サピエンス全史 / ユヴァル・ノア・ハラリ / ISBN:9784309226712 / 2016-09-08 / ジャンル: 歴史, 科学<br>7. Clean Code / Robert C. Martin / ISBN:9784048930598 / 2017-12-18 / ジャンル: 技術書<br>8. 嫌われる勇気 / 岸見一郎・古賀史健 / ISBN:9784478025819 / 2013-12-13 / ジャンル: 自己啓発<br>9. 火花 / 又吉直樹 / ISBN:9784163902302 / 2015-03-11 / ジャンル: 小説<br>10. FACTFULNESS / ハンス・ロスリング / ISBN:9784822289607 / 2019-01-11 / ジャンル: ビジネス, 科学<br>11. コンテナ物語 / マルク・レビンソン / ISBN:9784822251468 / 2007-01-18 / ジャンル: ビジネス, 歴史<br><br>各書籍に description と image_url も設定すること。<br>firstOrCreate（ISBN重複防止）と genres()->sync() を使用。 |
| ReviewSeeder | reviews テーブルにレビューデータを32件投入する。<br><br>5人のユーザーが11冊の書籍に対してレビューを投稿。<br>rating は 3〜5 の範囲。<br>各書籍に2〜4件のレビューを配分。<br>具体的なコメント内容を設定すること。<br><br>firstOrCreate（book_id + user_id の重複防止）を使用。 |
| FavoriteSeeder | favorites テーブルにお気に入りデータを投入する。<br><br>各ユーザーに3〜5冊のお気に入りを設定。<br>syncWithoutDetaching を使用。 |
| ReviewLikeSeeder | review_likes テーブルにいいねデータを投入する。<br><br>各レビューに0〜3人のユーザーがいいね（自分のレビューを除く）。<br>syncWithoutDetaching を使用。 |
| DatabaseSeeder | 上記 Seeder を DatabaseSeeder の run() で依存関係を考慮した順番に呼び出す。<br><br>実行順:<br>1. UserSeeder<br>2. GenreSeeder<br>3. BookSeeder<br>4. ReviewSeeder<br>5. FavoriteSeeder<br>6. ReviewLikeSeeder<br><br>`php artisan db:seed` でまとめて投入できるようにする。 |

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
| 単体テスト<br>(Unit Tests) | 環境 | Framework | フレームワークが正しく構成され、基本アサーションが動作すること。 |
| | モデル | Bookモデル関連 | Bookモデルのリレーション（user, reviews, genres, favoritedByUsers）が正しく定義されていること。 |
| | モデル | Reviewモデル関連 | Reviewモデルのリレーション（user, book, likedByUsers）が正しく定義されていること。 |
| | モデル | Userモデル関連 | Userモデルのリレーション（books, reviews, favoriteBooks, likedReviews）が正しく定義されていること。 |
| 機能テスト<br>(Feature Tests) | 画面アクセス | 書籍一覧 | 書籍一覧ページ（/books）が正常に表示されること（200レスポンス）。 |
| | 画面アクセス | 書籍登録フォーム | 認証ユーザーのみが書籍登録フォーム（/books/create）を表示でき、<br>ゲストはログインにリダイレクトされること。 |
| | 書籍CRUD | 書籍詳細 | 書籍詳細ページ（/books/{book}）が正常に表示され、<br>書籍タイトルが画面に含まれること。 |
| | 書籍CRUD | 書籍登録 | 認証ユーザーが書籍を登録でき、ジャンルがbook_genreテーブルに紐付けられること。<br>バリデーションエラー時は適切にエラーが返されること。 |
| | 書籍CRUD | 書籍編集 | 書籍所有者のみが編集でき、ジャンルの同期（sync）が正しく動作すること。<br>他ユーザーがアクセスした場合は403 Forbiddenとなること。 |
| | 書籍CRUD | 書籍削除 | 書籍所有者のみが削除でき、削除後に書籍一覧にリダイレクトされること。<br>DBからレコードが削除されること。 |
| | レビュー | レビュー投稿 | 認証ユーザーがレビューを投稿でき、reviewsテーブルにレコードが作成されること。<br>ゲストはログインにリダイレクトされること。<br>rating のバリデーション（1〜5の範囲）が動作すること。 |
| | レビュー | レビュー編集 | レビュー投稿者のみが編集フォームを表示・更新でき、<br>他ユーザーは403 Forbiddenとなること。<br>更新後のデータがDBに反映されること。 |
| | レビュー | レビュー削除 | レビュー投稿者のみが削除でき、他ユーザーは403 Forbiddenとなること。<br>削除後に書籍詳細にリダイレクトされること。 |
| | ジャンル | ジャンル一覧 | 認証ユーザーがジャンル一覧ページ（/genres）を表示できること。 |
| | ジャンル | ジャンル登録 | 認証ユーザーがジャンルを作成でき、genresテーブルにレコードが作成されること。<br>名前のユニーク制約が動作すること。 |
| | ジャンル | ジャンル登録フォーム | 認証ユーザーがジャンル登録フォーム（/genres/create）を表示できること。 |
| | ジャンル | ジャンル詳細 | ジャンル詳細ページ（/genres/{genre}）で<br>ジャンルに紐づく書籍タイトルが表示されること。 |
| | ジャンル | ジャンル編集 | 認証ユーザーがジャンル名を更新でき、genresテーブルのレコードが更新されること。<br>編集フォームが表示できること。 |
| | ジャンル | ジャンル削除制約 | 書籍が紐付いているジャンルは削除できず、エラーメッセージが表示されること。<br>紐付きがないジャンルは正常に削除できること。 |
| | お気に入り | お気に入り追加 | 認証ユーザーがお気に入りを追加でき、<br>favoritesテーブルにレコードが作成されること。 |
| | お気に入り | お気に入り解除 | 認証ユーザーがお気に入りを解除でき、<br>favoritesテーブルからレコードが削除されること。 |
| | お気に入り | お気に入りトグル | 同一操作で追加→解除が正しく動作すること。 |
| | お気に入り | お気に入り一覧 | 認証ユーザーのお気に入り一覧ページが正常に表示され、<br>お気に入り書籍のタイトルが含まれること。 |
| | お気に入り | ゲスト制限 | ゲストがお気に入り操作を行うとログインにリダイレクトされること。 |
| | いいね | いいね追加 | 認証ユーザーがレビューにいいねを追加でき、<br>review_likesテーブルにレコードが作成されること。 |
| | いいね | いいね解除 | 認証ユーザーがレビューのいいねを解除でき、<br>review_likesテーブルからレコードが削除されること。 |
| | いいね | いいねトグル | 同一操作で追加→解除が正しく動作すること。 |
| | いいね | ゲスト制限 | ゲストがいいね操作を行うとログインにリダイレクトされること。 |
| | ランキング | ランキング表示 | ランキングページ（/ranking）が正常に表示され、<br>レビューのある書籍タイトルが含まれること。 |
| | ランキング | ランキング順序 | 書籍が平均評価の降順で正しく並ぶこと。 |
| | 認証 | 認証済みリダイレクト | 認証済みユーザーがログインページにアクセスした場合、ホームにリダイレクトされること。<br>ゲストはアクセス可能であること。 |
| | 基盤 | アプリケーション疎通 | アプリケーションのトップページ（/）が正常なレスポンス（200）を返すこと。 |

---

## シート10: データ要件

このセクションで定義された情報を管理するために、最適なテーブル構造を自分で設計し、ER図を作成してください。
【重要】ER図をコーチに提出し、承認を得てから実装に進んでください。

### 管理すべきデータ一覧

| No. | データ名 | 説明 | 管理すべき情報 | 備考 |
|---|---|---|---|---|
| DR01 | 書籍情報 | ユーザーが登録する書籍 | タイトル（<=255）<br>著者（<=255）<br>ISBN（13桁、ユニーク）<br>出版日（date）<br>説明（text、任意）<br>画像URL（任意）<br>作成者ID | books テーブル。<br>app/Models/Book |
| DR02 | ジャンルマスタ | 書籍分類 | name（<=255、ユニーク） | genres テーブル。<br>database/seeders/GenreSeeder.php |
| DR03 | 書籍×ジャンル | 多対多紐付け | book_id, genre_id（複合主キー） | book_genre テーブル。<br>中間テーブル。 |
| DR04 | レビュー | 書籍へのレビュー | user_id<br>book_id<br>rating（1〜5）<br>comment（text） | reviews テーブル。<br>app/Models/Review |
| DR05 | お気に入り | ユーザーのお気に入り書籍 | user_id, book_id（複合主キー） | favorites テーブル。<br>中間テーブル。 |
| DR06 | いいね | レビューへのいいね | user_id, review_id（複合主キー） | review_likes テーブル。<br>中間テーブル。 |
| DR07 | ユーザー | アプリケーション利用者 | name（<=255）<br>email（<=255、ユニーク）<br>password（ハッシュ化）<br>remember_token<br>email_verified_at | users テーブル。<br>database/seeders/UserSeeder.php |
| DR08 | 認証補助 | パスワードリセットトークン等 | password_reset_tokens<br>personal_access_tokens<br>failed_jobs | Laravel標準マイグレーション。 |

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
| | | two_factor_secret | text | | | | NULL許可（nullable）<br>Fortify追加カラム |
| | | two_factor_recovery_codes | text | | | | NULL許可（nullable）<br>Fortify追加カラム |
| | | two_factor_confirmed_at | timestamp | | | | NULL許可（nullable）<br>Fortify追加カラム |
| | | remember_token | varchar(100) | | | | $table->rememberToken()<br>（NULL許可） |
| | | created_at | timestamp | | | | $table->timestamps() |
| | | updated_at | timestamp | | | | $table->timestamps() |
| 2 | genresテーブル | | | | | | |
| | | id | bigint unsigned | ○ | ○ | | $table->id() |
| | | name | varchar(255) | | ○ | | UNIQUE |
| | | created_at | timestamp | | | | $table->timestamps() |
| | | updated_at | timestamp | | | | $table->timestamps() |
| 3 | booksテーブル | | | | | | |
| | | id | bigint unsigned | ○ | ○ | | $table->id() |
| | | user_id | bigint unsigned | | ○ | users.id | ON DELETE CASCADE<br>書籍の登録者 |
| | | title | varchar(255) | | ○ | | |
| | | author | varchar(255) | | ○ | | |
| | | isbn | varchar(13) | | ○ | | UNIQUE<br>13桁固定 |
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
| | | book_id | bigint unsigned | ○（複合） | ○ | books.id | ON DELETE CASCADE<br>複合主キー |
| | | genre_id | bigint unsigned | ○（複合） | ○ | genres.id | ON DELETE CASCADE<br>複合主キー |
| 6 | favoritesテーブル | | | | | | |
| | | user_id | bigint unsigned | ○（複合） | ○ | users.id | ON DELETE CASCADE<br>複合主キー |
| | | book_id | bigint unsigned | ○（複合） | ○ | books.id | ON DELETE CASCADE<br>複合主キー |
| 7 | review_likesテーブル | | | | | | |
| | | user_id | bigint unsigned | ○（複合） | ○ | users.id | ON DELETE CASCADE<br>複合主キー |
| | | review_id | bigint unsigned | ○（複合） | ○ | reviews.id | ON DELETE CASCADE<br>複合主キー |

### ER図

（ER図を添付してください）
