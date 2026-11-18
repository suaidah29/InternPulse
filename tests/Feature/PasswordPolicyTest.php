<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function passes(string $password, ?string $name = null, ?string $email = null): bool
    {
        return ! Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            ['password' => PasswordPolicy::rulesFor($name, $email)],
        )->fails();
    }

    public function test_it_accepts_a_strong_password(): void
    {
        $this->assertTrue($this->passes('Tembikai!Merah2026'));
        $this->assertTrue($this->passes('Cafe.Latte#9pm'));
    }

    public function test_it_rejects_each_rule_on_its_own(): void
    {
        $this->assertFalse($this->passes('Sh0rt!'), 'too short');
        $this->assertFalse($this->passes(str_repeat('Aa1!', 19)), 'longer than bcrypt can hash');
        $this->assertFalse($this->passes('alllowercase1!'), 'no uppercase');
        $this->assertFalse($this->passes('ALLUPPERCASE1!'), 'no lowercase');
        $this->assertFalse($this->passes('NoNumbers!!aa'), 'no number');
        $this->assertFalse($this->passes('NoSymbols1234'), 'no symbol');
        $this->assertFalse($this->passes(''), 'empty');
    }

    public function test_it_rejects_the_persons_own_name_or_email(): void
    {
        $this->assertFalse($this->passes('Aisyah!2026Strong', 'Aisyah Rahman', 'aisyah@example.test'));
        $this->assertFalse($this->passes('Rahman!2026Strong', 'Aisyah Rahman', 'aisyah@example.test'));
        $this->assertFalse($this->passes('AISYAH!2026Strong', 'Aisyah Rahman', 'aisyah@example.test'), 'case does not matter');
        $this->assertFalse($this->passes('Aisyah!2026Strong', null, 'aisyah@example.test'), 'email alone is enough');
        $this->assertTrue($this->passes('Tembikai!Merah2026', 'Aisyah Rahman', 'aisyah@example.test'));
        $this->assertTrue($this->passes('Ba!2026Tembikai', 'Ai', 'ax@b.test'), 'two-letter fragments are ignored');
    }

    public function test_the_summary_is_bilingual_and_matches_the_rules(): void
    {
        $summary = PasswordPolicy::summary();
        $this->assertCount(5, $summary);
        foreach ($summary as [$en, $ms]) {
            $this->assertNotSame('', $en);
            $this->assertNotSame('', $ms);
            $this->assertNotSame($en, $ms);
        }
        $this->assertStringContainsString((string) PasswordPolicy::MIN_LENGTH, $summary[0][0]);
    }

    private function invitation(string $name, string $email): string
    {
        $token = str_repeat('a', 64);
        $inviter = User::factory()->create(['role' => 'superadmin']);
        DB::table('supervisor_invitations')->insert([
            'name' => $name, 'email' => $email, 'role' => 'supervisor', 'department' => 'IT', 'invited_by' => $inviter->id,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(),
            'delivery_status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    public function test_invitation_acceptance_applies_the_policy(): void
    {
        $token = $this->invitation('Aina Test', 'aina@example.test');

        $this->post(route('invitation.accept', $token), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post(route('invitation.accept', $token), ['password' => 'Aina!Strong2026', 'password_confirmation' => 'Aina!Strong2026'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'aina@example.test']);

        $this->post(route('invitation.accept', $token), ['password' => 'Tembikai!Merah2026', 'password_confirmation' => 'Tembikai!Merah2026'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Tembikai!Merah2026', User::where('email', 'aina@example.test')->value('password')));
    }

    public function test_password_reset_applies_the_policy(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'intern', 'name' => 'Aina Test', 'email' => 'aina@example.test']);
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Aina!Strong2026', 'password_confirmation' => 'Aina!Strong2026'])
            ->assertSessionHasErrors('password');
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'NoSymbols12345', 'password_confirmation' => 'NoSymbols12345'])
            ->assertSessionHasErrors('password');

        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Tembikai!Merah2026', 'password_confirmation' => 'Tembikai!Merah2026'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Tembikai!Merah2026', $user->fresh()->password));
    }

    public function test_a_signed_in_person_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'Original!Pass1x']);
        $this->actingAs($user);

        $this->post(route('profile.password'), ['current_password' => 'wrong', 'password' => 'Tembikai!Merah2026', 'password_confirmation' => 'Tembikai!Merah2026'])
            ->assertSessionHasErrors('current_password');
        $this->post(route('profile.password'), ['current_password' => 'Original!Pass1x', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrors('password');
        $this->post(route('profile.password'), ['current_password' => 'Original!Pass1x', 'password' => 'Tembikai!Merah2026', 'password_confirmation' => 'different!One2'])
            ->assertSessionHasErrors('password');

        $this->post(route('profile.password'), ['current_password' => 'Original!Pass1x', 'password' => 'Tembikai!Merah2026', 'password_confirmation' => 'Tembikai!Merah2026'])
            ->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('Tembikai!Merah2026', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_changing_the_password_signs_out_other_browsers_only(): void
    {
        $user = User::factory()->create(['password' => 'Original!Pass1x']);
        DB::table('sessions')->insert([
            ['id' => 'other-browser', 'user_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)
            ->post(route('profile.password'), ['current_password' => 'Original!Pass1x', 'password' => 'Tembikai!Merah2026', 'password_confirmation' => 'Tembikai!Merah2026'])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('sessions', ['id' => 'other-browser']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_translated_text_never_leaks_into_an_attribute(): void
    {
        // $t() returns markup, so it must not be used inside aria-label="..." or similar.
        $user = User::factory()->create(['role' => 'supervisor']);
        foreach ([route('profile.edit'), route('workspace.tasks', ['view' => 'board']), route('workspace.tasks')] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/[a-z-]+="<span/', $html, $url.' has translated markup inside an attribute');
        }
    }

    public function test_the_profile_page_advertises_the_rules(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('At least 12 characters')
            ->assertSee('Two-factor authentication')
            ->assertSee('Not your name or email address');
    }
}
