<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogbookPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_notes_and_supervisor_document_verification(): void
    {
        Storage::fake('local');
        $sv = User::factory()->create(['role' => 'supervisor']);
        $other = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);
        $this->actingAs($intern)->post(route('workspace.logbook.store'), ['activity_date' => now('Asia/Kuala_Lumpur')->toDateString(), 'points' => 'Private work', 'photo' => UploadedFile::fake()->image('work.jpg')])->assertRedirect();
        $log = DB::table('daily_logs')->first();
        $this->actingAs($sv)->get(route('workspace.records'))->assertDontSee('Private work');
        $this->get(route('workspace.logbook.photo', $log->id))->assertForbidden();
        $this->post(route('workspace.review', ['type' => 'log', 'id' => $log->id]), ['decision' => 'approved'])->assertNotFound();
        $this->actingAs($intern)->post(route('workspace.logbook.submit'), ['title' => 'Week one', 'document' => UploadedFile::fake()->create('log.pdf', 20, 'application/pdf')])->assertRedirect();
        $doc = DB::table('logbook_documents')->first();
        $this->actingAs($other)->get(route('workspace.logbook.file', ['id' => $doc->id, 'kind' => 'document']))->assertNotFound();
        $this->actingAs($sv)->post(route('workspace.logbook.verify', $doc->id), ['status' => 'verified'])->assertSessionHasErrors('signature');
        Storage::disk('local')->put('profiles/signature.png', 'signature');
        $sv->signature_path = 'profiles/signature.png';
        $sv->save();
        $this->post(route('workspace.logbook.verify', $doc->id), ['status' => 'verified'])->assertRedirect();
        $this->assertDatabaseHas('logbook_documents', ['id' => $doc->id, 'status' => 'verified', 'reviewed_by' => $sv->id]);
        $this->actingAs($intern)->get(route('workspace.logbook.receipt', $doc->id))->assertOk()->assertSee('Week one');
    }
}
