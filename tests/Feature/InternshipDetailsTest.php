<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternshipDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_can_update_internship_details_without_changing_account_access(): void
    {
        $manager = User::factory()->create(['role' => 'superadmin']);
        $intern = User::factory()->create(['role' => 'intern', 'is_active' => true]);
        $data = ['university' => 'Universiti Example', 'course' => 'Management', 'internship_start_date' => '2026-01-01', 'internship_end_date' => '2026-06-01', 'internship_status' => 'completed'];
        $this->actingAs($manager)->post(route('management.people.internship', $intern->id), $data)->assertRedirect();
        $this->assertDatabaseHas('users', $data + ['id' => $intern->id, 'is_active' => true]);
        $this->post(route('management.people.internship', $intern->id), array_merge($data, ['internship_end_date' => '2025-12-31']))->assertSessionHasErrors('internship_end_date');
        $this->actingAs($intern)->post(route('management.people.internship', $intern->id), $data)->assertForbidden();
    }
}
