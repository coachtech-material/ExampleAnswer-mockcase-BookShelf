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
