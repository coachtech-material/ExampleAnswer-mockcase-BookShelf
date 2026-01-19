# Chapter 7: レビュー機能の実装

## 🎯 このセクションで学ぶこと

このセクションでは、書籍に対するレビュー（評価とコメント）を投稿・編集・削除する機能を実装します。

- **ネストしたリソース**: 書籍（`Book`）に紐づくレビュー（`Review`）という、親子関係のあるリソースの扱い方を学びます。
- **リレーションを活用したデータ作成**: `$book->reviews()->create([...])`のように、リレーションを通じてデータを作成する方法を学びます。
- **ポリシーによる認可**: 自分が投稿したレビューのみ編集・削除できるように制御します。

---

## 🧠 先輩エンジニアの思考プロセス：なぜこの手順で実装するのか？

Chapter 6と同様に、このChapterでも**段階的に実装**を進めていきます。このアプローチには、以下のような明確なメリットがあります。

| ステップ | 目的 | なぜこのステップが必要か？ |
|:---|:---|:---|
| **1. 機能実装** | まずは「動くもの」を最優先で作成する | 機能が正しく動作しない場合、問題がロジックにあるのか、権限設定にあるのかの切り分けが難しくなる。まずは**機能そのものの正しさを担保する**ことが重要。 |
| **2. 動作確認** | 機能が期待通りに動くかを確認する | この段階では「誰でも編集・削除できる」というセキュリティホールがある状態。しかし、それは**意図的なもの**であり、この後のステップで塞ぐことを前提としている。 |
| **3. 認可実装** | 機能に「セキュリティ」という鎧を着せる | 機能が正しく動くことを確認した上で、初めて「誰がその機能を使えるのか」という権限の問題に取り組む。これにより、**問題の切り分けが容易になり**、手戻りが少なくなる。 |

この「**まず動かす、次に守る**」という考え方は、実務でも非常に重要な開発プロセスです。複雑な問題を一度に解決しようとせず、一つずつ着実にクリアしていくことで、結果的に高品質なコードを効率的に生み出すことができます。

---

## 7.1. フォームリクエストの作成

まずは、レビューの投稿（`store`）と更新（`update`）で利用するフォームリクエストを作成します。これにより、コントローラーからバリデーションロジックを分離できます。

```bash
sail artisan make:request StoreReviewRequest
sail artisan make:request UpdateReviewRequest
```

---

## 7.2. フォームリクエストの実装

作成した2つのフォームリクエストファイルに、バリデーションルールを定義します。

### 📖 コードリーディング：StoreReviewRequest / UpdateReviewRequest

`app/Http/Requests/StoreReviewRequest.php` と `app/Http/Requests/UpdateReviewRequest.php` を以下のように編集します。

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

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function authorize(): bool` | このリクエストの実行を許可するかどうかを決定します。 | `FormRequest`の機能の一つ。現時点では誰でもリクエストできるように`true`を返しますが、将来的には特定の条件下でのみ`true`を返すようにロジックを組むことも可能です。 |
| `public function rules(): array` | このリクエストで受け取るデータに対するバリデーションルールを定義します。 | このメソッドが返す配列に従って、Laravelが自動でバリデーションを実行してくれます。 |
| `'rating' => [...]` | `rating`（評価）フィールドに対するルール。 | 5段階評価を想定しています。 |
| `'required'` | この値は必須項目であることを示します。 | 評価点は必ず入力してもらう必要があります。 |
| `'integer'` | この値は整数でなければならないことを示します。 | 評価は「3.5」のような小数は許可しません。 |
| `'min:1', 'max:5'` | この値は1から5までの範囲でなければならないことを示します。 | 1未満や6以上の不正な値が送られてくるのを防ぎます。 |
| `'comment' => [...]` | `comment`（コメント）フィールドに対するルール。 | レビューコメントを想定しています。 |
| `'nullable'` | この値は空（`null`）でも良いことを示します。 | コメントは任意入力とし、評価だけでも投稿できるようにします。 |
| `'string'` | この値は文字列でなければならないことを示します。 | - |
| `'max:1000'` | この値は最大1000文字までであることを示します。 | データベースの負荷やUIの表示崩れを防ぐため、長すぎるコメントを制限します。 |

---

## 7.3. ReviewController.php の実装 (権限チェックなし)

次に、`app/Http/Controllers/ReviewController.php`に、レビューの投稿・編集・更新・削除のロジックを実装します。

### 📖 コードリーディング：ReviewController（権限チェックなし）

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        $book->reviews()->create([
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review)
    {
        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review)
    {
        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function store(...)` | 新しいレビューを保存するメソッド。 | `POST /books/{book}/reviews`というルートに対応します。 |
