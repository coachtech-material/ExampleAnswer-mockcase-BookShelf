# Chapter 4: 認可機能 (Policy)

このChapterでは、LaravelのPolicy（ポリシー）を使って、特定のユーザーが特定のアクション（更新や削除など）を実行できるかを制御する「認可」機能を実装します。

## 4-1. Policyとは？ なぜ必要か？

認可は「誰が何をして良いか」を定義することです。例えば、「自分の投稿は編集・削除できるが、他人の投稿はできない」といったルールです。

**思考プロセス:**
このロジックをControllerに直接書くこともできますが、アプリケーションが複雑になるにつれてControllerが肥大化し、可読性やメンテナンス性が低下します。Policyとして認可ロジックを分離することで、再利用性が高まり、コードがクリーンになります。

## 4-2. BookPolicyの作成と登録

書籍（Book）に関する認可ロジックをまとめるための`BookPolicy`を作成します。

### Step 1: Policyの生成

`--model=Book` オプションを付けることで、`Book`モデルに対する基本的なメソッド（`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`）の雛形が生成されます。

```bash
sail artisan make:policy BookPolicy --model=Book
```

### Step 2: Policyの登録

作成したPolicyをLaravelに認識させるため、`AuthServiceProvider`に登録します。

**`app/Providers/AuthServiceProvider.php`**
```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Policies\BookPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class, // この行を追加
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

**コードリーディング:**
- `$policies`プロパティに、モデルとポリシーのクラスをマッピングします。これにより、Laravelは`Book`モデルに関する認可チェックを行う際に、自動的に`BookPolicy`を使用するようになります。

## 4-3. Policyへのロジック実装

`BookPolicy`の各メソッドに、具体的な認可ルールを実装します。

**`app/Policies/BookPolicy.php`**
```php
<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
```

**コードリーディング:**
- `update(User $user, Book $book)`: 第一引数には現在認証中のユーザー、第二引数には対象となるモデル（この場合は書籍）のインスタンスが渡されます。
- `return $user->id === $book->user_id;`: ユーザーのIDと、書籍に紐付いている`user_id`が一致する場合にのみ`true`を返します。これにより、「書籍を登録した本人」だけが更新・削除できるというルールを実現します。

## 4-4. ControllerとBladeでの認可チェック

実装したPolicyを使って、ControllerのアクションとBladeテンプレートの両方で認可チェックを行います。

### Step 1: Controllerでの利用

Controllerでは`authorize`メソッドを使います。このメソッドは、認可に失敗した場合、自動的に403 Forbiddenレスポンスを返します。

**`app/Http/Controllers/BookController.php`**
```php
// ... (use文は省略)

class BookController extends Controller
{
    // ... (index, create, store, showは省略)

    public function edit(Book $book)
    {
        $this->authorize("update", $book); // 認可チェックを追加
        $genres = Genre::all();
        return view("books.edit", compact("book", "genres"));
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorize("update", $book); // 認可チェックを追加
        $book->update($request->only(["title", "author", "isbn", "description"]));
        $book->genres()->sync($request->genres);

        return redirect()->route("books.show", $book)->with("success", "書籍情報を更新しました。");
    }

    public function destroy(Book $book)
    {
        $this->authorize("delete", $book); // 認可チェックを追加
        $book->delete();

        return redirect()->route("books.index")->with("success", "書籍を削除しました。");
    }
}
```

### Step 2: Bladeテンプレートでの利用

Bladeでは`@can`ディレクティブを使います。これにより、権限のないユーザーには編集ボタンや削除ボタンそのものを表示しないようにできます。

**`resources/views/books/show.blade.php`**
```blade
{{-- ... 書籍詳細情報 ... --}}

@can("update", $book)
    <a href="{{ route("books.edit", $book) }}">編集</a>
@endcan

@can("delete", $book)
    <form action="{{ route("books.destroy", $book) }}" method="POST" onsubmit="return confirm(\'本当に削除しますか？\');">
        @csrf
        @method("DELETE")
        <button type="submit">削除</button>
    </form>
@endcan
```

**思考プロセス:**
Controllerでの認可（バックエンド）とBladeでの認可（フロントエンド）は、両方実装することが重要です。Bladeでボタンを隠すだけでは、URLを直接叩かれた場合に対応できません。逆にControllerだけで制御すると、権限のないユーザーにもボタンが見えてしまい、UX（ユーザー体験）を損ないます。両方でチェックすることで、セキュアで使いやすいアプリケーションになります。

## 4-5. 動作確認

1.  ユーザーAでログインし、書籍Aを登録します。
2.  ユーザーBでログインし、書籍Aの詳細ページにアクセスします。
3.  書籍Aの詳細ページで、編集・削除ボタンが表示されていないことを確認します。
4.  ユーザーBで、書籍Aの編集ページのURL（例: `/books/1/edit`）に直接アクセスし、「403 This action is unauthorized.」というエラーページが表示されることを確認します。
5.  ユーザーAでログインし直し、書籍Aの編集・削除ができることを確認します。

---

これで、アプリケーションに堅牢な認可機能を実装できました。次のChapterでは、レビュー機能の実装に進みます。
