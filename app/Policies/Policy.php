<?php

namespace App\Policies;

use App\Models\User;
use App\Services\TeamScope;

/**
 * The two questions every policy in this application needs to ask about a person:
 * may this account make decisions at all, and is this particular person theirs to
 * decide for?
 *
 * Subjects are query-builder rows rather than Eloquent models, so the methods take
 * `object` and only read the columns they need. See AuthorizationServiceProvider
 * for how these classes are registered with the Gate.
 */
abstract class Policy
{
    public function __construct(protected TeamScope $scope) {}

    /** Superadmins and supervisors decide; an intern answers only for their own work. */
    protected function isReviewer(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'supervisor'], true);
    }

    protected function isIntern(User $user): bool
    {
        return $user->role === 'intern';
    }

    /**
     * A supervisor answers for their own interns and never for themselves; a
     * superadmin answers for everyone.
     */
    protected function supervises(User $user, mixed $personId): bool
    {
        if (! $this->isReviewer($user) || $personId === null) {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        return (int) $personId !== (int) $user->id
            && in_array((int) $personId, $this->teamIds($user), true);
    }

    /** Whether $personId is on this account's team at all. */
    protected function inTeam(User $user, mixed $personId): bool
    {
        return $personId !== null && in_array((int) $personId, $this->teamIds($user), true);
    }

    /**
     * The person ids this account may see, as integers.
     *
     * @return list<int>
     */
    protected function teamIds(User $user): array
    {
        return array_map('intval', $this->scope->idsFor($user));
    }
}
