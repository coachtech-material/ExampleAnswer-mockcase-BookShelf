# Chapter 10: 総まとめと応用

このChapterでは、これまでに学んだ知識を応用し、より高度でクリーンなコードを書くためのテクニックとして、サービスコンテナ、イベント、通知について学びます。

## 10-1. サービスコンテナと依存性の注入 (DI)

サービスコンテナは、クラスの依存関係を管理し、依存性の注入（Dependency Injection, DI）を行うための強力なツールです。これまでControllerのメソッドで`Request`やモデルクラスを引数に受け取ってきましたが、これもDIの一種です。

### 具体例: 独自のサービスクラスを作成する

例えば、書籍のランキングを集計するロジックが複雑になったとします。このロジックをControllerに直接書くのではなく、専用の`RankingService`クラスに切り出してみましょう。

**Step 1: サービスクラスの作成**

`app/Services/RankingService.php` を作成します。

```php
<?php

namespace App\Services;

use App\Models\Book;

class RankingService
{
    public function getTopRatedBooks(int $limit = 10)
    {
        return Book::withAvg("reviews", "rating")
            ->orderByDesc("reviews_avg_rating")
            ->take($limit)
            ->get();
    }
}
```

**Step 2: ControllerでDIを利用する**

`BookController`のコンストラクタで`RankingService`を注入します。

```php
<?php

namespace App\Http\Controllers;

use App\Services\RankingService; // インポート

class BookController extends Controller
{
    private $rankingService;

    public function __construct(RankingService $rankingService) // DI
    {
        $this->rankingService = $rankingService;
    }

    public function ranking()
    {
        $topBooks = $this->rankingService->getTopRatedBooks();
        return view("books.ranking", compact("topBooks"));
    }
}
```

**思考プロセス:**
- **なぜDIを使うのか？**: `new RankingService()`のようにController内で直接インスタンス化する（ハードコーディングする）と、`RankingService`の仕様変更（例: コンストラクタに引数が必要になる）があった場合に、このサービスクラスを利用している全てのControllerを修正する必要があり、非常に手間がかかります。DIを使えば、Laravelのサービスコンテナが自動でインスタンスの生成と注入を行ってくれるため、クラス間の結合度が下がり（疎結合）、変更に強い柔軟なコードになります。
- **テストが容易になる**: DIを利用していると、テスト時に本物の`RankingService`の代わりに、モック（偽物）のサービスを注入できます。これにより、他のクラスに依存しない、独立した単体テスト（ユニットテスト）が容易になります。

## 10-2. イベントとリスナー

イベントは、アプリケーションで特定の出来事が発生したことを知らせる仕組みです。例えば「ユーザーが登録された」「注文が完了した」などです。そのイベントを「聞き耳を立てて」待っているのがリスナーで、イベントが発生すると特定のアクション（例: 確認メールを送信する）を実行します。

### 具体例: 書籍が登録されたらログを記録する

**Step 1: イベントとリスナーの生成**

```bash
sail artisan event:generate
```

`app/Providers/EventServiceProvider.php`にイベントとリスナーを登録します。

```php
protected $listen = [
    // ...
    'App\Events\BookRegistered' => [
        'App\Listeners\LogBookRegistration',
    ],
];
```

上記のコマンドを実行すると、`app/Events/BookRegistered.php`と`app/Listeners/LogBookRegistration.php`が自動生成されます。

**Step 2: イベントクラスの実装**

イベント発生時にリスナーに渡したいデータを定義します。

```php
// app/Events/BookRegistered.php

public $book;

public function __construct(Book $book)
{
    $this->book = $book;
}
```

**Step 3: リスナークラスの実装**

イベントを受け取って実行する処理を`handle`メソッドに記述します。

```php
// app/Listeners/LogBookRegistration.php

use App\Events\BookRegistered;
use Illuminate\Support\Facades\Log;

public function handle(BookRegistered $event): void
{
    Log::info("新しい書籍が登録されました: " . $event->book->title);
}
```

**Step 4: イベントの発行**

`BookController`の`store`メソッドで、書籍が保存された後にイベントを発行します。

```php
// app/Http/Controllers/BookController.php

use App\Events\BookRegistered; // インポート

public function store(StoreBookRequest $request)
{
    // ... 書籍の保存処理 ...
    $book = Book::create(...);

    BookRegistered::dispatch($book); // イベントを発行

    return redirect()->route("books.show", $book);
}
```

**思考プロセス:**
- **なぜイベントを使うのか？**: イベントを使うことで、主要な処理（書籍の登録）と付随的な処理（ログ記録、メール送信、在庫更新など）を分離できます。これにより、`BookController`は「書籍を登録する」という本来の責務に集中でき、コードの見通しが良くなります。また、将来「書籍登録時にSlack通知も送りたい」となった場合、新しいリスナーを追加するだけで済み、既存のControllerのコードを変更する必要がありません。

## 10-3. 通知 (Notification)

通知は、メール、Slack、SMSなど、様々なチャネルを通じてユーザーに情報を送るための機能です。イベントと似ていますが、より「通知を送る」という目的に特化しています。

### 具体例: レビューが投稿されたら書籍の著者にメールで通知する

**Step 1: 通知クラスの作成**

```bash
sail artisan make:notification ReviewPostedNotification
```

**Step 2: 通知クラスの実装**

```php
// app/Notifications/ReviewPostedNotification.php

use App\Models\Review;

class ReviewPostedNotification extends Notification
{
    // ...
    protected $review;

    public function __construct(Review $review)
    {
        $this->review = $review;
    }

    public function via(object $notifiable): array
    {
        return ["mail"]; // 通知チャネルとしてメールを指定
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject("あなたの書籍に新しいレビューが投稿されました")
                    ->line($this->review->user->name . "さんがあなたの書籍「" . $this->review->book->title . "」にレビューを投稿しました。")
                    ->line("評価: " . $this->review->rating)
                    ->line("コメント: " . $this->review->comment)
                    ->action("書籍を確認する", url("/books/" . $this->review->book->id))
                    ->line("ご利用ありがとうございます！");
    }
}
```

**Step 3: 通知の送信**

`ReviewController`の`store`メソッドで通知を送信します。

```php
// app/Http/Controllers/ReviewController.php

use App\Notifications\ReviewPostedNotification; // インポート

public function store(StoreReviewRequest $request, Book $book)
{
    // ... レビューの保存処理 ...
    $review = $book->reviews()->save(...);

    // 書籍の著者（Userモデル）に通知を送信
    $book->user->notify(new ReviewPostedNotification($review));

    return redirect()->route("books.show", $book);
}
```

**思考プロセス:**
- **Notifiableトレイト**: `User`モデルにはデフォルトで`Notifiable`トレイトが使われており、`notify()`メソッドを呼び出すことができます。
- **柔軟なチャネル**: `via()`メソッドで返す配列に`slack`や`database`を追加するだけで、同じ通知内容をSlackやデータベース（サイト内通知など）にも簡単に送信できます。通知のロジックと送信先のチャネルが分離されているため、拡張性が非常に高いです。

---

お疲れ様でした！これで基本機能編は終了です。これらの応用的なテクニックを使いこなすことで、よりメンテナンス性が高く、スケーラブルなアプリケーションを構築できるようになります。
