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
