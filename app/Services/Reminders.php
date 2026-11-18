<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Reminders
{
    /**
     * Write an in-app notification for a user, but at most once per day for a
     * given key. Scheduled commands run daily, so this keeps a re-run (or a
     * manual retry) from stacking duplicate reminders.
     */
    public static function notifyOnce(int $userId, string $key, string $message, ?int $taskId = null): bool
    {
        $alreadySent = DB::table('workspace_notifications')
            ->where('user_id', $userId)
            ->where('message', 'like', $key.'%')
            ->whereDate('created_at', Carbon::today()->toDateString())
            ->exists();

        if ($alreadySent) {
            return false;
        }

        DB::table('workspace_notifications')->insert([
            'user_id' => $userId,
            'message' => $message,
            'task_id' => $taskId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Interns who should have logged something on the given day but did not.
     * Interns outside their internship window, and inactive accounts, are skipped.
     */
    public static function internsWithoutLog(Carbon $date): array
    {
        return DB::table('users')
            ->where('role', 'intern')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('internship_start_date')->orWhere('internship_start_date', '<=', $date->toDateString()))
            ->where(fn ($q) => $q->whereNull('internship_end_date')->orWhere('internship_end_date', '>=', $date->toDateString()))
            ->whereNotExists(function ($q) use ($date) {
                $q->select(DB::raw(1))
                    ->from('daily_logs')
                    ->whereColumn('daily_logs.user_id', 'users.id')
                    ->where('daily_logs.activity_date', $date->toDateString());
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
