<?php

namespace App\Providers;

use App\Policies\AttendancePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DueDateRequestPolicy;
use App\Policies\InternEvaluationPolicy;
use App\Policies\InternGuidePolicy;
use App\Policies\LogbookPolicy;
use App\Policies\Policy;
use App\Policies\SupervisorInvitationPolicy;
use App\Policies\TaskBlockerPolicy;
use App\Policies\WorkspacePolicy;
use App\Policies\WorkTaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Policies for rows, not models.
 *
 * The application reads with the query builder, so a task or a logbook document
 * arrives as a plain object and Gate cannot pick a policy from its class. Each
 * policy therefore publishes the ability names it answers to, and they are
 * registered here: `Gate::authorize('task.decide', $task)`.
 *
 * App\Policies\UserPolicy is absent from this list on purpose — its subject is an
 * Eloquent User, so `Gate::authorize('update', $person)` finds it by convention.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    /** @var list<class-string<Policy>> */
    private const POLICIES = [
        WorkTaskPolicy::class,
        DueDateRequestPolicy::class,
        AttendancePolicy::class,
        TaskBlockerPolicy::class,
        LogbookPolicy::class,
        InternEvaluationPolicy::class,
        InternGuidePolicy::class,
        DepartmentPolicy::class,
        SupervisorInvitationPolicy::class,
        WorkspacePolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $policy) {
            foreach ($policy::abilities() as $ability => $method) {
                Gate::define($ability, $policy.'@'.$method);
            }
        }
    }
}
