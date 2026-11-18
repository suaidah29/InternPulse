<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner, array $o = []): int
    {
        return DB::table('work_tasks')->insertGetId($o + ['title' => 'T'.uniqid(), 'assigned_to' => $owner->id, 'created_by' => $owner->id, 'priority' => 'normal', 'status' => 'todo', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function team(): array
    {
        $sv = User::factory()->create(['role' => 'supervisor']);
        $a = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);
        $b = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);

        return [$sv, $a, $b];
    }

    public function test_board_groups_tasks_into_four_columns_and_skips_archived(): void
    {
        [$sv, $a] = $this->team();
        $this->task($a, ['title' => 'Alpha', 'status' => 'todo']);
        $this->task($a, ['title' => 'Bravo', 'status' => 'review']);
        $this->task($a, ['title' => 'Charlie', 'status' => 'completed']);
        $this->task($a, ['title' => 'Hidden', 'archived_at' => now()]);

        $html = $this->actingAs($sv)->get(route('workspace.tasks', ['view' => 'board']))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="kanban-col '));
        foreach (['Alpha', 'Bravo', 'Charlie'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->assertStringNotContainsString('Hidden', $html);
        $this->assertStringContainsString('kanban.js', $html);
    }

    public function test_list_view_is_still_the_default(): void
    {
        [$sv, $a] = $this->team();
        $this->task($a);
        $html = $this->actingAs($sv)->get(route('workspace.tasks'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-kanban', $html);
        $this->assertStringContainsString('record-list', $html);
    }

    public function test_only_the_assignee_gets_drag_handles_and_completed_is_locked(): void
    {
        [$sv, $a, $b] = $this->team();
        $this->task($a, ['title' => 'Mine', 'status' => 'todo']);
        $this->task($b, ['title' => 'Theirs', 'status' => 'todo']);
        $this->task($a, ['title' => 'Finished', 'status' => 'completed']);

        $html = $this->actingAs($a)->get(route('workspace.tasks', ['view' => 'board']))->assertOk()->getContent();
        $this->assertStringNotContainsString('Theirs', $html, 'interns only see their own work');
        $this->assertSame(1, substr_count($html, 'draggable="true"'), 'only the open own card is draggable');

        $sup = $this->actingAs($sv)->get(route('workspace.tasks', ['view' => 'board']))->getContent();
        $this->assertSame(0, substr_count($sup, 'draggable="true"'), 'supervisors cannot move interns’ cards');
        $this->assertSame(1, substr_count($html, 'data-locked'));
    }

    public function test_move_endpoint_returns_json_and_records_activity(): void
    {
        [, $a] = $this->team();
        $id = $this->task($a);

        $this->actingAs($a)->postJson(route('workspace.tasks.progress', $id), ['status' => 'in_progress'])
            ->assertOk()->assertJson(['status' => 'in_progress', 'label' => ['In progress', 'Sedang dibuat']]);

        $this->assertDatabaseHas('work_tasks', ['id' => $id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('task_activity', ['task_id' => $id, 'kind' => 'progress']);
    }

    public function test_move_cannot_complete_or_touch_someone_elses_task(): void
    {
        [$sv, $a, $b] = $this->team();
        $id = $this->task($a);

        $this->actingAs($a)->postJson(route('workspace.tasks.progress', $id), ['status' => 'completed'])->assertStatus(422);
        $this->actingAs($b)->postJson(route('workspace.tasks.progress', $id), ['status' => 'review'])->assertNotFound();
        $this->actingAs($sv)->postJson(route('workspace.tasks.progress', $id), ['status' => 'review'])->assertForbidden();
        $this->assertDatabaseHas('work_tasks', ['id' => $id, 'status' => 'todo']);

        $done = $this->task($a, ['status' => 'completed']);
        $this->actingAs($a)->postJson(route('workspace.tasks.progress', $done), ['status' => 'todo'])->assertStatus(409);
    }

    public function test_form_post_still_redirects_with_a_flash(): void
    {
        [, $a] = $this->team();
        $id = $this->task($a);
        $this->actingAs($a)->post(route('workspace.tasks.progress', $id), ['status' => 'review'])->assertRedirect()->assertSessionHas('success');
    }
}
