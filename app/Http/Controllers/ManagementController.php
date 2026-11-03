<?php

namespace App\Http\Controllers;

use App\Services\SupervisorInvitations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $supervisors = DB::table('users')->where('role', 'supervisor')->when($search, fn ($q) => $q->where(function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
        }))->orderBy('name')->paginate(8, ['*'], 'supervisors_page')->withQueryString();

        $taskStats = [
            'open' => DB::table('work_tasks')->whereNull('archived_at')->where('status', '!=', 'completed')->count(),
            'overdue' => DB::table('work_tasks')->whereNull('archived_at')->where('status', '!=', 'completed')->whereDate('due_date', '<', now('Asia/Kuala_Lumpur')->toDateString())->count(),
            'review' => DB::table('work_tasks')->whereNull('archived_at')->where('status', 'review')->count(),
            'completed' => DB::table('work_tasks')->whereNull('archived_at')->where('status', 'completed')->count(),
            'logs' => DB::table('daily_logs')->where('status', 'pending')->count(),
            'attendance' => DB::table('attendance_records')->where('status', 'pending')->count(),
            'due' => DB::table('due_date_requests')->where('status', 'pending')->count(),
        ];
        $load = DB::table('work_tasks')->whereNull('archived_at')->where('status', '!=', 'completed')->select('assigned_to')->selectRaw('COUNT(*) as open_count')->groupBy('assigned_to');
        $workload = DB::table('users as u')->leftJoin('users as s', 'u.supervisor_id', '=', 's.id')->leftJoinSub($load, 'w', 'u.id', '=', 'w.assigned_to')
            ->where('u.role', 'intern')->where('u.is_active', true)->select('u.id', 'u.name', 'u.department', 's.name as supervisor_name')
            ->selectRaw('COALESCE(w.open_count, 0) as open_count')->orderByDesc('open_count')->orderBy('u.name')->paginate(10, ['*'], 'workload_page')->withQueryString();

        return view('management.dashboard', ['taskStats' => $taskStats, 'workload' => $workload, 'supervisors' => $supervisors, 'invitations' => DB::table('supervisor_invitations')->orderByDesc('updated_at')->paginate(8, ['*'], 'invitations_page')->withQueryString(), 'supervisorCount' => DB::table('users')->where('role', 'supervisor')->count(), 'internCount' => DB::table('users')->where('role', 'intern')->count(), 'pendingCount' => DB::table('supervisor_invitations')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(), 'departments' => DB::table('departments')->count(), 'activity' => DB::table('management_activity')->orderByDesc('id')->limit(5)->get(), 'search' => $search, 'preview' => $request->session()->get('supervisor_import', []), 'localMail' => config('mail.default') === 'smtp' && in_array(config('mail.mailers.smtp.host'), ['127.0.0.1', 'localhost'])]);
    }

    public function store(Request $request, SupervisorInvitations $service): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email:rfc|max:255', 'department' => 'required|string|max:100|exists:departments,name']);
        $ok = $service->send($data, $request->user()->id);

        return to_route('management.dashboard')->with($ok ? 'success' : 'warning', $ok ? 'Invitation submitted to the mail server. / Jemputan dihantar ke pelayan e-mel.' : 'Invitation saved, but email could not be sent. Check mail settings and resend. / Jemputan disimpan tetapi e-mel gagal dihantar.');
    }

    public function resend(Request $request, int $invitation, SupervisorInvitations $service): RedirectResponse
    {
        $row = DB::table('supervisor_invitations')->where('id', $invitation)->first();
        abort_if(! $row, 404);
        abort_if($row->accepted_at, 409);
        $ok = $service->send((array) $row, $request->user()->id, $row->id);

        return to_route('management.dashboard')->with($ok ? 'success' : 'warning', $ok ? 'A new invitation was sent. The previous link is no longer valid. / Pautan jemputan baharu dihantar.' : 'Email failed. Please check mail settings. / E-mel gagal dihantar.');
    }

    public function revoke(Request $request, int $invitation): RedirectResponse
    {
        DB::transaction(function () use ($request, $invitation): void {
            $row = DB::table('supervisor_invitations')->where('id', $invitation)->lockForUpdate()->first();
            abort_if(! $row, 404);
            abort_if($row->accepted_at, 409);
            DB::table('supervisor_invitations')->where('id', $invitation)->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('management_activity')->insert(['actor_id' => $request->user()->id, 'action' => 'invitation.revoked', 'subject_email' => $row->email, 'created_at' => now(), 'updated_at' => now()]);
        });

        return to_route('management.dashboard')->with('success', 'Invitation cancelled. / Jemputan dibatalkan.');
    }

    public function template(): Response
    {
        return response("name,email,department\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="supervisors-template.csv"']);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate(['csv' => 'required|file|max:512|extensions:csv,txt']);
        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        if (! $header) {
            fclose($handle);
            throw ValidationException::withMessages(['csv' => 'CSV is empty.']);
        }
        $header = array_map(fn ($v) => strtolower(trim((string) $v, "\xEF\xBB\xBF \t\n\r")), $header);
        if ($header !== ['name', 'email', 'department']) {
            fclose($handle);
            throw ValidationException::withMessages(['csv' => 'Use the template columns: name,email,department.']);
        }
        $rows = [];
        $seen = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($line === [null]) {
                continue;
            }if (count($rows) >= 100) {
                fclose($handle);
                throw ValidationException::withMessages(['csv' => 'Maximum 100 supervisors per import.']);
            }
            $data = ['name' => trim($line[0] ?? ''), 'email' => strtolower(trim($line[1] ?? '')), 'department' => trim($line[2] ?? '')];
            $errors = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email:rfc|max:255', 'department' => 'required|string|max:100|exists:departments,name'])->errors()->all();
            if (count($line) !== 3) {
                $errors[] = 'Expected exactly three columns.';
            }
            if (isset($seen[$data['email']])) {
                $errors[] = 'Duplicate email in this file.';
            }$seen[$data['email']] = true;
            if (DB::table('users')->whereRaw('LOWER(email) = ?', [$data['email']])->exists() || DB::table('supervisor_invitations')->where('email', $data['email'])->exists()) {
                $errors[] = 'Email already registered or invited.';
            }
            $rows[] = $data + ['errors' => $errors];
        }fclose($handle);
        if (! $rows) {
            throw ValidationException::withMessages(['csv' => 'Add at least one supervisor.']);
        }$request->session()->put('supervisor_import', $rows);

        return to_route('management.dashboard')->withFragment('import-preview');
    }

    public function import(Request $request, SupervisorInvitations $service): RedirectResponse
    {
        $rows = $request->session()->pull('supervisor_import', []);
        if (! $rows || collect($rows)->contains(fn ($row) => count($row['errors']) > 0)) {
            throw ValidationException::withMessages(['csv' => 'Upload a valid CSV preview before sending invitations.']);
        }
        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            try {
                $service->send($row, $request->user()->id) ? $sent++ : $failed++;
            } catch (ValidationException $error) {
                $failed++;
            }
        }

        return to_route('management.dashboard')->with($failed ? 'warning' : 'success', "{$sent} invitations submitted; {$failed} not sent. / {$sent} jemputan dihantar; {$failed} tidak dihantar.");
    }
}
