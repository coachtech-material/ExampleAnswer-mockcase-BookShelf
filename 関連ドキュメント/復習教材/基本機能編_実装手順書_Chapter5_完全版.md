'''# Chapter 5: 認可機能 (Policy)

このChapterでは、LaravelのPolicy（ポリシー）を使って、特定のユーザーが特定のアクション（更新や削除など）を実行できるかを制御する「認可」機能を実装します。「自分の投稿は編集・削除できるが、他人の投稿はできない」といった、アプリケーションに必須のセキュリティルールを構築します。

## 5-1. Policyとは？ なぜ必要か？

認証（Authentication）が「誰であるか」を確認するプロセスであるのに対し、**認可（Authorization）**は「その人が何をして良いか」を決定するプロセスです。

> **思考プロセス:**
> なぜ認可ロジックを分離するのでしょうか？ この「更新・削除は本人に限る」というロジックを、コントローラ内に直接 `if (Auth::id() !== $book->user_id) { abort(403); }` のように書くことも可能です。しかし、この方法にはいくつかの問題があります。
> 
> - **コードの重複**: 同じ認可ロジックが複数のコントローラやメソッドに散在し、重複コードを生み出します。
> - **責務の混在**: コントローラの本来の責務はリクエストの処理であり、ここに認可という別の関心事が混ざることで、コードが複雑化し、見通しが悪くなります。
> - **テストの困難**: 認可ロジックがコントローラと密結合していると、単体テストが書きにくくなります。
> 
> **Policy**は、特定のモデルに対する認可ロジックをカプセル化するための専用クラスです。認可ロジックをPolicyに分離することで、これらの問題を解決し、コードをクリーンで再利用可能、かつテストしやすい状態に保つことができます。これは、関心の分離（Separation of Concerns）というソフトウェア設計の重要な原則を実践するものです。

## 5-2. BookPolicyの作成と登録

書籍（Book）に関する認可ロジックをまとめるための`BookPolicy`を作成します。

### Step 1: Policyの生成

`--model=Book` オプションを付けることで、`Book`モデルに対する基本的なメソッド（`viewAny`, `view`, `create`, `update`, `delete`など）の雛形が生成されます。

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
        //
    }
}
```
> **コード解説:**
> `$policies`プロパティにモデルとポリシーのクラスをマッピングします。これにより、Laravelは`Book`モデルに関する認可チェックを行う際に、自動的に`BookPolicy`を使用するようになります。この仕組みにより、コントローラ側でポリシーを意識的にインスタンス化する必要がなくなります。

## 5-3. Policyへのロジック実装

`BookPolicy`の`update`と`delete`メソッドに、具体的な認可ルールを実装します。

**`app/Policies/BookPolicy.php`**
```php
<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

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
> **コード解説:**
> - `update(User $user, Book $book)`: 第一引数には現在認証中の`User`モデル、第二引数には対象となる`Book`モデルのインスタンスが、Laravelによって自動的にDI（依存性注入）されます。
> - `return $user->id === $book->user_id;`: ユーザーのIDと、書籍に紐付いている`user_id`が一致する場合にのみ`true`（許可）を返します。これにより、「書籍を登録した本人」だけが更新・削除できるというルールを実現します。`false`を返すと、アクションは拒否されます。

## 5-4. ControllerとBladeでの認可チェック

実装したPolicyを使って、ControllerのアクションとBladeテンプレートの両方で認可チェックを行います。

### Step 1: Controllerでの利用

Controllerでは`authorize`メソッドを使います。このメソッドは、認可に失敗した場合（Policyのメソッドが`false`を返した場合）、自動的に403 Forbidden HTTPレスポンスを生成して処理を中断します。

**`app/Http/Controllers/BookController.php`**
```php
// ... (use文は省略)

class BookController extends Controller
{
    // ... (index, create, store, showは省略)

    public function edit(Book $book)
    {
        $this->authorize('update', $book); // 認可チェックを追加
        // ...
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorize('update', $book); // 認可チェックを追加
        // ...
    }

    public function destroy(Book $book)
    {
        $this->authorize('delete', $book); // 認可チェックを追加
        // ...
    }
}
```

### Step 2: Bladeテンプレートでの利用

Bladeでは`@can`ディレクティブを使います。これにより、権限のないユーザーには編集ボタンや削除ボタンそのものを表示しないようにできます。

**`resources/views/books/show.blade.php`**
```blade
{{-- ... 書籍詳細情報 ... --}}

@can('update', $book)
    <a href="{{ route('books.edit', $book) }}">編集</a>
@endcan

@can('delete', $book)
    <form action="{{ route('books.destroy', $book) }}" method="POST" onsubmit="return confirm('本当に削除しますか？');">
        @csrf
        @method('DELETE')
        <button type="submit">削除</button>
    </form>
@endcan
```

> **思考プロセス:**
> なぜバックエンド（コントローラ）とフロントエンド（Blade）の両方でチェックを行うのでしょうか？ これは**二重の防御（Defense in Depth）**というセキュリティの原則に基づいています。
> 
> - **フロントエンドでのチェック (`@can`)**: これは主に**UX（ユーザー体験）**のためのものです。権限のない操作ボタンを最初から表示しないことで、ユーザーを混乱させないようにします。しかし、HTMLを書き換えればこのチェックは簡単に回避できてしまいます。
> - **バックエンドでのチェック (`authorize`)**: これが**本質的なセキュリティ**です。たとえ悪意のあるユーザーがURLを直接叩いたり、リクエストを偽装したりしても、サーバーサイドで必ず権限を検証し、不正な操作をブロックします。
> 
> このように、役割の異なる2つのチェックを組み合わせることで、セキュアでかつユーザーフレンドリーなアプリケーションを実現できます。

## 5-5. 動作確認

1.  ユーザーAでログインし、書籍Aを登録します。
2.  ユーザーBでログインし、書籍Aの詳細ページにアクセスします。
3.  書籍Aの詳細ページで、編集・削除ボタンが表示されていないことを確認します。
4.  ユーザーBで、書籍Aの編集ページのURL（例: `/books/1/edit`）に直接アクセスし、「403 This action is unauthorized.」というエラーページが表示されることを確認します。
5.  ユーザーAでログインし直し、書籍Aの編集・削除ができることを確認します。

---

これで、アプリケーションに堅牢な認可機能を実装できました。次のChapterでは、ここまでのバックエンドロジックに対応するビュー（Bladeテンプレート）を作成し、実際に画面から操作できるようにします。
'''
