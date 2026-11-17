<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogbookRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_and_resubmit_preserve_history_and_enforce_access(): void
    {
        Storage::fake('local');
        $sv = User::factory()->create(['role' => 'supervisor']);
        $other = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);
        $this->actingAs($intern)->post(route('workspace.logbook.submit'), ['title' => 'Week 1', 'document' => UploadedFile::fake()->create('week.pdf', 10, 'application/pdf')])->assertRedirect();
        $original = DB::table('logbook_documents')->first();
        $this->actingAs($other)->post(route('workspace.logbook.return', $original->id), ['review_note' => 'Fix date'])->assertNotFound();
        $this->actingAs($sv)->post(route('workspace.logbook.return', $original->id), ['review_note' => ''])->assertSessionHasErrors('review_note');
        $this->post(route('workspace.logbook.return', $original->id), ['review_note' => 'Fix date'])->assertRedirect();
        $this->assertDatabaseHas('logbook_documents', ['id' => $original->id, 'status' => 'returned', 'review_note' => 'Fix date']);
        $this->actingAs($intern)->get(route('workspace.logbook'))->assertSee('Fix date');
        $this->get(route('workspace.logbook.receipt', $original->id))->assertNotFound();
        $upload = fn () => UploadedFile::fake()->create('revised.pdf', 10, 'application/pdf');
        $this->post(route('workspace.logbook.resubmit', $original->id), ['document' => $upload()])->assertRedirect();
        $this->assertDatabaseHas('logbook_documents', ['previous_document_id' => $original->id, 'status' => 'pending', 'user_id' => $intern->id]);
        $this->post(route('workspace.logbook.resubmit', $original->id), ['document' => $upload()])->assertStatus(409);
        $this->assertDatabaseCount('logbook_documents', 2);
    }
}
