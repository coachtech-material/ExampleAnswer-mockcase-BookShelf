<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reading-plans.index'))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login_for_index(): void
    {
        $this->get(route('reading-plans.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress->value,
        ]);
    }

    public function test_in_progress_duplicate_creation_is_rejected(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        ReadingPlan::factory()->for($user)->for($book)->inProgress()->create();

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('book_id');
        $this->assertSame(1, ReadingPlan::where('user_id', $user->id)->where('book_id', $book->id)->count());
    }

    public function test_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();

        $this->actingAs($owner)
            ->get(route('reading-plans.edit', $plan))
            ->assertOk();
    }

    public function test_other_user_cannot_view_edit_form(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('reading-plans.edit', $plan))
            ->assertForbidden();
    }

    public function test_owner_can_delete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }

    public function test_owner_delete_plan_also_removes_related_notifications(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherPlan = ReadingPlan::factory()->for($owner)->inProgress()->create();

        $owner->notify(new PlanReminderNotification(
            $plan,
            PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        ));
        $owner->notify(new PlanReminderNotification(
            $otherPlan,
            PlanReminderNotification::TIMING_THREE_DAYS_BEFORE,
        ));
        $this->assertSame(2, $owner->fresh()->notifications()->count());

        $this->actingAs($owner)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'));

        // 削除した計画への通知のみ削除されている
        $remaining = $owner->fresh()->notifications()->get();
        $this->assertCount(1, $remaining);
        $this->assertSame($otherPlan->id, $remaining->first()->data['plan_id']);
    }

    public function test_other_user_cannot_delete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id]);
    }

    public function test_complete_action_marks_plan_as_completed(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create([
            'completed_at' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'));

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertNotNull($plan->completed_at);
    }

    public function test_other_user_cannot_complete_plan(): void
    {
        $owner = User::factory()->create();
        $plan = ReadingPlan::factory()->for($owner)->inProgress()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->post(route('reading-plans.complete', $plan))
            ->assertForbidden();
    }

    public function test_index_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->for($user)->inProgress()->create();
        ReadingPlan::factory()->for($user)->completed()->create();

        $response = $this->actingAs($user)
            ->get(route('reading-plans.index', ['status' => 'completed']));

        $response->assertOk();
        $plans = $response->viewData('readingPlans');
        $this->assertCount(1, $plans);
        $this->assertSame(ReadingPlanStatus::Completed, $plans->first()->status);
    }
}
