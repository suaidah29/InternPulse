<?php

namespace App\Policies;

use App\Models\User;

/**
 * An intern raises a help request on their own task; their reviewer resolves it.
 */
final class TaskBlockerPolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'blocker.create' => 'create',
            'blocker.resolve' => 'resolve',
        ];
    }

    /** Only an intern raises a help request. "Is this task theirs?" is the scoped
     * load below, which answers 404 so it does not confirm that a task exists. */
    public function create(User $user): bool
    {
        return $this->isIntern($user);
    }

    public function resolve(User $user, object $blocker): bool
    {
        return $this->supervises($user, $blocker->intern_id ?? null);
    }
}
