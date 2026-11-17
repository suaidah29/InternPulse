<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_requests_are_scoped_and_resolved_with_feedback(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $other = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $outsider = User::factory()->create(['role' => 'intern']);
        $task = DB::table('work_tasks')->insertGetId(['title' => 'Assigned work', 'assigned_to' => $intern->id, 'created_by' => $supervisor->id, 'created_at' => now(), 'updated_at' => now()]);
        $url = route('workspace.tasks.blockers.store', $task);
        $this->actingAs($outsider)->post($url, ['category' => 'access', 'description' => 'Need access'])->assertNotFound();
        $this->actingAs($intern)->post($url, ['category' => 'access', 'description' => 'Need access'])->assertRedirect();
        $this->post($url, ['category' => 'other', 'description' => 'Duplicate'])->assertSessionHasErrors('description');
        $blocker = DB::table('task_blockers')->first();
        $this->actingAs($other)->post(route('workspace.blockers.resolve', $blocker->id), ['resolution' => 'Done'])->assertForbidden();
        $this->actingAs($supervisor)->post(route('workspace.blockers.resolve', $blocker->id), ['resolution' => 'Access granted'])->assertRedirect();
        $this->assertDatabaseHas('task_blockers', ['id' => $blocker->id, 'status' => 'resolved', 'resolution' => 'Access granted']);
        $this->actingAs($intern)->get(route('workspace.tasks.show', $task))->assertSee('Access granted');
    }

    public function test_only_assigned_supervisor_can_write_evaluation_and_intern_can_read_it(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $other = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $data = ['period' => 'midpoint', 'quality' => 4, 'communication' => 5, 'teamwork' => 4, 'feedback' => 'Good progress'];
        $this->actingAs($other)->post(route('workspace.evaluations.save', $intern->id), $data)->assertNotFound();
        $this->actingAs($supervisor)->post(route('workspace.evaluations.save', $intern->id), $data)->assertRedirect();
        $this->actingAs($intern)->get(route('workspace.evaluations.show', $intern->id))->assertOk()->assertSee('Good progress');
        $this->actingAs($other)->get(route('workspace.evaluations.show', $intern->id))->assertForbidden();
    }
}
