# Chapter 19: 読書計画機能とリマインダー通知

---

## 🎯 このセクションで学ぶこと

このChapterでは、読書計画の管理機能と、期日に応じたリマインダー通知の仕組みを実装します。本機能は本案件で最も難易度の高い応用要件であり、複数の Laravel 標準機能を組み合わせて構築します。

- **PHP Enum + Eloquent cast** によるステータス管理（型安全）
- **Eloquent scope** を使った絞り込みクエリ
- **DB::transaction** の本来の用途（複数 SQL を 1 単位で扱う）
- **Schedule + Console Command** を使った日次バッチ処理
- **Notification facade** + DatabaseChannel による画面内通知
- **Policy** に状態判定を統合する Laravel 慣習

---

## 1. はじめに 📖

### なぜステータス管理に PHP Enum + Eloquent cast を使うのか？

> 「ステータスは『進行中』『完了』『期限切れ』の 3 値しか取らない。これを文字列で扱うと、コード中に `'in_progress'` がリテラルとして散在し、タイプミスや IDE 補完の効かない状態になる。PHP 8.1 のネイティブ Enum を使えば、`ReadingPlanStatus::InProgress` のようにケース名で参照でき、IDE が補完してくれるし、`match` 式で網羅性のチェックも効く。Eloquent の `casts()` で Enum クラスに紐付ければ、モデルから取り出した瞬間に Enum オブジェクトとして扱える。」

> 「比較も `if ($plan->status === ReadingPlanStatus::Completed)` のように Enum インスタンス同士の === で書ける。文字列比較とは別次元の安全性だ。Enum の便利メソッド（label() で日本語ラベル、badgeClass() で Tailwind クラス）も持たせることで、画面表示のロジックを Enum に集約できる。」

### なぜ Eloquent scope (active / completed / expired) を整備するのか？

> 「一覧画面でステータス絞り込みするとき、毎回 `where('status', ReadingPlanStatus::InProgress)` と書くのは冗長だ。Eloquent scope を使って `ReadingPlan::active()` のように呼べるようにすれば、Controller 側のコードも簡潔になる。検索ロジックの再利用性も上がる。」

> 「scope 名は実際の DB 値（in_progress）と一致させるのが自然な選択もあるが、`active` のように『現在進行中であること』を直感的に表す慣習的な命名でも OK。重要なのは、scope 名が読み手にとって意味が通じるかどうか。」

### DB::transaction はどこで使うのか？

> 「DB::transaction の本来の用途は『複数の SQL 文を 1 単位として扱う』ことだ。1 つでも失敗したら全体をロールバックして、データの整合性を保つ。」

> 「Laravel の `update()` は内部で 1 つの SQL UPDATE 文を発行するので、複数カラムを同時に更新しても DB レベルで原子的だ。だから、単一 update で済む場面（読了する操作 / 期限変更）では Transaction は不要。Auto-expire の bulk update も同様に 1 SQL なので不要だ。」

> 「では、F-3 で Transaction を使う場面はあるのか？ある。**計画削除時に、その計画に紐づくリマインダー通知も同時に削除**するケース。これは『通知の DELETE + 計画の DELETE = 複数 SQL』だ。1 つでも失敗したら DB の整合性が崩れる（計画は消えたのに通知が残る等）。だから destroy では Transaction で囲む。」

### なぜ Schedule + Console Command で日次バッチを実装するのか？

> 「リマインダー通知や期日経過の自動失効は、毎日 1 回・特定の時刻に実行したい。これを HTTP リクエスト駆動で実装するのは無理だ。Laravel の Schedule + Console Command を使えば、`Console\Kernel::schedule()` に `daily()->at('20:00')` を登録するだけで、Cron 経由で 1 日 1 回自動実行される仕組みが整う。」

> 「本番では Cron に `* * * * * php artisan schedule:run` を 1 行登録するだけで、Schedule に登録した全ジョブが適切なタイミングで動く。開発環境では `sail artisan schedule:work` で同等の動作確認ができる。」

> 「Schedule で daily が保証されるおかげで、同日中の重複発火を防ぐためのフラグカラム（last_notified_at 等）は不要だ。1 日に 1 回しか走らない以上、同日重複は構造的に発生しない。**YAGNI の原則**で、不要なカラムは作らない。」

### DatabaseChannel を選んだ理由は？

