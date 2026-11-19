<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesWorkspace;
use App\Services\Audit;
use App\Services\TaskStatus;
use App\Services\WorkspaceActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Work: the list, the board, and every move a task can make.
 */
final class TaskController extends Controller
{
    use ScopesWorkspace;

    public function tasks(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:180', 'due_date' => 'nullable|date_format:Y-m-d']);
        $query = $this->tasksQuery($r)->when($r->query('archived') !== '1', fn ($q) => $q->whereNull('archived_at'));
        $query->when($r->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$r->query('q').'%'));
        $query->when($r->filled('due_date'), fn ($q) => $q->where('due_date', $r->query('due_date')));
        if ($r->filled('task')) {
            $query->where('id', (int) $r->query('task'));
        }
        if ($r->query('status')) {
            $query->where('status', $r->query('status'));
        }if ($r->query('assignee')) {
            $query->where('assigned_to', (int) $r->query('assignee'));
        }
        $board = $r->query('view') === 'board';
        // The board shows every current task at once, so it never includes archived work or pages.
        $boardTasks = $board
            ? (clone $query)->whereNull('archived_at')->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id')->limit(300)->get()
            : collect();
        $tasks = $query->orderByDesc('id')->paginate(12)->withQueryString();
        $names = DB::table('users')->whereIn('id', $this->teamIds($r))->pluck('name', 'id');

