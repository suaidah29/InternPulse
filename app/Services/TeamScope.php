<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Which people and tasks may this account see?"
 *
 * Extracted from the workspace controllers so a second one (the inbox) can ask
 * the same question without copying the rules — a copied scope rule is how an
 * intern ends up looking at someone else's task.
 */
final class TeamScope
{
    /** @return list<int> */
    public function idsFor(User $user): array
    {
        return match ($user->role) {
            'superadmin' => DB::table('users')->pluck('id')->all(),
            'supervisor' => DB::table('users')->where('supervisor_id', $user->id)->where('role', 'intern')
                ->pluck('id')->push($user->id)->all(),
            default => [$user->id],
        };
    }

    /** @return list<int> the interns this account supervises (empty for an intern) */
    public function internIdsFor(User $user): array
    {
        return match ($user->role) {
            'superadmin' => DB::table('users')->where('role', 'intern')->pluck('id')->all(),
            'supervisor' => DB::table('users')->where('supervisor_id', $user->id)->where('role', 'intern')->pluck('id')->all(),
            default => [],
        };
    }

    public function taskQuery(User $user): Builder
    {
        return DB::table('work_tasks')->whereIn('assigned_to', $this->idsFor($user));
    }

    /**
     * Tasks that need *this* account's decision: a supervisor never reviews their
     * own work, a superadmin may review anyone's.
     *
     * @return Collection<int, int>
     */
    public function reviewableTaskIds(User $user): Collection
    {
        return $this->taskQuery($user)
            ->when($user->role === 'supervisor', fn (Builder $q) => $q->where('assigned_to', '!=', $user->id))
            ->pluck('id');
    }

    /** One task, or 404 when it is outside this account's team. */
    public function taskFor(User $user, int $id): object
    {
        return $this->taskQuery($user)->where('id', $id)->firstOrFail();
    }

    /** @return list<object> active interns and supervisors this account may assign to */
    public function membersFor(User $user): array
    {
        return DB::table('users')
            ->whereIn('id', $this->idsFor($user))
            ->whereIn('role', ['intern', 'supervisor'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /** @return array<int, string> id => name, for the people in scope */
    public function namesFor(User $user): array
    {
        return DB::table('users')->whereIn('id', $this->idsFor($user))->pluck('name', 'id')->all();
    }
}
