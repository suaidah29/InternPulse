<?php

namespace App\Policies;

use App\Models\User;

/**
 * Attendance is recorded by interns and verified by the intern's supervisor.
 */
final class AttendancePolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return [
            'attendance.record' => 'record',
            'attendance.decide' => 'decide',
            'attendance.viewFile' => 'viewFile',
        ];
    }

    public function record(User $user): bool
    {
        return $this->isIntern($user);
    }

    public function decide(User $user, object $record): bool
    {
        return $this->supervises($user, $record->user_id ?? null);
    }

    /** The photo is evidence attached to one person's attendance day. */
    public function viewFile(User $user, object $record): bool
    {
        return in_array((int) ($record->user_id ?? 0), $this->teamIds($user), true);
    }
}
