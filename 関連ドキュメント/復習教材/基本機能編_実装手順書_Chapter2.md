## Chapter 2: 最初の関門 - 認証機能の実装

### はじめに

環境という土台が固まったので、いよいよ機能実装に入ります。ほとんどのWebアプリケーションには「誰が使っているのか」を管理する機能、つまり**認証機能**が必要です。このChapterでは、Laravelが提供する便利なスターターキットを使って、堅牢な認証機能をあっという間に実装する方法を学びます。

---

### Section 1: 認証とは？ - Laravel Breezeという名の近道

#### 1.1. 認証機能の役割

認証機能は、大きく分けて以下の3つの役割を担います。

1.  **会員登録 (Register)**: 新しいユーザーが、自身を識別するための情報（名前、メールアドレス、パスワードなど）をシステムに登録する機能。
2.  **ログイン (Login)**: 登録済みのユーザーが、本人であることを証明してシステムを使い始める機能。
3.  **ログアウト (Logout)**: システムの使用を終了する機能。

これらを自力でゼロから作るのは大変です。パスワードの安全な保存方法（ハッシュ化）、ログイン状態の維持（セッション管理）、不正なアクセスからの保護（CSRF対策）など、考慮すべき点が山積みだからです。

#### 1.2. Laravel Breezeで時間を節約する

そこで登場するのが、Laravel公式が提供する**Laravel Breeze**です。Breezeは、認証機能に必要な以下の要素をすべて自動で生成してくれるスターターキットです。

- **ルーティング**: 会員登録ページ、ログインページなどのURL設定
- **コントローラ**: 登録処理、ログイン処理などのロジック
- **ビュー**: 登録フォーム、ログインフォームなどの画面

このような、基本的な骨組みを自動生成することを「**スカフォールディング(Scaffolding)**」と呼びます。Breezeを使うことで、私たちは面倒な定型作業から解放され、アプリケーション固有の機能開発に集中できるのです。

---

### Section 2: Breezeのインストールとセットアップ

それでは、Breezeを導入していきましょう。コマンドをいくつか実行するだけで完了します。

#### 2.1. Breezeパッケージのインストール

まず、Composerを使って`laravel/breeze`パッケージをプロジェクトに追加します。これも開発用のパッケージなので`--dev`を付けます。

```bash
sail composer require laravel/breeze --dev
```

#### 2.2. Breezeのスカフォールディング実行

次に、ArtisanコマンドでBreezeをインストールします。今回はBladeテンプレートを使いたいので、`blade`オプションを指定します。

```bash
sail artisan breeze:install blade
```

実行すると、いくつか質問されますが、すべて`yes`でOKです。

> **【何が起こった？】**
> このコマンドは、認証機能に必要な多数のファイルをプロジェクト内に自動で配置・上書きしました。具体的には...
> - `routes/auth.php`が作成され、認証関連のルートが定義されました。
> - `app/Http/Controllers/Auth/`ディレクトリに、認証処理を行うコントローラが多数作成されました。
> - `resources/views/auth/`ディレクトリに、ログインや会員登録のBladeテンプレートが作成されました。
> - 既存の`package.json`や`tailwind.config.js`なども、Breeze用に更新されました。

#### 2.3. フロントエンドのビルドとデータベースの準備

Breezeによって新しいCSSやJavaScriptのファイルが追加されたので、再度`npm`コマンドを実行して依存関係を解決し、ビルドします。

```bash
# 依存関係をインストール
sail npm install

# フロントエンドアセットをビルド
sail npm run build
```

> **【`npm run dev` と `npm run build` の違い】**
> - `npm run dev`: 開発中に使い、ファイルの変更を監視して**自動で**再ビルドし続けます。
> - `npm run build`: 本番環境用、または一度だけ手動でビルドしたい時に使います。ファイルを圧縮するなど、最適化も行います。
> 今回はBreezeのインストールでファイルが大きく変わったので、一度`build`でクリーンな状態にしています。この後は、再び`sail npm run dev`を起動しておくと良いでしょう。

最後に、Breezeが使用する`users`テーブルなどをデータベースに作成します。`migrate`コマンドは、`database/migrations/`ディレクトリにある設計図（マイグレーションファイル）を元に、データベースにテーブルを作成するコマンドです。

```bash
sail artisan migrate
```

これで認証機能のセットアップは完了です！簡単すぎて驚きですよね。

---

### Section 3: コードリーディング - Breezeの魔法を解き明かす

「動いた、終わり！」ではエンジニアとして成長できません。Breezeが生成したコードを読み解き、中で何が行われているのかを理解することが重要です。

#### 3.1. ルーティング (`routes/auth.php`)

このファイルは、認証に関するすべてのURL（ルート）を定義しています。いくつか見てみましょう。

```php
// routes/auth.php

use App\Http\Controllers\Auth\RegisteredUserController;

// ...

Route::middleware("guest")->group(function () {
    Route::get("register", [RegisteredUserController::class, "create"])
                ->name("register");

    Route::post("register", [RegisteredUserController::class, "store"]);

    // ... ログイン用のルートなど
});

Route::middleware("auth")->group(function () {
    Route::post("logout", [AuthenticatedSessionController::class, "destroy"])
                ->name("logout");
});
```

