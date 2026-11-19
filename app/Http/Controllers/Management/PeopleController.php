<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\User;
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
 * Accounts: who exists, whose intern they are, and the 2FA escape hatch.
 */
final class PeopleController extends Controller
{
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
        $person = User::query()->where('id', $id)->whereIn('role', ['supervisor', 'intern'])->firstOrFail();
        Gate::authorize('update', $person);
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
            if ($person->role === 'intern' && (int) $person->supervisor_id !== (int) $data['supervisor_id']) {
                $pendingDocuments = DB::table('logbook_documents')->where('user_id', $id)->whereIn('status', ['pending', 'returned']);
                if ((clone $pendingDocuments)->exists()) {
                    $pendingDocuments->update(['supervisor_id' => $data['supervisor_id'], 'updated_at' => now()]);
                    WorkspaceActivity::notify([(int) $data['supervisor_id']], $r->user()->id, 'Logbook documents await review / Dokumen logbook menunggu semakan');
                }
            }
            if ($person->role === 'supervisor') {
                DB::table('users')->where('supervisor_id', $id)->update(['department' => $data['department']]);
            }if (! $data['is_active']) {
                DB::table('sessions')->where('user_id', $id)->delete();
            }Audit::record($r->user()->id, 'account.updated', $person->email);
        });

        return back()->with('success', 'Account updated. / Akaun dikemas kini.');
    }

    public function updateInternship(Request $r, int $id): RedirectResponse
    {
        $person = User::query()->where('role', 'intern')->where('id', $id)->firstOrFail();
        Gate::authorize('updateInternship', $person);
        $data = $r->validate([
            'university' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'internship_start_date' => 'nullable|date_format:Y-m-d',
            'internship_end_date' => 'nullable|date_format:Y-m-d|after_or_equal:internship_start_date',
            'internship_status' => ['nullable', Rule::in(['upcoming', 'ongoing', 'completed', 'withdrawn'])],
        ]);
        DB::transaction(function () use ($r, $id, $data, $person): void {
            DB::table('users')->where('id', $id)->update($data + ['updated_at' => now()]);
            Audit::record($r->user()->id, 'internship.updated', $person->email);
        });

        return back()->with('success', 'Internship details updated. / Maklumat latihan industri dikemas kini.');
    }

    public function resetTwoFactor(Request $r, int $id): RedirectResponse
    {
        $person = User::query()->where('id', $id)->whereIn('role', ['supervisor', 'intern'])->firstOrFail();
        Gate::authorize('resetTwoFactor', $person);
        abort_unless($person->two_factor_confirmed_at, 409, 'This account does not use two-factor authentication.');

        DB::table('users')->where('id', $id)->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'updated_at' => now(),
        ]);
        Audit::record($r->user()->id, 'user.two_factor_reset', $person->name.' <'.$person->email.'>');
        DB::table('workspace_notifications')->insert([
            'user_id' => $id,
            'message' => 'Two-factor authentication was reset by Management. Sign in with your password and set it up again. / Pengesahan dua faktor telah ditetapkan semula oleh Pengurusan. Log masuk dengan kata laluan dan sediakannya semula.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Two-factor reset for '.$person->name.'. They can sign in with their password and set it up again. / Dua faktor ditetapkan semula untuk '.$person->name.'.');
    }
}
