<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'CorrectHorse!23x';

    private function login(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    private function enableOn(User $user, array $codes = []): string
    {
        $secret = TwoFactor::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => TwoFactor::hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    private function workingAs(User $user): self
    {
        return $this->actingAs($user);
    }

    private function wrongCode(string $valid): string
    {
        return ($valid[0] === '9' ? '0' : (string) ((int) $valid[0] + 1)).substr($valid, 1);
    }

    public function test_matches_the_rfc_6238_reference_vectors(): void
    {
        // The RFC uses the ASCII secret "12345678901234567890"; 6 digits are the last 6 of its 8.
        $secret = TwoFactor::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        $this->assertSame('287082', TwoFactor::codeAt($secret, 59));
        $this->assertSame('081804', TwoFactor::codeAt($secret, 1111111109));
        $this->assertSame('005924', TwoFactor::codeAt($secret, 1234567890));
        $this->assertSame('12345678901234567890', TwoFactor::base32Decode($secret));
    }

    public function test_verification_accepts_one_period_of_clock_drift_only(): void
    {
        $secret = TwoFactor::generateSecret();
        $now = 1_800_000_000;
        $code = TwoFactor::codeAt($secret, $now);

        $this->assertTrue(TwoFactor::verify($secret, $code, 1, $now));
        $this->assertTrue(TwoFactor::verify($secret, ' '.$code.' ', 1, $now), 'spaces are ignored');
        $this->assertTrue(TwoFactor::verify($secret, $code, 1, $now + 30), 'one period of drift');
        $this->assertTrue(TwoFactor::verify($secret, $code, 1, $now - 30));
        $this->assertFalse(TwoFactor::verify($secret, $code, 1, $now + 60), 'two periods is too much drift');
        $this->assertFalse(TwoFactor::verify($secret, $this->wrongCode($code), 1, $now));
        $this->assertFalse(TwoFactor::verify($secret, 'abcdef', 1, $now));
        $this->assertFalse(TwoFactor::verify(null, $code, 1, $now));
        $this->assertFalse(TwoFactor::verify($secret, '', 1, $now));
    }

    public function test_secret_and_recovery_codes_have_the_expected_shape(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', TwoFactor::generateSecret());
        $this->assertNotSame(TwoFactor::generateSecret(), TwoFactor::generateSecret());

        $codes = TwoFactor::recoveryCodes();
        $this->assertCount(8, $codes);
        $this->assertSame(count($codes), count(array_unique($codes)));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z2-7]{5}-[A-Z2-7]{5}$/', $code);
        }
        $this->assertSame(str_replace('-', '', $codes[0]), TwoFactor::normalizeRecoveryCode($codes[0]));
        $this->assertSame(0, TwoFactor::matchRecoveryCode(TwoFactor::hashRecoveryCodes($codes), strtolower($codes[0])));
        $this->assertNull(TwoFactor::matchRecoveryCode(TwoFactor::hashRecoveryCodes($codes), 'AAAAA-AAAAA'));
    }

    public function test_sign_in_is_only_complete_after_the_second_factor(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $secret = $this->enableOn($user);

        $this->login($user);
        $this->assertGuest();
        $this->assertSame($user->id, session('login.two_factor_user_id'));

        $this->get(route('login.two-factor'))->assertOk()->assertSee('Two-step verification')->assertSee($user->email);

        $this->post(route('login.two-factor.store'), ['code' => TwoFactor::codeAt($secret, time())])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.two_factor_user_id'));
    }

    public function test_a_wrong_code_keeps_the_visitor_signed_out(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $secret = $this->enableOn($user);
        $this->login($user);

        $this->post(route('login.two-factor.store'), ['code' => $this->wrongCode(TwoFactor::codeAt($secret, time()))])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_codes_work_once_and_are_then_deleted(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $codes = TwoFactor::recoveryCodes();
        $this->enableOn($user, $codes);

        $this->login($user);
        $this->post(route('login.two-factor.store'), ['code' => $codes[2]])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $user->fresh()->recoveryCodes());

        $this->post(route('logout'));
        $this->login($user);
        $this->post(route('login.two-factor.store'), ['code' => $codes[2]])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.two-factor.store'), ['code' => strtolower($codes[3])])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_challenge_needs_a_password_step_first(): void
    {
        $this->get(route('login.two-factor'))->assertRedirect(route('login'));
        $this->post(route('login.two-factor.store'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_an_account_deactivated_after_the_password_step_cannot_finish(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $secret = $this->enableOn($user);
        $this->login($user);

        $user->forceFill(['is_active' => false])->save();
        $this->post(route('login.two-factor.store'), ['code' => TwoFactor::codeAt($secret, time())])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_cancel_returns_to_the_password_form(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->enableOn($user);
        $this->login($user);

        $this->post(route('login.two-factor.cancel'))->assertRedirect(route('login'));
        $this->assertNull(session('login.two_factor_user_id'));
        $this->get(route('login.two-factor'))->assertRedirect(route('login'));
    }

    public function test_the_challenge_is_throttled(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->enableOn($user);
        $this->login($user);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login.two-factor.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post(route('login.two-factor.store'), ['code' => '000000'])->assertStatus(429);
    }

    public function test_setup_requires_a_valid_code_before_it_is_saved(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->actingAs($user);

        $this->post(route('profile.two-factor.start'), ['current_password' => 'not-my-password'])->assertSessionHasErrors('current_password');
        $this->assertNull(session('security.pending_two_factor_secret'));

        $this->post(route('profile.two-factor.start'), ['current_password' => self::PASSWORD])->assertRedirect(route('profile.edit'));
        $pending = session('security.pending_two_factor_secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $pending);

        $this->get(route('profile.edit'))->assertOk()->assertSee(trim(chunk_split($pending, 4, ' ')));

        $this->post(route('profile.two-factor.enable'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        $this->post(route('profile.two-factor.enable'), ['code' => TwoFactor::codeAt($pending, time())])->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertSame($pending, $user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes(), 'codes are stored hashed');
        $this->assertNull(session('security.pending_two_factor_secret'));
    }

    public function test_secret_is_encrypted_at_rest_and_never_serialised(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $secret = $this->enableOn($user);

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, (string) $raw);
        $this->assertArrayNotHasKey('two_factor_secret', $user->fresh()->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->fresh()->toArray());
    }

    public function test_turning_it_off_needs_the_password_and_restores_plain_sign_in(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->enableOn($user, TwoFactor::recoveryCodes());

        $this->actingAs($user)->post(route('profile.two-factor.disable'), ['current_password' => 'wrong-one'])->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->hasTwoFactor());

        $this->post(route('profile.two-factor.disable'), ['current_password' => self::PASSWORD])->assertRedirect(route('profile.edit'));
        $this->assertFalse($user->fresh()->hasTwoFactor());
        $this->assertNull($user->fresh()->two_factor_secret);

        $this->post(route('logout'));
        $this->login($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_codes_can_be_regenerated_and_the_old_ones_stop_working(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $old = TwoFactor::recoveryCodes();
        $this->enableOn($user, $old);

        $this->actingAs($user)->post(route('profile.two-factor.recovery'), ['current_password' => self::PASSWORD])->assertRedirect(route('profile.edit'));
        $new = session('two_factor_recovery_codes');
        $this->assertCount(8, $new);
        $this->assertEmpty(array_intersect($old, $new));

        $this->post(route('logout'));
        $this->login($user);
        $this->post(route('login.two-factor.store'), ['code' => $old[0]])->assertSessionHasErrors('code');
        $this->post(route('login.two-factor.store'), ['code' => $new[0]])->assertRedirect(route('dashboard'));
    }

    public function test_a_superadmin_can_reset_two_factor_for_a_locked_out_person(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $intern = User::factory()->create(['role' => 'intern', 'password' => self::PASSWORD]);
        $this->enableOn($intern);

        $this->actingAs($intern)->post(route('management.people.two-factor', $intern->id))->assertForbidden();

        $this->actingAs($admin)->post(route('management.people.two-factor', $intern->id))->assertRedirect();
        $this->assertFalse($intern->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('management_activity', ['action' => 'user.two_factor_reset']);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $intern->id]);

        $this->post(route('logout'));
        $this->login($intern);
        $this->assertAuthenticatedAs($intern);

        $this->workingAs($admin)->post(route('management.people.two-factor', $intern->id))->assertStatus(409);
    }

    public function test_accounts_without_two_factor_sign_in_directly(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->login($user);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.two_factor_user_id'));
    }
}
