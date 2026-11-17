<?php

namespace App\Services;

final class TaskStatus
{
    /**
     * Human labels for the terse database status values, in both interface
     * languages. Keys match the values stored in work_tasks.status.
     */
    public const LABELS = [
        'todo' => ['To do', 'Belum mula'],
        'in_progress' => ['In progress', 'Sedang dibuat'],
        'review' => ['For review', 'Untuk semakan'],
        'completed' => ['Completed', 'Selesai'],
    ];

    /**
     * Labels for the values stored in task_activity.kind.
     */
    public const KIND_LABELS = [
        'progress' => ['Progress update', 'Kemas kini kemajuan'],
        'comment' => ['Comment', 'Komen'],
        'assigned' => ['Assigned', 'Ditugaskan'],
        'approved' => ['Approved', 'Diluluskan'],
        'changes_requested' => ['Changes requested', 'Pembetulan diminta'],
        'help_requested' => ['Help requested', 'Bantuan diminta'],
        'help_resolved' => ['Help resolved', 'Bantuan diselesaikan'],
    ];

    /** @return array{0:string,1:string} */
    public static function label(?string $status): array
    {
        $fallback = ucfirst(str_replace('_', ' ', (string) $status));

        return self::LABELS[$status ?? ''] ?? [$fallback, $fallback];
    }

    /** @return array{0:string,1:string} */
    public static function kindLabel(?string $kind): array
    {
        $fallback = ucfirst(str_replace('_', ' ', (string) $kind));

        return self::KIND_LABELS[$kind ?? ''] ?? [$fallback, $fallback];
    }

    /** English only, for text stored in activity bodies and notifications. */
    public static function english(?string $status): string
    {
        return self::label($status)[0];
    }
}
