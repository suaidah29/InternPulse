<?php

namespace App\Policies;

use App\Models\User;

/**
 * A deadline change waits for the reviewer of the person who asked for it.
 */
final class DueDateRequestPolicy extends Policy
{
    /** @return array<string, string> */
    public static function abilities(): array
    {
        return ['dueRequest.decide' => 'decide'];
    }

    public function decide(User $user, object $request): bool
    {
        return $this->supervises($user, $request->requested_by ?? null);
    }
}
