# Chapter 4: 認証機能とマスタデータの準備

## 🎯 このセクションで学ぶこと

このセクションでは、アプリケーションの「入り口」を整備します。具体的には以下の点を学びます。

- **認証機能の実装**: ユーザー登録（会員登録）とログイン機能を、Laravelの仕組みを活用して実装します。
- **レイアウトとコンポーネント**: 全ページ共通のヘッダーやフッターを「レイアウト」として定義し、再利用可能な「コンポーネント」を作成します。
- **マスタデータのシーディング**: 開発やテストを効率化するために、ジャンルなどの初期データをデータベースに投入する方法を学びます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜ認証を先に実装するのか？

多くのWebアプリケーションでは、「誰がこの操作を行っているか」を特定することが重要です。書籍の登録やレビューの投稿は、ログインしているユーザーに紐づけて記録する必要があります。認証機能を先に整備しておくことで、後続の機能開発がスムーズに進みます。

| 課題 | 解決策 | なぜこのChapterでやるのか？ |
|:---|:---|:---|
| 誰が書籍を登録したか分からない | **認証機能**でログインユーザーを特定し、`user_id`を記録する | 書籍やレビューの「所有者」を明確にし、編集・削除の権限管理を可能にする。 |
| 全ページでヘッダーやフッターを毎回書くのは非効率 | **レイアウト**と**コンポーネント**で共通部分を一元管理する | DRY原則（Don't Repeat Yourself）に従い、保守性を高める。 |
| 開発中にテストデータを手動で入力するのは面倒 | **Seeder**で初期データを自動投入する | `sail artisan migrate:fresh --seed`一発で、いつでもクリーンな状態から開発を再開できる。 |

---

## 4.1. 認証コントローラーの作成

Laravelには認証機能を簡単に実装するための仕組みが用意されています。まずは認証に必要なコントローラーを作成します。

```bash
# 認証関連のコントローラーを作成
sail artisan make:controller Auth/RegisteredUserController
sail artisan make:controller Auth/AuthenticatedSessionController
```

### 4.1.1. コードリーディング：`RegisteredUserController`

```php
// app/Http/Controllers/Auth/RegisteredUserController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect('/');
    }
}
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `$request->validate([...])` | リクエストデータのバリデーションを実行します。 | `array` | バリデーションに失敗すると、自動的に前のページにリダイレクトされ、エラーメッセージが表示されます。 |
| `User::create([...])` | `User`モデルの新しいレコードをデータベースに作成します。 | `User` | `::create()`は静的メソッドで、`$fillable`に指定されたカラムのみが保存されます。 |
| `Hash::make($request->password)` | パスワードを安全にハッシュ化します。 | `string` | 生のパスワードをデータベースに保存してはいけません。必ずハッシュ化します。 |
| `Auth::login($user)` | 作成したユーザーでログイン状態にします。 | `void` | 登録後、自動的にログインさせることでユーザー体験を向上させます。 |

---

## 4.2. 認証ルートの定義

認証に関するルートを`routes/auth.php`ファイルに定義します。

```bash
# ファイルを作成
touch routes/auth.php
```

```php
// routes/auth.php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
```

| 部分 | 説明 | 💡 ポイント |
|:---|:---|:---|
| `Route::middleware('guest')` | 未ログインユーザーのみアクセス可能なルートをグループ化します。 | ログイン済みユーザーが登録ページにアクセスしようとすると、リダイレクトされます。 |
| `Route::middleware('auth')` | ログイン済みユーザーのみアクセス可能なルートをグループ化します。 | ログアウトは、ログインしているユーザーだけが実行できます。 |
| `->name('login')` | ルートに名前を付けます。 | `route('login')`でURLを生成できます。URLが変わっても、名前で参照していれば修正不要です。 |

---

## 4.3. レイアウトとコンポーネントの作成

全ページ共通のレイアウトと、再利用可能なUIコンポーネントを作成します。

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/layouts
mkdir -p resources/views/components
mkdir -p app/View/Components

touch resources/views/layouts/app.blade.php
touch resources/views/layouts/guest.blade.php
touch app/View/Components/AppLayout.php
touch app/View/Components/GuestLayout.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 4.4. 認証ビューの作成

```bash
# ディレクトリとファイルを作成
mkdir -p resources/views/auth
touch resources/views/auth/login.blade.php
touch resources/views/auth/register.blade.php
```

各bladeファイルは「Preparedblade-mockcase-BookShelf」リポジトリを参照してください。

---

## 4.5. マスタデータの準備（ジャンルSeeder）

開発を効率化するために、ジャンルの初期データをデータベースに投入します。

### 4.5.1. Seederファイルの作成

```bash
sail artisan make:seeder GenreSeeder
```

### 4.5.2. Seederの実装

```php
// database/seeders/GenreSeeder.php

<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            '小説',
            'ビジネス',
            '技術書',
            '自己啓発',
            'エッセイ',
            '歴史',
            '科学',
            '芸術',
            '料理',
            '旅行',
        ];

        foreach ($genres as $genre) {
            Genre::firstOrCreate(['name' => $genre]);
        }
    }
}
```

| 部分 | 説明 | 戻り値 | 💡 ポイント |
|:---|:---|:---|:---|
| `Genre::firstOrCreate([...])` | 指定した条件のレコードが存在すれば取得し、なければ作成します。 | `Genre` | Seederを複数回実行しても、重複データが作成されません。 |
| `foreach ($genres as $genre)` | 配列をループして、各ジャンルを登録します。 | - | シンプルで分かりやすい実装です。 |

### 4.5.3. DatabaseSeederへの登録

```php
// database/seeders/DatabaseSeeder.php

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GenreSeeder::class,
        ]);
    }
}
```

### 4.5.4. データベースへのデータ投入

```bash
# テーブルを再作成し、Seederを実行
sail artisan migrate:fresh --seed
```

> **🧠 先輩エンジニアの思考プロセス**
> `migrate:fresh --seed`は、開発中に「データベースをまっさらな状態に戻して、初期データを入れ直す」ときに非常に便利です。本番環境では絶対に実行しないでください（全データが消えます）。

これで、認証機能と初期データの準備が整いました。次のChapterでは、いよいよ書籍管理機能（CRUD）を実装していきます。
