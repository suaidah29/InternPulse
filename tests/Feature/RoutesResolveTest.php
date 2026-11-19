<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A route that names a controller method which no longer exists only fails when
 * somebody visits it, and then as a 500. Splitting a controller is exactly the
 * change that can do that quietly, so this walks every registered route.
 */
class RoutesResolveTest extends TestCase
{
    public function test_every_route_points_at_a_controller_method_that_exists(): void
    {
        $checked = 0;

        /** @var Route $route */
        foreach (Router::getRoutes() as $route) {
            $action = $route->getActionName();

            if ($action === 'Closure' || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);

            $this->assertTrue(class_exists($class), "Route {$route->uri()} names a missing controller {$class}");
            $this->assertTrue(
                method_exists($class, $method),
                "Route {$route->uri()} names {$class}::{$method}(), which does not exist"
            );
            $this->assertTrue(
                (new ReflectionMethod($class, $method))->isPublic(),
                "Route {$route->uri()} names {$class}::{$method}(), which is not public"
            );

            $checked++;
        }

        $this->assertGreaterThan(60, $checked, 'expected the whole application to be routed to controllers');
    }

    public function test_the_workspace_and_management_names_are_still_routed(): void
    {
        // Route names are what the views link to; the controller behind them moved.
        foreach ([
            'workspace.overview', 'workspace.tasks', 'workspace.tasks.store', 'workspace.tasks.update',
            'workspace.tasks.show', 'workspace.tasks.archive', 'workspace.tasks.progress',
            'workspace.tasks.due', 'workspace.tasks.comment', 'workspace.tasks.decision',
            'workspace.reviews', 'workspace.review', 'workspace.records', 'workspace.attendance.store',
            'workspace.team', 'workspace.team.invite', 'workspace.guides', 'workspace.notifications',
            'workspace.notifications.read', 'workspace.files',
            'management.people', 'management.people.update', 'management.people.internship',
            'management.people.two-factor', 'management.interns.invite', 'management.departments',
            'management.departments.store', 'management.departments.update', 'management.departments.delete',
            'management.guides.store', 'management.guides.update', 'management.guides.delete',
            'management.reports', 'management.reports.export', 'management.activity',
        ] as $name) {
            $this->assertNotNull(Router::getRoutes()->getByName($name), "route {$name} is gone");
        }
    }
}
