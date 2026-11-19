<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One queue of "things waiting on you", assembled from the tables that already
 * hold them. Before this, a supervisor had to open Approvals, Logbook, Team
 * tasks and the dashboard to find out whether anything needed them today.
 *
 * Each section carries the rows plus the endpoint that clears them, so the page
 * can be acted on instead of just read.
 */
final class ActionQueue
{
    public function __construct(private readonly TeamScope $scope) {}

    /** @return array{total: int, sections: list<array<string, mixed>>} */
    public function for(User $user): array
    {
        if ($user->role === 'intern') {
            return ['total' => 0, 'sections' => []];
        }

        $names = $this->scope->namesFor($user);
        $taskIds = $this->scope->reviewableTaskIds($user);
        $internIds = $this->scope->internIdsFor($user);
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        $sections = [
            $this->section('review', 'For review', 'Tugasan untuk semakan',
                'Finished work waiting for your decision.',
                'Kerja siap menunggu keputusan anda.',
                route('workspace.tasks'),
                $this->reviewRows($taskIds, $names)),

            $this->section('due', 'Due date requests', 'Permintaan tarikh akhir',
                'An intern asking to move a deadline.',
                'Intern meminta tarikh akhir dipinda.',
                route('workspace.reviews'),
                $this->dueRows($taskIds, $names)),

            $this->section('logbook', 'Logbook documents', 'Dokumen logbook',
                'Signed logbook pages waiting for your verification.',
                'Halaman logbook menunggu pengesahan anda.',
                route('workspace.logbook'),
                $this->logbookRows($user, $names)),

            $this->section('attendance', 'Attendance', 'Kehadiran',
                'Check-in photos waiting for verification.',
                'Gambar daftar masuk menunggu pengesahan.',
                route('workspace.records'),
                $this->attendanceRows($internIds, $names)),

            $this->section('blockers', 'Blockers', 'Halangan',
                'Interns who have said they are stuck.',
                'Intern yang menyatakan mereka tersekat.',
                route('workspace.tasks'),
                $this->blockerRows($taskIds, $names)),

            $this->section('overdue', 'Overdue tasks', 'Tugasan lewat',
                'Past their due date and not completed.',
                'Melepasi tarikh akhir dan belum selesai.',
                route('workspace.tasks'),
                $this->overdueRows($taskIds, $today, $names)),
        ];

        return [
            'total' => array_sum(array_map(fn (array $section): int => $section['count'], $sections)),
            'sections' => $sections,
        ];
    }

    /**
     * The number for the sidebar badge, and it runs on every page for a supervisor,
     * so it must stay cheap: six COUNT queries and no rows pulled into memory.
     * Deliberately not cached — an approval has to clear the badge immediately.
     */
    public function countFor(User $user): int
    {
        return array_sum($this->counts($user));
    }

    /** @return array<string, int> */
    private function counts(User $user): array
    {
        if ($user->role === 'intern') {
            return array_fill_keys(['review', 'due', 'logbook', 'attendance', 'blockers', 'overdue'], 0);
        }

        $tasks = $this->taskCountScope($user);
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $internIds = $this->scope->internIdsFor($user);

        return [
            'review' => (clone $tasks)->where('status', 'review')->count(),
            'due' => DB::table('due_date_requests')->where('status', 'pending')
                ->whereIn('task_id', (clone $tasks)->select('id'))->count(),
            'logbook' => DB::table('logbook_documents')->where('status', 'pending')
                ->when($user->role === 'supervisor', fn ($q) => $q->where('supervisor_id', $user->id))
                ->count(),
            'attendance' => $internIds === [] ? 0 : DB::table('attendance_records')
                ->whereIn('user_id', $internIds)->where('status', 'pending')->count(),
            'blockers' => DB::table('task_blockers')->where('status', 'open')
                ->whereIn('task_id', (clone $tasks)->select('id'))->count(),
            'overdue' => (clone $tasks)->where('status', '!=', 'completed')
                ->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
        ];
    }

