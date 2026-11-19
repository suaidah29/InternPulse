<?php

namespace App\Policies;

use App\Models\User;

/**
 * Guides are written in Management and read by the departments they are written for.
 */
final class InternGuidePolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'guide.save' => 'save',
            'guide.delete' => 'delete',
            'guide.download' => 'download',
        ];
    }

    public function save(User $user): bool
    {
        return $user->role === 'superadmin';
    }

    public function delete(User $user): bool
    {
        return $this->save($user);
    }

    /** A departmental guide belongs to its department; a guide with no department is for everyone. */
    public function download(User $user, object $guide): bool
    {
        return $user->role === 'superadmin'
            || ! $guide->department
            || $guide->department === $user->department;
    }
}
