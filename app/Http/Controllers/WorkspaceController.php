<?php

namespace App\Http\Controllers;

use App\Services\InternshipProgress;
use App\Services\LogStreak;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The workspace home: the dashboard each role opens on.
 *
 * Everything else an intern or a supervisor does — tasks, approvals, attendance,
 * the team, guides, the bell, files — has its own controller beside this one.
 */
final class WorkspaceController extends Controller
{
    public function overview(Request $r): View
    {
        Gate::authorize('workspace.overview');
        $supervising = $r->user()->role === 'supervisor';
        $ids = $supervising ? DB::table('users')->where('role', 'intern')->where('supervisor_id', $r->user()->id)->pluck('id')->all() : [$r->user()->id];
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $base = DB::table('work_tasks')->whereIn('assigned_to', $ids)->whereNull('archived_at');
        $counts = [
            'open' => (clone $base)->where('status', '!=', 'completed')->count(),
            'overdue' => (clone $base)->where('status', '!=', 'completed')->where('due_date', '<', $today)->count(),
            'review' => (clone $base)->where('status', 'review')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];
        $tasks = (clone $base)->where('status', '!=', 'completed')->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id')->limit(6)->get();
        $names = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');
        $openBlockers = DB::table('task_blockers as b')->join('work_tasks as t', 't.id', '=', 'b.task_id')->whereIn('t.assigned_to', $ids)->whereColumn('t.assigned_to', 'b.intern_id')->whereNull('t.archived_at')->where('t.status', '!=', 'completed')->where('b.status', 'open')->select('b.*', 't.title')->orderByDesc('b.id')->limit(10)->get();
        $unreadCount = DB::table('workspace_notifications')->where('user_id', $r->user()->id)->whereNull('read_at')->count();
        $returnedDocuments = $supervising ? 0 : DB::table('logbook_documents as d')->where('d.user_id', $r->user()->id)->where('d.status', 'returned')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('logbook_documents as next')->whereColumn('next.previous_document_id', 'd.id'))->count();
        $latestFeedback = $supervising ? collect() : DB::table('task_activity as a')->join('work_tasks as t', 't.id', '=', 'a.task_id')->where('t.assigned_to', $r->user()->id)->whereNull('t.archived_at')->where('a.kind', 'changes_requested')->where('t.status', 'in_progress')->select('t.id', 't.title', 'a.body', 'a.created_at')->orderByDesc('a.id')->limit(5)->get();
        $hasFinalEvaluation = ! $supervising && DB::table('intern_evaluations')->where('intern_id', $r->user()->id)->where('period', 'final')->exists();
        $showFinishChecklist = ! $supervising && $r->user()->internship_end_date && $r->user()->internship_end_date <= now('Asia/Kuala_Lumpur')->addDays(30)->toDateString();
        $pendingLogs = DB::table('logbook_documents')->where('supervisor_id', $r->user()->id)->whereIn('user_id', $ids)->where('status', 'pending')->count();
        $pendingAttendance = DB::table('attendance_records')->whereIn('user_id', $ids)->where('status', 'pending')->count();
        $pendingDue = DB::table('due_date_requests')->whereIn('requested_by', $ids)->where('status', 'pending')->count();
        $log = DB::table('daily_logs')->where('user_id', $r->user()->id)->where('activity_date', $today)->first();
        $attendance = DB::table('attendance_records')->where('user_id', $r->user()->id)->where('attendance_date', $today)->first();
        $supervisor = DB::table('users')->where('id', $r->user()->supervisor_id)->first();
        $workload = DB::table('users as u')->leftJoinSub((clone $base)->where('status', '!=', 'completed')->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to'), 'w', 'w.assigned_to', '=', 'u.id')->whereIn('u.id', $ids)->select('u.name', 'u.id', 'u.is_active', DB::raw('COALESCE(w.total, 0) as total'))->orderByDesc('total')->orderBy('u.name')->paginate(10)->withQueryString();

        $timeline = $supervising ? null : InternshipProgress::for($r->user());
        $streak = $supervising ? null : LogStreak::for($r->user());

        return view('management.role-dashboard', compact('timeline', 'streak', 'supervising', 'counts', 'tasks', 'names', 'pendingLogs', 'pendingAttendance', 'pendingDue', 'log', 'attendance', 'supervisor', 'workload', 'today', 'openBlockers', 'unreadCount', 'returnedDocuments', 'latestFeedback', 'hasFinalEvaluation', 'showFinishChecklist'));
    }
}
