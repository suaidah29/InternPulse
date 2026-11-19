<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may read an intern's ratings: the intern, their own supervisor, and Management.
 *
 * Writing a rating is not here on purpose: the supervisor route loads the intern
 * through "where supervisor_id = me", so an attempt on anyone else's intern is a
 * 404 and does not advertise that the account exists.
 */
final class InternEvaluationPolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return ['evaluation.view' => 'view'];
    }

    public function view(User $user, object $intern): bool
    {
        return $user->role === 'superadmin'
            || (int) $intern->id === (int) $user->id
            || ($user->role === 'supervisor' && (int) $intern->supervisor_id === (int) $user->id);
    }
}