    /**
     * Same rule as TeamScope, expressed as a query so a superadmin scope costs no
     * PHP memory at all.
     */
    private function taskCountScope(User $user): Builder
    {
        $query = DB::table('work_tasks')->whereNull('archived_at');

        return match ($user->role) {
            'supervisor' => $query->whereIn('assigned_to', $this->scope->idsFor($user))->where('assigned_to', '!=', $user->id),
            'intern' => $query->where('assigned_to', $user->id),
            default => $query,
        };
    }

    private function section(string $key, string $label, string $labelMs, string $lede, string $ledeMs, string $href, Collection $rows): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'label_ms' => $labelMs,
            'lede' => $lede,
            'lede_ms' => $ledeMs,
            'href' => $href,
            'rows' => $rows,
            'count' => $rows->count(),
        ];
    }

    private function reviewRows(Collection $taskIds, array $names): Collection
    {
        return DB::table('work_tasks')
            ->whereIn('id', $taskIds)
            ->where('status', 'review')
            ->whereNull('archived_at')
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'assigned_to', 'due_date', 'updated_at'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'who' => $names[$row->assigned_to] ?? '—',
                'meta' => $row->due_date ? 'Due '.$row->due_date : 'No due date',
                'href' => route('workspace.tasks.show', $row->id),
            ]);
    }

    private function dueRows(Collection $taskIds, array $names): Collection
    {
        return DB::table('due_date_requests as d')
            ->join('work_tasks as t', 't.id', '=', 'd.task_id')
            ->whereIn('d.task_id', $taskIds)
            ->where('d.status', 'pending')
            ->orderBy('d.id')
            ->limit(10)
            ->get(['d.id', 'd.requested_by', 'd.requested_date', 'd.reason', 't.title'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'who' => $names[$row->requested_by] ?? '—',
                'meta' => 'New date '.$row->requested_date,
                'note' => $row->reason,
                'review_type' => 'due',
            ]);
    }

    private function logbookRows(User $user, array $names): Collection
    {
        return DB::table('logbook_documents')
            ->where('status', 'pending')
            ->when($user->role === 'supervisor', fn ($q) => $q->where('supervisor_id', $user->id))
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'user_id', 'title', 'created_at'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'who' => $names[$row->user_id] ?? '—',
                'meta' => 'Submitted '.Carbon::parse($row->created_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i'),
            ]);
    }

    private function attendanceRows(array $internIds, array $names): Collection
    {
        if ($internIds === []) {
            return collect();
        }

        return DB::table('attendance_records')
            ->whereIn('user_id', $internIds)
            ->where('status', 'pending')
            ->orderByDesc('attendance_date')
            ->limit(10)
            ->get(['id', 'user_id', 'attendance_date', 'checked_in_at'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->attendance_date,
                'who' => $names[$row->user_id] ?? '—',
                'meta' => 'Checked in '.Carbon::parse($row->checked_in_at)->timezone('Asia/Kuala_Lumpur')->format('H:i').' MYT',
                'photo' => route('workspace.files', ['type' => 'attendance', 'id' => $row->id]),
                'review_type' => 'attendance',
            ]);
    }

    private function blockerRows(Collection $taskIds, array $names): Collection
    {
        return DB::table('task_blockers as b')
            ->join('work_tasks as t', 't.id', '=', 'b.task_id')
            ->whereIn('b.task_id', $taskIds)
            ->where('b.status', 'open')
            ->orderByDesc('b.id')
            ->limit(10)
            ->get(['b.id', 'b.task_id', 'b.intern_id', 'b.category', 'b.description', 't.title'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'who' => $names[$row->intern_id] ?? '—',
                'meta' => ucfirst(str_replace('_', ' ', $row->category)),
                'note' => $row->description,
                'href' => route('workspace.tasks.show', $row->task_id),
            ]);
    }

    private function overdueRows(Collection $taskIds, string $today, array $names): Collection
    {
        return DB::table('work_tasks')
            ->whereIn('id', $taskIds)
            ->whereNull('archived_at')
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'assigned_to', 'due_date'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'title' => $row->title,
                'who' => $names[$row->assigned_to] ?? '—',
                'meta' => 'Due '.$row->due_date.' — '.(int) round(Carbon::parse($row->due_date)->diffInDays($today, true)).' day(s) late',
                'href' => route('workspace.tasks.show', $row->id),
            ]);
    }
}
