<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every workspace page should open with the same frosted header band: the
 * eyebrow chip, one h1 with the page name and one line of context. Profile,
 * task details and evaluations used to bury their h1 inside a card instead.
 */
class PageHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function intern(): array
    {
        $sv = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);
        $task = DB::table('work_tasks')->insertGetId([
            'title' => 'Draft the onboarding deck', 'assigned_to' => $intern->id, 'created_by' => $sv->id,
            'priority' => 'normal', 'status' => 'todo', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$sv, $intern, $task];
    }

    private function page(string $url): string
    {
        return $this->get($url)->assertOk()->getContent();
    }

    private function assertHeaderBand(string $html, string $context): void
    {
        $this->assertStringContainsString('class="glass page-header"', $html, $context.': no glass header band');
        $this->assertSame(1, substr_count($html, '<h1'), $context.': expected exactly one h1');
        $this->assertStringNotContainsString('class="page-heading"', $html, $context.': old page-heading markup left behind');
    }

    public function test_every_intern_page_opens_with_the_same_glass_header(): void
    {
        [, $intern, $task] = $this->intern();
        $this->actingAs($intern);

        foreach ([
            'tasks' => route('workspace.tasks'),
            'task detail' => route('workspace.tasks.show', $task),
            'attendance' => route('workspace.records'),
            'guides' => route('workspace.guides'),
            'logbook' => route('workspace.logbook'),
            'notifications' => route('workspace.notifications'),
            'evaluations' => route('workspace.evaluations.show', $intern->id),
            'profile' => route('profile.edit'),
        ] as $label => $url) {
            $this->assertHeaderBand($this->page($url), $label);
        }
    }

    public function test_intern_dashboard_uses_the_welcome_banner_as_its_header(): void
    {
        [, $intern] = $this->intern();

        $html = $this->actingAs($intern)->get(route('workspace.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('welcome-banner glass', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'dashboard: expected exactly one h1');
    }

    public function test_header_shows_the_page_eyebrow_and_the_task_facts(): void
    {
        [$sv, $intern, $task] = $this->intern();

        $tasks = $this->actingAs($intern)->get(route('workspace.tasks'))->assertOk()->getContent();
        $this->assertStringContainsString('PROGRESS WITH PURPOSE', $tasks);
        // The list/board switch belongs to the header, not to a separate bar.
        $this->assertMatchesRegularExpression('/page-header.*view-toggle/is', $tasks);

        DB::table('work_tasks')->where('id', $task)->update(['department' => 'Engineering', 'due_date' => '2026-12-31']);
        $detail = $this->actingAs($intern)->get(route('workspace.tasks.show', $task))->assertOk()->getContent();
        $this->assertStringContainsString('Draft the onboarding deck', $detail);
        $this->assertStringContainsString('Engineering', $detail);
        $this->assertStringContainsString($intern->name, $detail, 'the assignee is named in the header lede');
        $this->assertStringContainsString('2026-12-31', $detail);
        $this->assertStringContainsString('page-header-actions', $detail, 'status badge should sit in the header actions');
    }

    public function test_header_actions_keep_their_forms_working(): void
    {
        [, $intern] = $this->intern();
        DB::table('workspace_notifications')->insert([
            'user_id' => $intern->id, 'message' => 'A new task was assigned to you',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($intern)->get(route('workspace.notifications'))->assertOk()->getContent();
        $this->assertStringContainsString('page-header-actions', $html);
        $this->assertStringContainsString(route('workspace.notifications.read'), $html);

        $this->post(route('workspace.notifications.read'))->assertRedirect();
        $this->assertSame(1, DB::table('workspace_notifications')->where('user_id', $intern->id)->whereNotNull('read_at')->count());
    }

    public function test_component_escapes_plain_text_headings_and_keeps_translated_markup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin']));

        $html = $this->blade(
            '<x-page-header :eyebrow="$t(\'EYEBROW\',\'EYEBROW MS\')" :heading="$raw" :lede="$t(\'Line of context\',\'Baris konteks\')" />',
            ['t' => fn (string $en, string $ms) => new \Illuminate\Support\HtmlString('<span data-en="'.e($en).'" data-ms="'.e($ms).'">'.e($en).'</span>'), 'raw' => 'Fix <script>alert(1)</script> now']
        )->__toString();

        $this->assertStringContainsString('class="glass page-header"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('data-ms="EYEBROW MS"', $html, 'translated markup should not be escaped');
        $this->assertStringNotContainsString('page-header-actions', $html, 'no empty actions block when the slot is unused');
    }

    public function test_management_and_supervisor_pages_use_the_same_header_band(): void
    {
        $sa = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($sa);
        foreach ([
            'management overview' => route('management.dashboard'),
            'people' => route('management.people'),
            'departments' => route('management.departments'),
            'reports' => route('management.reports'),
            'audit trail' => route('management.activity'),
        ] as $label => $url) {
            $this->assertHeaderBand($this->page($url), $label);
        }

        $sv = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($sv);
        foreach ([
            'approvals' => route('workspace.reviews'),
            'team tasks' => route('workspace.tasks'),
            'team attendance' => route('workspace.records'),
        ] as $label => $url) {
            $this->assertHeaderBand($this->page($url), $label);
        }
    }
}
