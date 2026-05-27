# Chapter 20: 読書計画機能とリマインダー通知

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

> **マイグレーションについて:** `reading_plans` / `notifications` テーブルのマイグレーションは Chapter 02（データベース設計とマイグレーション）の 2.2.7 / 2.2.8 で既に作成済みです。本 Chapter では Controller / Model / Policy / Notification / Console Command など、テーブル定義以外の実装に集中します。

### 4.1. `app/Enums/ReadingPlanStatus.php`

```bash
mkdir -p app/Enums
touch app/Enums/ReadingPlanStatus.php
```

```php
<?php

namespace App\Enums;

/**
 * 読書計画のステータス
 *
 * - InProgress: 進行中（期日までに「読了する」操作で Completed に切り替わる）
 * - Completed: 完了済み（「読了する」操作を実行済み）
 * - Expired: 期日超過（in_progress のまま target_date を過ぎた）
 */
enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    /**
     * UI 表示用のラベルを返す
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Completed => '完了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * UI 表示用のバッジカラー（Tailwind クラス）を返す
     */
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

`label()` は Blade からの表示用、`badgeClass()` は同じ Blade からバッジの Tailwind クラスを取得するためのメソッド。Enum 内に表示ロジックを集約することで、Blade 側で `match` を書かずに `$plan->status->label()` だけで済む。

### 4.2. `app/Models/ReadingPlan.php`

```bash
sail artisan make:model ReadingPlan
```

```php
<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        'status' => ReadingPlanStatus::class,
        'completed_at' => 'datetime',
    ];

    /**
     * 計画を立てたユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計画対象の書籍
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * 進行中の計画を絞り込むスコープ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::InProgress);
    }

    /**
     * 完了済みの計画を絞り込むスコープ
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Completed);
    }

    /**
     * 期限切れの計画を絞り込むスコープ
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Expired);
    }
}
```

`status` を Enum にキャストすることで `$plan->status === ReadingPlanStatus::InProgress` のような型安全な比較が可能になる。`scopeActive` / `scopeCompleted` / `scopeExpired` の 3 つは Controller で `->active()` のように呼べるようになり、Controller の責務が「クエリの組み立て」から「リクエストの分岐」に縮小される。

#### `app/Models/User.php` への readingPlans リレーション追加

**この追加を忘れると「読書計画」画面で `Call to undefined method App\Models\User::readingPlans()` エラーが発生する。** ReadingPlan モデル定義に合わせて User モデル側にも逆向きのリレーションを追加すること。

```php
    /**
     * ユーザーが立てた読書計画
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }
```

> **重要:** ファイル冒頭に `use Illuminate\Database\Eloquent\Relations\HasMany;` が import されているか確認すること。Step 15.2 で User モデルを書き換えた時点で既に import されているはずだが、もし無い場合は追加する。`use` 宣言が無いと PHP が `HasMany` を `App\Models\HasMany` と解釈し、`Return value must be of type App\Models\HasMany` というエラーが発生する。

### 4.3. `database/factories/ReadingPlanFactory.php`

```bash
sail artisan make:factory ReadingPlanFactory --model=ReadingPlan
```

```php
<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    protected $model = ReadingPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReadingPlanStatus::Expired,
            'target_date' => now()->subDays(3)->format('Y-m-d'),
            'completed_at' => null,
        ]);
    }
}
```

`inProgress()` / `completed()` / `expired()` の 3 つの状態 state を提供することで、テスト側で `ReadingPlan::factory()->expired()->create()` のように 1 行で目的の状態を作れる。

### 4.4. `app/Policies/ReadingPlanPolicy.php` と AuthServiceProvider 登録

```bash
sail artisan make:policy ReadingPlanPolicy --model=ReadingPlan
```

```php
<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * Determine whether the user can update the reading plan.
     *
     * 所有者 かつ 完了済みでない 場合のみ編集可能。
     * completed 計画は編集不可（読了済みなので変更させない）。
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id
            && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * Determine whether the user can delete the reading plan.
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
```

`update` には「所有者である」と「完了済みでない」の 2 条件を集約する。Controller / FormRequest にこのチェックを散らさないことで、編集ルートの認可を Policy 1 箇所で制御できる。

次に `app/Providers/AuthServiceProvider.php` の `$policies` 配列に **追記** する（既存の `Book` / `Review` のマッピングに `ReadingPlan` を追加する）:

```php
<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Policies\BookPolicy;
use App\Policies\ReadingPlanPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Book::class => BookPolicy::class,
        Review::class => ReviewPolicy::class,
        ReadingPlan::class => ReadingPlanPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
```

> **注意:** 既存の `Book` / `Review` マッピングを **置き換える** のではなく、`ReadingPlan` 行を **追加** すること。`use` 文の追加も忘れずに行う。

### 4.5. `app/Http/Requests/{Store,Update}ReadingPlanRequest.php`

```bash
sail artisan make:request StoreReadingPlanRequest
sail artisan make:request UpdateReadingPlanRequest
```

#### `app/Http/Requests/StoreReadingPlanRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = ReadingPlan::where('user_id', Auth::id())
                        ->where('book_id', $value)
                        ->where('status', ReadingPlanStatus::InProgress)
                        ->exists();
                    if ($exists) {
                        $fail('この書籍は既に進行中の読書計画が存在します。');
                    }
                },
            ],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '書籍IDは整数で入力してください。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }
}
```

#### `app/Http/Requests/UpdateReadingPlanRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 認可は ReadingPlanPolicy@update に集約（Controller 側で $this->authorize('update', $plan) を呼ぶ）
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_date' => [
                'required',
                'date',
                'after_or_equal:today',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var ReadingPlan $plan */
                    $plan = $this->route('reading_plan');
                    $exists = ReadingPlan::where('user_id', Auth::id())
                        ->where('book_id', $plan->book_id)
                        ->where('status', ReadingPlanStatus::InProgress)
                        ->where('id', '!=', $plan->id)
                        ->exists();
                    if ($exists) {
                        $fail('この書籍は既に進行中の読書計画が存在します。');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }
}
```

両 FormRequest とも `Closure` バリデーションで「同じ書籍に対する進行中計画が他に存在するか」を確認する。Store では `Auth::id()` の全進行中計画を、Update では「自身を除く」進行中計画を検査する点が異なる。Update の `authorize()` は `true` を返し、認可は Controller 側の `$this->authorize('update', $plan)` で `ReadingPlanPolicy@update` に委譲する。

### 4.6. `app/Notifications/PlanReminderNotification.php`

```bash
sail artisan make:notification PlanReminderNotification
```

```php
<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanReminderNotification extends Notification
{
    use Queueable;

    public const TIMING_THREE_DAYS_BEFORE = 'three_days_before';

    public const TIMING_ON_DUE_DATE = 'on_due_date';

    public const TIMING_THREE_DAYS_AFTER = 'three_days_after';

    public function __construct(
        public ReadingPlan $plan,
        public string $timing,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification (for DatabaseChannel).
     *
     * @return array<string, mixed>
     */
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

    private function buildTitle(): string
    {
        return match ($this->timing) {
            self::TIMING_THREE_DAYS_BEFORE => '読書計画リマインド — 期限まであと 3 日',
            self::TIMING_ON_DUE_DATE => '読書計画 — 本日が期限',
            self::TIMING_THREE_DAYS_AFTER => '読書計画 — 期限超過 3 日経過',
        };
    }

    private function buildBody(): string
    {
        $title = $this->plan->book->title;

        return match ($this->timing) {
            self::TIMING_THREE_DAYS_BEFORE => "「{$title}」の期限まで残り 3 日です。引き続き読書を進めましょう。",
            self::TIMING_ON_DUE_DATE => "「{$title}」は本日が期限です。読了済みなら完了登録を、もう少し必要なら期限を変更してください。",
            self::TIMING_THREE_DAYS_AFTER => "「{$title}」の期限から 3 日が経過しました。読了済みなら完了登録、続けるなら期限を変更してください。",
        };
    }
}
```

通知のチャネルは `database` のみ（メールは送らない）。3 つの timing 定数（`TIMING_THREE_DAYS_BEFORE` / `TIMING_ON_DUE_DATE` / `TIMING_THREE_DAYS_AFTER`）でいつのリマインダーかを区別し、`toDatabase()` の `data` カラムには `plan_id` / `book_title` / `timing` / `title` / `body` の 5 フィールドを保存する。`book_title` を保存しておくことで、後から書籍タイトルが変更されても通知本文は当時のままで残る。

### 4.7. `app/Console/Commands/RunReadingPlanDailyBatch.php` と Console Kernel

```bash
sail artisan make:command RunReadingPlanDailyBatch
```

```php
<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\PlanReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RunReadingPlanDailyBatch extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reading-plans:run-daily';

    /**
     * @var string
     */
    protected $description = '読書計画の日次バッチ：期日経過した in_progress を一括 Expired 化し、3 日前 / 当日 / 3 日後 の各タイミングでリマインダー通知を発火する。';

    public function handle(): int
    {
        $today = Carbon::today();

        // 1. 期日経過した in_progress 計画を一括 Expired 化
        // bulk update では updated_at が自動更新されないため明示的に付与
        ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', '<', $today)
            ->update([
                'status' => ReadingPlanStatus::Expired,
                'updated_at' => now(),
            ]);

        // 2. 期日 3 日前の in_progress 計画にリマインダー（予告）発火
        $this->notify(
            ReadingPlan::query()
                ->with(['user', 'book'])
                ->where('status', ReadingPlanStatus::InProgress)
                ->whereDate('target_date', $today->copy()->addDays(3))
                ->get(),
            PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        );

        // 3. 期日当日の in_progress 計画にリマインダー（最終リマインド）発火
        $this->notify(
            ReadingPlan::query()
                ->with(['user', 'book'])
                ->where('status', ReadingPlanStatus::InProgress)
                ->whereDate('target_date', $today)
                ->get(),
            PlanReminderNotification::TIMING_ON_DUE_DATE,
        );

        // 4. 期日 3 日後の Expired 計画にリマインダー（再エンゲージメント）発火
        $this->notify(
            ReadingPlan::query()
                ->with(['user', 'book'])
                ->where('status', ReadingPlanStatus::Expired)
                ->whereDate('target_date', $today->copy()->subDays(3))
                ->get(),
            PlanReminderNotification::TIMING_THREE_DAYS_AFTER,
        );

        return self::SUCCESS;
    }

    /**
     * 対象計画群に通知を発火する
     *
     * @param  Collection<int, ReadingPlan>  $plans
     */
    private function notify(Collection $plans, string $timing): void
    {
        $plans->each(function (ReadingPlan $plan) use ($timing): void {
            $plan->user->notify(new PlanReminderNotification($plan, $timing));
        });
    }
}
```

> **重要:** `Eloquent` の `update()` を Builder 経由で **bulk update** する場合、Eloquent モデルのライフサイクルを通らないため `updated_at` カラムは自動更新されない。自動更新したい場合は `'updated_at' => now()` を明示的に組み込む必要がある。Auto-expire のクエリでこの注意を守らないと、`updated_at` が古いまま残り、後続の差分検知系処理で不具合の原因になる。

次に `app/Console/Kernel.php` の `schedule()` に毎日 20:00 実行を登録する:

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 読書計画の日次バッチ：毎日 20:00 に実行
        $schedule->command('reading-plans:run-daily')->daily()->at('20:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
```

