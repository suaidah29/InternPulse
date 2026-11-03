<?php

namespace App\Http\Controllers;

use App\Services\SupervisorInvitations;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceController extends Controller
{
    private function audit(Request $r, string $action, string $subject): void
    {
        DB::table('management_activity')->insert(['actor_id' => $r->user()->id, 'action' => $action, 'subject_email' => mb_substr($subject, 0, 255), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function teamIds(Request $r): array
    {
        if ($r->user()->role === 'superadmin') {
            return DB::table('users')->pluck('id')->all();
        }
        if ($r->user()->role === 'supervisor') {
            return DB::table('users')->where('supervisor_id', $r->user()->id)->where('role', 'intern')->pluck('id')->push($r->user()->id)->all();
        }

        return [$r->user()->id];
    }

    private function tasksQuery(Request $r): Builder
    {
        return DB::table('work_tasks')->whereIn('assigned_to', $this->teamIds($r));
    }

    private function task(Request $r, int $id): object
    {
        return $this->tasksQuery($r)->where('id', $id)->firstOrFail();
    }

    private function reviewer(Request $r, int $userId): void
    {
        abort_unless(in_array($r->user()->role, ['superadmin', 'supervisor']) && in_array($userId, $this->teamIds($r)) && ($r->user()->role === 'superadmin' || $userId !== $r->user()->id), 403);
    }

    private function members(Request $r): array
    {
        return DB::table('users')->whereIn('id', $this->teamIds($r))->whereIn('role', ['intern', 'supervisor'])->where('is_active', true)->orderBy('name')->get()->all();
    }

    public function people(Request $r): View
    {
        return view('management.people', ['people' => DB::table('users as u')->leftJoin('users as s', 'u.supervisor_id', '=', 's.id')->whereIn('u.role', ['supervisor', 'intern'])->when($r->query('q'), fn ($q) => $q->where(function ($q) use ($r) {
            $q->where('u.name', 'like', '%'.$r->query('q').'%')->orWhere('u.email', 'like', '%'.$r->query('q').'%');
        }))->when(in_array($r->query('role'), ['intern', 'supervisor']), fn ($q) => $q->where('u.role', $r->query('role')))
            ->when($r->filled('department'), fn ($q) => $q->where('u.department', $r->query('department')))
            ->when($r->filled('supervisor_id'), fn ($q) => $q->where('u.supervisor_id', $r->query('supervisor_id')))
            ->when(in_array($r->query('active'), ['0', '1'], true), fn ($q) => $q->where('u.is_active', $r->query('active')))
            ->select('u.*', 's.name as supervisor_name')->orderBy('u.role')->orderBy('u.name')->paginate(15)->withQueryString(), 'supervisors' => DB::table('users')->where('role', 'supervisor')->where('is_active', true)->orderBy('name')->get(), 'departments' => DB::table('departments')->orderBy('name')->get()]);
    }

    public function updatePerson(Request $r, int $id): RedirectResponse
    {
        $person = DB::table('users')->where('id', $id)->whereIn('role', ['supervisor', 'intern'])->firstOrFail();
        $data = $r->validate(['name' => 'required|string|max:255', 'department' => ['required', Rule::exists('departments', 'name')], 'supervisor_id' => [$person->role === 'intern' ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'supervisor')->where('is_active', true)], 'is_active' => 'required|boolean']);
        if ($person->role === 'intern') {
            $sv = DB::table('users')->where('id', $data['supervisor_id'])->first();
            $data['department'] = $sv->department;
        } else {
            $data['supervisor_id'] = null;
            if (! $data['is_active'] && DB::table('users')->where('supervisor_id', $id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Transfer active interns to another supervisor before deactivating this supervisor. / Pindahkan intern aktif dahulu.']);
            }
        }
        DB::transaction(function () use ($r, $id, $data, $person): void {
            DB::table('users')->where('id', $id)->update($data + ['updated_at' => now()]);
            if ($person->role === 'supervisor') {
                DB::table('users')->where('supervisor_id', $id)->update(['department' => $data['department']]);
            }if (! $data['is_active']) {
                DB::table('sessions')->where('user_id', $id)->delete();
            }$this->audit($r, 'account.updated', $person->email);
        });

        return back()->with('success', 'Account updated. / Akaun dikemas kini.');
    }

    public function inviteIntern(Request $r, SupervisorInvitations $service): RedirectResponse
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => 'required|email:rfc|max:255', 'supervisor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'supervisor')->where('is_active', true)]]);
        if ($r->user()->role === 'supervisor') {
            abort_unless((int) $data['supervisor_id'] === $r->user()->id, 403);
        }
        $sv = DB::table('users')->where('id', $data['supervisor_id'])->first();
        if (! $sv->department) {
            throw ValidationException::withMessages(['supervisor_id' => 'Assign a department to this supervisor first. / Tetapkan jabatan penyelia dahulu.']);
        }
        $data['department'] = $sv->department;
        $data['role'] = 'intern';
        $ok = $service->send($data, $r->user()->id);

        return back()->with($ok ? 'success' : 'warning', $ok ? 'Intern invitation sent. / Jemputan intern dihantar.' : 'Invitation saved but email failed. / E-mel jemputan gagal dihantar.');
    }

    public function departments(): View
    {
        return view('management.departments', ['departments' => DB::table('departments')->orderBy('name')->get()]);
    }

    public function saveDepartment(Request $r, ?int $id = null): RedirectResponse
    {
        $data = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($id)]]);
        DB::transaction(function () use ($r, $id, $data): void {
            if ($id) {
                $old = DB::table('departments')->where('id', $id)->firstOrFail();
                foreach (['users', 'supervisor_invitations', 'work_tasks', 'intern_guides'] as $table) {
                    DB::table($table)->where('department', $old->name)->update(['department' => $data['name']]);
                }DB::table('departments')->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                DB::table('departments')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            }$this->audit($r, 'department.saved', $data['name']);
        });

        return back()->with('success', 'Department saved. / Jabatan disimpan.');
    }

    public function deleteDepartment(Request $r, int $id): RedirectResponse
    {
        $d = DB::table('departments')->where('id', $id)->firstOrFail();
        foreach (['users', 'supervisor_invitations', 'work_tasks', 'intern_guides'] as $table) {
            if (DB::table($table)->where('department', $d->name)->exists()) {
                throw ValidationException::withMessages(['department' => 'This department is in use and cannot be deleted. / Jabatan ini masih digunakan.']);
            }
        }
        DB::table('departments')->where('id', $id)->delete();
        $this->audit($r, 'department.deleted', $d->name);

        return back()->with('success', 'Department deleted. / Jabatan dipadam.');
    }

    public function tasks(Request $r): View
    {
        $query = $this->tasksQuery($r)->when($r->query('archived') !== '1', fn ($q) => $q->whereNull('archived_at'));
        if ($r->query('status')) {
            $query->where('status', $r->query('status'));
        }if ($r->query('assignee')) {
            $query->where('assigned_to', (int) $r->query('assignee'));
        }
        $tasks = $query->orderByDesc('id')->paginate(12)->withQueryString();
        $names = DB::table('users')->whereIn('id', $this->teamIds($r))->pluck('name', 'id');

        return view('management.tasks', ['tasks' => $tasks, 'names' => $names, 'members' => $this->members($r), 'files' => DB::table('work_files')->whereIn('task_id', $tasks->pluck('id'))->get()->groupBy('task_id'), 'canAssign' => $r->user()->role !== 'intern', 'workload' => $this->tasksQuery($r)->whereNull('archived_at')->where('status', '!=', 'completed')->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to')]);
    }

    public function saveTask(Request $r, ?int $id = null): RedirectResponse
    {
        abort_if($r->user()->role === 'intern', 403);
        $old = $id ? $this->task($r, $id) : null;
        $data = $r->validate(['title' => 'required|string|max:180', 'description' => 'nullable|string|max:10000', 'assigned_to' => ['required', 'integer', Rule::in(array_map(fn ($m) => $m->id, $this->members($r)))], 'priority' => ['required', Rule::in(['normal', 'urgent'])], 'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'completed'])], 'due_date' => 'nullable|date_format:Y-m-d', 'category' => 'nullable|string|max:100']);
        $member = DB::table('users')->where('id', $data['assigned_to'])->firstOrFail();
        $data['department'] = $member->department;
        $data['completed_at'] = $data['status'] === 'completed' ? ($old?->completed_at ?? now()) : null;
        if ($id) {
            DB::table('work_tasks')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $id = DB::table('work_tasks')->insertGetId($data + ['created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }$this->audit($r, 'task.saved', $data['title']);

        return back()->with('success', 'Task saved. / Tugasan disimpan.');
    }

    public function archiveTask(Request $r, int $id): RedirectResponse
    {
        abort_if($r->user()->role === 'intern', 403);
        $task = $this->task($r, $id);
        DB::table('work_tasks')->where('id', $id)->update(['archived_at' => $task->archived_at ? null : now(), 'updated_at' => now()]);
        $this->audit($r, 'task.archive_changed', $task->title);

        return back()->with('success', 'Task archive updated. / Arkib tugasan dikemas kini.');
    }

    public function progress(Request $r, int $id): RedirectResponse
    {
        $task = $this->task($r, $id);
        abort_if($task->archived_at || $task->status === 'completed', 409);
        $data = $r->validate(['status' => ['required', Rule::in(['todo', 'in_progress', 'review'])], 'progress_note' => 'nullable|string|max:10000', 'work_url' => 'nullable|url:http,https|max:2000', 'evidence' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,docx|max:10240']);
        unset($data['evidence']);
        $data['completed_at'] = null;
        DB::table('work_tasks')->where('id', $id)->update($data + ['updated_at' => now()]);
        if ($r->hasFile('evidence')) {
            $path = $r->file('evidence')->store('task-evidence', 'local');
            DB::table('work_files')->insert(['task_id' => $id, 'uploaded_by' => $r->user()->id, 'path' => $path, 'original_name' => $r->file('evidence')->getClientOriginalName(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->audit($r, 'task.progress', $task->title);

        return back()->with('success', 'Progress saved. / Kemajuan disimpan.');
    }

    public function requestDue(Request $r, int $id): RedirectResponse
    {
        $task = $this->task($r, $id);
        abort_unless($task->assigned_to === $r->user()->id && ! $task->archived_at && $task->status !== 'completed', 403);
        $data = $r->validate(['requested_date' => 'required|date_format:Y-m-d|after_or_equal:'.now('Asia/Kuala_Lumpur')->toDateString(), 'reason' => 'required|string|min:20|max:2000']);
        DB::transaction(function () use ($r, $id, $data): void {
            DB::table('work_tasks')->where('id', $id)->lockForUpdate()->first();
            if (DB::table('due_date_requests')->where('task_id', $id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => 'A request is already pending. / Permintaan masih menunggu.']);
            }DB::table('due_date_requests')->insert($data + ['task_id' => $id, 'requested_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Request submitted. The current deadline is unchanged until approved. / Permintaan dihantar.');
    }

    public function reviews(Request $r): View
    {
        abort_if($r->user()->role === 'intern', 403);
        $ids = $this->teamIds($r);
        $taskIds = $this->tasksQuery($r)->when($r->user()->role === 'supervisor', fn ($q) => $q->where('assigned_to', '!=', $r->user()->id))->pluck('id');

        return view('management.reviews', ['requests' => DB::table('due_date_requests as d')->join('work_tasks as t', 't.id', '=', 'd.task_id')->whereIn('d.task_id', $taskIds)->where('d.status', 'pending')->select('d.*', 't.title')->get(), 'logs' => DB::table('daily_logs')->whereIn('user_id', $ids)->where('status', 'pending')->orderByDesc('activity_date')->get(), 'attendance' => DB::table('attendance_records')->whereIn('user_id', $ids)->where('status', 'pending')->orderByDesc('attendance_date')->get(), 'names' => DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')]);
    }

    public function review(Request $r, string $type, int $id): RedirectResponse
    {
        abort_if($r->user()->role === 'intern', 403);
        $table = ['due' => 'due_date_requests', 'log' => 'daily_logs', 'attendance' => 'attendance_records'][$type] ?? null;
        abort_if(! $table, 404);
        $data = $r->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'note' => 'nullable|string|max:2000']);
        if ($data['decision'] === 'rejected' && ! trim($data['note'] ?? '')) {
            throw ValidationException::withMessages(['note' => 'Please provide a reason. / Sila berikan sebab.']);
        }
        DB::transaction(function () use ($r, $type, $id, $table, $data): void {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'pending', 409);
            $uid = $type === 'due' ? $row->requested_by : $row->user_id;
            $this->reviewer($r, $uid);
            $column = $type === 'attendance' ? 'note' : 'review_note';
            DB::table($table)->where('id', $id)->update(['status' => $data['decision'], $column => ($data['note'] ?? null), 'reviewed_by' => $r->user()->id, 'updated_at' => now()]);
            if ($type === 'due' && $data['decision'] === 'approved') {
                $task = $this->task($r, $row->task_id);
                abort_if($task->archived_at || $task->status === 'completed' || $task->assigned_to !== $row->requested_by, 409);
                DB::table('work_tasks')->where('id', $row->task_id)->update(['due_date' => $row->requested_date, 'updated_at' => now()]);
            }$this->audit($r, $type.'.'.$data['decision'], 'Record #'.$id);
        });

        return back()->with('success', 'Review saved. / Semakan disimpan.');
    }

    public function records(Request $r): View
    {
        $ids = $this->teamIds($r);

        return view('management.records', ['logs' => DB::table('daily_logs')->whereIn('user_id', $ids)->orderByDesc('activity_date')->paginate(10, ['*'], 'logs_page')->withQueryString(), 'attendance' => DB::table('attendance_records')->whereIn('user_id', $ids)->orderByDesc('attendance_date')->paginate(10, ['*'], 'attendance_page')->withQueryString(), 'names' => DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')]);
    }

    public function saveLog(Request $r): RedirectResponse
    {
        abort_unless($r->user()->role === 'intern', 403);
        $data = $r->validate(['activity_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Kuala_Lumpur')->toDateString(), 'points' => 'required|string|max:10000', 'summary' => 'nullable|string|max:12000']);
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $data['points'])));
        $data['summary'] = ($data['summary'] ?? '') ?: implode(' ', array_map(fn ($line) => rtrim(ltrim($line, '-• '), '. ').'.', $lines));
        $old = DB::table('daily_logs')->where('user_id', $r->user()->id)->where('activity_date', $data['activity_date'])->first();
        if ($old && $old->status === 'approved') {
            throw ValidationException::withMessages(['activity_date' => 'An approved log cannot be edited. / Log disahkan tidak boleh diubah.']);
        }
        DB::table('daily_logs')->updateOrInsert(['user_id' => $r->user()->id, 'activity_date' => $data['activity_date']], $data + ['status' => 'pending', 'review_note' => null, 'reviewed_by' => null, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);

        return back()->with('success', 'Logbook saved. You can copy the summary into a hardcopy logbook. / Logbook disimpan dan boleh disalin ke buku fizikal.');
    }

    public function checkIn(Request $r): RedirectResponse
    {
        abort_unless($r->user()->role === 'intern', 403);
        $r->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $date = now('Asia/Kuala_Lumpur')->toDateString();
        if (DB::table('attendance_records')->where('user_id', $r->user()->id)->where('attendance_date', $date)->exists()) {
            throw ValidationException::withMessages(['photo' => 'Already checked in today. / Kehadiran hari ini sudah direkod.']);
        }
        $path = $r->file('photo')->store('attendance', 'local');
        DB::table('attendance_records')->insert(['user_id' => $r->user()->id, 'attendance_date' => $date, 'checked_in_at' => now(), 'photo_path' => $path, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Attendance submitted for verification. / Kehadiran dihantar untuk pengesahan.');
    }

    public function guides(Request $r): View
    {
        $q = DB::table('intern_guides');
        if ($r->user()->role !== 'superadmin') {
            $q->where(fn ($q) => $q->whereNull('department')->orWhere('department', $r->user()->department));
        }

        return view('management.guides', ['guides' => $q->orderByDesc('id')->get(), 'departments' => DB::table('departments')->orderBy('name')->get()]);
    }

    public function saveGuide(Request $r, ?int $id = null): RedirectResponse
    {
        $data = $r->validate(['title' => 'required|string|max:180', 'department' => ['nullable', Rule::exists('departments', 'name')], 'body' => 'required|string|max:20000', 'file' => 'nullable|file|mimes:pdf,docx,jpg,jpeg,png,webp|max:10240']);
        unset($data['file']);
        if ($id) {
            DB::table('intern_guides')->where('id', $id)->firstOrFail();
        }
        if ($r->hasFile('file')) {
            $data['file_path'] = $r->file('file')->store('guides', 'local');
            $data['file_name'] = $r->file('file')->getClientOriginalName();
        }if ($id) {
            DB::table('intern_guides')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            DB::table('intern_guides')->insert($data + ['created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }$this->audit($r, 'guide.saved', $data['title']);

        return back()->with('success', 'Guide saved. / Panduan disimpan.');
    }

    public function deleteGuide(Request $r, int $id): RedirectResponse
    {
        $guide = DB::table('intern_guides')->where('id', $id)->firstOrFail();
        DB::table('intern_guides')->where('id', $id)->delete();
        $this->audit($r, 'guide.deleted', $guide->title);

        return back()->with('success', 'Guide deleted. / Panduan dipadam.');
    }

    public function download(Request $r, string $type, int $id): Response
    {
        if ($type === 'task') {
            $file = DB::table('work_files')->where('id', $id)->firstOrFail();
            $this->task($r, $file->task_id);
            $path = $file->path;
            $name = $file->original_name;
        } elseif ($type === 'attendance') {
            $file = DB::table('attendance_records')->where('id', $id)->firstOrFail();
            abort_unless(in_array($file->user_id, $this->teamIds($r)), 403);
            $path = $file->photo_path;
            $name = 'attendance-'.basename($path);
        } elseif ($type === 'guide') {
            $file = DB::table('intern_guides')->where('id', $id)->firstOrFail();
            abort_unless($r->user()->role === 'superadmin' || ! $file->department || $file->department === $r->user()->department, 403);
            $path = $file->file_path;
            $name = $file->file_name;
        } else {
            abort(404);
        }abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function reportRows(Request $r): Collection
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'assignee' => 'nullable|integer']);

        return $this->tasksQuery($r)->leftJoin('users as u', 'u.id', '=', 'work_tasks.assigned_to')->when($r->query('from'), fn ($q) => $q->whereDate('work_tasks.created_at', '>=', $r->query('from')))->when($r->query('to'), fn ($q) => $q->whereDate('work_tasks.created_at', '<=', $r->query('to')))->when($r->query('assignee'), fn ($q) => $q->where('assigned_to', $r->query('assignee')))->select('work_tasks.*', 'u.name as assignee_name')->orderByDesc('work_tasks.id')->get();
    }

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

    public function activity(): View
    {
        return view('management.activity', ['activity' => DB::table('management_activity as a')->join('users as u', 'u.id', '=', 'a.actor_id')->select('a.*', 'u.name as actor')->orderByDesc('a.id')->paginate(30)]);
    }
}
