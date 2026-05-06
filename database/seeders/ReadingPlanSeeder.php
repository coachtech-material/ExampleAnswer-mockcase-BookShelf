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
