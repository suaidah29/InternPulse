<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_respects_access_and_can_download_original(): void
    {
        Storage::fake('local');
        $sv = User::factory()->create(['role' => 'supervisor']);
        $intern = User::factory()->create(['role' => 'intern', 'supervisor_id' => $sv->id]);
        $other = User::factory()->create(['role' => 'intern']);
        Storage::disk('local')->put('task-evidence/sample.pdf', '%PDF-1.4 test');
        $task = DB::table('work_tasks')->insertGetId(['title' => 'Example', 'assigned_to' => $intern->id, 'created_by' => $sv->id, 'created_at' => now(), 'updated_at' => now()]);
        $file = DB::table('work_files')->insertGetId(['task_id' => $task, 'uploaded_by' => $intern->id, 'path' => 'task-evidence/sample.pdf', 'original_name' => 'sample.pdf', 'created_at' => now(), 'updated_at' => now()]);
        $url = route('workspace.files', ['type' => 'task', 'id' => $file]);
        $this->actingAs($intern)->get($url)->assertOk()->assertHeader('Content-Disposition', 'inline');
        $this->get($url.'?download=1')->assertDownload('sample.pdf');
        $this->actingAs($other)->get($url)->assertNotFound();
    }
}
