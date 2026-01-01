# Chapter 7: ジャンル管理機能 (管理者向け)

このChapterでは、管理者のみがアクセスできるジャンル管理機能（CRUD）を実装します。ミドルウェアを使った特定のルートへのアクセス制御が重要なポイントです。

## 7-1. 管理者権限の仕組み

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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

マイグレーションを実行します。

```bash
sail artisan migrate
```

**思考プロセス:**
`is_admin`カラムのデフォルト値を`false`に設定しておくことで、既存のユーザーや新規登録されたユーザーは自動的に一般ユーザーとなります。管理者ユーザーは、データベースを直接操作して`is_admin`を`true`に変更する必要があります。

## 7-2. 管理者認証ミドルウェアの作成

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
            abort(403, '管理者権限がありません。');
        }

        return $next($request);
    }
}
```

**コードリーディング:**
- `!Auth::check()`: まずログインしているかを確認します。
- `!Auth::user()->is_admin`: ログインしているユーザーの`is_admin`プロパティが`true`でない場合（つまり管理者でない場合）をチェックします。
- `abort(403, ...)`: 条件に一致した場合、403 Forbiddenエラーを返して処理を中断します。

### Step 3: ミドルウェアの登録

作成したミドルウェアを`app/Http/Kernel.php`に登録して、ルートで使えるようにします。

**`app/Http/Kernel.php`**
```php
protected $routeMiddleware = [
    // ... 既存のミドルウェア
    'admin' => \App\Http\Middleware\AdminMiddleware::class, // この行を追加
];
```

## 7-3. ジャンル管理機能の実装

管理者専用のジャンル管理機能（CRUD）を実装します。

### Step 1: ControllerとRequestの作成

```bash
sail artisan make:controller Admin/GenreController
sail artisan make:request Admin/StoreGenreRequest
sail artisan make:request Admin/UpdateGenreRequest
```

**思考プロセス:**
`Admin/GenreController`のようにサブディレクトリを切ることで、管理者向けのコントローラであることが明確になります。名前空間も`App\Http\Controllers\Admin`となり、コードの整理に役立ちます。

### Step 2: ルーティングの設定

管理者用のルートをグループ化し、`admin`ミドルウェアを適用します。

**`routes/web.php`**
```php
use App\Http\Controllers\Admin\GenreController as AdminGenreController;

// ... 他のルート

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('genres', AdminGenreController::class)->except('show');
});
```

**コードリーディング:**
- `middleware(['auth', 'admin'])`: ログイン認証と管理者認証の両方を通過する必要があります。
- `prefix('admin')`: URLの先頭に`/admin`が付きます。（例: `/admin/genres`）
- `name('admin.')`: ルート名の先頭に`admin.`が付きます。（例: `admin.genres.index`）

### Step 3: Controllerの実装

`Admin/GenreController`にCRUDロジックを実装します。基本的な構造は通常のCRUDと同じです。

**`app/Http/Controllers/Admin/GenreController.php`**
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Http\Requests\Admin\StoreGenreRequest;
use App\Http\Requests\Admin\UpdateGenreRequest;

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::withCount('books')->paginate(10);
        return view('admin.genres.index', compact('genres'));
    }

    public function create()
    {
        return view('admin.genres.create');
    }

    public function store(StoreGenreRequest $request)
    {
        Genre::create($request->validated());
        return redirect()->route('admin.genres.index')->with('success', 'ジャンルを作成しました。');
    }

    public function edit(Genre $genre)
    {
        return view('admin.genres.edit', compact('genre'));
    }

    public function update(UpdateGenreRequest $request, Genre $genre)
    {
        $genre->update($request->validated());
        return redirect()->route('admin.genres.index')->with('success', 'ジャンルを更新しました。');
    }

    public function destroy(Genre $genre)
    {
        if ($genre->books()->exists()) {
            return back()->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }
        $genre->delete();
        return redirect()->route('admin.genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
```

### Step 4: Requestの実装

バリデーションルールを定義します。

**`app/Http/Requests/Admin/StoreGenreRequest.php`**
```php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255|unique:genres,name',
    ];
}
```

**`app/Http/Requests/Admin/UpdateGenreRequest.php`**
```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255', Rule::unique('genres')->ignore($this->genre)],
    ];
}
```

### Step 5: Bladeテンプレートの作成

`resources/views/admin/genres`ディレクトリを作成し、`index`, `create`, `edit`の各Bladeファイルを作成します。内容は通常のCRUDのビューとほぼ同じですが、URLやルート名が管理者用のもの（`admin.`プレフィックス付き）に変わります。

**`resources/views/admin/genres/index.blade.php`** (抜粋)
```blade
<a href="{{ route('admin.genres.create') }}">新規作成</a>
...
<a href="{{ route('admin.genres.edit', $genre) }}">編集</a>
<form action="{{ route('admin.genres.destroy', $genre) }}" method="POST">
    ...
</form>
```

## 7-4. 動作確認

1.  データベースで特定のユーザーの`is_admin`を`1`（true）に変更します。
2.  一般ユーザーでログインし、`/admin/genres`にアクセスして403エラーが表示されることを確認します。
3.  管理者ユーザーでログインし、`/admin/genres`にアクセスしてジャンル一覧が表示されることを確認します。
4.  ジャンルの新規作成、編集、削除（紐付く書籍がない場合）、削除（紐付く書籍がある場合のエラー）が正しく動作することを確認します。

---

これで、特定の権限を持つユーザーのみが操作できる、安全な管理機能が実装できました。次のChapterでは、検索機能を実装します。
