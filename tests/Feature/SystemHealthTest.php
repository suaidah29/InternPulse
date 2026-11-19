<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
    }

    public function test_only_a_superadmin_can_open_it(): void
    {
        $this->actingAs($this->superadmin())->get('/management/health')
            ->assertOk()
            ->assertSee('System health', false);

        $this->actingAs(User::factory()->create(['role' => 'supervisor']))->get('/management/health')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'intern']))->get('/management/health')
            ->assertForbidden();
    }

    public function test_the_sidebar_offers_it_to_a_superadmin(): void
    {
        $this->actingAs($this->superadmin())->get('/management/health')
            ->assertSee(route('management.health'), false);
    }

    public function test_a_healthy_install_reports_no_warnings(): void
    {
        Cache::put('schedule_heartbeat', now()->toIso8601String(), now()->addDay());

        $report = app(SystemHealth::class)->report();

        $this->assertSame('ok', $report['status']);
        $this->assertSame([], array_values(array_filter(
            $report['checks'],
            fn (array $check): bool => $check['ok'] === false,
        )));
    }

    public function test_it_flags_a_missing_scheduler_heartbeat(): void
    {
        Cache::forget('schedule_heartbeat');

        $report = app(SystemHealth::class)->report();
        $scheduler = collect($report['checks'])->firstWhere('label', 'Scheduler');

        $this->assertFalse($scheduler['ok']);
        $this->assertStringContainsString('schedule:run', $scheduler['detail']);
        $this->assertSame('warn', $report['status']);
    }

    public function test_it_flags_a_stale_scheduler_heartbeat(): void
    {
        Cache::put('schedule_heartbeat', now()->subMinutes(40)->toIso8601String(), now()->addDay());

        $scheduler = collect(app(SystemHealth::class)->report()['checks'])->firstWhere('label', 'Scheduler');

        $this->assertFalse($scheduler['ok']);
        $this->assertStringContainsString('40 minutes ago', $scheduler['detail']);
    }

    public function test_it_flags_a_backed_up_queue(): void
    {
        Cache::put('schedule_heartbeat', now()->toIso8601String(), now()->addDay());
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->subMinutes(45)->timestamp,
        ]);

        $report = app(SystemHealth::class)->report();
        $worker = collect($report['checks'])->firstWhere('label', 'Queue worker');

        $this->assertSame(1, $report['queue']['pending']);
        $this->assertFalse($worker['ok']);
        $this->assertStringContainsString('queue:work', $worker['detail']);
    }

    public function test_failed_jobs_are_counted_and_listed(): void
    {
        Cache::put('schedule_heartbeat', now()->toIso8601String(), now()->addDay());
        DB::table('failed_jobs')->insert([
            'uuid' => 'a-uuid',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: SMTP refused the connection\nstack trace here",
            'failed_at' => now(),
        ]);

        $report = app(SystemHealth::class)->report();

        $this->assertSame(1, $report['queue']['failed']);
        $this->assertSame('default', $report['queue']['failed_rows']->first()->queue);
        $this->actingAs($this->superadmin())->get('/management/health')
            ->assertOk()
            ->assertSee('SMTP refused the connection', false);
    }

    public function test_failed_invitation_mail_is_listed_with_a_resend_link(): void
    {
        Cache::put('schedule_heartbeat', now()->toIso8601String(), now()->addDay());
        $admin = $this->superadmin();
        $id = DB::table('supervisor_invitations')->insertGetId([
            'name' => 'Aina Rahman',
            'email' => 'aina@internpulse.test',
            'department' => 'Engineering',
            'token_hash' => hash('sha256', 'token'),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(3),
            'delivery_status' => 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, app(SystemHealth::class)->report()['mail']['failed']);

        $this->actingAs($admin)->get('/management/health')
            ->assertOk()
            ->assertSee('aina@internpulse.test', false)
            ->assertSee(route('management.invitations.resend', $id), false);
    }

    public function test_the_schedule_is_read_from_the_console_routes(): void
    {
        $events = app(SystemHealth::class)->report()['scheduler']['events'];

        $this->assertStringContainsString('logbook:remind', $events);
        $this->assertStringContainsString('tasks:overdue', $events);
    }

    public function test_the_heartbeat_command_is_scheduled_every_five_minutes(): void
    {
        $event = $this->scheduledEvent('Record that the scheduler is running');

        $this->assertStringContainsString('*/5', $event->expression);

        $event->run($this->app);
        $this->assertNotNull(Cache::get('schedule_heartbeat'), 'Running the event must record the heartbeat.');
    }

    private function scheduledEvent(string $description): \Illuminate\Console\Scheduling\Event
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        foreach ($schedule->events() as $event) {
            if ($event->description === $description) {
                return $event;
            }
        }

        $this->fail('No scheduled event described as "'.$description.'".');
    }
}
