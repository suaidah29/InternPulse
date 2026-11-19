<?php

namespace App\Policies;

use App\Models\User;

/**
 * Which workspace screens a role may open at all.
 *
 * These are page-level rules, not rules about a particular record: they used to sit
 * at the top of the workspace controllers and the Inbox controller.
 */
final class WorkspacePolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'workspace.overview' => 'overview',
            'workspace.team' => 'team',
            'workspace.reviews' => 'reviews',
            'workspace.inbox' => 'inbox',
        ];
    }

    /** The dashboard is a person's own day or a supervisor's team; Management has /management. */
    public function overview(User $user): bool
    {
        return in_array($user->role, ['intern', 'supervisor'], true);
    }

    public function team(User $user): bool
    {
        return $user->role === 'supervisor';
    }

    public function reviews(User $user): bool
    {
        return $this->isReviewer($user);
    }

    /** An intern has nobody waiting on them; their dashboard is the queue. */
    public function inbox(User $user): bool
    {
        return $this->isReviewer($user);
    }
}
