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

    /**
     * Semantic tone for a badge or bar. Colour carries meaning, never brand:
     * neutral = not started, info = underway, warn = waiting on someone,
     * ok = done, danger = needs action now.
     */
    public const TONES = [
        'todo' => 'neutral',
        'in_progress' => 'info',
        'review' => 'warn',
        'completed' => 'ok',
    ];

    public static function tone(?string $status): string
    {
        return self::TONES[$status ?? ''] ?? 'neutral';
    }

    public static function priorityTone(?string $priority): string
    {
        return $priority === 'urgent' ? 'danger' : 'neutral';
    }

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
