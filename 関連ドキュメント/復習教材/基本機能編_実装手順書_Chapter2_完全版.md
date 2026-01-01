# Chapter 2: 認証機能 - Laravel Breezeで素早く実装する

アプリケーションの土台ができたので、最初の機能として「認証機能」を実装します。ほとんどのWebアプリケーションにはログインや会員登録が必要であり、これを最初に実装することで、後の機能開発で「ログインしているユーザー」を前提とした実装がスムーズに進められるからです。

## 2-1. 要件の確認と技術選定

まずは、PMから渡された要件定義書を確認します。

> **【要件定義書（詳細度50%）より抜粋】**
> 
> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | 認証 | 会員登録 | ユーザーが新しいアカウントを作成できる |
> | | ログイン | 登録済みのユーザーがログインできる |
> | | ログアウト | ログイン中のユーザーがログアウトできる |

### 思考プロセス：どうやって実装するか？

認証機能をゼロから作るのは非常に大変です。パスワードのハッシュ化、セッション管理、ログイン状態の維持、CSRF対策など、セキュリティに関する多くの知識が必要になります。

そこで、現場のエンジニアは**「車輪の再発明を避ける」**ことを考えます。つまり、信頼できる既存のライブラリやパッケージを活用します。Laravelには、まさにこのための素晴らしいスターターキットが用意されています。

-   **Laravel Breeze**: シンプルでカスタマイズしやすい認証機能を提供。BladeテンプレートとTailwind CSSで構成されており、今回のプロジェクトに最適です。
-   **Laravel Jetstream**: より高機能なスターターキット。チーム管理や二要素認証などが必要な場合はこちらを検討しますが、今回はBreezeで十分です。

今回は、シンプルさとカスタマイズのしやすさから**Laravel Breeze**を選択します。

## 2-2. Laravel Breezeの導入 (完全版)

それでは、Laravel Breezeをインストールして認証機能をセットアップしましょう。コマンドを順番に実行していくだけで、必要なルート、コントローラ、ビューがすべて自動で生成されます。

```bash
# 1. Laravel Breezeをインストール
sail composer require laravel/breeze --dev

# 2. Breezeをインストール（Bladeテンプレートを使用）
sail artisan breeze:install

# 3. npmの依存関係をインストール
sail npm install

# 4. フロントエンドのアセットをビルド
sail npm run dev

# 5. マイグレーションを実行
# (Chapter 1で実行済みですが、Breezeが追加したマイグレーションを反映させるために再度実行します)
sail artisan migrate
```

たったこれだけで、以下の機能がすべて実装されました。

-   会員登録 (`/register`)
-   ログイン (`/login`)
-   ログアウト
-   パスワードリセット

ブラウザで `http://localhost` にアクセスし、画面右上の「Register」や「Log in」リンクから実際に動作を確認してみてください。

## 2-3. コードリーディング：Breezeは何をしてくれたのか？

「魔法のように」機能が実装されましたが、エンジニアは中身を理解せずに先に進むことはありません。Breezeが生成したコードを読み解き、何が行われているのかを把握しましょう。

### 1. ルーティング (`routes/auth.php`)

`routes/web.php`の最後に`require __DIR__."/auth.php";`という行が追加され、`routes/auth.php`が読み込まれるようになっています。このファイルを見てみましょう。

```php
// routes/auth.php
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
// ...

Route::middleware("guest")->group(function () {
    Route::get("register", [RegisteredUserController::class, "create"])
                ->name("register");

    Route::post("register", [RegisteredUserController::class, "store"]);

    Route::get("login", [AuthenticatedSessionController::class, "create"])
                ->name("login");

    Route::post("login", [AuthenticatedSessionController::class, "store"]);

    // ... パスワードリセット関連のルート
});

Route::middleware("auth")->group(function () {
    // ... メール認証関連のルート

    Route::post("logout", [AuthenticatedSessionController::class, "destroy"])
                ->name("logout");
});
```