> 「Laravel Notification facade はメール / SMS / Slack / DatabaseChannel など多様なチャネルをサポートしている。今回はアプリ内の通知一覧画面で表示する設計なので、DB に保存する DatabaseChannel が最適だ。メール送信は SMTP 設定が必要で運用コストも上がる。画面内通知ならログインしたユーザーが自然に確認できる UX で、本案件のスコープに合っている。」

### 通知データの 5 フィールド構造（plan_id / book_title / timing / title / body）の意図

> 「Laravel の DatabaseNotification は `data` カラムに JSON で任意の構造を保存できる。F-3 では 5 フィールドを格納する：
>
> - **plan_id**: 計画削除時に関連通知を特定するキーとして使う
> - **book_title**: 計画が削除されても、通知本文で書籍名を表示できるよう値を埋め込む（参照ではなく文字列で）
> - **timing**: 通知が 3 タイミング（3 日前 / 当日 / 3 日後）のどれかを識別
> - **title / body**: 画面表示用の文言」

> 「なぜ book_title を別カラムで持たず data に埋め込むのか？通知発火後に書籍タイトルが変更されても、通知の内容は『発火時点の書籍名』で固定したいからだ。これは『**耐性のある通知設計**』の典型例。書籍を `belongsTo` で参照すると、書籍が改名されたら通知の表示も変わってしまう。」

### Policy@update に completed チェックを統合する理由

> 「認可ルールの責務を分散させない、というのが Laravel の慣習だ。所有者チェックは Policy で、状態チェックは FormRequest で、と分けると認可ロジックがコード中に散らばって追跡しづらくなる。リソースに対する権限判定は Policy に集約する。」

> 「今回、`ReadingPlanPolicy@update` を『所有者 かつ completed でない』に統合した。これで Controller の `edit` / `update` / `destroy` で `$this->authorize('update', $plan)` を呼ぶだけで、所有者チェックと completed 編集禁止の両方が同時に効く。FormRequest の `authorize()` は単純に `true` を返すだけで OK。」

### last_notified_at / started_at を持たない設計判断

> 「シンプルさを優先する。」

> 「`last_notified_at` は『同日中の通知重複防止』のために設計しがちだが、Schedule で daily が保証されるなら不要。1 日 1 回しか動かないバッチに対して、追加でフラグカラムを持つのは冗長だ。冗長な実装は将来の保守コストになる。」

> 「`started_at` は『計画開始日時』を記録するつもりで設計しがちだが、F-3 では『計画作成 = 計画開始』なので、`created_at` と常に一致する。冗長カラムなので削除し、`created_at` で代替する。」

> 「これらは **YAGNI**（You Aren't Gonna Need It）の原則だ。今いらないものは作らない。本当に必要になったら、その時に追加すれば良い。早すぎる最適化は害悪。」

### Seeder の動的シード + ユーザー集約の意図

> 「読書計画機能は時間軸（期日との相対位置）で挙動が変わる。target_date が今日 / 3 日後 / 3 日前 で発火するリマインダーが違う、期日経過で自動 expired 化される、など。だから、シードデータの target_date は固定日付（例: '2026-05-15'）で書くのではなく、`Carbon::today()` 起点で `addDays(N)` / `subDays(N)` を使って**動的に設定**する。これで動作確認するタイミングがいつでも、同じシナリオが再現できる。」

> 「動作確認の効率も考えた。各シナリオを別ユーザーに分散させると、何度もログイン切り替えする必要がある。だから主要シナリオは『山田太郎』1 ユーザーに集約し、他ユーザー認可テスト用に『鈴木花子』に 1 計画追加。これで 1 アカウントログインで全シナリオを画面確認できる。」

> 「実務でも、機能の動作検証を可能にするダミーデータの設計はエンジニアの重要なタスクだ。特に時間軸 × 状態の組み合わせで挙動が変わる機能では、すべての挙動パターンを再現するシナリオを網羅的に用意する必要がある。」

---

## 2. 要件の確認 📋