| `(StoreReviewRequest $request, Book $book)` | 引数の型ヒントによる**DI（依存性の注入）**。 | Laravelがリクエスト内容から自動で`StoreReviewRequest`と`Book`のインスタンスを生成し、メソッドに渡してくれます。 |
| `$book->reviews()->create([...])` | **リレーション経由でのデータ作成**。 | `Book`モデルと`Review`モデルのリレーションを利用して、新しいレビューを作成します。`book_id`が自動でセットされるため、コードが簡潔になります。 |
| `'user_id' => Auth::id()` | レビューの投稿者として、現在ログインしているユーザーのIDをセットします。 | `Auth::id()`は、`Auth::user()->id`のショートカットです。 |
| `return redirect()->route(...)` | 処理完了後、指定した名前付きルートにリダイレクトさせます。 | ユーザーを投稿後の書籍詳細ページに戻します。 |
| `->with('success', '...')` | **セッションへのフラッシュメッセージ**を保存します。 | リダイレクト先のページで一度だけ表示される「レビューを投稿しました。」というメッセージを設定します。 |
| `public function edit(Review $review)` | レビュー編集画面を表示するメソッド。 | `GET /reviews/{review}/edit`というルートに対応します。ここでもルートモデルバインディングが機能しています。 |
| `return view('reviews.edit', compact('review'))` | `reviews.edit`ビューを表示し、`review`変数を渡します。 | `compact('review')`は`['review' => $review]`と同じ意味のPHPの関数です。 |
| `public function update(...)` | 既存のレビューを更新するメソッド。 | `PUT /reviews/{review}`というルートに対応します。 |
| `$review->update([...])` | 渡されたデータでモデルの属性を更新し、データベースに保存します。 | マスアサインメントを利用しています。`$fillable`に設定された属性のみが更新対象となります。 |
| `public function destroy(Review $review)` | レビューを削除するメソッド。 | `DELETE /reviews/{review}`というルートに対応します。 |
| `$book = $review->book;` | **削除前に**リダイレクト先となる書籍モデルを取得します。 | レビューを削除すると`$review->book`リレーションにアクセスできなくなるため、先に変数に保持しておく必要があります。 |
| `$review->delete()` | 該当するレビューをデータベースから削除します。 | - |

> **💡 ポイント**
> この段階で一度、レビューの投稿、編集、削除が問題なく動作するか確認しましょう。まだポリシーを適用していないため、**どのユーザーでも**他人のレビューを編集・削除できてしまうはずです。この「穴」がある状態を意図的に作り、次のステップで塞いでいきます。

---

## 7.4. ポリシーの作成と実装

レビュー機能が動作することを確認したら、次にセキュリティ（認可）を実装します。

```bash
sail artisan make:policy ReviewPolicy --model=Review
```

### 📖 コードリーディング：ReviewPolicy

作成された`app/Policies/ReviewPolicy.php`を以下のように編集します。

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

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `public function update(...)` | `update`アクションに対する認可ロジック。 | メソッド名はコントローラーのメソッド名や`$this->authorize()`の第一引数に対応します。 |
| `(User $user, Review $review)` | 第一引数には必ず現在の認証済みユーザーが、第二引数以降には関連するモデルが渡されます。 | Laravelが`authorize`メソッド呼び出し時に自動でこれらの引数を渡してくれます。 |
| `: bool` | **戻り値の型宣言**。このメソッドが必ず真偽値（`true`か`false`）を返すことを示します。 | PHP 7から導入された機能で、コードの堅牢性を高めます。 |
| `return $user->id === $review->user_id;` | **認可の核心ロジック**。 | ログインしているユーザーのIDと、レビューを投稿したユーザーのIDが一致するかを比較します。一致すれば`true`（許可）、しなければ`false`（拒否）を返します。`===`は型まで比較する厳密な比較演算子です。 |

---

## 7.5. ポリシーの登録

作成したポリシーをLaravelに認識させるため、`app/Providers/AuthServiceProvider.php`に登録します。これはモデルとポリシーを「紐付ける」作業です。

### 📖 コードリーディング：AuthServiceProvider

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Review; // 追加
use App\Policies\BookPolicy;
use App\Policies\ReviewPolicy; // 追加
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class, // 追加
    ];

    public function boot(): void
    {
        //
    }
}
```

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `protected $policies = [...]` | モデルとポリシーのマッピングを定義するプロパティ。 | ここに登録することで、Laravelは`Review`モデルに対する認可リクエストがあった際に、自動的に`ReviewPolicy`を使用するようになります。 |
| `Review::class => ReviewPolicy::class` | `Review`モデルが対象の場合、`ReviewPolicy`クラスの認可ロジックを適用するという宣言。 | `::class`は、クラスの完全修飾名を文字列として取得するPHPの機能です。 |

---

## 7.6. コントローラーへの権限チェック追加

最後に、`ReviewController`の各メソッドに、ポリシーを使った権限チェックのコードを追加します。これでセキュリティホールが塞がります。

### 📖 コードリーディング：ReviewController（権限チェックあり）

`app/Http/Controllers/ReviewController.php`の`edit`, `update`, `destroy`メソッドを以下のように修正してください。

```php
// app/Http/Controllers/ReviewController.php (修正箇所のみ)

public function edit(Review $review)
{
    // 権限チェックを追加
    $this->authorize('update', $review);
    return view('reviews.edit', compact('review'));
}

public function update(UpdateReviewRequest $request, Review $review)
{
    // 権限チェックを追加
    $this->authorize('update', $review);
    $review->update([
        'rating' => $request->rating,
        'comment' => $request->comment,
    ]);

    return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
}

public function destroy(Review $review)
{
    // 権限チェックを追加
    $this->authorize('delete', $review);
    $book = $review->book;
    $review->delete();

    return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
}
```

| コード / 構文 | 値・機能の解説 | 構文・背景の解説 |
|:---|:---|:---|
| `$this->authorize('update', $review);` | **認可の実行**。`ReviewPolicy`の`update`メソッドを呼び出します。 | `Controller`トレイトが提供する便利なメソッドです。第一引数にポリシーのメソッド名、第二引数にチェック対象のモデルを渡します。認可が下りない場合（ポリシーが`false`を返した場合）、Laravelは自動的に**403 Forbidden**のHTTPレスポンスを生成し、処理を中断します。 |
| `$this->authorize('delete', $review);` | `ReviewPolicy`の`delete`メソッドを呼び出します。 | `edit`と`update`で同じ`update`ポリシーを共有しているのは、「レビューを更新できる人は、編集画面も開けるべき」という自然な権限設定に基づいています。 |

これで、レビュー機能の実装は完了です。他人のレビューの編集・削除ボタンが表示されなくなり、直接URLにアクセスしても403エラーが表示されることを確認してください。