-   `Route::middleware("guest")`: ログインしていないユーザー（ゲスト）だけがアクセスできるルートのグループです。会員登録ページやログインページに、ログイン済みのユーザーがアクセスできないのはこのためです。
-   `Route::middleware("auth")`: ログイン済みのユーザーだけがアクセスできるルートのグループです。ログアウト機能などがここに含まれます。

### 2. コントローラ (`app/Http/Controllers/Auth/`)

`RegisteredUserController.php`の`store`メソッドを見てみましょう。これが会員登録処理の本体です。

```php
// app/Http/Controllers/Auth/RegisteredUserController.php
public function store(Request $request): RedirectResponse
{
    // 1. バリデーション
    $request->validate([
        "name" => ["required", "string", "max:255"],
        "email" => ["required", "string", "lowercase", "email", "max:255", "unique:".User::class],
        "password" => ["required", "confirmed", Rules\Password::defaults()],
    ]);

    // 2. ユーザーの作成
    $user = User::create([
        "name" => $request->name,
        "email" => $request->email,
        "password" => Hash::make($request->password),
    ]);

    // 3. イベントの発行（メール認証など）
    event(new Registered($user));

    // 4. ログイン処理
    Auth::login($user);

    // 5. リダイレクト
    return redirect(route("dashboard", absolute: false));
}
```

-   **① バリデーション**: `validate`メソッドで入力値を検証しています。`confirmed`は`password_confirmation`フィールドと値が一致するかをチェックし、`unique`は`users`テーブル内でメールアドレスが重複していないかをチェックします。
-   **② ユーザーの作成**: `User`モデルを使って新しいユーザーをDBに保存します。パスワードは`Hash::make()`で安全にハッシュ化されています。
-   **④ ログイン処理**: `Auth::login($user)`で、作成したユーザーをそのままログイン状態にします。
-   **⑤ リダイレクト**: 登録とログインが完了したら、ダッシュボード（今回は書籍一覧ページに相当）にリダイレクトします。

### 3. ビュー (`resources/views/auth/`)

`register.blade.php`を見てみましょう。

```html
<!-- resources/views/auth/register.blade.php -->
<form method="POST" action="{{ route("register") }}">
    @csrf

    <!-- Name -->
    <div>
        <x-input-label for="name" :value="__("Name")" />
        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old("name")" required autofocus autocomplete="name" />
        <x-input-error :messages="$errors->get("name")" class="mt-2" />
    </div>

    <!-- Password Confirmation -->
    <div class="mt-4">
        <x-input-label for="password_confirmation" :value="__("Confirm Password")" />
        <x-text-input id="password_confirmation" class="block mt-1 w-full"
                        type="password"
                        name="password_confirmation" required autocomplete="new-password" />
        <x-input-error :messages="$errors->get("password_confirmation")" class="mt-2" />
    </div>
</form>
```

-   `<x-input-label>`, `<x-text-input>`, `<x-input-error>`: これらは**Bladeコンポーネント**です。共通のUI部品を再利用可能にする仕組みで、実体は`resources/views/components/`ディレクトリにあります。これにより、コードの重複を減らし、一貫性のあるUIを簡単に構築できます。
-   `name="password_confirmation"`: このフィールドがあることで、コントローラの`"confirmed"`バリデーションルールが機能します。

## まとめ

このChapterでは、Laravel Breezeを使って認証機能を迅速に実装し、その裏側で何が起きているのかをコードリーディングによって解明しました。

-   **技術選定**: ゼロから作らず、信頼できるパッケージ（Breeze）を活用する。
-   **ルーティング**: `auth`と`guest`ミドルウェアでアクセス制御を行う。
-   **コントローラ**: バリデーション、DB操作、リダイレクトという一連の流れを理解する。
-   **ビュー**: BladeコンポーネントでUIが効率的に作られていることを知る。

これで、アプリケーションの基本的な骨格と認証機能が整いました。次のChapterから、いよいよこのアプリケーションのメイン機能である「書籍管理機能」の実装に入ります。
