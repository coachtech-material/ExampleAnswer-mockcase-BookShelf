# Chapter 8: レビューいいね機能

このChapterでは、レビューに対して「いいね」を付けたり解除したりできる機能を実装します。

---

## 8.1. コントローラーの作成

```bash
sail artisan make:controller ReviewLikeController
```

---

## 8.2. ReviewLikeControllerの実装

```php
<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    public function store(Review $review)
    {
        Auth::user()->likedReviews()->syncWithoutDetaching($review->id);
        return back();
    }

    public function destroy(Review $review)
    {
        Auth::user()->likedReviews()->detach($review->id);
        return back();
    }
}
```

---

## 8.3. ルートの追加

`routes/web.php` の認証必須ルートに以下を追加します。

```php
// Review Like management
Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('likes.store');
Route::delete('/reviews/{review}/unlike', [ReviewLikeController::class, 'destroy'])->name('likes.destroy');
```

---

## 8.4. 動作確認

1. 書籍詳細ページのレビュー一覧で「いいね」ボタンをクリックし、いいねが追加できることを確認
2. いいね済みのレビューで「いいね解除」ボタンをクリックし、いいねが解除できることを確認
3. いいね数が正しく表示されることを確認

これで、レビューいいね機能の実装が完了しました。
