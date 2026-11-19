<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * One row in management_activity, the log a superadmin reads at /management/activity.
 *
 * It was a private helper on the workspace controller; the split would have copied
 * it into four controllers, so it is a service with one signature instead.
 */
final class Audit
{
    public static function record(int $actorId, string $action, string $subject): void
    {
        DB::table('management_activity')->insert([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_email' => mb_substr($subject, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
