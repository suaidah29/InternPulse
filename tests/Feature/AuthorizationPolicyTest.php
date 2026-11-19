<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The rules themselves, asserted through the Gate rather than through a request.
 *
 * The HTTP tests already prove the controllers call them; this file pins down what
 * each answer means, so a later change to a policy has to be argued with here.
 */
class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $supervisor;

    private User $otherSupervisor;

    private User $intern;

    private User $otherIntern;

    private object $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => 'superadmin', 'department' => 'IT']);
        $this->supervisor = User::factory()->create(['role' => 'supervisor', 'department' => 'IT']);
        $this->otherSupervisor = User::factory()->create(['role' => 'supervisor', 'department' => 'Finance']);
        $this->intern = User::factory()->create(['role' => 'intern', 'department' => 'IT', 'supervisor_id' => $this->supervisor->id]);
        $this->otherIntern = User::factory()->create(['role' => 'intern', 'department' => 'Finance', 'supervisor_id' => $this->otherSupervisor->id]);
        $this->task = $this->task($this->intern);
    }

    private function task(User $assignee, array $overrides = []): object
    {
        $id = DB::table('work_tasks')->insertGetId($overrides + [
            'title' => 'Draft the deck',
            'assigned_to' => $assignee->id,
            'created_by' => $this->supervisor->id,
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('work_tasks')->where('id', $id)->first();
    }

    /** @param  array<string, mixed>  $arguments */
    private function allows(User $user, string $ability, mixed ...$arguments): bool
    {
        return Gate::forUser($user)->allows($ability, $arguments);
    }

    public function test_work_is_handed_out_and_edited_by_reviewers_only(): void
    {
        $this->assertTrue($this->allows($this->manager, 'task.create'));
        $this->assertTrue($this->allows($this->supervisor, 'task.create'));
        $this->assertFalse($this->allows($this->intern, 'task.create'), 'an intern never creates work');

        $this->assertTrue($this->allows($this->supervisor, 'task.update', $this->task));
        $this->assertFalse($this->allows($this->otherSupervisor, 'task.update', $this->task));
        $this->assertFalse($this->allows($this->intern, 'task.update', $this->task));
        $this->assertTrue($this->allows($this->supervisor, 'task.archive', $this->task));
    }

    public function test_a_supervisor_does_not_manage_the_work_they_gave_themselves(): void
    {
        $own = $this->task($this->supervisor);

        $this->assertFalse($this->allows($this->supervisor, 'task.update', $own), 'not editable');
        $this->assertFalse($this->allows($this->supervisor, 'task.archive', $own), 'not archivable');
        $this->assertFalse($this->allows($this->supervisor, 'task.assign', $own), 'no assign panel');
        $this->assertFalse($this->allows($this->supervisor, 'task.decide', $own), 'cannot approve own work');
        $this->assertTrue($this->allows($this->manager, 'task.decide', $own), 'Management may');
    }

    public function test_progress_belongs_to_the_assignee_and_approval_to_their_reviewer(): void
    {
        $this->assertTrue($this->allows($this->intern, 'task.progress', $this->task));
        $this->assertTrue($this->allows($this->intern, 'task.requestDue', $this->task));
        $this->assertFalse($this->allows($this->otherIntern, 'task.progress', $this->task));
        $this->assertFalse($this->allows($this->supervisor, 'task.progress', $this->task));

        $this->assertTrue($this->allows($this->supervisor, 'task.decide', $this->task));
        $this->assertFalse($this->allows($this->otherSupervisor, 'task.decide', $this->task));
        $this->assertFalse($this->allows($this->intern, 'task.decide', $this->task));
    }

    public function test_the_review_queues_belong_to_the_supervisor_who_owns_the_person(): void
    {
        $due = (object) ['requested_by' => $this->intern->id];
        $attendance = (object) ['user_id' => $this->intern->id];

        $this->assertTrue($this->allows($this->supervisor, 'dueRequest.decide', $due));
        $this->assertFalse($this->allows($this->otherSupervisor, 'dueRequest.decide', $due));
        $this->assertFalse($this->allows($this->intern, 'dueRequest.decide', $due));
        $this->assertTrue($this->allows($this->manager, 'dueRequest.decide', $due));

        $this->assertTrue($this->allows($this->supervisor, 'attendance.decide', $attendance));
        $this->assertFalse($this->allows($this->otherSupervisor, 'attendance.decide', $attendance));
        $this->assertTrue($this->allows($this->supervisor, 'attendance.viewFile', $attendance));
        $this->assertFalse($this->allows($this->otherIntern, 'attendance.viewFile', $attendance));
    }

    public function test_attendance_is_recorded_by_interns_only(): void
    {
        $this->assertTrue($this->allows($this->intern, 'attendance.record'));
        $this->assertFalse($this->allows($this->supervisor, 'attendance.record'));
        $this->assertFalse($this->allows($this->manager, 'attendance.record'));
    }

    public function test_help_requests_are_raised_by_interns_and_resolved_by_their_reviewer(): void
    {
        $blocker = (object) ['intern_id' => $this->intern->id];

        $this->assertTrue($this->allows($this->intern, 'blocker.create'));
        $this->assertFalse($this->allows($this->supervisor, 'blocker.create'));

        $this->assertTrue($this->allows($this->supervisor, 'blocker.resolve', $blocker));
        $this->assertFalse($this->allows($this->otherSupervisor, 'blocker.resolve', $blocker));
        $this->assertTrue($this->allows($this->manager, 'blocker.resolve', $blocker));
    }

    public function test_the_logbook_chain_is_interns_writing_and_supervisors_verifying(): void
    {
        $this->assertTrue($this->allows($this->intern, 'logbook.view'));
        $this->assertTrue($this->allows($this->supervisor, 'logbook.view'));
        $this->assertFalse($this->allows($this->manager, 'logbook.view'), 'Management reads reports, not the chain');

        $this->assertTrue($this->allows($this->intern, 'log.write'));
        $this->assertFalse($this->allows($this->supervisor, 'log.write'));
        $this->assertTrue($this->allows($this->intern, 'logbook.create'));
        $this->assertTrue($this->allows($this->intern, 'logbook.resubmit'));

        $this->assertTrue($this->allows($this->supervisor, 'logbook.verify'));
        $this->assertFalse($this->allows($this->intern, 'logbook.verify'));
        $this->assertFalse($this->allows($this->manager, 'logbook.verify'));
    }

    public function test_an_interns_ratings_are_readable_by_them_their_supervisor_and_management(): void
    {
        $this->assertTrue($this->allows($this->intern, 'evaluation.view', $this->intern));
        $this->assertTrue($this->allows($this->supervisor, 'evaluation.view', $this->intern));
        $this->assertTrue($this->allows($this->manager, 'evaluation.view', $this->intern));
        $this->assertFalse($this->allows($this->otherIntern, 'evaluation.view', $this->intern));
        $this->assertFalse($this->allows($this->otherSupervisor, 'evaluation.view', $this->intern));
    }

    public function test_a_guide_is_readable_by_the_department_it_was_written_for(): void
    {
        $departmental = (object) ['department' => 'IT'];
        $shared = (object) ['department' => null];

        $this->assertTrue($this->allows($this->intern, 'guide.download', $shared), 'no department = everyone');
        $this->assertTrue($this->allows($this->supervisor, 'guide.download', $departmental));
        $this->assertFalse($this->allows($this->otherSupervisor, 'guide.download', $departmental));
        $this->assertTrue($this->allows($this->manager, 'guide.download', $departmental));

        $this->assertTrue($this->allows($this->manager, 'guide.save'));
        $this->assertFalse($this->allows($this->supervisor, 'guide.save'));
        $this->assertFalse($this->allows($this->intern, 'guide.delete'));
    }

    public function test_departments_and_invitation_letters_are_management_work(): void
    {
        $this->assertTrue($this->allows($this->manager, 'department.save'));
        $this->assertTrue($this->allows($this->manager, 'department.delete'));
        $this->assertFalse($this->allows($this->supervisor, 'department.save'));
        $this->assertFalse($this->allows($this->supervisor, 'department.delete'));

        $this->assertTrue($this->allows($this->manager, 'invitation.resend'));
        $this->assertTrue($this->allows($this->manager, 'invitation.revoke'));
        $this->assertFalse($this->allows($this->supervisor, 'invitation.resend'));
    }

    public function test_a_supervisor_invites_into_their_own_team_only(): void
    {
        $this->assertTrue($this->allows($this->supervisor, 'invitation.invite', (string) $this->supervisor->id));
        $this->assertFalse($this->allows($this->supervisor, 'invitation.invite', (string) $this->otherSupervisor->id));
        $this->assertTrue($this->allows($this->manager, 'invitation.invite', (string) $this->supervisor->id));
        $this->assertFalse($this->allows($this->intern, 'invitation.invite', (string) $this->supervisor->id));
    }

    public function test_the_workspace_screens_are_open_to_the_roles_that_use_them(): void
    {
        $this->assertTrue($this->allows($this->intern, 'workspace.overview'));
        $this->assertTrue($this->allows($this->supervisor, 'workspace.overview'));
        $this->assertFalse($this->allows($this->manager, 'workspace.overview'), 'Management has /management');

        $this->assertTrue($this->allows($this->supervisor, 'workspace.team'));
        $this->assertFalse($this->allows($this->intern, 'workspace.team'));

        $this->assertTrue($this->allows($this->supervisor, 'workspace.reviews'));
        $this->assertTrue($this->allows($this->manager, 'workspace.reviews'));
        $this->assertFalse($this->allows($this->intern, 'workspace.reviews'));

        $this->assertFalse($this->allows($this->intern, 'workspace.inbox'));
        $this->assertTrue($this->allows($this->supervisor, 'workspace.inbox'));
    }

    public function test_account_decisions_use_the_conventional_user_policy(): void
    {
        $this->assertTrue($this->allows($this->manager, 'update', $this->intern));
        $this->assertFalse($this->allows($this->supervisor, 'update', $this->intern), 'accounts are edited in Management');
        $this->assertFalse($this->allows($this->manager, 'update', $this->manager), 'a superadmin row is not edited here');

        $this->assertTrue($this->allows($this->manager, 'updateInternship', $this->intern));
        $this->assertFalse($this->allows($this->manager, 'updateInternship', $this->supervisor));

        $this->assertTrue($this->allows($this->manager, 'resetTwoFactor', $this->intern));
        $this->assertFalse($this->allows($this->supervisor, 'resetTwoFactor', $this->intern));

        $this->assertTrue($this->allows($this->supervisor, 'view', $this->intern));
        $this->assertFalse($this->allows($this->otherSupervisor, 'view', $this->intern));
        $this->assertTrue($this->allows($this->intern, 'view', $this->intern));

        $this->assertTrue($this->allows($this->supervisor, 'signature', $this->supervisor));
        $this->assertFalse($this->allows($this->intern, 'signature', $this->intern), 'interns do not sign');
        $this->assertFalse($this->allows($this->supervisor, 'signature', $this->intern), 'never someone else');
    }
}
