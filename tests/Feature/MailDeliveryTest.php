<?php

namespace Tests\Feature;

use App\Jobs\SendSupervisorInvitation;
use App\Mail\SupervisorInvitationMail;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\SupervisorInvitations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function invite(User $manager, string $email = 'aina@example.test'): int
    {
        app(SupervisorInvitations::class)->send(
            ['name' => 'Aina Test', 'email' => $email, 'department' => 'IT'],
            $manager->id,
        );

        return (int) DB::table('supervisor_invitations')->where('email', $email)->value('id');
    }

    private function invitation(int $id): object
    {
        return DB::table('supervisor_invitations')->where('id', $id)->first();
    }

    public function test_invitations_are_queued_instead_of_sent_inside_the_request(): void
    {
        Queue::fake();
        Mail::fake();

        $id = $this->invite($this->manager());

        Queue::assertPushed(SendSupervisorInvitation::class, function (SendSupervisorInvitation $job) use ($id) {
            return $job->invitationId === $id
                && $job->email === 'aina@example.test'
                && str_contains($job->url, '/invitations/');
        });

        Mail::assertNothingSent();
        $this->assertSame('queued', $this->invitation($id)->delivery_status);
        $this->assertNull($this->invitation($id)->sent_at);
    }

    public function test_the_queued_job_sends_the_mail_and_marks_the_invitation_sent(): void
    {
        Mail::fake();
        $id = $this->invite($this->manager());

        (new SendSupervisorInvitation($id, 'aina@example.test', 'Aina Test', 'IT', 'http://localhost/invitations/token-abc'))->handle();

        Mail::assertSent(SupervisorInvitationMail::class, function (SupervisorInvitationMail $mail) {
            return $mail->hasTo('aina@example.test')
                && $mail->department === 'IT'
                && str_ends_with($mail->invitationUrl, '/invitations/token-abc');
        });

        $invitation = $this->invitation($id);
        $this->assertSame('sent', $invitation->delivery_status);
        $this->assertNotNull($invitation->sent_at);
    }

    public function test_a_cancelled_invitation_is_not_emailed_when_the_job_runs(): void
    {
        Queue::fake();
        $id = $this->invite($this->manager());
        Mail::fake();
        DB::table('supervisor_invitations')->where('id', $id)->update(['revoked_at' => now()]);

        (new SendSupervisorInvitation($id, 'aina@example.test', 'Aina Test', 'IT', 'http://localhost/invitations/token-abc'))->handle();

        Mail::assertNothingSent();
        $this->assertNull($this->invitation($id)->sent_at);
    }

    public function test_a_used_invitation_is_not_emailed_when_the_job_runs(): void
    {
        Queue::fake();
        $id = $this->invite($this->manager());
        Mail::fake();
        DB::table('supervisor_invitations')->where('id', $id)->update(['accepted_at' => now()]);

        (new SendSupervisorInvitation($id, 'aina@example.test', 'Aina Test', 'IT', 'http://localhost/invitations/token-abc'))->handle();

        Mail::assertNothingSent();
        $this->assertSame('queued', $this->invitation($id)->delivery_status);
    }

    public function test_a_failed_job_marks_the_invitation_failed_so_the_list_can_offer_a_resend(): void
    {
        $id = $this->invite($this->manager());

        (new SendSupervisorInvitation($id, 'aina@example.test', 'Aina Test', 'IT', 'http://localhost/invitations/token-abc'))
            ->failed(new \RuntimeException('SMTP unreachable'));

        $this->assertSame('failed', $this->invitation($id)->delivery_status);
    }

    public function test_the_invitation_list_shows_a_queued_invitation_as_sending(): void
    {
        Queue::fake();
        $manager = $this->manager();
        $this->invite($manager);

        $this->actingAs($manager)->get(route('management.dashboard'))
            ->assertOk()
            ->assertSee('Sending')
            ->assertSee('Menghantar');
    }

    public function test_the_job_is_a_queueable_job_with_retries(): void
    {
        $job = new SendSupervisorInvitation(1, 'a@example.test', 'A', 'IT', 'http://localhost/invitations/x');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }

    public function test_password_reset_mail_goes_through_the_queued_notification(): void
    {
        Notification::fake();
        Mail::fake();
        $user = User::factory()->create(['role' => 'intern', 'email' => 'intern@example.test']);

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
            return $notification instanceof ShouldQueue;
        });
        Mail::assertNothingSent();
    }

    public function test_the_reset_notification_links_back_to_the_reset_form_and_says_when_it_expires(): void
    {
        $user = User::factory()->create(['role' => 'intern', 'email' => 'intern@example.test']);
        $mail = (new ResetPasswordNotification('token-123'))->toMail($user);

        $this->assertStringContainsString('/reset-password/token-123', $mail->actionUrl);
        $this->assertStringContainsString(rawurlencode('intern@example.test'), $mail->actionUrl);
        $this->assertStringContainsString('Reset your InternPulse password', $mail->subject);
        $this->assertStringContainsString('Set semula', $mail->subject);

        $body = implode(' ', array_map('strval', [...$mail->introLines, ...$mail->outroLines]));
        $this->assertStringContainsString('60 minutes', $body);
        $this->assertStringContainsString('60 minit', $body);
    }

    public function test_a_real_reset_request_still_stores_a_token_nobody_can_skip(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'intern', 'email' => 'intern@example.test']);

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->assertDatabaseCount('password_reset_tokens', 1);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_an_unknown_email_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
}
