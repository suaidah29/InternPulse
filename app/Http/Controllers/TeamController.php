<?php

namespace App\Http\Controllers;

use App\Services\SupervisorInvitations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A supervisor's own interns, and inviting another one.
 */
final class TeamController extends Controller
{
    public function team(Request $r): View
    {
        Gate::authorize('workspace.team');

        return view('management.team', ['people' => DB::table('users')->where('role', 'intern')->where('supervisor_id', $r->user()->id)->when($r->query('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->query('q').'%')->orWhere('email', 'like', '%'.$r->query('q').'%')))->orderBy('name')->paginate(15)->withQueryString()]);
    }

    /** Escape hatch for a lost phone with no recovery codes left: only a superadmin can clear it. */
    public function inviteIntern(Request $r, SupervisorInvitations $service): RedirectResponse
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => 'required|email:rfc|max:255', 'supervisor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'supervisor')->where('is_active', true)]]);
        Gate::authorize('invitation.invite', $data['supervisor_id']);
        $sv = DB::table('users')->where('id', $data['supervisor_id'])->first();
        if (! $sv->department) {
            throw ValidationException::withMessages(['supervisor_id' => 'Assign a department to this supervisor first. / Tetapkan jabatan penyelia dahulu.']);
        }
        $data['department'] = $sv->department;
        $data['role'] = 'intern';
        $ok = $service->send($data, $r->user()->id);

        return back()->with($ok ? 'success' : 'warning', $ok ? 'Intern invitation sent. / Jemputan intern dihantar.' : 'Invitation saved but email failed. / E-mel jemputan gagal dihantar.');
    }
}
