<?php

namespace App\Policies;

use App\Models\User;

/**
 * Invitations: Management sends them to anyone, a supervisor only into their own team.
 */
final class SupervisorInvitationPolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'invitation.invite' => 'invite',
            'invitation.resend' => 'resend',
            'invitation.revoke' => 'revoke',
        ];
    }

    /**
     * @param  int|string|null  $supervisorId  the supervisor the new account would report to
     */
    public function invite(User $user, mixed $supervisorId = null): bool
    {
        if (! $this->isReviewer($user)) {
            return false;
        }

        return $user->role === 'superadmin' || (int) $supervisorId === (int) $user->id;
    }

    public function resend(User $user): bool
    {
        return $user->role === 'superadmin';
    }

    public function revoke(User $user): bool
    {
        return $this->resend($user);
    }
}