| 種別 | ファイル | 内容 |
|:---|:---|:---|
| Migration | `2026_05_05_120001_create_reading_plans_table.php` | reading_plans テーブル |
| Migration | `2026_05_05_120002_create_notifications_table.php` | notifications テーブル（Laravel 標準） |
| Enum | `app/Enums/ReadingPlanStatus.php` | ステータス Enum |
| Model | `app/Models/ReadingPlan.php` | 計画モデル + 3 scope |
| Factory | `database/factories/ReadingPlanFactory.php` | テスト用 Factory |
| Policy | `app/Policies/ReadingPlanPolicy.php` | 計画 Policy |
| Provider | `app/Providers/AuthServiceProvider.php` | Policy 登録（Append） |
| FormRequest | `app/Http/Requests/StoreReadingPlanRequest.php` | 作成バリデーション |
| FormRequest | `app/Http/Requests/UpdateReadingPlanRequest.php` | 更新バリデーション |
| Notification | `app/Notifications/PlanReminderNotification.php` | リマインダー通知クラス |
| Console | `app/Console/Commands/RunReadingPlanDailyBatch.php` | 日次バッチ Command |
| Console | `app/Console/Kernel.php` | Schedule 登録 |
| Controller | `app/Http/Controllers/ReadingPlanController.php` | 計画 CRUD |
| Controller | `app/Http/Controllers/NotificationController.php` | 通知一覧 |
| Routes | `routes/web.php` | 計画 / 通知ルート |
| Seeder | `database/seeders/ReadingPlanSeeder.php` | シード |
| Seeder | `database/seeders/DatabaseSeeder.php` | 実行順登録 |

---

## 3. 先輩エンジニアの思考プロセス 💭

読書計画とリマインダー通知の設計判断（モデル分割・状態遷移・Notification の使い方等）は本 Chapter の各実装セクションで都度解説しています。詳細は Chapter 16-19 の応用機能の各 Chapter とも合わせて参照してください。

---

## 4. 実装 🚀

完全コードは `応用機能編_完全手順書.md` の **Step 19** を参照してください。本 Chapter では設計の核心となるコード片と詳細解説に絞ります。

### 19.1. PHP Enum + Eloquent cast

```php
// app/Enums/ReadingPlanStatus.php
enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Completed => '完了',
            self::Expired => '期限切れ',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }
}
```

Model 側で:
```php
protected $casts = [
    'target_date' => 'date',
    'status' => ReadingPlanStatus::class,
    'completed_at' => 'datetime',
];
```

これで `$plan->status` から取り出した瞬間に Enum オブジェクトとして扱える。

### 19.2. Eloquent scope

```php
// app/Models/ReadingPlan.php
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', ReadingPlanStatus::InProgress);
}

public function scopeCompleted(Builder $query): Builder
{
    return $query->where('status', ReadingPlanStatus::Completed);
}

public function scopeExpired(Builder $query): Builder
{
    return $query->where('status', ReadingPlanStatus::Expired);
}
```

呼び出すために、`User` モデル側にも逆向きのリレーションを追記する:

```php
// app/Models/User.php
public function readingPlans(): HasMany
{
    return $this->hasMany(ReadingPlan::class);
}
```

これで `Auth::user()->readingPlans()->active()->get()` のように呼び出せる。リレーション追記を忘れると「読書計画」画面で `Call to undefined method App\Models\User::readingPlans()` エラーが発生する。

### 19.3. destroy で Transaction を使う（複数 SQL の場面）

```php
// app/Http/Controllers/ReadingPlanController.php
public function destroy(ReadingPlan $readingPlan): RedirectResponse
{
    $this->authorize('delete', $readingPlan);

    DB::transaction(function () use ($readingPlan): void {
        Auth::user()->notifications()
            ->where('data->plan_id', $readingPlan->id)
            ->delete();

        $readingPlan->delete();
    });

    return redirect()->route('reading-plans.index')
        ->with('success', '読書計画を削除しました。');
}
```

**ポイント**: 複数 DELETE 文を 1 単位で扱うため Transaction で囲む。`data->plan_id` は Laravel の JSON path クエリで、`notifications.data` JSON カラム内の `plan_id` を検索している。

一方、`complete()` や `update()` は単一 update なので Transaction は不要：

```php
public function complete(ReadingPlan $readingPlan): RedirectResponse
{
    $this->authorize('update', $readingPlan);

    $readingPlan->update([
        'status' => ReadingPlanStatus::Completed,
        'completed_at' => now(),
    ]);

    return redirect()->route('reading-plans.index')
        ->with('success', '読書計画を完了しました。');
}
```

### 19.4. Schedule + Console Command

`app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('reading-plans:run-daily')->daily()->at('20:00');
}
```

