<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkflowExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_conversation_review_notifications_and_access(): void
    {
        $sv = User::factory()->create(['role' => 'supervisor', 'department' => 'IT']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);
        $outsider = User::factory()->create(['role' => 'intern']);
        $this->actingAs($sv)->post(route('workspace.tasks.store'), ['title' => 'Workflow example', 'assigned_to' => $intern->id, 'priority' => 'normal', 'status' => 'todo'])->assertRedirect();
        $id = DB::table('work_tasks')->value('id');
        $this->actingAs($intern)->get(route('workspace.tasks.show', $id))->assertOk()->assertSee('Update progress');
        $this->get(route('workspace.notifications'))->assertOk()->assertSee('Workflow example');
        $this->post(route('workspace.tasks.progress', $id), ['status' => 'review', 'progress_note' => 'Ready for your feedback'])->assertRedirect();
        $this->get(route('workspace.records'))->assertOk()->assertSee('data-log-suggestion="Workflow example"', false);
        $this->post(route('workspace.tasks.decision', $id), ['decision' => 'completed', 'comment' => 'Self approval'])->assertForbidden();
        $this->actingAs($outsider)->get(route('workspace.tasks.show', $id))->assertNotFound();
        $this->post(route('workspace.tasks.comment', $id), ['comment' => 'Not allowed'])->assertNotFound();
        $this->actingAs($sv)->get(route('workspace.tasks.show', $id))->assertOk()->assertSee('Approve completion')->assertDontSee('Save progress');
        $this->post(route('workspace.tasks.progress', $id), ['status' => 'todo'])->assertForbidden();
        $this->post(route('workspace.tasks.update', $id), ['title' => 'Workflow example', 'assigned_to' => $intern->id, 'priority' => 'normal', 'status' => 'completed'])->assertSessionHasErrors('status');
        $this->post(route('workspace.tasks.comment', $id), ['comment' => '<script>alert(1)</script>'])->assertRedirect();
        $this->get(route('workspace.tasks.show', $id))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post(route('workspace.tasks.decision', $id), ['decision' => 'in_progress', 'comment' => 'Please improve spacing'])->assertRedirect();
        $this->assertDatabaseHas('work_tasks', ['id' => $id, 'status' => 'in_progress']);
        $this->actingAs($intern)->post(route('workspace.tasks.progress', $id), ['status' => 'review'])->assertRedirect();
        $this->actingAs($sv)->post(route('workspace.tasks.decision', $id), ['decision' => 'completed', 'comment' => 'Looks good now'])->assertRedirect();
        $this->assertDatabaseHas('work_tasks', ['id' => $id, 'status' => 'completed']);
        $this->post(route('workspace.tasks.decision', $id), ['decision' => 'completed', 'comment' => 'Duplicate'])->assertStatus(409);
        $this->actingAs($intern)->post(route('workspace.notifications.read'))->assertRedirect();
        $this->assertSame(0, DB::table('workspace_notifications')->where('user_id', $intern->id)->whereNull('read_at')->count());
        $this->assertGreaterThan(0, DB::table('workspace_notifications')->where('user_id', $sv->id)->whereNull('read_at')->count());
    }

    public function test_password_recovery_uses_single_use_token_and_preserves_role(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'intern']);
        $this->get(route('login'))->assertOk()->assertSee('Forgot password');
        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');
        $mail = Notification::sent($user, ResetPassword::class)->first();
        $this->assertNotNull($mail);
        $this->get(route('password.reset', ['token' => $mail->token, 'email' => $user->email]))->assertOk();
        $data = ['email' => $user->email, 'token' => $mail->token, 'password' => 'UpdatedPassword!123', 'password_confirmation' => 'UpdatedPassword!123'];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertSame('intern', $user->fresh()->role);
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])->assertSessionHas('success');
    }
}
