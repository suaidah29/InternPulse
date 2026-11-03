<?php

namespace App\Services;

use App\Mail\SupervisorInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupervisorInvitations
{
    public function send(array $data, int $actorId, ?int $resendId = null): bool
    {
        $role = $data['role'] ?? 'supervisor';
        if (! in_array($role, ['supervisor', 'intern'])) {
            throw ValidationException::withMessages(['role' => 'Invalid invitation role.']);
        }
        if ($role === 'intern') {
            $sv = DB::table('users')->where('id', $data['supervisor_id'] ?? 0)->where('role', 'supervisor')->where('is_active', true)->first();
            if (! $sv || ! $sv->department) {
                throw ValidationException::withMessages(['supervisor_id' => 'Choose an active supervisor with a department.']);
            }
            $data['department'] = $sv->department;
        }
        $token = Str::random(64);
        $email = Str::lower(trim($data['email']));
        $id = DB::transaction(function () use ($data, $actorId, $resendId, $token, $email): int {
            if (DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already uses this email. / E-mel ini sudah mempunyai akaun.']);
            }
            $existing = DB::table('supervisor_invitations')->where('email', $email)->lockForUpdate()->first();
            if ($existing && ($resendId !== $existing->id || $existing->accepted_at)) {
                throw ValidationException::withMessages(['email' => 'This email already has an invitation. Use Resend in the invitation list. / Gunakan Hantar semula dalam senarai jemputan.']);
            }
            $values = ['role' => $data['role'] ?? 'supervisor', 'supervisor_id' => ($data['role'] ?? 'supervisor') === 'intern' ? $data['supervisor_id'] : null, 'name' => $data['name'], 'email' => $email, 'department' => $data['department'], 'token_hash' => hash('sha256', $token), 'invited_by' => $actorId, 'expires_at' => now()->addHours(48), 'revoked_at' => null, 'delivery_status' => 'pending', 'sent_at' => null, 'updated_at' => now()];
            if ($existing) {
                DB::table('supervisor_invitations')->where('id', $existing->id)->update($values);
                $id = $existing->id;
            } else {
                $id = DB::table('supervisor_invitations')->insertGetId($values + ['created_at' => now()]);
            }
            DB::table('management_activity')->insert(['actor_id' => $actorId, 'action' => $existing ? 'invitation.resent' : 'invitation.created', 'subject_email' => $email, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
        try {
            Mail::to($email)->send(new SupervisorInvitationMail($data['name'], $data['department'], route('invitation.show', ['token' => $token]), $role));
            DB::table('supervisor_invitations')->where('id', $id)->where('token_hash', hash('sha256', $token))->update(['delivery_status' => 'sent', 'sent_at' => now()]);

            return true;
        } catch (\Throwable $error) {
            DB::table('supervisor_invitations')->where('id', $id)->where('token_hash', hash('sha256', $token))->update(['delivery_status' => 'failed']);
            logger()->warning('Supervisor invitation mail failed', ['invitation_id' => $id, 'exception' => get_class($error)]);

            return false;
        }
    }
}
