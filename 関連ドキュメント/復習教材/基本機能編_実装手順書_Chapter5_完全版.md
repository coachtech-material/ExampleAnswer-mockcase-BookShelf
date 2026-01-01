# Chapter 5: 認可機能 (Policy)

このChapterでは、LaravelのPolicy（ポリシー）を使って、特定のユーザーが特定のアクション（更新や削除など）を実行できるかを制御する「認可」機能を実装します。「自分の投稿は編集・削除できるが、他人の投稿はできない」といった、アプリケーションに必須のセキュリティルールを構築します。

## 5-1. Policyとは？ なぜ必要か？

認証（Authentication）が「誰であるか」を確認するプロセスであるのに対し、**認可（Authorization）**は「その人が何をして良いか」を決定するプロセスです。

> **思考プロセス:**
> なぜ認可ロジックを分離するのでしょうか？ この「更新・削除は本人に限る」というロジックを、コントローラ内に直接 `if (Auth::id() !== $book->user_id) { abort(403); }` のように書くことも可能です。しかし、この方法にはいくつかの問題があります。
> 
> - **コードの重複**: 同じ認可ロジックが複数のコントローラやメソッドに散在し、重複コードを生み出します。
> - **責務の混在**: コントローラの本来の責務はリクエストの処理であり、ここに認可という別の関心事が混ざることで、コードが複雑化し、見通しが悪くなります。
> 
> **Policy**は、特定のモデルに対する認可ロジックをカプセル化するための専用クラスです。認可ロジックをPolicyに分離することで、これらの問題を解決し、コードをクリーンで再利用可能、かつテストしやすい状態に保つことができます。

## 5-2. BookPolicyの作成と登録

書籍（Book）に関する認可ロジックをまとめるための`BookPolicy`を作成します。

### Step 1: Policyの生成

```bash
sail artisan make:policy BookPolicy --model=Book
```

### Step 2: Policyの登録

作成したPolicyをLaravelに認識させるため、`AuthServiceProvider`に登録します。

**`app/Providers/AuthServiceProvider.php`**
```php
protected $policies = [
    Book::class => BookPolicy::class,
];
```

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
> - `return $user->id === $book->user_id;`: ユーザーのIDと、書籍に紐付いている`user_id`が一致する場合にのみ`true`（許可）を返します。これにより、「書籍を登録した本人」だけが更新・削除できるというルールを実現します。

## 5-4. ControllerとBladeでの認可チェック

実装したPolicyを使って、ControllerのアクションとBladeテンプレートの両方で認可チェックを行います。

### Step 1: Controllerでの利用

Controllerでは`authorize`メソッドを使います。このメソッドは、認可に失敗した場合、自動的に403 Forbidden HTTPレスポンスを生成して処理を中断します。

**`app/Http/Controllers/BookController.php`**
```php
// ...

public function edit(Book $book)
{
    $this->authorize("update", $book); // 認可チェックを追加
    // ...
}

public function update(UpdateBookRequest $request, Book $book)
{
    $this->authorize("update", $book); // 認可チェックを追加
    // ...
}

public function destroy(Book $book)
{
    $this->authorize("delete", $book); // 認可チェックを追加
    // ...
}
```

### Step 2: Bladeテンプレートでの利用

Bladeでは`@can`ディレクティブを使います。これにより、権限のないユーザーには編集ボタンや削除ボタンそのものを表示しないようにできます。

**`resources/views/books/show.blade.php`**
```blade
@can("update", $book)
    <a href="{{ route("books.edit", $book) }}">編集</a>
@endcan

@can("delete", $book)
    <form action="{{ route("books.destroy", $book) }}" method="POST" onsubmit="return confirm("本当に削除しますか？");">
        @csrf
        @method("DELETE")
        <button type="submit">削除</button>
    </form>
@endcan
```

> **思考プロセス:**
> なぜバックエンド（コントローラ）とフロントエンド（Blade）の両方でチェックを行うのでしょうか？ これは**二重の防御（Defense in Depth）**というセキュリティの原則に基づいています。
> 
> - **フロントエンドでのチェック (`@can`)**: これは主に**UX（ユーザー体験）**のためのものです。権限のない操作ボタンを最初から表示しないようにします。しかし、HTMLを書き換えればこのチェックは簡単に回避できてしまいます。
> - **バックエンドでのチェック (`authorize`)**: これが**本質的なセキュリティ**です。たとえ悪意のあるユーザーがURLを直接叩いたり、リクエストを偽装したりしても、サーバーサイドで必ず権限を検証し、不正な操作をブロックします。

---

これで、アプリケーションに堅牢な認可機能を実装できました。次のChapterでは、レビュー機能の実装に進みます。
