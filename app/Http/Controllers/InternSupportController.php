<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InternSupportController extends Controller
{
    public function showEvaluations(Request $request, int $id): View
    {
        $intern = DB::table('users')->where('id', $id)->where('role', 'intern')->firstOrFail();
        abort_unless($request->user()->role === 'superadmin' || ($request->user()->role === 'supervisor' && (int) $intern->supervisor_id === (int) $request->user()->id) || ($request->user()->role === 'intern' && (int) $intern->id === (int) $request->user()->id), 403);
        $evaluations = DB::table('intern_evaluations')->where('intern_id', $id)->orderBy('period')->get();

        return view('management.evaluations', compact('intern', 'evaluations'));
    }

    public function createBlocker(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'intern', 403);
        $data = $request->validate(['category' => ['required', Rule::in(['access', 'clarification', 'other'])], 'description' => 'required|string|max:2000']);
        DB::transaction(function () use ($request, $id, $data): void {
            $task = DB::table('work_tasks')->where('id', $id)->where('assigned_to', $request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($task->archived_at || $task->status === 'completed', 409);
            if (DB::table('task_blockers')->where('task_id', $id)->where('status', 'open')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['description' => 'A help request is already open. / Permintaan bantuan masih terbuka.']);
            }
            DB::table('task_blockers')->insert($data + ['task_id' => $id, 'intern_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            WorkspaceActivity::record($task, $request->user()->id, 'help_requested', $data['description']);
        });

        return to_route('workspace.tasks.show', $id)->with('success', 'Help request sent. / Permintaan bantuan dihantar.');
    }

    public function resolveBlocker(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['resolution' => 'required|string|max:2000']);
        $taskId = DB::transaction(function () use ($request, $id, $data): int {
            $blocker = DB::table('task_blockers')->where('id', $id)->lockForUpdate()->firstOrFail();
            $task = DB::table('work_tasks')->where('id', $blocker->task_id)->firstOrFail();
            $intern = DB::table('users')->where('id', $task->assigned_to)->where('role', 'intern')->first();
            abort_unless($intern && (int) $blocker->intern_id === (int) $intern->id && ($request->user()->role === 'superadmin' || ($request->user()->role === 'supervisor' && (int) $intern->supervisor_id === (int) $request->user()->id)), 403);
            abort_unless($blocker->status === 'open' && ! $task->archived_at && $task->status !== 'completed', 409);
            DB::table('task_blockers')->where('id', $id)->update(['status' => 'resolved', 'resolution' => $data['resolution'], 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_at' => now()]);
            WorkspaceActivity::record($task, $request->user()->id, 'help_resolved', $data['resolution']);

            return $task->id;
        });

        return to_route('workspace.tasks.show', $taskId)->with('success', 'Help request resolved. / Permintaan bantuan diselesaikan.');
    }

    public function evaluation(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'supervisor', 403);
        DB::table('users')->where('id', $id)->where('role', 'intern')->where('supervisor_id', $request->user()->id)->firstOrFail();
        $data = $request->validate(['period' => ['required', Rule::in(['midpoint', 'final'])], 'quality' => 'required|integer|between:1,5', 'communication' => 'required|integer|between:1,5', 'teamwork' => 'required|integer|between:1,5', 'feedback' => 'required|string|max:4000']);
        $existing = DB::table('intern_evaluations')->where('intern_id', $id)->where('period', $data['period'])->first();
        DB::table('intern_evaluations')->updateOrInsert(['intern_id' => $id, 'period' => $data['period']], $data + ['supervisor_id' => $request->user()->id, 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()]);
        WorkspaceActivity::notify([$id], $request->user()->id, 'Evaluation updated / Penilaian dikemas kini');

        return to_route('workspace.team')->with('success', 'Evaluation saved. / Penilaian disimpan.');
    }
}
