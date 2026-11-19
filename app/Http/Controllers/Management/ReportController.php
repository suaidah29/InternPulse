<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Concerns\ScopesWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task reports and the CSV export.
 */
final class ReportController extends Controller
{
    use ScopesWorkspace;

    public function reports(Request $r): View
    {
        $rows = $this->reportRows($r);

        return view('management.reports', ['rows' => $rows, 'members' => $this->members($r)]);
    }

    public function export(Request $r): Response
    {
        $rows = $this->reportRows($r);
        $safe = fn ($v) => preg_match('/^[=+@\-\t\r\n]/', (string) $v) ? "'".$v : (string) $v;

        return response()->streamDownload(function () use ($rows, $safe): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Task', 'Assignee', 'Department', 'Priority', 'Status', 'Due date', 'Completed at'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map($safe, [$row->title, $row->assignee_name, $row->department, $row->priority, $row->status, $row->due_date, $row->completed_at]), ',', '"', '');
            }fclose($out);
        }, 'internpulse-tasks.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportRows(Request $r): Collection
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'assignee' => 'nullable|integer']);

        return $this->tasksQuery($r)->leftJoin('users as u', 'u.id', '=', 'work_tasks.assigned_to')->when($r->query('from'), fn ($q) => $q->whereDate('work_tasks.created_at', '>=', $r->query('from')))->when($r->query('to'), fn ($q) => $q->whereDate('work_tasks.created_at', '<=', $r->query('to')))->when($r->query('assignee'), fn ($q) => $q->where('assigned_to', $r->query('assignee')))->select('work_tasks.*', 'u.name as assignee_name')->orderByDesc('work_tasks.id')->get();
    }
}
