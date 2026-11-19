<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may hand out work, move it, approve it, or take it off the board.
 */
final class WorkTaskPolicy extends Policy
{
    /** @return array<string, string> ability => method */
    public static function abilities(): array
    {
        return [
            'task.create' => 'create',
            'task.update' => 'update',
            'task.archive' => 'archive',
            'task.assign' => 'assign',
            'task.progress' => 'progress',
            'task.requestDue' => 'requestDue',
            'task.decide' => 'decide',
        ];
    }

    /** Only a reviewer hands out work: an intern never creates or reassigns it. */
    public function create(User $user): bool
    {
        return $this->isReviewer($user);
    }

    /**
     * Someone else's team is not this account's to edit, and a supervisor does not
     * edit the work they assigned to themselves.
     */
    public function update(User $user, object $task): bool
    {
        return $this->isReviewer($user)
            && $this->inTeam($user, $task->assigned_to ?? null)
            && ! ($user->role === 'supervisor' && (int) $task->assigned_to === (int) $user->id);
    }

    public function archive(User $user, object $task): bool
    {
        return $this->update($user, $task);
    }

    /** The assign panel is for the reviewer who did not take the work themselves. */
    public function assign(User $user, object $task): bool
    {
        return $this->isReviewer($user)
            && $this->inTeam($user, $task->assigned_to ?? null)
            && (int) $task->assigned_to !== (int) $user->id;
    }

    /** Progress and deadline requests belong to the assignee alone. */
    public function progress(User $user, object $task): bool
    {
        return (int) $task->assigned_to === (int) $user->id;
    }

    public function requestDue(User $user, object $task): bool
    {
        return $this->progress($user, $task);
    }

    /** Approving, or sending work back, is the assignee's reviewer's call. */
    public function decide(User $user, object $task): bool
    {
        return $this->supervises($user, $task->assigned_to ?? null);
    }
}
