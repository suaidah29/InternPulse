<?php

namespace App\Console\Commands;

use App\Services\Reminders;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DigestOverdueTasks extends Command
{
    protected $signature = 'tasks:overdue {--date= : Day to treat as today, Y-m-d (defaults to today)}';

    protected $description = 'Notify assignees of overdue tasks and send supervisors a daily digest';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $overdue = DB::table('work_tasks as t')
            ->join('users as u', 'u.id', '=', 't.assigned_to')
            ->whereNull('t.archived_at')
            ->where('t.status', '!=', 'completed')
            ->whereNotNull('t.due_date')
            ->where('t.due_date', '<', $today->toDateString())
            ->select('t.id', 't.title', 't.due_date', 't.assigned_to', 'u.supervisor_id')
            ->orderBy('t.due_date')
            ->get();

        $assigneeNotices = 0;
        $digests = 0;

        foreach ($overdue as $task) {
            $assigneeNotices += Reminders::notifyOnce(
                (int) $task->assigned_to,
                'Overdue task',
                'Overdue task / Tugasan lewat: "'.$task->title.'" was due '.Carbon::parse($task->due_date)->format('j M Y').'. Update the status or ask for help.',
                (int) $task->id
            ) ? 1 : 0;
        }

        foreach ($overdue->groupBy('supervisor_id') as $supervisorId => $tasks) {
            if (! $supervisorId) {
                continue;
            }
            $digests += Reminders::notifyOnce(
                (int) $supervisorId,
                'Team digest',
                'Team digest / Ringkasan pasukan: '.$tasks->count().' overdue task'.($tasks->count() === 1 ? '' : 's').' to follow up with your interns.'
            ) ? 1 : 0;
        }

        $this->info("Overdue tasks: {$overdue->count()} found, {$assigneeNotices} assignee notice(s), {$digests} supervisor digest(s).");

        return self::SUCCESS;
    }
}
