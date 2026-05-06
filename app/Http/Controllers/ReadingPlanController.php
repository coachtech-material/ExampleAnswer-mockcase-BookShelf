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
