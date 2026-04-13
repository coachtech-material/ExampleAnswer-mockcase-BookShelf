# Chapter 07: 親子の絆 - レビュー機能を実装する

## 🎯 このChapterの目標

このチャプターでは、書籍に対するレビュー（評価とコメント）を投稿・編集・削除する機能を実装します。

- **ネストしたリソース**: 書籍（`Book`）に紐づくレビュー（`Review`）という、親子関係のあるリソースの扱い方
- **リレーションを活用したデータ作成**: `$book->reviews()->create([...])`
- **ポリシーによる認可**: 自分が投稿したレビューのみ編集・削除可能

---

## 📖 背景知識

### ネストしたリソースとは？

レビューは単独では存在できません。必ず「どの書籍に対するレビューか」という親子関係があります。

- `POST /books/{book}/reviews` -- 書籍に対するレビューを投稿
- `GET /reviews/{review}/edit` -- レビューを編集
- `PUT /reviews/{review}` -- レビューを更新
- `DELETE /reviews/{review}` -- レビューを削除

---

## 📋 要件の確認

### バリデーションルール

| 項目 | ルール | エラーメッセージ |
|:---|:---|:---|
| rating | required / integer / min:1 / max:5 | 評価は必須です。/ 評価は1~5の整数で入力してください。 |
| comment | nullable / string / max:1000 | コメントは1000文字以内で入力してください。 |

---

## 💭 なぜこう作るのか？

### Point 1: リレーション経由でデータを作成する

```php
// リレーション経由で作成（book_id は自動セット）
$book->reviews()->create([
    'user_id' => Auth::id(),
    ...
]);
```

### Point 2: 削除前にリレーション先を退避する

`$review->delete()` した後では `$review->book` にアクセスできなくなるため、削除前に `$book = $review->book;` で退避しておきます。

---

## 🚀 コードの実装

### `app/Http/Controllers/ReviewController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        $book->reviews()->create([
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment ?? '',
        ]);

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review)
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);
        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment ?? '',
        ]);

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);
        $book = $review->book;
        $review->delete();

        return redirect()->route('books.show', $book)->with('success', 'レビューを削除しました。');
    }
}
```

### FormRequest（`StoreReviewRequest` / `UpdateReviewRequest`）

```php
public function rules(): array
{
    return [
        'rating' => ['required', 'integer', 'min:1', 'max:5'],
        'comment' => ['nullable', 'string', 'max:1000'],
    ];
}
```

---

## 🔍 コードリーディング

| コード | 解説 |
|:---|:---|
| `$book->reviews()->create([...])` | リレーション経由でのデータ作成。`book_id` が自動セット。 |
| `'user_id' => Auth::id()` | 現在ログインしているユーザーのID。 |
| `$request->comment ?? ''` | コメントが空の場合は空文字をセット。 |
| `$book = $review->book;` | 削除前にリダイレクト先の書籍を退避。 |

---

## 🧐 調べ方のヒント

| 疑問 | プロンプト例 |
|:---|:---|
| リレーション経由の作成 | 「Laravel でリレーション経由でデータを作成する方法を教えてください。$book->reviews()->create() の仕組みも知りたいです。」 |

---

## ✅ 動作確認

1. ログイン後、書籍詳細ページからレビューを投稿できる
2. 自分が投稿したレビューの編集・削除ができる
3. 他人のレビューの編集URLに直接アクセスすると403エラー

---

## ✨ このChapterのまとめ

- **リレーション経由のデータ作成**: `$book->reviews()->create([...])` で `book_id` を自動セット
- **Policyによる認可**: 「投稿者のみ編集・削除可能」
- **削除前のリレーション退避**: `$book = $review->book;`

次の Chapter 08 では、書籍に対する**お気に入り機能**を実装します。