採点時には `sail artisan reading-plans:run-daily` を直接実行することで、Cron を待たずにバッチ動作を確認できる。

### 4.8. `app/Http/Controllers/ReadingPlanController.php`

```bash
sail artisan make:controller ReadingPlanController --resource
```

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧を表示（ReadingPlanStatus Enum を活用した status 絞り込み + Eloquent scope）
     */
    public function index(Request $request): View
    {
        $statusValue = $request->input('status');
        $query = Auth::user()->readingPlans()->with('book');

        $status = ReadingPlanStatus::tryFrom($statusValue ?? '');
        if ($status === ReadingPlanStatus::InProgress) {
            $query->active();
        } elseif ($status === ReadingPlanStatus::Completed) {
            $query->completed();
        } elseif ($status === ReadingPlanStatus::Expired) {
            $query->expired();
        }

        $readingPlans = $query->orderBy('target_date')->get();

        return view('reading-plans.index', [
            'readingPlans' => $readingPlans,
            'currentStatus' => $statusValue,
        ]);
    }

    /**
     * 読書計画作成フォームを表示（書籍プルダウン）
     */
    public function create(): View
    {
        $books = Book::orderBy('title')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を新規作成
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Auth::user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を作成しました。');
    }

    /**
     * 読書計画編集フォームを表示（所有者かつ completed でない場合のみ）
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画を更新（Expired 計画は in_progress に復帰）
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);
        $validated = $request->validated();

        $updateData = ['target_date' => $validated['target_date']];

        if ($readingPlan->status === ReadingPlanStatus::Expired) {
            $updateData['status'] = ReadingPlanStatus::InProgress;
        }

        $readingPlan->update($updateData);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画を削除（関連リマインダー通知も同時削除し、Transaction で原子化）
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        DB::transaction(function () use ($readingPlan): void {
            Auth::user()->notifications()
                ->where('data->plan_id', $readingPlan->id)
                ->delete();

            $readingPlan->delete();
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 「読了する」操作で計画を Completed 化
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を完了しました。');
    }
}
```

> **重要:** `destroy` の `DB::transaction` は「関連通知の削除」と「計画の削除」を 1 つの単位で扱うために必要。一方の `update` / `complete` / `store` は 1 SQL なので Transaction は不要（Eloquent の `update()` は内部で 1 SQL なので原子性が保証されている）。「複数 SQL を不可分に処理する場面」だけで Transaction を使うのが Laravel 慣習。

### 4.9. `app/Http/Controllers/NotificationController.php`

```bash
sail artisan make:controller NotificationController
```

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知一覧を表示（時系列降順）
     */
    public function index(): View
    {
        $notifications = Auth::user()->notifications()->latest()->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読化（Notifiable trait の markAsRead を使用）
     */
    public function markAsRead(string $id): RedirectResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
```

`Notifiable` トレイトが提供する `notifications()` リレーションで通知一覧を取得し、`markAsRead()` で `read_at` を更新する。コントローラの責務を最小化し、表示と既読化の 2 アクションのみを提供する。

### 4.10. `routes/web.php`（最終版）

```php
<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// トップページ（書籍一覧）
Route::get('/', [BookController::class, 'index'])->name('home');

// 書籍関連（認証不要）
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// ランキング（認証不要）
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

// 認証が必要なルート
Route::middleware('auth')->group(function () {
    // ジャンル管理
    Route::resource('genres', GenreController::class);

    // 書籍管理（createは{book}より先に定義）
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.searchByIsbn');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // レビュー管理
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // いいね
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    // マイ読書レポート
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // 読書計画
    Route::post('/reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])->name('reading-plans.complete');
    Route::resource('reading-plans', ReadingPlanController::class)->except(['show']);

    // 通知
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

// 書籍詳細（認証不要、{book}パラメータを含むため最後に定義）
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// 認証機能用ルート、RouteServiceProvider.php でミドルウェアを設定しているので必要ない
// require __DIR__.'/auth.php';
```

> **重要（順序）:** `Route::post('/reading-plans/{reading_plan}/complete', ...)` を `Route::resource('reading-plans', ...)` の **前** に置く必要がある。逆にすると `resource` の `update` ルート（`PUT /reading-plans/{reading_plan}`）がマッチしてしまう、あるいは `complete` パラメータが `{reading_plan}` として解釈されて 404 になる。明示的なルートを Resource 系より先に定義するのが鉄則。

### 4.11. `resources/views/layouts/navigation.blade.php`（提供済の確認）

ナビゲーション Blade は `coachtech-prepared-file/Preparedblade-mockcase-BookShelf` から提供されている。読書計画機能と通知機能の実装後に、以下のリンク・アイコンが追加で表示されるはずなので、配置とリンクの動作を確認する:

- 「読書計画」ナビゲーションリンク（`reading-plans.index` への遷移）
- 通知ベルアイコン（`notifications.index` への遷移、未読件数のバッジ表示）

提供 Blade を改変する必要はない。`Auth::user()->unreadNotifications->count()` を参照しているため、通知が DB に投入されるとベルの右上に件数バッジが表示される。動作確認は 19.15 で行う。

### 4.12. `database/seeders/ReadingPlanSeeder.php` と DatabaseSeeder 登録

```bash
sail artisan make:seeder ReadingPlanSeeder
```

```php
<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 読書計画のシードデータを投入する。
     *
     * 採点者がいつ実行しても同じ挙動になるよう、Carbon::today() 起点で動的に target_date を設定する。
     * 採点時の動作確認効率を考慮し、主要シナリオ（リマインダー / Auto-expire / 完了済み等）は山田太郎 1 ユーザーに集約する。
     * 鈴木花子に 1 計画を最後に追加し、他ユーザー認可テスト用とする（ID = 6）。
     */
    public function run(): void
    {
        $today = Carbon::today();
        $books = Book::all();

        // 山田太郎（主要シナリオ集約: ID 1〜5）
        $yamada = User::where('email', 'yamada@example.com')->first();
        $yamadaPlans = [
            // 1. 期日 3 日後 / in_progress → 3 日前リマインダー対象
            ['target_date' => $today->copy()->addDays(3), 'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
            // 2. 期日当日 / in_progress → 当日リマインダー対象
            ['target_date' => $today->copy(), 'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
            // 3. 期日 3 日前 / in_progress → バッチで Auto-expire 化 + 3 日後再エンゲージメント対象（二重シナリオ）
            ['target_date' => $today->copy()->subDays(3), 'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
            // 4. 期日 7 日後 / in_progress → リマインダー対象外
            ['target_date' => $today->copy()->addDays(7), 'status' => ReadingPlanStatus::InProgress, 'completed_at' => null],
            // 5. 期日 10 日前 / completed → 完了済み（編集不可・絞り込み確認用）
            ['target_date' => $today->copy()->subDays(10), 'status' => ReadingPlanStatus::Completed, 'completed_at' => $today->copy()->subDays(5)],
        ];
        foreach ($yamadaPlans as $i => $plan) {
            ReadingPlan::create([
                'user_id' => $yamada->id,
                'book_id' => $books[$i]->id,
                'target_date' => $plan['target_date'],
                'status' => $plan['status'],
                'completed_at' => $plan['completed_at'],
            ]);
        }

        // 鈴木花子（他ユーザー認可テスト用: ID 6）
        // 山田太郎ログイン中に URL `/reading-plans/6/edit` を直打ちして 403 確認するためのデータ
        $suzuki = User::where('email', 'suzuki@example.com')->first();
        ReadingPlan::create([
            'user_id' => $suzuki->id,
            'book_id' => $books[5]->id,
            'target_date' => $today->copy()->addDays(5),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
    }
}
```

`Carbon::today()` 起点の動的シードにより、いつ実行しても同じ「日数差」のシナリオが再現できる（採点日依存性を回避）。山田太郎 1 ユーザーに 5 シナリオ（3 日前 / 当日 / Auto-expire+3 日後 / 範囲外 / 完了済み）を集約することで、採点者は山田太郎にログインするだけで全シナリオを 1 画面で確認できる。鈴木花子の 1 計画（ID = 6）は他ユーザー認可テスト用で、山田太郎ログイン状態で `/reading-plans/6/edit` を直打ちすると 403 が返ることを確認する。

次に `database/seeders/DatabaseSeeder.php` の `call()` 配列の末尾に `ReadingPlanSeeder` を追記する:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 依存関係を考慮して実行順序を変更
        $this->call([
            UserSeeder::class,        // 先にユーザーを作成
            GenreSeeder::class,       // ジャンルも先に作成
            BookSeeder::class,        // ユーザーとジャンルを使って書籍を作成
            ReviewSeeder::class,      // ユーザーと書籍を使ってレビューを作成
            FavoriteSeeder::class,    // ユーザーと書籍を使ってお気に入りを作成
            ReviewLikeSeeder::class,  // ユーザーとレビューを使っていいねを作成
            ReadingPlanSeeder::class, // ユーザーと書籍を使って読書計画を作成（応用機能）
        ]);
    }
}
```

`ReadingPlanSeeder` は `User` と `Book` に依存するので、必ず `UserSeeder` / `BookSeeder` の **後** に置く。

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
