<?php

namespace App\Http\Controllers\Concerns;

use App\Services\TeamScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * The four questions a workspace screen asks about people it may see: which ids,
 * which tasks, which one task, and the members it can assign to.
 *
 * They live in one trait because every answer is "whatever TeamScope says" — a
 * controller copy of that rule is how an intern ends up looking at a colleague's
 * task. The service is stateless, so resolving it here is the same object the
 * container would inject.
 */
trait ScopesWorkspace
{
    protected function teamIds(Request $request): array
    {
        return $this->scope()->idsFor($request->user());
    }

    protected function tasksQuery(Request $request): Builder
    {
        return $this->scope()->taskQuery($request->user());
    }

    /** One task, or 404 when it is not this account's to see. */
    protected function task(Request $request, int $id): object
    {
        return $this->scope()->taskFor($request->user(), $id);
    }

    /** @return list<object> the active interns and supervisors this account may assign to */
    protected function members(Request $request): array
    {
        return $this->scope()->membersFor($request->user());
    }

    private function scope(): TeamScope
    {
        return app(TeamScope::class);
    }
}
