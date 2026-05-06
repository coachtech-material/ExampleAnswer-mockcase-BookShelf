<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanAutoExpireBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_plans_past_due_date_are_marked_as_expired(): void
    {
        Carbon::setTestNow('2026-05-15 20:00:00');

        $user = User::factory()->create();
        $expiredPlan = ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-14',
        ]);
        $activePlan = ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => '2026-05-20',
        ]);

        $this->artisan('reading-plans:run-daily')->assertSuccessful();

        $expiredPlan->refresh();
        $this->assertSame(ReadingPlanStatus::Expired, $expiredPlan->status);

        $activePlan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $activePlan->status);
    }
}