        return view('management.tasks', ['board' => $board, 'boardTasks' => $boardTasks, 'tasks' => $tasks, 'names' => $names, 'members' => $this->members($r), 'files' => DB::table('work_files')->whereIn('task_id', $tasks->pluck('id'))->get()->groupBy('task_id'), 'canAssign' => $r->user()->role !== 'intern', 'workload' => $this->tasksQuery($r)->whereNull('archived_at')->where('status', '!=', 'completed')->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to')]);
    }

    public function taskDetail(Request $r, int $id): View
    {
        $task = $this->task($r, $id);

        return view('management.task-detail', ['blockers' => DB::table('task_blockers')->where('task_id', $id)->orderByDesc('id')->get(), 'members' => $this->members($r), 'workload' => $this->tasksQuery($r)->whereNull('archived_at')->where('status', '!=', 'completed')->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to'), 'canAssign' => Gate::allows('task.assign', $task), 'task' => $task, 'owner' => DB::table('users')->find($task->assigned_to), 'files' => DB::table('work_files')->where('task_id', $id)->get(), 'history' => DB::table('task_activity as a')->join('users as u', 'u.id', '=', 'a.actor_id')->where('a.task_id', $id)->select('a.*', 'u.name')->orderByDesc('a.id')->paginate(15), 'canReview' => Gate::allows('task.decide', $task)]);
    }

    public function saveTask(Request $r, ?int $id = null): RedirectResponse
    {
        $old = $id ? $this->task($r, $id) : null;
        $old ? Gate::authorize('task.update', $old) : Gate::authorize('task.create');
        $data = $r->validate(['title' => 'required|string|max:180', 'description' => 'nullable|string|max:10000', 'assigned_to' => ['required', 'integer', Rule::in(array_map(fn ($m) => $m->id, $this->members($r)))], 'priority' => ['required', Rule::in(['normal', 'urgent'])], 'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'completed'])], 'due_date' => 'nullable|date_format:Y-m-d', 'category' => 'nullable|string|max:100']);
        if ($data['status'] === 'completed' && ($old?->status !== 'completed' || (int) $data['assigned_to'] !== $old->assigned_to)) {
            throw ValidationException::withMessages(['status' => 'Use Approve completion on the task review. / Gunakan Sahkan selesai pada semakan tugasan.']);
        }
        $member = DB::table('users')->where('id', $data['assigned_to'])->firstOrFail();
        $data['department'] = $member->department;
        $data['completed_at'] = $data['status'] === 'completed' ? ($old?->completed_at ?? now()) : null;
        if ($id) {
            DB::table('work_tasks')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $id = DB::table('work_tasks')->insertGetId($data + ['created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }Audit::record($r->user()->id, 'task.saved', $data['title']);

        WorkspaceActivity::record(DB::table('work_tasks')->find($id), $r->user()->id, $old ? 'updated' : 'assigned', 'Status: '.$data['status']);

        return to_route('workspace.tasks.show', $id)->with('success', 'Task saved. / Tugasan disimpan.');
    }

    public function archiveTask(Request $r, int $id): RedirectResponse
    {
        $task = $this->task($r, $id);
        Gate::authorize('task.archive', $task);
        DB::table('work_tasks')->where('id', $id)->update(['archived_at' => $task->archived_at ? null : now(), 'updated_at' => now()]);
        Audit::record($r->user()->id, 'task.archive_changed', $task->title);

        return back()->with('success', 'Task archive updated. / Arkib tugasan dikemas kini.');
    }

    public function comment(Request $r, int $id): RedirectResponse
    {
        $task = $this->task($r, $id);
        abort_if($task->archived_at, 409);
        $data = $r->validate(['comment' => 'required|string|max:4000']);
        WorkspaceActivity::record($task, $r->user()->id, 'comment', $data['comment']);

        return back()->with('success', 'Comment added. / Komen ditambah.');
    }

    public function progress(Request $r, int $id): RedirectResponse|JsonResponse
    {
        $task = $this->task($r, $id);
        abort_if($task->archived_at || $task->status === 'completed', 409);
        Gate::authorize('task.progress', $task);
        $data = $r->validate(['status' => ['required', Rule::in(['todo', 'in_progress', 'review'])], 'progress_note' => 'nullable|string|max:10000', 'work_url' => 'nullable|url:http,https|max:2000', 'evidence' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,docx|max:10240']);
        unset($data['evidence']);
        $data['completed_at'] = null;
        DB::table('work_tasks')->where('id', $id)->update($data + ['updated_at' => now()]);
        if ($r->hasFile('evidence')) {
            $path = $r->file('evidence')->store('task-evidence', 'local');
            DB::table('work_files')->insert(['task_id' => $id, 'uploaded_by' => $r->user()->id, 'path' => $path, 'original_name' => $r->file('evidence')->getClientOriginalName(), 'created_at' => now(), 'updated_at' => now()]);
        }
        Audit::record($r->user()->id, 'task.progress', $task->title);
        WorkspaceActivity::record($task, $r->user()->id, 'progress', TaskStatus::english($data['status']).' · '.($data['progress_note'] ?? ''));

        if ($r->expectsJson()) {
            return response()->json(['status' => $data['status'], 'label' => TaskStatus::label($data['status'])]);
        }

        return back()->with('success', 'Progress saved. / Kemajuan disimpan.');
    }

    public function requestDue(Request $r, int $id): RedirectResponse
    {
        $task = $this->task($r, $id);
        abort_unless(Gate::allows('task.requestDue', $task) && ! $task->archived_at && $task->status !== 'completed', 403);
        $data = $r->validate(['requested_date' => 'required|date_format:Y-m-d|after_or_equal:'.now('Asia/Kuala_Lumpur')->toDateString(), 'reason' => 'required|string|min:20|max:2000']);
        DB::transaction(function () use ($r, $id, $data): void {
            DB::table('work_tasks')->where('id', $id)->lockForUpdate()->first();
            if (DB::table('due_date_requests')->where('task_id', $id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => 'A request is already pending. / Permintaan masih menunggu.']);
            }DB::table('due_date_requests')->insert($data + ['task_id' => $id, 'requested_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        WorkspaceActivity::notify(WorkspaceActivity::reviewers($r->user()->id), $r->user()->id, 'Deadline change requested / Permintaan tarikh akhir', $id);

        return back()->with('success', 'Request submitted. The current deadline is unchanged until approved. / Permintaan dihantar.');
    }

    public function decideTask(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate(['decision' => ['required', Rule::in(['completed', 'in_progress'])], 'comment' => 'required|string|min:3|max:4000']);
        DB::transaction(function () use ($r, $id, $data): void {
            $task = $this->tasksQuery($r)->where('id', $id)->lockForUpdate()->firstOrFail();
            Gate::authorize('task.decide', $task);
            abort_if($task->archived_at || $task->status !== 'review', 409);
            DB::table('work_tasks')->where('id', $id)->update(['status' => $data['decision'], 'completed_at' => $data['decision'] === 'completed' ? now() : null, 'updated_at' => now()]);
            WorkspaceActivity::record($task, $r->user()->id, $data['decision'] === 'completed' ? 'approved' : 'changes_requested', $data['comment']);
        });

        return back()->with('success', 'Review saved. / Semakan disimpan.');
    }
}
