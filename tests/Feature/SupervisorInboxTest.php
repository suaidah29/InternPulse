<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActionQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorInboxTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $intern, User $creator, array $overrides = []): int
    {
        return DB::table('work_tasks')->insertGetId($overrides + [
            'title' => 'Draft the onboarding deck',
            'assigned_to' => $intern->id,
            'created_by' => $creator->id,
            'department' => 'Engineering',
            'priority' => 'medium',
            'status' => 'in_progress',
            'due_date' => now('Asia/Kuala_Lumpur')->addDays(3)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_intern_has_no_inbox(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);

        $this->actingAs($intern)->get(route('workspace.inbox'))->assertForbidden();
        $this->assertSame(0, app(ActionQueue::class)->countFor($intern));
    }

    public function test_it_shows_only_your_own_team_and_nobody_elses(): void
    {
        $mine = User::factory()->create(['role' => 'supervisor']);
        $theirs = User::factory()->create(['role' => 'supervisor']);
        $myIntern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $mine->id, 'name' => 'My Intern']);
        $theirIntern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $theirs->id, 'name' => 'Their Intern']);

        $this->task($myIntern, $mine, ['title' => 'My pending review', 'status' => 'review']);
        $this->task($theirIntern, $theirs, ['title' => 'Their pending review', 'status' => 'review']);

        $this->actingAs($mine)->get(route('workspace.inbox'))
            ->assertOk()
            ->assertSee('My pending review')
            ->assertDontSee('Their pending review');
    }

    public function test_a_supervisor_never_sees_their_own_work_as_waiting_on_them(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->task($supervisor, $supervisor, ['title' => 'My own task', 'status' => 'review']);

        $this->actingAs($supervisor)->get(route('workspace.inbox'))
            ->assertOk()
            ->assertDontSee('My own task')
            ->assertSee('Nothing is waiting on you.');
    }

    public function test_it_collects_every_kind_of_waiting_item(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id, 'name' => 'Aina Rahman']);

        $reviewTask = $this->task($intern, $supervisor, ['title' => 'Finished deck', 'status' => 'review']);
        $lateTask = $this->task($intern, $supervisor, [
            'title' => 'Late report',
            'due_date' => now('Asia/Kuala_Lumpur')->subDays(4)->toDateString(),
        ]);

        DB::table('due_date_requests')->insert([
            'task_id' => $lateTask, 'requested_by' => $intern->id,
            'requested_date' => now('Asia/Kuala_Lumpur')->addDays(10)->toDateString(),
            'reason' => 'Waiting on the vendor', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('logbook_documents')->insert([
            'user_id' => $intern->id, 'supervisor_id' => $supervisor->id,
            'title' => 'Week 3 logbook', 'path' => 'logbooks/week3.pdf', 'original_name' => 'week3.pdf',
            'sha256' => str_repeat('a', 64), 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendance_records')->insert([
            'user_id' => $intern->id, 'attendance_date' => now('Asia/Kuala_Lumpur')->toDateString(),
            'checked_in_at' => now(), 'photo_path' => 'attendance/x.jpg', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_blockers')->insert([
            'task_id' => $reviewTask, 'intern_id' => $intern->id, 'category' => 'access',
            'description' => 'No staging credentials', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $report = app(ActionQueue::class)->for($supervisor);
        $counts = collect($report['sections'])->pluck('count', 'key')->all();

        $this->assertSame(1, $counts['review']);
        $this->assertSame(1, $counts['due']);
        $this->assertSame(1, $counts['logbook']);
        $this->assertSame(1, $counts['attendance']);
        $this->assertSame(1, $counts['blockers']);
        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(6, $report['total']);

        $this->actingAs($supervisor)->get(route('workspace.inbox'))
            ->assertOk()
            ->assertSee('Finished deck')
            ->assertSee('Late report')
            ->assertSee('Waiting on the vendor')
            ->assertSee('Week 3 logbook')
            ->assertSee('No staging credentials')
            ->assertSee('Aina Rahman');
    }

    public function test_the_sidebar_badge_shows_the_number_waiting(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $this->task($intern, $supervisor, ['status' => 'review']);
        $this->task($intern, $supervisor, ['title' => 'Second', 'status' => 'review']);

        $this->actingAs($supervisor)->get(route('workspace.overview'))
            ->assertOk()
            ->assertSee('nav-badge', false)
            ->assertSee('aria-label="2 waiting"', false);
    }

    public function test_a_clear_inbox_says_so_instead_of_showing_empty_sections(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);

        $this->actingAs($supervisor)->get(route('workspace.inbox'))
            ->assertOk()
            ->assertSee('Nothing is waiting on you.')
            ->assertDontSee('Open full page');
    }

    public function test_the_rows_can_be_cleared_from_the_inbox(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $task = $this->task($intern, $supervisor);
        $request = DB::table('due_date_requests')->insertGetId([
            'task_id' => $task, 'requested_by' => $intern->id,
            'requested_date' => now('Asia/Kuala_Lumpur')->addDays(7)->toDateString(),
            'reason' => 'Vendor delay', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($supervisor)->get(route('workspace.inbox'))
            ->assertSee(route('workspace.review', ['type' => 'due', 'id' => $request]), false);

        $this->assertSame(1, app(ActionQueue::class)->countFor($supervisor));

        $this->post(route('workspace.review', ['type' => 'due', 'id' => $request]), ['decision' => 'approved'])
            ->assertRedirect();

        $this->assertSame(0, app(ActionQueue::class)->countFor($supervisor), 'The cleared request must leave the inbox.');
        $this->assertDatabaseHas('due_date_requests', ['id' => $request, 'status' => 'approved']);
    }

    public function test_a_logbook_row_offers_verification_and_warns_without_a_signature(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $document = DB::table('logbook_documents')->insertGetId([
            'user_id' => $intern->id, 'supervisor_id' => $supervisor->id,
            'title' => 'Week 4 logbook', 'path' => 'logbooks/week4.pdf', 'original_name' => 'week4.pdf',
            'sha256' => str_repeat('b', 64), 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($supervisor)->get(route('workspace.inbox'))
            ->assertOk()
            ->assertSee(route('workspace.logbook.verify', $document), false)
            ->assertSee('Upload a signature in Edit profile');
    }

    public function test_a_superadmin_sees_every_team(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $this->task($intern, $supervisor, ['title' => 'A task in Engineering', 'status' => 'review']);

        $this->actingAs($admin)->get(route('workspace.inbox'))
            ->assertOk()
            ->assertSee('A task in Engineering');
    }
}
