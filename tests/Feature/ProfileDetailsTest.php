<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The profile page used to ask for the current password twice: once in the details
 * form (email change guard) and again in the Sign-in security card. The details form
 * now keeps the email change behind a disclosure, so the password is asked once.
 */
class ProfileDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Ladang!Hijau2026';

    private function user(string $role = 'supervisor'): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => 'nadia@example.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function profileForm(string $html): string
    {
        preg_match('~<form[^>]*action="[^"]*/profile"[^>]*>(.*?)</form>~s', $html, $m);

        return $m[1] ?? '';
    }

    public function test_details_form_asks_for_the_password_once_and_only_for_an_email_change(): void
    {
        $html = $this->actingAs($this->user())->get(route('profile.edit'))->assertOk()->getContent();
        $form = $this->profileForm($html);

        $this->assertNotSame('', $form, 'The profile details form should render.');
        $this->assertSame(1, substr_count($form, 'type="password"'), 'The details form should hold one password input.');
        $this->assertSame(1, substr_count($form, 'name="current_password"'));

        // Both the email input and its password guard live inside the disclosure.
        $disclosure = strpos($form, '<details class="email-change"');
        $this->assertNotFalse($disclosure);
        $this->assertGreaterThan($disclosure, strpos($form, 'name="email"'));
        $this->assertGreaterThan($disclosure, strpos($form, 'name="current_password"'));
        $this->assertSame(0, substr_count(substr($form, 0, $disclosure), 'type="password"'));

        // Closed by default, so the details grid never shows a password box at rest.
        $this->assertDoesNotMatchRegularExpression('~<details class="email-change"\s+open~', $form);
        // The address itself is still visible, just not as an editable duplicate.
        $this->assertStringContainsString('nadia@example.test', $form);
    }

    public function test_editing_the_name_alone_needs_no_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profile.update'), ['name' => 'Nadia Rahman', 'email' => $user->email])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nadia Rahman', $user->fresh()->name);
    }

    public function test_changing_the_email_still_requires_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.test'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('nadia@example.test', $user->fresh()->email);

        $this->actingAs($user)
            ->post(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.test', 'current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('nadia@example.test', $user->fresh()->email);
    }

    public function test_email_change_succeeds_with_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new@example.test',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('new@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at, 'A new address needs verifying again.');
    }

    public function test_the_disclosure_reopens_when_an_email_change_fails(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('profile.update'), ['name' => $user->name, 'email' => 'typo@example.test'])
            ->assertSessionHasErrors('current_password');

        $html = $this->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<details class="email-change"\s+open~', $html);
        $this->assertStringContainsString('value="typo@example.test"', $html, 'The typed address survives the round trip.');
    }
}
