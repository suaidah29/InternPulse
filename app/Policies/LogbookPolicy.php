<?php

namespace App\Policies;

use App\Models\User;

/**
 * The daily logbook: interns write it, their supervisor verifies it.
 *
 * A superadmin reads the logbook through Management but is not part of the chain,
 * so verification stays a supervisor's act.
 */
final class LogbookPolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'logbook.view' => 'view',
            'log.write' => 'writeLog',
            'log.photo' => 'viewPhoto',
            'logbook.create' => 'createDocument',
            'logbook.verify' => 'verify',
            'logbook.resubmit' => 'resubmit',
        ];
    }

    /** The logbook screens exist for the two sides of the chain, not for Management. */
    public function view(User $user): bool
    {
        return in_array($user->role, ['intern', 'supervisor'], true);
    }

    public function writeLog(User $user): bool
    {
        return $this->isIntern($user);
    }

    /** The photo on a daily entry is that intern's own evidence. */
    public function viewPhoto(User $user): bool
    {
        return $this->isIntern($user);
    }

    public function createDocument(User $user): bool
    {
        return $this->isIntern($user);
    }

    public function verify(User $user): bool
    {
        return $user->role === 'supervisor';
    }

    public function resubmit(User $user): bool
    {
        return $this->isIntern($user);
    }
}