Console Command の `handle()`:
```php
// app/Console/Commands/RunReadingPlanDailyBatch.php
public function handle(): int
{
    $today = Carbon::today();

    // 1. 期日経過した in_progress を一括 Expired 化
    // bulk update では updated_at が自動更新されないため明示的に付与
    ReadingPlan::query()
        ->where('status', ReadingPlanStatus::InProgress)
        ->whereDate('target_date', '<', $today)
        ->update([
            'status' => ReadingPlanStatus::Expired,
            'updated_at' => now(),
        ]);

    // 2. 期日 3 日前の進行中計画にリマインダー通知発火
    $this->notify(
        ReadingPlan::query()
            ->with(['user', 'book'])
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', $today->copy()->addDays(3))
            ->get(),
        PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
    );

    // 3. 期日当日の進行中計画 / 4. 期日 3 日後の期限切れ計画 ...
    return self::SUCCESS;
}
```

### 19.5. 通知データの 5 フィールド構造

```php
// app/Notifications/PlanReminderNotification.php
public function toDatabase(object $notifiable): array
{
    return [
        'plan_id' => $this->plan->id,
        'book_title' => $this->plan->book->title,
        'timing' => $this->timing,
        'title' => $this->buildTitle(),
        'body' => $this->buildBody(),
    ];
}
```

`book_title` を `belongsTo` 参照ではなく**値として埋め込む**ことで、書籍タイトル変更後も通知発火時の値が保たれる（耐性のある通知設計）。

### 19.6. Policy に状態判定を統合

```php
// app/Policies/ReadingPlanPolicy.php
public function update(User $user, ReadingPlan $readingPlan): bool
{
    return $user->id === $readingPlan->user_id
        && $readingPlan->status !== ReadingPlanStatus::Completed;
}
```

Controller 側は単純化される：
```php
public function edit(ReadingPlan $readingPlan): View
{
    $this->authorize('update', $readingPlan);
    return view('reading-plans.edit', compact('readingPlan'));
}
```

`UpdateReadingPlanRequest::authorize()` も `return true;` で OK（認可は Policy に集約）。

### 19.7. 動的シード + ユーザー集約

```php
// database/seeders/ReadingPlanSeeder.php
$today = Carbon::today();

// 山田太郎に主要シナリオ 5 計画を集約（ID 1〜5）
$yamadaPlans = [
    ['target_date' => $today->copy()->addDays(3),  'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
    ['target_date' => $today->copy(),               'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
    ['target_date' => $today->copy()->subDays(3),   'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
    ['target_date' => $today->copy()->addDays(7),   'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
    ['target_date' => $today->copy()->subDays(10),  'status' => ReadingPlanStatus::Completed,  'completed_at' => $today->copy()->subDays(5)],
];

// 鈴木花子に 1 計画を最後に追加（ID = 6）
// 山田太郎ログイン中に URL /reading-plans/6/edit 直打ちで 403 確認するためのデータ
```

`Carbon::today()` 起点で動的に target_date を設定することで、いつ実行しても同じ挙動になる。

---

## 5. コードの詳細解説 🔍

### Transaction の判断軸（重要）

| シナリオ | Transaction | 理由 |
|:---|:---:|:---|
| `complete()` で status + completed_at を同時更新 | ❌ | 単一 `update()` = 1 SQL UPDATE 文 = DB レベルで原子的 |
| `update()` で target_date 変更（Expired 復帰含む） | ❌ | 同上 |
| Auto-expire の bulk update | ❌ | 単一 mass update = 1 SQL UPDATE 文 |
| `destroy()` で計画削除 + 関連通知削除 | ✅ | 複数 DELETE 文 = 複数 SQL を 1 単位で扱う必要あり |

### bulk update で `updated_at` を明示する理由

Eloquent の `Model::update()` はモデルイベントを発火し timestamps を自動更新するが、Query Builder 経由の bulk update（`Model::query()->update(...)`）は **モデルイベントが発火しない**ため、`updated_at` が自動更新されない。運用上の追跡性のため、`'updated_at' => now()` を明示的に付与する。

### routes 順序の重要性

```php
// routes/web.php
// 必ず complete を resource の前に置く
Route::post('/reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])
    ->name('reading-plans.complete');
Route::resource('reading-plans', ReadingPlanController::class)->except(['show']);
```

