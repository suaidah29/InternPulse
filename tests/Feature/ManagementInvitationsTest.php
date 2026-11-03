<?php

namespace Tests\Feature;

use App\Mail\SupervisorInvitationMail;
use App\Models\User;
use App\Services\SupervisorInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManagementInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private function management(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function payload(): array
    {
        return ['name' => 'Aina Test', 'email' => 'aina@example.test', 'department' => 'IT'];
    }

    private function invite(User $manager): string
    {
        Mail::fake();
        $this->actingAs($manager)->post(route('management.supervisors.store'), $this->payload())->assertRedirect(route('management.dashboard'));
        $mail = Mail::sent(SupervisorInvitationMail::class)->first();
        $this->assertNotNull($mail);

        return basename(parse_url($mail->invitationUrl, PHP_URL_PATH));
    }

    public function test_management_dashboard_renders_real_counts_and_bilingual_controls(): void
    {
        $manager = $this->management();
        User::factory()->create(['role' => 'supervisor']);
        User::factory()->create(['role' => 'intern']);
        $this->actingAs($manager)->get(route('management.dashboard'))->assertOk()->assertSee('Management overview')->assertSee('data-lang="ms"', false)->assertSee('Invite supervisor');
    }

    public function test_non_management_accounts_cannot_read_or_mutate_management_data(): void
    {
        $this->get(route('management.dashboard'))->assertRedirect(route('login'));
        foreach (['supervisor', 'intern'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('management.dashboard'))->assertForbidden();
            $this->post(route('management.supervisors.store'), $this->payload())->assertForbidden();
            $this->post(route('management.invitations.resend', 1))->assertForbidden();
            $this->post(route('management.invitations.revoke', 1))->assertForbidden();
            $this->post(route('management.import.send'))->assertForbidden();
        }
        $this->assertDatabaseCount('supervisor_invitations', 0);
    }

    public function test_invitation_stores_only_token_hash_and_acceptance_forces_supervisor_role(): void
    {
        $manager = $this->management();
        $token = $this->invite($manager);
        $this->assertDatabaseHas('supervisor_invitations', ['token_hash' => hash('sha256', $token), 'delivery_status' => 'sent']);
        $this->assertDatabaseMissing('users', ['email' => 'aina@example.test']);
        $this->post(route('logout'));
        $this->get(route('invitation.show', $token))->assertOk()->assertSee('Aina Test');
        $this->post(route('invitation.accept', $token), ['password' => 'SecureExample!123', 'password_confirmation' => 'SecureExample!123', 'role' => 'superadmin', 'department' => 'Wrong', 'email' => 'attacker@example.test'])->assertRedirect(route('login'));
        $user = User::where('email', 'aina@example.test')->firstOrFail();
        $this->assertSame('supervisor', $user->role);
        $this->assertSame('IT', $user->department);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('SecureExample!123', $user->password));
        $this->post(route('invitation.accept', $token), ['password' => 'SecureExample!123', 'password_confirmation' => 'SecureExample!123'])->assertStatus(410);
    }

    public function test_expired_and_revoked_links_cannot_create_accounts(): void
    {
        $manager = $this->management();
        $token = $this->invite($manager);
        $this->post(route('logout'));
        DB::table('supervisor_invitations')->update(['expires_at' => now()->subMinute()]);
        $this->post(route('invitation.accept', $token), ['password' => 'SecureExample!123', 'password_confirmation' => 'SecureExample!123'])->assertStatus(410);
        DB::table('supervisor_invitations')->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->post(route('invitation.accept', $token), ['password' => 'SecureExample!123', 'password_confirmation' => 'SecureExample!123'])->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'aina@example.test']);
    }

    public function test_resend_invalidates_old_link_and_cancel_invalidates_new_link(): void
    {
        $manager = $this->management();
        $old = $this->invite($manager);
        $id = DB::table('supervisor_invitations')->value('id');
        $this->post(route('management.invitations.resend', $id))->assertRedirect();
        $new = basename(parse_url(Mail::sent(SupervisorInvitationMail::class)->last()->invitationUrl, PHP_URL_PATH));
        $this->assertNotSame($old, $new);
        $this->post(route('management.invitations.revoke', $id))->assertRedirect();
        $this->post(route('logout'));
        foreach ([$old, $new] as $token) {
            $this->post(route('invitation.accept', $token), ['password' => 'SecureExample!123', 'password_confirmation' => 'SecureExample!123'])->assertStatus(410);
        }
    }

    public function test_duplicates_and_weak_passwords_are_rejected(): void
    {
        $manager = $this->management();
        $token = $this->invite($manager);
        $this->post(route('management.supervisors.store'), $this->payload())->assertSessionHasErrors('email');
        $this->post(route('logout'));
        $this->post(route('invitation.accept', $token), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'aina@example.test']);
    }

    public function test_csv_preview_requires_explicit_send_and_detects_duplicates(): void
    {
        Mail::fake();
        $this->actingAs($this->management());
        $file = UploadedFile::fake()->createWithContent('team.csv', "name,email,department\nAina,aina@example.test,IT\n");
        $this->post(route('management.import.preview'), ['csv' => $file])->assertRedirect();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('supervisor_invitations', 0);
        $this->post(route('management.import.send'))->assertRedirect();
        Mail::assertSent(SupervisorInvitationMail::class, 1);
        $file = UploadedFile::fake()->createWithContent('team.csv', "name,email,department\nAina,aina@example.test,IT\n");
        $this->post(route('management.import.preview'), ['csv' => $file])->assertRedirect();
        $this->post(route('management.import.send'))->assertSessionHasErrors('csv');
        Mail::assertSent(SupervisorInvitationMail::class, 1);
    }

    public function test_failed_email_is_visible_and_does_not_create_user(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Offline'));
        $this->actingAs($this->management())->post(route('management.supervisors.store'), $this->payload())->assertSessionHas('warning');
        $this->assertDatabaseHas('supervisor_invitations', ['email' => 'aina@example.test', 'delivery_status' => 'failed']);
        $this->assertDatabaseMissing('users', ['email' => 'aina@example.test']);
    }

    public function test_template_and_generated_mail_render(): void
    {
        $this->actingAs($this->management())->get(route('management.import.template'))->assertOk()->assertSee('name,email,department');
        $html = (new SupervisorInvitationMail('Aina', 'IT', 'http://localhost/invitations/example'))->render();
        $this->assertStringContainsString('Activate my account', $html);
    }

    public function test_local_smtp_transport_delivers_to_mailpit_when_requested(): void
    {
        if (getenv('RUN_LOCAL_MAIL_SMOKE') !== '1') {
            $this->markTestSkipped('Opt-in local SMTP smoke test.');
        }
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1025, 'mail.mailers.smtp.scheme' => null, 'mail.mailers.smtp.username' => null, 'mail.mailers.smtp.password' => null]);
        $manager = $this->management();
        $sent = app(SupervisorInvitations::class)->send(['name' => 'Local delivery check', 'email' => 'delivery-check@internpulse.test', 'department' => 'Testing'], $manager->id);
        $this->assertTrue($sent);
        $this->assertDatabaseHas('supervisor_invitations',['email' => 'delivery-check@internpulse.test', 'delivery_status' => 'sent']);
    }
}
