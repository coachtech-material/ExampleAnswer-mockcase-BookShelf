# Chapter 07: 親子の絆 - レビュー機能を実装する

## 🎯 このセクションで学ぶこと

このチャプターでは、書籍に対するレビュー（評価とコメント）を投稿・編集・削除する機能を実装します。書籍（Book）に紐づくレビュー（Review）という親子関係のあるリソースを、FormRequest・Policy・リレーション経由のデータ作成という3つの仕組みを組み合わせて構築します。

- **ネストしたリソース**: 書籍（`Book`）に紐づくレビュー（`Review`）という、親子関係のあるリソースの扱い方を学びます
- **リレーションを活用したデータ作成**: `$book->reviews()->create([...])`のように、リレーションを通じてデータを作成する方法を学びます
- **ポリシーによる認可**: 自分が投稿したレビューのみ編集・削除できるように制御します
- **フォームリクエストによるバリデーション**: `StoreReviewRequest` / `UpdateReviewRequest` に入力チェックを分離します

## 1. はじめに 📖

### ネストしたリソースとは？

Chapter 06では書籍という「独立したリソース」のCRUDを実装しました。このチャプターでは、レビューという「書籍に従属するリソース」を扱います。

レビューは単独では存在できません。必ず「どの書籍に対するレビューか」という親子関係があります。このような関係をWebアプリケーションで表現する際、URLにも親子関係を反映させるのが一般的です:

- `POST /books/{book}/reviews` — 書籍に対するレビューを投稿
- `GET /reviews/{review}/edit` — レビューを編集
- `PUT /reviews/{review}` — レビューを更新
- `DELETE /reviews/{review}` — レビューを削除

投稿時は「どの書籍か」が必要なのでURLに `{book}` を含みますが、編集・更新・削除はレビュー自体のIDで特定できるため `{review}` のみで十分です。

### このチャプターで扱う範囲

- `ReviewController`（store / edit / update / destroy）
- `StoreReviewRequest` / `UpdateReviewRequest`（バリデーション）
- `ReviewPolicy`（認可 — Chapter 06で作成済み、ここで活用します）

## 2. 要件の確認 📋

### 画面・操作一覧

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 | 認可 |
|:---|:---|:---|:---|:---|:---|
| レビュー投稿 | POST | `/books/{book}/reviews` | ReviewController@store | 必要 | — |
| レビュー編集フォーム | GET | `/reviews/{review}/edit` | ReviewController@edit | 必要 | ReviewPolicy@update（投稿者のみ） |
| レビュー更新処理 | PUT | `/reviews/{review}` | ReviewController@update | 必要 | ReviewPolicy@update（投稿者のみ） |
| レビュー削除処理 | DELETE | `/reviews/{review}` | ReviewController@destroy | 必要 | ReviewPolicy@delete（投稿者のみ） |

### バリデーションルール

| 項目 | ルール | エラーメッセージ |
|:---|:---|:---|
| rating | required / integer / min:1 / max:5 | 評価は必須です。/ 評価は1〜5の整数で入力してください。 |
| comment | required / string / max:1000 | コメントは必須です。/ コメントは1000文字以内で入力してください。 |

### 認可ルール（ReviewPolicy）

| 操作 | 条件 | 違反時 |
|:---|:---|:---|
| レビュー編集（update） | `$user->id === $review->user_id`（投稿者本人のみ） | 403 Forbidden |
| レビュー削除（delete） | `$user->id === $review->user_id`（投稿者本人のみ） | 403 Forbidden |

## 3. 先輩エンジニアの思考プロセス 💭

レビュー機能の実装は、書籍管理機能（Chapter 06）と似ていますが、一つ大きな違いがあります。それは「**親子関係**」です。レビューは必ず特定の「書籍」に紐づきます。この関係性をどう設計し、実装に落とし込むかがこのChapterの鍵となります。

### Point 1: ルート設計 — URLで親子関係を表現する

レビューは単独では存在せず、必ず書籍に属します。投稿時のURLは `POST /books/{book}/reviews` のように親子関係を表現します。一方、編集・更新・削除はレビューのIDだけで特定できるため、`/reviews/{review}` とシンプルにします。

### Point 2: リレーション経由でデータを作成する

レビューを作成する際、`book_id` を手動でセットするのは面倒で間違いのもとです。`$book->reviews()->create([...])` を使えば、Laravelが自動で `book_id` をセットしてくれます。

