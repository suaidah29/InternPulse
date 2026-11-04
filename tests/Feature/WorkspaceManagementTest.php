<?php

namespace Tests\Feature;

use App\Mail\SupervisorInvitationMail;
use App\Models\User;
use App\Services\DashboardSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function supervisor(string $department = 'IT'): User
    {
        return User::factory()->create(['role' => 'supervisor', 'department' => $department]);
    }

    private function intern(User $sv): User
    {
        return User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id, 'department' => $sv->department]);
    }

    private function task(User $owner, User $manager, string $title = 'Build intern portal'): int
    {
        return DB::table('work_tasks')->insertGetId(['title' => $title, 'assigned_to' => $owner->id, 'created_by' => $manager->id, 'department' => $owner->department, 'priority' => 'normal', 'status' => 'todo', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_management_pages_and_themes_render(): void
    {
        $this->actingAs($this->manager());
        foreach (['management.people', 'management.departments', 'workspace.tasks', 'workspace.reviews', 'workspace.records', 'workspace.guides', 'management.reports', 'management.activity'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('data-theme-choice="light"', false)->assertSee('data-theme-choice="dark"', false);
        }
    }

    public function test_dashboard_charts_calendar_and_search_respect_team_access(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $other = $this->intern($this->supervisor());
        $date = now('Asia/Kuala_Lumpur')->toDateString();
        $visible = $this->task($intern, $sv, 'Website team work');
        $hidden = $this->task($other, $manager, 'Website confidential work');
        DB::table('work_tasks')->whereIn('id', [$visible, $hidden])->update(['due_date' => $date]);
        $this->actingAs($sv)->get(route('workspace.overview'))->assertOk()->assertSee('Deadline calendar')->assertSee('welcome-banner');
        $summary = DashboardSummary::forUser($sv);
        $this->assertSame(1, (int) $summary['statuses']['todo']);
        $this->assertSame(1, (int) $summary['calendarCounts'][$date]);
        $this->assertSame(2, (int) DashboardSummary::forUser($manager)['statuses']['todo']);
        $this->get(route('workspace.tasks', ['q' => 'Website', 'due_date' => $date]))->assertOk()->assertSee('Website team work')->assertDontSee('Website confidential work');
        $this->get(route('workspace.tasks', ['q' => 'Nonmatching']))->assertOk()->assertDontSee('Website team work');
        DB::table('work_tasks')->where('id', $visible)->update(['archived_at' => now()]);
        $this->assertSame(0, DashboardSummary::forUser($intern)['statuses']->sum());
    }

    public function test_role_dashboards_show_only_the_correct_person_or_team(): void
    {
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $other = $this->intern($this->supervisor());
        $this->task($intern, $sv, 'Visible team assignment');
        $this->task($other, $sv, 'Other team confidential task');
        $this->actingAs($intern)->get(route('dashboard'))->assertRedirect(route('workspace.overview'));
        $this->get(route('workspace.overview'))->assertOk()->assertSee('Visible team assignment')->assertDontSee('Other team confidential task')->assertSee('Today’s checklist')->assertDontSee('Needs your attention');
        $this->actingAs($sv)->get(route('workspace.overview'))->assertOk()->assertSee('Visible team assignment')->assertDontSee('Other team confidential task')->assertSee('Needs your attention')->assertDontSee('Today’s checklist');
        $this->actingAs($this->manager())->get(route('workspace.overview'))->assertForbidden();
    }

    public function test_inactive_users_cannot_continue_existing_sessions(): void
    {
        $user = $this->manager();
        $user->is_active = false;
        $user->save();
        $this->actingAs($user)->get(route('management.people'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_intern_cannot_mutate_people_departments_guides_or_assign_tasks(): void
    {
        $sv = $this->supervisor();
        $this->actingAs($this->intern($sv));
        $this->get(route('management.people'))->assertForbidden();
        $this->post(route('management.departments.store'), ['name' => 'Blocked'])->assertForbidden();
        $this->post(route('management.guides.store'), ['title' => 'Blocked', 'body' => 'No'])->assertForbidden();
        $this->post(route('workspace.tasks.store'), [])->assertForbidden();
        $this->get(route('management.reports'))->assertForbidden();
    }

    public function test_management_can_transfer_intern_and_new_supervisor_gets_scoped_access(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $other = $this->supervisor('Marketing');
        $intern = $this->intern($sv);
        $task = $this->task($intern, $manager);
        $this->actingAs($manager)->post(route('management.people.update', $intern->id), ['name' => 'Transferred Intern', 'department' => 'IT', 'supervisor_id' => $other->id, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $intern->id, 'supervisor_id' => $other->id, 'department' => 'Marketing']);
        $this->actingAs($sv)->get(route('workspace.tasks'))->assertOk()->assertDontSee('Build intern portal');
        $this->post(route('workspace.tasks.archive', $task))->assertNotFound();
        $this->actingAs($other)->get(route('workspace.tasks'))->assertOk()->assertSee('Build intern portal');
    }

    public function test_active_intern_must_be_transferred_before_supervisor_deactivation(): void
    {
        $sv = $this->supervisor();
        $this->intern($sv);
        $this->actingAs($this->manager())->post(route('management.people.update', $sv->id), ['name' => $sv->name, 'department' => 'IT', 'is_active' => 0])->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('users', ['id' => $sv->id, 'is_active' => 1]);
    }

    public function test_department_rename_updates_members_and_used_department_cannot_be_deleted(): void
    {
        $sv = $this->supervisor();
        $this->actingAs($this->manager());
        $id = DB::table('departments')->where('name', 'IT')->value('id');
        $this->post(route('management.departments.update', $id), ['name' => 'Technology'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $sv->id, 'department' => 'Technology']);
        $this->post(route('management.departments.delete', $id))->assertSessionHasErrors('department');
    }

    public function test_management_invites_intern_with_fixed_supervisor_and_role(): void
    {
        Mail::fake();
        $manager = $this->manager();
        $sv = $this->supervisor();
        $this->actingAs($manager)->post(route('management.interns.invite'), ['name' => 'New Intern', 'email' => 'newintern@example.test', 'supervisor_id' => $sv->id, 'role' => 'superadmin'])->assertRedirect();
        $mail = Mail::sent(SupervisorInvitationMail::class)->first();
        $this->assertSame('intern', $mail->inviteRole);
        $token = basename(parse_url($mail->invitationUrl, PHP_URL_PATH));
        $this->post(route('logout'));
        $this->post(route('invitation.accept', $token), ['password' => 'StrongPassword!123', 'password_confirmation' => 'StrongPassword!123'])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newintern@example.test', 'role' => 'intern', 'supervisor_id' => $sv->id, 'department' => 'IT']);
    }

    public function test_task_crud_and_due_date_approval(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $this->actingAs($manager)->post(route('workspace.tasks.store'), ['title' => 'New website', 'description' => 'Build pages', 'assigned_to' => $intern->id, 'priority' => 'urgent', 'status' => 'todo', 'due_date' => now()->addDay()->toDateString()])->assertRedirect();
        $id = DB::table('work_tasks')->value('id');
        $this->actingAs($intern)->post(route('workspace.tasks.due', $id), ['requested_date' => now()->addDays(3)->toDateString(), 'reason' => 'Additional design feedback needs two more working days.'])->assertRedirect();
        $request = DB::table('due_date_requests')->first();
        $this->post(route('workspace.review', ['type' => 'due', 'id' => $request->id]), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($manager)->post(route('workspace.review', ['type' => 'due', 'id' => $request->id]), ['decision' => 'approved', 'note' => 'Approved after discussion'])->assertRedirect();
        $this->assertDatabaseHas('work_tasks', ['id' => $id, 'due_date' => now()->addDays(3)->toDateString()]);
        $this->post(route('workspace.tasks.archive', $id))->assertRedirect();
        $this->assertNotNull(DB::table('work_tasks')->value('archived_at'));
    }

    public function test_private_evidence_is_not_accessible_to_other_supervisors_or_interns(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $other = $this->supervisor('Marketing');
        $id = $this->task($intern, $manager);
        $this->actingAs($intern)->post(route('workspace.tasks.progress', $id), ['status' => 'review', 'progress_note' => 'Screens are complete', 'work_url' => 'https://example.com', 'evidence' => UploadedFile::fake()->image('screen.png')])->assertRedirect();
        $file = DB::table('work_files')->first();
        $this->assertNotNull($file);
        $this->get(route('workspace.files', ['type' => 'task', 'id' => $file->id]))->assertOk();
        $this->actingAs($other)->get(route('workspace.files', ['type' => 'task', 'id' => $file->id]))->assertNotFound();
        $this->actingAs($sv)->get(route('workspace.files', ['type' => 'task', 'id' => $file->id]))->assertOk();
    }

    public function test_logbook_and_attendance_submission_review_and_scope(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $other = $this->supervisor('Marketing');
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $this->actingAs($intern)->post(route('workspace.logbook.store'), ['activity_date' => $today, 'points' => "Created login screen\nUpdated documentation", 'summary' => ''])->assertRedirect();
        $this->post(route('workspace.attendance.store'), ['photo' => UploadedFile::fake()->image('work.jpg')])->assertRedirect();
        $log = DB::table('daily_logs')->first();
        $attendance = DB::table('attendance_records')->first();
        $this->assertStringContainsString('Created login screen.', $log->summary);
        $this->actingAs($other)->post(route('workspace.review', ['type' => 'log', 'id' => $log->id]), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($manager)->post(route('workspace.review', ['type' => 'log', 'id' => $log->id]), ['decision' => 'approved', 'note' => 'Good progress'])->assertRedirect();
        $this->post(route('workspace.review', ['type' => 'attendance', 'id' => $attendance->id]), ['decision' => 'approved', 'note' => 'Verified'])->assertRedirect();
        $this->actingAs($intern)->post(route('workspace.logbook.store'), ['activity_date' => $today, 'points' => 'Change approved log', 'summary' => ''])->assertSessionHasErrors('activity_date');
        $this->post(route('workspace.attendance.store'), ['photo' => UploadedFile::fake()->image('again.jpg')])->assertSessionHasErrors('photo');
    }

    public function test_guides_are_scoped_by_department_and_report_escapes_csv_formulas(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $other = $this->supervisor('Marketing');
        $this->actingAs($manager)->post(route('management.guides.store'), ['title' => 'Internal IT setup', 'department' => 'IT', 'body' => 'Setup Laravel environment.'])->assertRedirect();
        $this->actingAs($other)->get(route('workspace.guides'))->assertDontSee('Internal IT setup');
        $this->actingAs($sv)->get(route('workspace.guides'))->assertSee('Internal IT setup');
        $this->task($sv, $manager, '=SUM(1,1)');
        $response = $this->actingAs($manager)->get(route('management.reports.export'));
        $response->assertOk();
        $this->assertStringContainsString("'=SUM(1,1)", $response->streamedContent());
    }

    public function test_supervisor_cannot_assign_tasks_outside_their_team(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $other = $this->supervisor('Marketing');
        $intern = $this->intern($other);
        $this->actingAs($sv)->post(route('workspace.tasks.store'), ['title' => 'Forbidden task', 'assigned_to' => $intern->id, 'priority' => 'normal', 'status' => 'todo'])->assertSessionHasErrors('assigned_to');
        $this->assertDatabaseCount('work_tasks', 0);
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => false, 'password' => 'StrongPassword!123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword!123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_optional_log_summary_and_optional_approval_note_work(): void
    {
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $this->actingAs($intern)->post(route('workspace.logbook.store'), ['activity_date' => now('Asia/Kuala_Lumpur')->toDateString(), 'points' => 'Finished the page design'])->assertRedirect();
        $log = DB::table('daily_logs')->first();
        $this->assertNotNull($log);
        $this->actingAs($sv)->post(route('workspace.review', ['type' => 'log', 'id' => $log->id]), ['decision' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('daily_logs', ['id' => $log->id, 'status' => 'approved']);
    }

    public function test_supervisor_cannot_approve_own_due_request(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $id = $this->task($sv, $manager);
        $this->actingAs($sv)->post(route('workspace.tasks.due', $id), ['requested_date' => now()->addDays(2)->toDateString(), 'reason' => 'Waiting for required feedback from the client.'])->assertRedirect();
        $req = DB::table('due_date_requests')->first();
        $this->post(route('workspace.review', ['type' => 'due', 'id' => $req->id]), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_completed_task_cannot_be_reopened_via_progress_and_unsafe_links_are_rejected(): void
    {
        $manager = $this->manager();
        $sv = $this->supervisor();
        $intern = $this->intern($sv);
        $id = $this->task($intern, $manager);
        $this->actingAs($intern)->post(route('workspace.tasks.progress', $id), ['status' => 'review', 'work_url' => 'javascript:alert(1)'])->assertSessionHasErrors('work_url');
        DB::table('work_tasks')->where('id', $id)->update(['status' => 'completed']);
        $this->post(route('workspace.tasks.progress', $id), ['status' => 'in_progress'])->assertStatus(409);
    }

    public function test_task_and_guide_forms_render_actual_fields_not_blade_directives(): void
    {
        $this->actingAs($this->manager());
        $this->get(route('workspace.tasks'))->assertOk()->assertSee('name="assigned_to"', false)->assertSee('name="title"', false)->assertDontSee('@include');
        $this->get(route('workspace.guides'))->assertOk()->assertSee('name="body"', false)->assertSee('name="title"', false)->assertDontSee('@include');
    }
}
