<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanReminderBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_days_before_reminder_is_dispatched(): void
    {
        Carbon::setTestNow('2026-05-12 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-15',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => PlanReminderNotification::class,
            'data->timing' => PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        ]);
    }

    public function test_on_due_date_reminder_is_dispatched(): void
    {
        Carbon::setTestNow('2026-05-15 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-15',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => PlanReminderNotification::class,
            'data->timing' => PlanReminderNotification::TIMING_ON_DUE_DATE,
        ]);
    }

    public function test_three_days_after_reminder_is_dispatched_for_expired_plans(): void
    {
        Carbon::setTestNow('2026-05-18 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->create([
            'status' => ReadingPlanStatus::Expired,
            'target_date' => '2026-05-15',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => PlanReminderNotification::class,
            'data->timing' => PlanReminderNotification::TIMING_THREE_DAYS_AFTER,
        ]);
    }

    public function test_no_reminder_for_out_of_window_plans(): void
    {
        Carbon::setTestNow('2026-05-12 20:00:00');

        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-20', // 8 日後 → 対象外
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $user->id,
        ]);
    }
}
