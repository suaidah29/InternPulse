<?php

namespace App\Policies;

use App\Models\User;

/**
 * Decisions about an account.
 *
 * This is the one policy Gate finds by itself: the subject is an Eloquent User, so
 * App\Policies\UserPolicy is the convention. Everything else is a query-builder row
 * and is registered by ability name in AuthorizationServiceProvider.
 */
final class UserPolicy
{
    /** Management reads every staff profile; a supervisor reads their own interns; anyone reads themselves. */
    public function view(User $actor, User $subject): bool
    {
        return $actor->role === 'superadmin'
            || (int) $actor->id === (int) $subject->id
            || ($actor->role === 'supervisor' && (int) $subject->supervisor_id === (int) $actor->id);
    }

    /** Accounts are edited in Management: a supervisor/intern row, never a superadmin. */
    public function update(User $actor, User $subject): bool
    {
        return $actor->role === 'superadmin' && $subject->role !== 'superadmin';
    }

    /** Internship dates belong to an intern's record. */
    public function updateInternship(User $actor, User $subject): bool
    {
        return $actor->role === 'superadmin' && $subject->role === 'intern';
    }

    /** A signature is a supervisor's or Management's, and only ever your own file. */
    public function signature(User $actor, User $subject): bool
    {
        return (int) $actor->id === (int) $subject->id && $actor->role !== 'intern';
    }

    /** Clearing someone else's second factor is the escape hatch for a lost phone. */
    public function resetTwoFactor(User $actor, User $subject): bool
    {
        return $actor->role === 'superadmin' && $subject->role !== 'superadmin';
    }
}
