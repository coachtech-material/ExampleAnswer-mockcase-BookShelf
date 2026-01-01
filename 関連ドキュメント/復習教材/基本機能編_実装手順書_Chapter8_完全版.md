
# Chapter 8: ジャンル管理機能 (管理者向け)

このChapterでは、管理者のみがアクセスできるジャンル管理機能（CRUD）を実装します。ミドルウェアを使った特定のルートへのアクセス制御が重要なポイントです。

## 8-1. 管理者権限の仕組み

ユーザーが管理者かどうかを判断する仕組みを実装します。`users`テーブルに`is_admin`のような真偽値カラムを追加するのが一般的です。

### Step 1: マイグレーションファイルの作成と実行

`is_admin`カラムを`users`テーブルに追加するためのマイグレーションを作成します。

```bash
sail artisan make:migration add_is_admin_to_users_table --table=users
```

**`database/migrations/xxxx_xx_xx_xxxxxx_add_is_admin_to_users_table.php`**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->boolean("is_admin")->default(false)->after("email");
        });
    }

    public function down(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn("is_admin");
        });
    }
};
```

マイグレーションを実行します。

```bash
sail artisan migrate
```

> **思考プロセス:**
> なぜ`is_admin`カラムを追加するのでしょうか？ アプリケーションには、一般ユーザーと管理者という異なる役割（ロール）を持つユーザーが存在します。`is_admin`カラム（フラグ）は、この役割を区別するための最もシンプルな方法です。`default(false)`と設定することで、新規登録ユーザーは自動的に一般ユーザーとなり、意図しない権限昇格を防ぎます。管理者は、開発者がデータベースを直接操作してフラグを`true`に設定することで作成します。より複雑な権限管理が必要な場合は、`laravel-permission`のような専用パッケージを導入することも検討しますが、今回はシンプルな管理者機能なので、この方法が最も手軽で適切です。

## 8-2. 管理者認証ミドルウェアの作成

管理者ユーザーのみが特定のルートにアクセスできるように、専用のミドルウェアを作成します。

### Step 1: ミドルウェアの生成

```bash
sail artisan make:middleware AdminMiddleware
```

### Step 2: ミドルウェアの実装

**`app/Http/Middleware/AdminMiddleware.php`**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403, "管理者権限がありません。");
        }

        return $next($request);
    }
}
```

> **コード解説:**
> ミドルウェアは、コントローラのアクションが実行される「前」に割り込んで処理を行うフィルターのようなものです。
> - `!Auth::check()`: まずリクエストが認証済みユーザーからのものかを確認します。
> - `!Auth::user()->is_admin`: 認証済みユーザーの`is_admin`プロパティが`true`でない（管理者でない）場合、`abort(403)`を呼び出して処理を中断し、403 Forbidden（アクセス禁止）エラーを返します。
> - `return $next($request);`: 上記のチェックを通過した場合のみ、`$next($request)`を呼び出してリクエストを次の処理（この場合はコントローラのアクション）へと渡します。

### Step 3: ミドルウェアの登録

作成したミドルウェアを`app/Http/Kernel.php`に登録して、ルート定義で使えるように「エイリアス（別名）」を設定します。

**`app/Http/Kernel.php`**
```php
protected $routeMiddleware = [
    // ... 既存のミドルウェア
    "admin" => \App\Http\Middleware\AdminMiddleware::class, // この行を追加
];
```

## 8-3. ジャンル管理機能の実装

管理者専用のジャンル管理機能（CRUD）を実装します。

### Step 1: ControllerとRequestの作成

```bash
sail artisan make:controller Admin/GenreController
sail artisan make:request Admin/StoreGenreRequest
sail artisan make:request Admin/UpdateGenreRequest
```

> **思考プロセス:**
> `Admin/GenreController`のようにサブディレクトリを切ることで、管理者向けのコントローラであることがファイル構造から明確になります。これにより、`App\Http\Controllers\Admin`という専用の名前空間が与えられ、一般ユーザー向けのコントローラと区別しやすくなり、大規模なアプリケーションになった際のコードの可読性と保守性が向上します。

### Step 2: ルーティングの設定

管理者用のルートをグループ化し、`admin`ミドルウェアを適用します。

**`routes/web.php`**
```php
use App\Http\Controllers\Admin\GenreController as AdminGenreController;

// ... 他のルート

Route::middleware(["auth", "admin"])->prefix("admin")->name("admin.")->group(function () {
    Route::resource("genres", AdminGenreController::class)->except("show");
});
```

> **コード解説:**
> - `middleware(["auth", "admin"])`: このグループ内のルートにアクセスするには、ログイン認証（`auth`）と管理者認証（`admin`）の両方を通過する必要があります。
> - `prefix("admin")`: グループ内のURLの先頭に自動的に`/admin`が付きます。（例: `/admin/genres`）
> - `name("admin.")`: グループ内のルート名の先頭に自動的に`admin.`が付きます。（例: `admin.genres.index`）
> - `Route::resource(...)`: ジャンル管理に必要なCRUDのルート（index, create, store, edit, update, destroy）をまとめて定義します。`except("show")`で、今回は不要な詳細表示ルートを除外しています。

### Step 3: Controller, Request, Bladeの実装

Controller、Request、Bladeの実装は、これまでのCRUD実装とほぼ同じです。ただし、ルート名やビューのパスに`admin.`や`admin/`といったプレフィックスが付く点に注意してください。

**`app/Http/Controllers/Admin/GenreController.php`**
```php
// ... (実装は前述の通り)
public function destroy(Genre $genre)
{
    if ($genre->books()->exists()) { // exists()の方が効率的
        return back()->with("error", "このジャンルには書籍が紐付いているため削除できません。");
    }
    $genre->delete();
    return redirect()->route("admin.genres.index")->with("success", "ジャンルを削除しました。");
}
```

> **思考プロセス (削除処理):**
> ジャンルを削除する前に、`$genre->books()->exists()`で紐付く書籍が存在するかをチェックしています。`count() > 0`よりも`exists()`の方が、レコードが1件でも見つかった時点で検索を打ち切るため、パフォーマンス的に有利です。データ整合性を保つため、関連データを持つ親レコードを安易に削除できないようにするのは、実務アプリケーションにおける重要な設計判断です。

## 8-4. 動作確認

1.  データベースで特定のユーザーの`is_admin`を`1`（true）に変更します。
2.  一般ユーザーでログインし、`/admin/genres`にアクセスして403エラーが表示されることを確認します。
3.  管理者ユーザーでログインし、`/admin/genres`にアクセスしてジャンル一覧が表示されることを確認します。
4.  ジャンルの新規作成、編集、削除（紐付く書籍がない場合）、削除（紐付く書籍がある場合のエラー）が正しく動作することを確認します。

---

これで、特定の権限を持つユーザーのみが操作できる、安全な管理機能が実装できました。次のChapterでは、検索機能を実装します。