- **`Route::get("register", ...)`**: HTTPのGETリクエストで`/register`というURLにアクセスが来たら、`RegisteredUserController`の`create`メソッドを呼び出す、という意味です。つまり、会員登録ページを表示する処理です。
- **`Route::post("register", ...)`**: HTTPのPOSTリクエストで`/register`にアクセスが来たら、`store`メソッドを呼び出します。こちらは、フォームから送信されたデータを受け取って、実際にユーザーを登録する処理です。
- **`->name("register")`**: このルートに「`register`」という名前を付けています。これにより、ビューファイルでURLを直接書く代わりに`route("register")`と書けるようになり、後からURLを変更するのが容易になります。
- **`Route::middleware("guest")`**: このグループ内のルートは、**ログインしていないユーザー（ゲスト）**しかアクセスできません。ログイン済みのユーザーが`/register`にアクセスしようとすると、自動的にダッシュボードにリダイレクトされます。
- **`Route::middleware("auth")`**: 逆に、このグループ内のルートは、**ログインしているユーザー**しかアクセスできません。ログアウト機能などがこれに該当します。

#### 3.2. コントローラ (`app/Http/Controllers/Auth/RegisteredUserController.php`)

次に、会員登録処理の本体であるコントローラを見てみましょう。

```php
// app/Http/Controllers/Auth/RegisteredUserController.php

class RegisteredUserController extends Controller
{
    // ... (createメソッドはビューを返すだけなので省略)

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        // 1. バリデーション
        $request->validate([
            "name" => ["required", "string", "max:255"],
            "email" => ["required", "string", "lowercase", "email", "max:255", "unique:".User::class],
            "password" => ["required", "confirmed", Rules\Password::defaults()],
        ]);

        // 2. ユーザー作成
        $user = User::create([
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
        ]);

        // 3. イベント発火
        event(new Registered($user));

        // 4. ログイン処理
        Auth::login($user);

        // 5. リダイレクト
        return redirect(route("dashboard", absolute: false));
    }
}
```

1.  **バリデーション**: `validate`メソッドで、リクエスト内容がルールに合っているか検証します。例えば`"email" => [..., "unique:".User::class]`は、「`email`は`users`テーブル内でユニーク（一意）でなければならない」という意味です。ルールに違反した場合、Laravelは自動的に前のページにリダイレクトし、エラーメッセージを表示してくれます。
2.  **ユーザー作成**: バリデーションを通過したら、`User::create()`で`users`テーブルに新しいレコードを作成します。`Hash::make()`でパスワードを安全な形式（ハッシュ）に変換しているのがポイントです。
3.  **イベント発火**: `Registered`というイベントを発火させています。これにより、例えば「ユーザーが登録されたら、確認メールを送る」といった追加処理を、このコントローラとは別の場所で実行できます。
4.  **ログイン処理**: `Auth::login($user)`で、作成したユーザーをそのままログイン状態にします。会員登録後、すぐにサービスを使い始められる親切設計です。
5.  **リダイレクト**: 最後に、`dashboard`という名前のルート（ダッシュボードページ）にリダイレクトさせています。

#### 3.3. ビュー (`resources/views/auth/register.blade.php`)

最後に、ユーザーが目にする登録フォームのビューです。Breezeは**Bladeコンポーネント**という仕組みを多用しており、コードが非常にスッキリしています。

```blade
<!-- resources/views/auth/register.blade.php -->

<x-guest-layout>
    <form method="POST" action="{{ route("register") }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__("Name")" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old("name")" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get("name")" class="mt-2" />
        </div>

        <!-- ... Email, Password ... -->

        <div class="flex items-center justify-end mt-4">
            <a class="..." href="{{ route("login") }}">
                {{ __("Already registered?") }}
            </a>

            <x-primary-button class="ms-4">
                {{ __("Register") }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
```

- **`<x-guest-layout>`**: ゲスト（未ログイン）ページ用の共通レイアウトを読み込んでいます。ヘッダーやフッターなどを共通化できます。
- **`<x-input-label>`**, **`<x-text-input>`**, **`<x-input-error>`**: これらはすべてBladeコンポーネントです。ラベル、テキスト入力欄、エラーメッセージ表示といった定型的なHTMLを、再利用可能な部品としてカプセル化しています。
- **`@csrf`**: **CSRF（クロスサイト・リクエスト・フォージェリ）**という脆弱性を防ぐための必須の記述です。これが無いと、悪意のある外部サイトから勝手にフォームを送信されてしまう可能性があります。Laravelは`@csrf`があるか常にチェックしており、無い場合はエラーを返します。
- **`:value="old("name")"`**: バリデーションエラーで前の画面に戻されたときに、入力していた値を復元するための記述です。ユーザーに再入力をさせない、親切な作りです。
- **`$errors->get("name")`**: `name`フィールドに関するバリデーションエラーメッセージを取得して、`<x-input-error>`コンポーネントに渡しています。

---

### Section 4: 動作確認 - 最初のユーザーになろう！

理論は十分です。実際に動かして、自分の手で最初のユーザーを登録してみましょう！

1.  ブラウザで `http://localhost/register` にアクセスしてください。Breezeが生成した会員登録フォームが表示されるはずです。
2.  名前、メールアドレス、パスワードを入力して「Register」ボタンをクリックします。
3.  「Dashboard」と表示されたページに遷移すれば、登録とログインは成功です！
4.  `http://localhost:8080` からphpMyAdminにログインし、`book-review-app`データベースの`users`テーブルを開いてみてください。今登録したユーザーの情報が、パスワードがハッシュ化された状態で保存されているのが確認できます。
5.  画面右上のユーザー名をクリックし、「Log Out」を選択してログアウトできることも確認しましょう。

おめでとうございます！これで、あなたのアプリケーションはユーザーを管理できるようになりました。次のChapterでは、このアプリケーションの核となる「書籍管理機能」を実装していきます。
