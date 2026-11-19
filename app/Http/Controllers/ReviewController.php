<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesWorkspace;
use App\Services\Audit;
use App\Services\WorkspaceActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The approval queue for deadlines and attendance.
 */
final class ReviewController extends Controller
{
    use ScopesWorkspace;

    public function reviews(Request $r): View
    {
        Gate::authorize('workspace.reviews');
        $ids = $this->teamIds($r);
        $taskIds = $this->tasksQuery($r)->when($r->user()->role === 'supervisor', fn ($q) => $q->where('assigned_to', '!=', $r->user()->id))->pluck('id');

        return view('management.reviews', ['requests' => DB::table('due_date_requests as d')->join('work_tasks as t', 't.id', '=', 'd.task_id')->whereIn('d.task_id', $taskIds)->where('d.status', 'pending')->select('d.*', 't.title')->get(), 'logs' => DB::table('daily_logs')->whereRaw('1 = 0')->orderByDesc('activity_date')->get(), 'attendance' => DB::table('attendance_records')->whereIn('user_id', $ids)->where('status', 'pending')->orderByDesc('attendance_date')->get(), 'names' => DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')]);
    }

    public function review(Request $r, string $type, int $id): RedirectResponse
    {
        Gate::authorize('workspace.reviews');
        $table = ['due' => 'due_date_requests', 'attendance' => 'attendance_records'][$type] ?? null;
        abort_if(! $table, 404);
        $data = $r->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'note' => 'nullable|string|max:2000']);
        if ($data['decision'] === 'rejected' && ! trim($data['note'] ?? '')) {
            throw ValidationException::withMessages(['note' => 'Please provide a reason. / Sila berikan sebab.']);
        }
        DB::transaction(function () use ($r, $type, $id, $table, $data): void {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'pending', 409);
            $uid = $type === 'due' ? $row->requested_by : $row->user_id;
            Gate::authorize($type === 'due' ? 'dueRequest.decide' : 'attendance.decide', $row);
            $column = $type === 'attendance' ? 'note' : 'review_note';
            DB::table($table)->where('id', $id)->update(['status' => $data['decision'], $column => ($data['note'] ?? null), 'reviewed_by' => $r->user()->id, 'updated_at' => now()]);
            if ($type === 'due' && $data['decision'] === 'approved') {
                $task = $this->task($r, $row->task_id);
                abort_if($task->archived_at || $task->status === 'completed' || $task->assigned_to !== $row->requested_by, 409);
                DB::table('work_tasks')->where('id', $row->task_id)->update(['due_date' => $row->requested_date, 'updated_at' => now()]);
            }Audit::record($r->user()->id, $type.'.'.$data['decision'], 'Record #'.$id);
            WorkspaceActivity::notify([$uid], $r->user()->id, $type.': '.$data['decision'].' / Semakan dikemas kini', $type === 'due' ? $row->task_id : null);
        });

        return back()->with('success', 'Review saved. / Semakan disimpan.');
    }
}
