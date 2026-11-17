<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_returns_to_profile_even_when_previous_page_is_an_image(): void
    {
        $user = User::factory()->create(['role' => 'intern']);
        $this->actingAs($user)->from(route('profile.image', 'photo'))->post(route('profile.update'), ['name' => 'Updated name', 'email' => $user->email])->assertRedirect(route('profile.edit'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated name']);
        $this->from(route('profile.image', 'photo'))->post(route('profile.update'), ['name' => '', 'email' => $user->email])->assertRedirect(route('profile.edit'))->assertSessionHasErrors('name');
    }
}