順序が逆だと、resource の `update` ルートが先にマッチして `complete` に到達しない可能性がある。Laravel のルートは「先に登録された順」で評価されるので、明示的なルートを resource より先に置く慣習を守る。

### Policy への state チェック統合の効果

Before（責務分散）:
- Controller@edit: `if ($plan->status === Completed) abort(403);`
- UpdateReadingPlanRequest@authorize: `$plan->status !== Completed;`
- Policy@update: 所有者チェックのみ

→ 認可ロジックが 3 箇所に分散

After（Policy 統合）:
- Policy@update: 所有者 && completed でない
- Controller@edit: `$this->authorize('update', $plan);` のみ
- FormRequest@authorize: `return true;`

→ 認可ロジックが Policy に集約。edit / update / destroy のどこから呼んでも同じ判定が効く。

### Seeder の動的シードと採点シナリオ

| 計画 ID | ユーザー | target_date | status | 用途 |
|:---:|:---|:---|:---:|:---|
| 1 | 山田太郎 | today + 3 | in_progress | 3 日前リマインダー対象 |
| 2 | 山田太郎 | today | in_progress | 当日リマインダー対象 |
| 3 | 山田太郎 | today - 3 | in_progress | バッチで Auto-expire 化 + 3 日後再エンゲージメント二重シナリオ |
| 4 | 山田太郎 | today + 7 | in_progress | リマインダー対象外（負例確認用） |
| 5 | 山田太郎 | today - 10 | completed | 完了済み（編集不可確認用） |
| 6 | 鈴木花子 | today + 5 | in_progress | 他ユーザー認可テスト用（URL 直打ち 403 確認） |

計画 ID 3 の **二重シナリオ**: `target_date = today - 3 / status = in_progress` という状態でバッチ実行 → Step 1 で Auto-expire 化（status = expired に変わる） → Step 4 で「期日 3 日後の期限切れ計画」条件にヒットしてリマインダー通知発火。1 レコードで Auto-expire と 3 日後リマインダーの両方を確認できる。

---

## 6. この実装にたどり着くための調べ方 🧐

| 疑問 | プロンプト例 |
|:---|:---|
| Laravel Notification の使い方 | 「Laravel Notification で DB に保存する通知を作成する方法を教えてください。markAsRead と既読管理も含めて。」 |
| ステート遷移の設計 | 「Laravel で読書計画のように `in_progress` / `completed` / `expired` といった状態を持つテーブルを設計する際のベストプラクティスを教えてください。」 |

---

## 7. 動作確認 ✅

```bash
# 1. DB をリセット + シード投入
sail artisan migrate:fresh --seed

# 2. 日次バッチを手動実行（採点用）
sail artisan reading-plans:run-daily

# 3. 山田太郎でログイン → 動作確認
#    /reading-plans で計画一覧（5 件）+ ステータス絞り込み確認
#    各計画の編集・削除・「読了する」ボタンを確認
#    /notifications で 3 タイミングの通知 + ベルアイコン未読バッジ + 既読化を確認
#    URL `/reading-plans/6/edit` 直打ちで 403 確認（鈴木花子の計画への認可エラー）

# 4. テスト全件実行
sail artisan test
```

---

## 8. まとめ ✨

このChapterでは、Laravel の主要な機能（Enum / scope / Transaction / Schedule + Command / Notification / Policy）を組み合わせて、本案件で最高難易度の機能を実装しました。

重要な設計判断のおさらい：

1. **PHP Enum + Eloquent cast** で型安全なステータス管理
2. **Eloquent scope** でクエリの再利用性向上
3. **DB::transaction は複数 SQL の場面でだけ使う**（destroy の関連通知削除）。単一 update では不要
4. **不要なカラムは持たない**（last_notified_at / started_at は YAGNI）。Schedule で daily が保証されるなら重複防止フラグは不要
5. **Policy に状態判定を統合する**（所有者 + completed 編集禁止）。Controller の認可呼び出しが 1 行で済む
6. **データ構造は耐性を考える**（通知 data に book_title を埋め込む）。参照ではなく値で持つことで、書籍タイトル変更にも耐える
7. **動作確認の効率を考えた Seeder 設計**（山田太郎に主要シナリオ集約 + Carbon::today() 起点の動的シード）

---

次の Chapter 20 では、これまで実装した応用機能（読書計画機能を含む）のテストを書きます。
