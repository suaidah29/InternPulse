<?php

namespace App\Services;

use App\Mail\ReminderMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class Reminders
{
    /** Columns on users that switch each reminder's email copy on or off. */
    public const EMAIL_LOGBOOK = 'email_logbook_reminders';

    public const EMAIL_TASKS = 'email_task_reminders';

    /**
     * Write an in-app notification for a user, but at most once per day for a
     * given key. Scheduled commands run daily, so this keeps a re-run (or a
     * manual retry) from stacking duplicate reminders.
     *
     * When $email is given and the person has that preference switched on, the
     * same reminder also goes out by email — once, because it only sends when
     * the notification was actually written.
     */
    public static function notifyOnce(
        int $userId,
        string $key,
        string $message,
        ?int $taskId = null,
        ?string $emailPreference = null,
        ?ReminderMail $email = null,
    ): bool {
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

        if ($email && $emailPreference) {
            self::email($userId, $emailPreference, $email);
        }

        return true;
    }

    /**
     * Queue the email copy of a reminder, unless the person switched it off, has
     * no address or is no longer active.
     */
    private static function email(int $userId, string $preference, ReminderMail $email): bool
    {
        if (! in_array($preference, [self::EMAIL_LOGBOOK, self::EMAIL_TASKS], true)) {
            return false;
        }

        $user = DB::table('users')->where('id', $userId)->first(['email', 'is_active', $preference]);

        if (! $user || ! $user->is_active || ! $user->email || ! $user->{$preference}) {
            return false;
        }

        Mail::to($user->email)->queue($email);

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
