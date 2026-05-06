<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanDeadlineChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_change_updates_target_date(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->inProgress()->create([
            'target_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ])->assertRedirect(route('reading-plans.index'));

        $plan->refresh();
        $this->assertSame(now()->addDays(10)->format('Y-m-d'), $plan->target_date->format('Y-m-d'));
    }

    public function test_expired_plan_recovers_to_in_progress(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->expired()->create();
        $this->assertSame(ReadingPlanStatus::Expired, $plan->status);

        $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => now()->addDays(7)->format('Y-m-d'),
        ])->assertRedirect(route('reading-plans.index'));

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
    }

    public function test_completed_plan_edit_returns_403(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->completed()->create();

        $this->actingAs($user)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('reading-plans.update', $plan), [
                'target_date' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertForbidden();
    }
}