```php
// ❌ 手動で book_id をセット
Review::create([
    'book_id' => $book->id,
    'user_id' => Auth::id(),
    ...
]);

// ✅ リレーション経由で作成（book_id は自動セット）
$book->reviews()->create([
    'user_id' => Auth::id(),
    ...
]);
```

### Point 3: Policyで認可を一元管理する

「自分のレビューは自分で編集・削除できるが、他人のレビューは触れない」というルールは、Chapter 06 で作成した `ReviewPolicy` に既に定義されています。コントローラーからは `$this->authorize('update', $review)` の一行で呼び出すだけです。

### Point 4: 削除前にリレーション先を退避する

`destroy` メソッドでは、レビュー削除後に書籍詳細ページにリダイレクトする必要があります。しかし、`$review->delete()` した後では `$review->book` にアクセスできなくなります。そのため、削除前に `$book = $review->book;` で退避しておきます。

## 4. 実装 🚀

### 4.1. FormRequestの作成

レビューの投稿と更新で利用するフォームリクエストを作成します。

```bash
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
```

#### `app/Http/Requests/StoreReviewRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は整数で入力してください。',
            'rating.min' => '評価は1〜5の整数で入力してください。',
            'rating.max' => '評価は1〜5の整数で入力してください。',
            'comment.required' => 'コメントは必須です。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
```

#### `app/Http/Requests/UpdateReviewRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価は必須です。',
            'rating.integer' => '評価は整数で入力してください。',
            'rating.min' => '評価は1〜5の整数で入力してください。',
            'rating.max' => '評価は1〜5の整数で入力してください。',
            'comment.required' => 'コメントは必須です。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
```

### 4.2. ReviewControllerの実装

`ReviewController` は Chapter 06 の「ルート定義とコントローラーの準備」で既に作成済みです。中身を実装していきましょう。

#### `app/Http/Controllers/ReviewController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * レビューを投稿
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $book->reviews()->create([
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    /**
     * レビュー編集フォームを表示
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    /**
     * レビューを更新
     */
    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューを削除
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

### 4.3. ReviewPolicy（Chapter 06で作成済み）

`ReviewPolicy` は Chapter 06 の「Policyの作成」で既に作成・登録済みです。参考として内容を再掲します。

#### `app/Policies/ReviewPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Determine whether the user can update the review.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * Determine whether the user can delete the review.
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
```

## 5. コードの詳細解説 🔍

### FormRequest の解説

| コード / 構文 | 解説 |
|:---|:---|
| `use Illuminate\Contracts\Validation\ValidationRule` | バリデーションルールの型を示すインターフェース。PHPDocの `@return` 型で使用されています。 |
| `public function authorize(): bool` | このリクエストの実行を許可するかどうかを決定します。`true` を返すことで誰でもリクエスト可能にしています。 |
| `public function rules(): array` | バリデーションルールを配列で返します。Laravelが自動でバリデーションを実行します。 |
| `'rating' => ['required', 'integer', 'min:1', 'max:5']` | 評価は必須・整数・1〜5の範囲。5段階評価を想定しています。 |
| `'comment' => ['required', 'string', 'max:1000']` | コメントは必須入力。文字列で最大1000文字。 |
| `public function messages(): array` | バリデーションエラー時のカスタムメッセージを日本語で定義します。 |

### ReviewController の解説

| コード / 構文 | 解説 |
|:---|:---|
| `store(StoreReviewRequest $request, Book $book): RedirectResponse` | レビュー投稿メソッド。`StoreReviewRequest` でバリデーション済みのデータと、ルートモデルバインディングで取得した `Book` を受け取ります。 |
| `$book->reviews()->create([...])` | **リレーション経由でのデータ作成**。`Book` モデルの `reviews()` リレーションを通じてレビューを作成します。`book_id` が自動セットされます。 |
| `'user_id' => Auth::id()` | レビューの投稿者として、現在ログインしているユーザーのIDをセットします。`Auth::id()` は `Auth::user()->id` のショートカットです。 |
| `return redirect()->route('books.show', $book)->with('success', '...')` | 書籍詳細ページにリダイレクトし、セッションにフラッシュメッセージを保存します。 |
| `$this->authorize('update', $review)` | `ReviewPolicy` の `update` メソッドを呼び出します。認可が下りない場合、403 Forbidden エラーが自動的に返されます。 |
| `$review->update([...])` | 渡されたデータでモデルの属性を更新し、データベースに保存します。`$fillable` に設定された属性のみが更新対象です。 |
| `$book = $review->book;` | **削除前に**リダイレクト先の書籍モデルを退避します。`$review->delete()` 後は `$review->book` にアクセスできなくなるためです。 |
| `$review->delete()` | 該当するレビューをデータベースから削除します。 |

### ReviewPolicy の解説

| コード / 構文 | 解説 |
|:---|:---|
| `update(User $user, Review $review): bool` | 第一引数には現在の認証済みユーザーが、第二引数には対象のレビューが自動で渡されます。 |
| `return $user->id === $review->user_id;` | ログインユーザーのIDとレビュー投稿者のIDが一致するかを厳密比較（`===`）します。`true` なら許可、`false` なら403エラーです。 |

## 6. この実装にたどり着くための調べ方 🧐

### Step 1: 公式ドキュメントを読みやすくまとめる

**プロンプト例**
```
以下はLaravelのEloquentリレーションに関する公式ドキュメントの一部です。
特に「リレーション経由でのデータ作成（create, save）」に焦点を当てて
分かりやすくまとめてください。

出力してほしい内容：
- 重要ポイント（10行以内）
- create() と save() の違い
- 親子関係のあるデータ作成でよくある落とし穴
- 最小で動かすための手順

--- ここから ---
（ここにLaravelのEloquent Relationshipsに関する公式ドキュメントを貼り付ける）
--- ここまで ---
```

### Step 2: 「なぜそうなる？」をはっきりさせる

**プロンプト例**
```
LaravelでネストしたリソースのCRUDを実装しようとしています。
私の理解はこうです：
「レビューは書籍に従属するので、投稿時は $book->reviews()->create() で
book_id を自動セットする。認可は ReviewPolicy で投稿者チェックを行い、
コントローラーからは $this->authorize() で呼び出す。」

お願い：
1) 正しいかチェックして、間違いがあれば反例で教えてください
2) $book->reviews()->create() の内部で何が起きているか説明してください
3) authorize() が失敗した場合の処理フローを教えてください
4) destroy メソッドで削除前に $book = $review->book; とする理由を教えてください
```

### Step 3: 設計レビュー

**プロンプト例**
```
以下のReviewControllerの設計をレビューしてください。

