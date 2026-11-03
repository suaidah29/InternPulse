<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $row = DB::table('supervisor_invitations')->where('token_hash', hash('sha256', $token))->first();
        $valid = $row && ! $row->accepted_at && ! $row->revoked_at && now()->lt($row->expires_at);

        return view('management.accept', ['invitation' => $valid ? $row : null, 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        DB::transaction(function () use ($token, $data): void {
            $row = DB::table('supervisor_invitations')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($row && ! $row->accepted_at && ! $row->revoked_at && now()->lt($row->expires_at), 410, 'Invitation is invalid or expired.');
            abort_if(User::whereRaw('LOWER(email) = ?', [$row->email])->exists(), 409, 'An account already uses this email.');
            $user = new User;
            $user->name = $row->name;
            $user->email = $row->email;
            $user->department = $row->department;
            abort_unless(in_array($row->role, ['supervisor', 'intern']), 410);
            if ($row->role === 'intern') {
                $sv = DB::table('users')->where('id', $row->supervisor_id)->where('role', 'supervisor')->where('is_active', true)->first();
                abort_unless($sv && $sv->department, 410, 'Supervisor no longer available. Request a new invitation.');
                $user->supervisor_id = $sv->id;
                $user->department = $sv->department;
            }
            $user->role = $row->role;
            $user->password = $data['password'];
            $user->email_verified_at = now();
            $user->save();
            DB::table('supervisor_invitations')->where('id', $row->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
            DB::table('management_activity')->insert(['actor_id' => $user->id, 'action' => 'invitation.accepted', 'subject_email' => $row->email, 'created_at' => now(), 'updated_at' => now()]);
        });

        return to_route('login')->with('account_created', true);
    }
}
