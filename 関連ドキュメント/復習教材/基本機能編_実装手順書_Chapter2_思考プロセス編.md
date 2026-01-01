# 実装手順書 Chapter 2: 認証機能の要件詰め・設計・実装編

## はじめに

アプリケーション開発において、最初に着手すべき機能の一つが「認証機能」です。なぜなら、多くの機能は「ログインしているユーザー」を前提とするからです。書籍を登録するのも、レビューを投稿するのも、「誰が」行ったのかを記録する必要があります。

このChapterでは、Laravel Breezeを使った認証機能の導入と、そのコードを読み解くことで、Laravelの認証の仕組みを理解します。

---

## Section 1: 認証機能の要件を確認する

まず、詳細度50%の要件定義書を確認します。

> **【機能一覧】**
> | 大機能 | 中機能 | 機能概要 |
> |---|---|---|
> | **認証** | ユーザー登録 | 名前、メールアドレス、パスワードで新規登録できる。 |
> | | ログイン | メールアドレスとパスワードでログインできる。 |
> | | ログアウト | ログイン状態を解除できる。 |

### 思考プロセス：認証機能をどう実装するか？

認証機能は、Webアプリケーションにおいて最も重要かつセキュリティに敏感な部分です。パスワードのハッシュ化、セッション管理、CSRF対策など、自前で実装するには多くの知識と注意が必要です。

**結論：自前で実装しない。**

Laravelには、認証機能を簡単に導入できる公式パッケージ（スターターキット）がいくつか用意されています。

| パッケージ名 | 特徴 |
|---|---|
| **Laravel Breeze** | 最もシンプル。Blade + Tailwind CSS。学習用途に最適。 |
| Laravel Jetstream | 高機能。2要素認証、チーム管理などを含む。 |
| Laravel Fortify | バックエンドのみ。フロントエンドは自分で用意する。 |

今回は、学習用途に最適な**Laravel Breeze**を使います。

### PMへのヒアリングシート（認証機能編）

認証機能については、Breezeのデフォルト仕様で進めて良いか確認します。

--- 

**To: PM（コーチ）**

お疲れ様です。認証機能の件で確認です。

Laravel Breezeを導入し、以下のデフォルト仕様で進めたいと考えておりますが、問題ないでしょうか。

**1. ユーザー登録**

| 項目名 | ルール |
|---|---|
| 名前 | 必須、文字列、最大255文字 |
| メールアドレス | 必須、メール形式、最大255文字、ユニーク |
| パスワード | 必須、8文字以上、確認入力と一致 |

**2. ログイン**

| 項目名 | ルール |
|---|---|
| メールアドレス | 必須、メール形式 |
| パスワード | 必須 |

**3. その他**

- 「パスワードリセット」「メール認証」機能は今回のスコープ外とし、実装しない。
- ログイン後のリダイレクト先は、書籍一覧ページ (`/books`) とする。

ご確認のほど、よろしくお願いいたします。

--- 

## Section 2: Laravel Breezeの導入

PMから「OK」が出たら、実装を開始します。

### 1. Breezeパッケージのインストール

```bash
# Composerを使ってBreezeパッケージをインストール
sail composer require laravel/breeze --dev
```

### 2. Breezeのセットアップ

```bash
# Breezeのスカフォールド（雛形）をインストール
# Bladeスタック（Blade + Tailwind CSS）を選択
sail artisan breeze:install blade

# npmパッケージのインストールとビルド
sail npm install
sail npm run build

# データベースのマイグレーション（usersテーブルなどが作成される）
sail artisan migrate
```

これだけで、ユーザー登録、ログイン、ログアウト、パスワードリセット、メール認証といった一連の認証機能が使えるようになります。

---

## Section 3: Breezeが生成したコードを読み解く

Breezeをインストールすると、多くのファイルが自動生成されます。現場のエンジニアは、これらのコードを「ブラックボックス」として使うのではなく、中身を理解しようとします。なぜなら、カスタマイズが必要になった時に対応できなくなるからです。

### 1. ルーティング (`routes/auth.php`)

認証関連のルートは、`routes/auth.php`に定義されています。

```php
// routes/auth.php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
// ... 他のコントローラのuse文

Route::middleware('guest')->group(function () {
    // ゲスト（未ログイン）ユーザーのみアクセス可能なルート
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    // ...
});

Route::middleware('auth')->group(function () {
    // 認証済み（ログイン済み）ユーザーのみアクセス可能なルート
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    // ...
});
```

> **【コードリーディング】**
> - `Route::middleware('guest')`: このグループ内のルートは、`guest`ミドルウェアによって保護されています。つまり、**ログインしていないユーザーだけ**がアクセスできます。ログイン済みのユーザーがアクセスしようとすると、ダッシュボード（または指定されたページ）にリダイレクトされます。
> - `Route::middleware('auth')`: このグループ内のルートは、`auth`ミドルウェアによって保護されています。つまり、**ログインしているユーザーだけ**がアクセスできます。未ログインのユーザーがアクセスしようとすると、ログインページにリダイレクトされます。

### 2. ユーザー登録コントローラ (`app/Http/Controllers/Auth/RegisteredUserController.php`)

```php
// app/Http/Controllers/Auth/RegisteredUserController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * 登録フォームを表示する
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * 新規ユーザー登録を処理する
     */
    public function store(Request $request): RedirectResponse
    {
        // バリデーション
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // ユーザーを作成
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // パスワードをハッシュ化
        ]);

        // Registeredイベントを発行（メール認証などのトリガーになる）
        event(new Registered($user));

        // 作成したユーザーでログイン状態にする
        Auth::login($user);

        // ダッシュボードにリダイレクト
        return redirect(route('dashboard', absolute: false));
    }
}
```

> **【コードリーディング】**
> - `Hash::make($request->password)`: パスワードを**ハッシュ化**してからデータベースに保存しています。これは非常に重要なセキュリティ対策です。もしパスワードを平文で保存していた場合、データベースが漏洩した際に全ユーザーのパスワードが露出してしまいます。ハッシュ化されていれば、元のパスワードを復元することは（事実上）不可能です。
> - `Auth::login($user)`: 作成したユーザーで自動的にログイン状態にしています。これにより、登録後すぐにアプリケーションを利用できます。
> - `Rules\Password::defaults()`: Laravelが提供するパスワードバリデーションルールのデフォルト設定を使用しています。最低8文字などのルールが適用されます。

### 3. ログイン後のリダイレクト先を変更する

デフォルトでは、ログイン後に`/dashboard`にリダイレクトされます。今回は`/books`（書籍一覧）にリダイレクトしたいので、設定を変更します。

`app/Providers/RouteServiceProvider.php`を編集します。

```php
// app/Providers/RouteServiceProvider.php

class RouteServiceProvider extends ServiceProvider
{
    /**
     * ログイン後のリダイレクト先
     */
    public const HOME = '/books'; // '/dashboard' から変更
    // ...
}
```

---

## まとめ

このChapterでは、Laravel Breezeを使った認証機能の導入と、そのコードの読み解き方を学びました。

**重要なポイント:**

1. **車輪の再発明をしない**: セキュリティに関わる機能は、実績のあるパッケージを利用する。
2. **ブラックボックスにしない**: 導入したパッケージのコードを読み、仕組みを理解する。
3. **カスタマイズポイントを把握する**: リダイレクト先の変更など、よくあるカスタマイズの方法を知っておく。

次のChapterでは、認証機能を土台として、書籍管理機能（CRUD）の実装に進みます。
