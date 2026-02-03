# Chapter 7: レビュー機能の実装

## 🎯 このセクションで学ぶこと

このセクションでは、書籍に対するレビュー（評価とコメント）を投稿・編集・削除する機能を実装します。

- **ネストしたリソース**: 書籍（`Book`）に紐づくレビュー（`Review`）という、親子関係のあるリソースの扱い方を学びます。
- **リレーションを活用したデータ作成**: `$book->reviews()->create([...])`のように、リレーションを通じてデータを作成する方法を学びます。
- **ポリシーによる認可**: 自分が投稿したレビューのみ編集・削除できるように制御します。
- **型定義の活用**: コントローラーやフォームリクエストに引数と戻り値の型を追加し、コードの可読性と堅牢性を向上させます。

---

## 🧠 先輩エンジニアの思考プロセス：なぜこの手順で実装するのか？

レビュー機能の実装は、書籍管理機能（Chapter 6）と似ていますが、一つ大きな違いがあります。それは「**親子関係**」です。レビューは必ず特定の「書籍」に紐づきます。この関係性をどう設計し、実装に落とし込むかがこのChapterの鍵となります。

| 設計・実装のポイント | 思考プロセス |
|:---|:---|
| **1. ルート設計** | レビューは単独では存在せず、必ず書籍に属する。ならばURLも`books/1/reviews`のように親子関係を表現すべき。→ **ネストしたリソースルート**を採用しよう。 |
| **2. データ作成** | レビューを作成する際、どの書籍に対するレビューなのかを`book_id`で示す必要がある。手動で`book_id`をセットするのは面倒だし、間違いのもと。→ **リレーション（`$book->reviews()`）経由で作成**すれば、Laravelが自動で`book_id`をセットしてくれるので安全で楽だ。 |
| **3. バリデーション** | レビュー投稿・更新時の入力値チェックは必須。毎回コントローラーに書くのは冗長。→ **フォームリクエスト**にバリデーションロジックを分離して、コントローラーをスリムに保とう。 |
| **4. 認可（権限管理）** | 「自分のレビューは自分で編集・削除できるが、他人のレビューは触れない」というルールは必須。`if`文でコントローラーに書くこともできるが、認可ロジックが散らばってしまう。→ **ポリシー**に認可ロジックを集約し、コントローラーからは`$this->authorize()`の一言で呼び出すだけにしよう。 |
| **5. 型定義** | 各メソッドが何を受け取り、何を返すのかを明確にしたい。→ **引数と戻り値に型定義**を追加して、コードの意図を明確にし、予期せぬエラーを防ごう。 |

このように、一つ一つの機能を「**どう実装するのが最もLaravelらしく、安全で、メンテナンスしやすいか**」と考えながら、適切な道具（リソースルート、リレーション、フォームリクエスト、ポリシーなど）を選択していくのが、良い設計への近道です。

---

## 7.1. フォームリクエストの作成と実装

まずは、レビューの投稿（`store`）と更新（`update`）で利用するフォームリクエストを作成し、バリデーションルールを定義します。

```bash
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
```

`app/Http/Requests/StoreReviewRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

`app/Http/Requests/UpdateReviewRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

#### 📖 コードリーディング：FormRequest

| メソッド / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function authorize(): bool` | このリクエストの実行を許可するかどうかを決定します。`: bool`は戻り値が真偽値であることを示します。ここでは一旦`true`を返します。 |
| `public function rules(): array` | バリデーションルールを配列形式で返します。`: array`は戻り値が配列であることを示します。 |
| `'rating' => ['required', 'integer', 'min:1', 'max:5']` | `rating`は必須、整数、1〜5の範囲というルールを定義しています。 |
| `'comment' => ['nullable', 'string', 'max:1000']` | `comment`は任意入力（`nullable`）、文字列、最大1000文字というルールを定義しています。 |

---

## 7.2. ReviewControllerの実装

次に、`ReviewController`にレビューの投稿・編集・更新・削除のロジックを実装します。

`app/Http/Controllers/ReviewController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $book->reviews()->create([
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review): View
    {
        $this->authorize('update', $review);
        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);
        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);
        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

#### 📖 コードリーディング：ReviewController

| メソッド | コード解説 |
|:---|:---|
| `public function store(StoreReviewRequest $request, Book $book): RedirectResponse` | `: RedirectResponse`は、処理後に別のページへリダイレクトすることを示します。`Book $book`でレビュー対象の書籍モデルを受け取ります。 |
| `$book->reviews()->create([...])` | **リレーション経由でのデータ作成**。`book_id`が自動でセットされるため、コードが簡潔かつ安全になります。 |
| `public function edit(Review $review): View` | `: View`は、このメソッドが`View`オブジェクト（HTMLページ）を返すことを示します。`Review $review`で編集対象のレビューモデルを受け取ります。 |
| `public function update(UpdateReviewRequest $request, Review $review): RedirectResponse` | `: RedirectResponse`でリダイレクトすることを示します。`UpdateReviewRequest`でバリデーションを行い、対象の`Review`モデルを更新します。 |
| `public function destroy(Review $review): RedirectResponse` | `: RedirectResponse`でリダイレクトすることを示します。対象の`Review`モデルを削除します。 |
| `$book = $review->book;` | **削除前に**リダイレクト先となる書籍モデルを取得します。レビューを削除すると`$review->book`リレーションにアクセスできなくなるためです。 |

> **✅ 動作確認**
> この時点で、レビューの投稿、編集、削除が動作することを確認しましょう。ただし、まだポリシーを有効にしていないため、他人のレビューも操作できてしまいます。

---

## 7.3. ポリシーの作成と適用

最後に、セキュリティ（認可）を実装して、自分のレビューしか操作できないようにします。

### 7.3.1. ポリシーの作成

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

`app/Policies/ReviewPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

#### 📖 コードリーディング：ReviewPolicy

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function update(User $user, Review $review): bool` | `update`アクションに対する認可ロジック。戻り値の型`: bool`は、このメソッドが必ず真偽値を返すことを保証します。 |
| `return $user->id === $review->user_id;` | **認可の核心ロジック**。ログインしているユーザーのIDと、レビューを投稿したユーザーのIDが一致するかを比較します。`true`なら許可、`false`なら拒否（403エラー）となります。 |

### 7.3.2. ポリシーの登録

`app/Providers/AuthServiceProvider.php`に`ReviewPolicy`を登録します。

```php
// ...
use App\Models\Review;
use App\Policies\ReviewPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class, // 追加
    ];
// ...
```

### 7.3.3. コントローラーへの権限チェック追加

`ReviewController`の`edit`, `update`, `destroy`メソッドに`$this->authorize()`を追加します。（上記`ReviewController`のコードには既に追加済みです）

| コード | 解説 |
|:---|:---|
| `$this->authorize('update', $review);` | `ReviewPolicy`の`update`メソッドを呼び出します。認可が下りない場合、Laravelは自動的に**403 Forbidden**エラーを返します。 |

> **✅ 最終動作確認**
> - 自分が投稿したレビューの編集・削除ができること。
> - 他人が投稿したレビューの編集・削除ボタンが表示されず、直接URLにアクセスすると403エラーになること。
> 
> 上記を確認できれば、レビュー機能の実装は完了です。
