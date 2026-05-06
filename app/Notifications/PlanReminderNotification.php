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
