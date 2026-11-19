<?php

namespace App\Policies;

use App\Models\User;

/**
 * Departments are the spine of every scope rule, so only Management edits them.
 */
final class DepartmentPolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'department.save' => 'save',
            'department.delete' => 'delete',
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
}
