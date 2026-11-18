<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function notificationsFor(User $user): array
    {
        return DB::table('workspace_notifications')->where('user_id', $user->id)->pluck('message')->all();
    }

    private function task(User $assignee, array $overrides = []): int
    {
        return DB::table('work_tasks')->insertGetId(array_merge([
            'title' => 'Homepage copy',
            'description' => 'Write the copy',
            'assigned_to' => $assignee->id,
            'created_by' => $assignee->id,
            'priority' => 'normal',
            'status' => 'in_progress',
            'due_date' => Carbon::today()->subDays(2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_intern_without_a_log_gets_one_reminder_per_day(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);

        $this->artisan('logbook:remind')->assertSuccessful();
        $this->artisan('logbook:remind')->assertSuccessful();

        $messages = $this->notificationsFor($intern);
        $this->assertCount(1, $messages, 'a re-run on the same day must not stack reminders');
        $this->assertStringContainsString('Logbook reminder', $messages[0]);
    }

    public function test_reminder_skips_interns_who_logged_or_are_outside_their_internship(): void
    {
        $logged = User::factory()->create(['role' => 'intern']);
        DB::table('daily_logs')->insert([
            'user_id' => $logged->id,
            'activity_date' => Carbon::today()->toDateString(),
            'points' => 'Reviewed the design',
            'summary' => 'Reviewed the design',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $finished = User::factory()->create([
            'role' => 'intern',
            'internship_start_date' => Carbon::today()->subMonths(6)->toDateString(),
            'internship_end_date' => Carbon::today()->subMonth()->toDateString(),
        ]);
        $notStarted = User::factory()->create([
            'role' => 'intern',
            'internship_start_date' => Carbon::today()->addMonth()->toDateString(),
        ]);
        $inactive = User::factory()->create(['role' => 'intern', 'is_active' => false]);

        $this->artisan('logbook:remind')->assertSuccessful();

        foreach ([$logged, $finished, $notStarted, $inactive] as $user) {
            $this->assertSame([], $this->notificationsFor($user), "{$user->name} should not be reminded");
        }
    }

    public function test_reminder_can_target_a_past_day(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);
        $day = Carbon::today()->subDays(3);

        $this->artisan('logbook:remind', ['--date' => $day->toDateString()])->assertSuccessful();

        $messages = $this->notificationsFor($intern);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString($day->format('j M Y'), $messages[0]);
    }

    public function test_overdue_task_notifies_assignee_and_supervisor_once(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);
        $this->task($intern);

        $this->artisan('tasks:overdue')->assertSuccessful();
        $this->artisan('tasks:overdue')->assertSuccessful();

        $internMessages = $this->notificationsFor($intern);
        $this->assertCount(1, $internMessages);
        $this->assertStringContainsString('Overdue task', $internMessages[0]);
        $this->assertStringContainsString('Homepage copy', $internMessages[0]);

        $supervisorMessages = $this->notificationsFor($supervisor);
        $this->assertCount(1, $supervisorMessages);
        $this->assertStringContainsString('1 overdue task', $supervisorMessages[0]);
    }

    public function test_completed_archived_and_future_tasks_are_not_chased(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $supervisor->id]);

        $this->task($intern, ['title' => 'Done already', 'status' => 'completed']);
        $this->task($intern, ['title' => 'Archived one', 'archived_at' => now()]);
        $this->task($intern, ['title' => 'Due next week', 'due_date' => Carbon::today()->addWeek()->toDateString()]);
        $this->task($intern, ['title' => 'No due date', 'due_date' => null]);

        $this->artisan('tasks:overdue')->assertSuccessful();

        $this->assertSame([], $this->notificationsFor($intern));
        $this->assertSame([], $this->notificationsFor($supervisor));
    }

    public function test_unassigned_supervisor_intern_does_not_break_the_digest(): void
    {
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => null]);
        $this->task($intern);

        $this->artisan('tasks:overdue')->assertSuccessful();

        $this->assertCount(1, $this->notificationsFor($intern));
    }

    public function test_notify_once_resets_on_a_new_day(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);

        $this->assertTrue(Reminders::notifyOnce($intern->id, 'Logbook reminder', 'Logbook reminder: yesterday'));
        $this->assertFalse(Reminders::notifyOnce($intern->id, 'Logbook reminder', 'Logbook reminder: again'));

        DB::table('workspace_notifications')->update(['created_at' => Carbon::yesterday()]);

        $this->assertTrue(Reminders::notifyOnce($intern->id, 'Logbook reminder', 'Logbook reminder: today'));
        $this->assertCount(2, $this->notificationsFor($intern));
    }

    public function test_the_schedule_is_registered(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->implode(' ');

        $this->assertStringContainsString('logbook:remind', $commands);
        $this->assertStringContainsString('tasks:overdue', $commands);
    }
}
