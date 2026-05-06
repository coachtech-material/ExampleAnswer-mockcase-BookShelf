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
