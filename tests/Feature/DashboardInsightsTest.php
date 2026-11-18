<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InternshipProgress;
use App\Services\LogStreak;
use App\Services\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    // Wednesday 12 Nov 2026
    private function today(): Carbon
    {
        return Carbon::parse('2026-11-12', 'Asia/Kuala_Lumpur');
    }

    private function log(User $u, string $date): void
    {
        DB::table('daily_logs')->insert(['user_id' => $u->id, 'activity_date' => $date, 'points' => 'x', 'summary' => 'x', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_progress_states(): void
    {
        $mk = fn ($s, $e) => (object) ['internship_start_date' => $s, 'internship_end_date' => $e];

        $this->assertSame('unset', InternshipProgress::for($mk(null, null), $this->today())['state']);
        $this->assertSame('unset', InternshipProgress::for($mk('2026-12-01', '2026-11-01'), $this->today())['state'], 'end before start is treated as unset');

        $active = InternshipProgress::for($mk('2026-11-01', '2026-11-30'), $this->today());
        $this->assertSame('active', $active['state']);
        $this->assertSame(30, $active['total_days']);
        $this->assertSame(12, $active['day']);
        $this->assertSame(2, $active['week']);
        $this->assertSame(5, $active['weeks']);
        $this->assertSame(18, $active['days_left']);
        $this->assertSame(40, $active['percent']);

        $first = InternshipProgress::for($mk('2026-11-12', '2026-11-30'), $this->today());
        $this->assertSame(1, $first['day']);
        $last = InternshipProgress::for($mk('2026-11-01', '2026-11-12'), $this->today());
        $this->assertSame('active', $last['state']);
        $this->assertSame(0, $last['days_left']);
        $this->assertSame(100, $last['percent']);

        $upcoming = InternshipProgress::for($mk('2026-11-20', '2026-12-20'), $this->today());
        $this->assertSame('upcoming', $upcoming['state']);
        $this->assertSame(8, $upcoming['starts_in']);

        $done = InternshipProgress::for($mk('2026-08-01', '2026-10-31'), $this->today());
        $this->assertSame('finished', $done['state']);
        $this->assertSame(100, $done['percent']);
    }

    public function test_streak_counts_weekdays_and_ignores_weekends_and_an_open_today(): void
    {
        $u = User::factory()->create(['role' => 'intern', 'internship_start_date' => '2026-10-01', 'internship_end_date' => '2026-12-31']);
        // Mon 2 .. Fri 6, then Mon 9, Tue 10, Wed 11 logged; today (Thu 12) not yet logged.
        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-09', '2026-11-10', '2026-11-11'] as $d) {
            $this->log($u, $d);
        }
        $s = LogStreak::for($u, Carbon::parse('2026-11-12'));
        $this->assertSame(8, $s['current'], 'weekend between Fri 6 and Mon 9 does not break it; open today is not held against them');
        $this->assertSame(8, $s['best']);
        $this->assertFalse($s['loggedToday']);
    }

    public function test_streak_breaks_on_a_missed_weekday_and_best_is_remembered(): void
    {
        $u = User::factory()->create(['role' => 'intern', 'internship_start_date' => '2026-10-01', 'internship_end_date' => '2026-12-31']);
        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'] as $d) {
            $this->log($u, $d); // 4 in a row, then Fri 6 missed
        }
        foreach (['2026-11-10', '2026-11-11', '2026-11-12'] as $d) {
            $this->log($u, $d); // Mon 9 missed, then 3 in a row incl. today
        }
        $s = LogStreak::for($u, Carbon::parse('2026-11-12'));
        $this->assertSame(3, $s['current']);
        $this->assertSame(4, $s['best']);
        $this->assertTrue($s['loggedToday']);
    }

    public function test_grid_marks_missed_weekend_off_and_future_correctly(): void
    {
        $u = User::factory()->create(['role' => 'intern', 'internship_start_date' => '2026-11-04', 'internship_end_date' => '2026-11-20']);
        $this->log($u, '2026-11-04');
        $s = LogStreak::for($u, Carbon::parse('2026-11-12'), 2);

        $this->assertCount(2, $s['columns']);
        $this->assertCount(7, $s['columns'][0]);
        $states = collect($s['columns'])->flatten(1)->pluck('state', 'date');

        $this->assertSame('off', $states['2026-11-02'], 'before placement start');
        $this->assertSame('logged', $states['2026-11-04']);
        $this->assertSame('missed', $states['2026-11-05']);
        $this->assertSame('weekend', $states['2026-11-07']);
        $this->assertSame('future', $states['2026-11-13']);
        $this->assertTrue(collect($s['columns'])->flatten(1)->firstWhere('date', '2026-11-12')['today']);
        $this->assertSame(1, $s['logged']);
        $this->assertSame('open', $states['2026-11-12'], 'today, not logged yet, is open rather than missed');
        $this->assertSame(6, $s['weekdays'], 'Wed 4 .. Wed 11 are counted; today is still open');
    }

    public function test_only_the_interns_own_logs_count(): void
    {
        $a = User::factory()->create(['role' => 'intern']);
        $b = User::factory()->create(['role' => 'intern']);
        $this->log($b, '2026-11-11');
        $this->assertSame(0, LogStreak::for($a, Carbon::parse('2026-11-12'))['current']);
    }

    public function test_status_tones_are_semantic(): void
    {
        $this->assertSame('neutral', TaskStatus::tone('todo'));
        $this->assertSame('info', TaskStatus::tone('in_progress'));
        $this->assertSame('warn', TaskStatus::tone('review'));
        $this->assertSame('ok', TaskStatus::tone('completed'));
        $this->assertSame('neutral', TaskStatus::tone('mystery'));
        $this->assertSame('danger', TaskStatus::priorityTone('urgent'));
        $this->assertSame('neutral', TaskStatus::priorityTone('normal'));
    }

    public function test_intern_dashboard_shows_timeline_heatmap_and_tone_classes(): void
    {
        $sv = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id,
            'internship_start_date' => now('Asia/Kuala_Lumpur')->subDays(10)->toDateString(),
            'internship_end_date' => now('Asia/Kuala_Lumpur')->addDays(50)->toDateString()]);
        DB::table('work_tasks')->insert(['title' => 'Late one', 'assigned_to' => $intern->id, 'created_by' => $sv->id, 'priority' => 'urgent', 'status' => 'review',
            'due_date' => now('Asia/Kuala_Lumpur')->subDay()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($intern)->get(route('workspace.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('internship-timeline', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('log-streak', $html);
        $this->assertSame(84, substr_count($html, 'class="hm-cell'), '12 weeks x 7 days');
        $this->assertStringContainsString('metric tone-danger', $html, 'overdue > 0 turns the card red');
        $this->assertStringContainsString('metric tone-warn', $html);
        $this->assertStringContainsString('badge danger', $html, 'urgent / overdue use the danger tone');
    }

    public function test_dashboard_without_dates_explains_instead_of_breaking(): void
    {
        $intern = User::factory()->create(['role' => 'intern']);
        $this->actingAs($intern)->get(route('workspace.overview'))->assertOk()->assertSee('internship dates have not been set', false);
    }

    public function test_supervisor_does_not_get_intern_widgets_and_zero_overdue_is_neutral(): void
    {
        $sv = User::factory()->create(['role' => 'supervisor']);
        $html = $this->actingAs($sv)->get(route('workspace.overview'))->assertOk()->getContent();

        $this->assertStringNotContainsString('internship-timeline', $html);
        $this->assertStringNotContainsString('hm-cell', $html);
        $this->assertStringContainsString('metric tone-neutral', $html);
    }
}
