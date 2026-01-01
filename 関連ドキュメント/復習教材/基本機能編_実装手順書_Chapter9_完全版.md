# Chapter 9: 非同期処理 (Queue)

このChapterでは、LaravelのQueue（キュー）を使って、時間のかかる処理をバックグラウンドで非同期に実行する方法を学びます。これにより、ユーザーの待ち時間を短縮し、アプリケーションの応答性を向上させることができます。

## 9-1. なぜ非同期処理が必要か？

例えば、ユーザー登録時に確認メールを送る、動画をアップロードしてエンコードする、大量のデータを処理してレポートを生成する、といった処理は完了までに数秒から数分かかることがあります。これらの処理を通常のWebリクエスト（同期処理）内で行うと、ユーザーはその間ずっと待たされ、UX（ユーザー体験）を著しく損ないます。

**思考プロセス:**
時間のかかる処理を「ジョブ」としてキューに投入し、Webサーバーとは別の「キューワーカー」というプロセスにバックグラウンドで実行させることで、ユーザーはすぐに次の操作に進むことができます。これが非同期処理の基本的な考え方です。

## 9-2. キューの設定

Laravelでは様々なキューのドライバ（Redis, Amazon SQS, データベースなど）が利用できますが、今回は最も手軽に試せる`database`ドライバを使用します。

### Step 1: キュードライバの変更

`.env`ファイルで、キュードライバを`database`に設定します。

**`.env`**
```
QUEUE_CONNECTION=database
```

### Step 2: キュー用のテーブル作成

キューに投入されたジョブを保存するためのテーブルを作成します。

```bash
sail artisan queue:table
sail artisan migrate
```

これにより、`jobs`テーブルと`failed_jobs`テーブルが作成されます。

## 9-3. Jobの作成

非同期で実行したい処理の単位を「Job（ジョブ）」として作成します。ここでは例として、書籍のインポート処理を行うJobを作成します。

### Step 1: Jobの生成

```bash
sail artisan make:job ImportBookJob
```

これにより、`app/Jobs/ImportBookJob.php`が生成されます。

### Step 2: Jobの実装

Jobクラスの`handle`メソッドに、非同期で実行したい処理を記述します。

**`app/Jobs/ImportBookJob.php`**
```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // Logファサードをインポート

class ImportBookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bookData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $bookData)
    {
        $this->bookData = $bookData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // ここに時間のかかる重い処理を記述する
        // 例: 外部APIから書籍情報を取得、DBに保存するなど

        Log::info("書籍インポート処理を開始します: " . $this->bookData["title"]);

        // 5秒待機して重い処理をシミュレート
        sleep(5);

        // 本来はここでDBに保存する
        // Book::create($this->bookData);

        Log::info("書籍インポート処理が完了しました: " . $this->bookData["title"]);
    }
}
```

**コードリーディング:**
- `implements ShouldQueue`: このインターフェースを実装することで、LaravelはこのJobがキューで非同期に実行されるべきだと認識します。
- `__construct(array $bookData)`: Jobのインスタンスを作成する際に、処理に必要なデータ（ここでは書籍データ）を受け取ります。
- `handle()`: このメソッドがキューワーカーによって呼び出され、実際の処理が実行されます。今回は`sleep(5)`で重い処理を擬似的に再現し、ログにメッセージを出力しています。

## 9-4. Jobのディスパッチ（投入）

作成したJobをキューに投入（ディスパッチ）します。これは通常、Controllerなどから行います。

### Step 1: テスト用のControllerとルートを追加

```bash
sail artisan make:controller TestQueueController
```

**`routes/web.php`**
```php
use App\Http\Controllers\TestQueueController;

Route::get("/test-queue", [TestQueueController::class, "dispatchJob"]);
```

**`app/Http/Controllers/TestQueueController.php`**
```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ImportBookJob;
use Illuminate\Http\Request;

class TestQueueController extends Controller
{
    public function dispatchJob()
    {
        $bookData = [
            "title" => "非同期で追加された本",
            "author" => "キュー・ワーカー",
            // ... 他のデータ
        ];

        ImportBookJob::dispatch($bookData);

        return "ジョブをキューに追加しました。";
    }
}
```

**コードリーディング:**
- `ImportBookJob::dispatch($bookData)`: `dispatch`ヘルパーメソッドを使ってJobをキューに投入します。引数に渡したデータは、Jobクラスのコンストラクタに渡されます。このメソッドは即座に処理を返し、実際の重い処理はバックグラウンドに任せられます。

## 9-5. キューワーカーの実行

キューに投入されたジョブを処理するために、キューワーカーを起動します。

```bash
sail artisan queue:work
```

このコマンドを実行すると、ワーカーは`jobs`テーブルを監視し、新しいジョブが投入されるとそれを取り出して`handle`メソッドを実行します。

## 9-6. 動作確認

1.  ターミナルで`sail artisan queue:work`を実行し、キューワーカーを起動したままにします。
2.  ブラウザで`/test-queue`にアクセスします。すぐに「ジョブをキューに追加しました。」というメッセージが表示されることを確認します。
3.  キューワーカーを実行しているターミナルを見ると、`ImportBookJob`が処理され、ログメッセージが出力されるのが確認できます。

    ```
    [...][INFO] Processing: App\Jobs\ImportBookJob
    [...][INFO] 書籍インポート処理を開始します: 非同期で追加された本
    (5秒後)
    [...][INFO] 書籍インポート処理が完了しました: 非同期で追加された本
    [...][INFO] Processed:  App\Jobs\ImportBookJob
    ```

4.  `storage/logs/laravel.log`ファイルにも、Jobから出力したログが記録されていることを確認します。

---

これで、時間のかかる処理を非同期化し、ユーザー体験を向上させる基本的な方法を習得しました。次のChapterでは、これまでの総まとめとして、いくつかの応用的なテクニックについて解説します。
