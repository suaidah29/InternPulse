<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WorkspaceActivity
{
    public static function notify(array $recipients, int $actor, string $message, ?int $taskId = null): void
    {
        foreach (array_unique($recipients) as $id) {
            if ($id && (int) $id !== $actor) {
                DB::table('workspace_notifications')->insert(['user_id' => $id, 'message' => $message, 'task_id' => $taskId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public static function reviewers(int $userId): array
    {
        $db = DB::class;
        $sv = $db::table('users')->where('id', $userId)->value('supervisor_id');

        return $sv ? [(int) $sv] : $db::table('users')->where('role', 'superadmin')->where('is_active', true)->pluck('id')->all();
    }

    public static function record(object $task, int $actor, string $kind, string $body): void
    {
        DB::table('task_activity')->insert(['task_id' => $task->id, 'actor_id' => $actor, 'kind' => $kind, 'body' => $body, 'created_at' => now(), 'updated_at' => now()]);
        self::notify([$task->assigned_to, ...self::reviewers($task->assigned_to)], $actor, 'Task update / Kemas kini tugasan: '.mb_substr($task->title, 0, 180), $task->id);
    }
}