- 目的：書籍に対するレビューのCRUD（投稿者のみ編集・削除可能）
- 制約：Laravel 10, PHP 8.2, Blade使用
- 設計案：
（ここにReviewController.phpのコードを貼り付ける）

見てほしい観点：
- store で book_id が正しくセットされるか
- authorize の呼び出し位置は適切か
- destroy でのリダイレクト先は正しいか
```

## 7. 動作確認 ✅

以下の項目を確認してください。

1. **レビュー投稿**
   - ログイン後、書籍詳細ページからレビューを投稿できる
   - 投稿後「レビューを投稿しました。」が表示される
   - 評価を空欄で投稿すると「評価は必須です。」が表示される

2. **レビュー編集**
   - 自分が投稿したレビューの編集ボタンが表示される
   - 編集フォームに既存の値がセットされている
   - 更新後「レビューを更新しました。」が表示される

3. **レビュー削除**
   - 自分が投稿したレビューの削除ボタンが表示される
   - 削除後「レビューを削除しました。」が表示される

4. **認可チェック**
   - 他人のレビューの編集・削除ボタンが表示されない
   - 他人のレビューの編集URLに直接アクセスすると403エラーが表示される

## 8. まとめ ✨

このチャプターでは、書籍に紐づくレビュー機能を実装しました。

- **リレーション経由のデータ作成**: `$book->reviews()->create([...])` で `book_id` を自動セットし、安全かつ簡潔にレビューを作成しました
- **FormRequestによるバリデーション分離**: `StoreReviewRequest` / `UpdateReviewRequest` にバリデーションルールとエラーメッセージを集約しました
- **Policyによる認可**: `ReviewPolicy` で「投稿者のみ編集・削除可能」というルールを定義し、`$this->authorize()` で一元的に呼び出しました
- **削除前のリレーション退避**: `$book = $review->book;` で削除後のリダイレクト先を確保するパターンを学びました

次の Chapter 08 では、書籍に対する**お気に入り機能**を実装します。多対多リレーションの `toggle()` メソッドを使った効率的な登録・解除の切り替えを学びます。
