<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesWorkspace;
use App\Services\DocumentPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * One door for every private file: task evidence, attendance photos, guide
 * attachments.
 */
final class FileController extends Controller
{
    use ScopesWorkspace;

    public function download(Request $r, string $type, int $id): Response
    {
        if ($type === 'task') {
            $file = DB::table('work_files')->where('id', $id)->firstOrFail();
            $this->task($r, $file->task_id);
            $path = $file->path;
            $name = $file->original_name;
        } elseif ($type === 'attendance') {
            $file = DB::table('attendance_records')->where('id', $id)->firstOrFail();
            Gate::authorize('attendance.viewFile', $file);
            $path = $file->photo_path;
            $name = 'attendance-'.basename($path);
        } elseif ($type === 'guide') {
            $file = DB::table('intern_guides')->where('id', $id)->firstOrFail();
            Gate::authorize('guide.download', $file);
            $path = $file->file_path;
            $name = $file->file_name;
        } else {
            abort(404);
        }abort_unless($path && Storage::disk('local')->exists($path), 404);

        return DocumentPreview::show($path, $name, $r->boolean('download'));
    }
}
