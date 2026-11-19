<?php

namespace App\Http\Controllers;

use App\Services\DocumentPreview;
use App\Services\WorkspaceActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LogbookController extends Controller
{
    private function documents(Request $request): Builder
    {
        // Scoping, not authorisation: Gate::authorize('logbook.view') runs first in index().
        $query = DB::table('logbook_documents');
        if ($request->user()->role === 'intern') {
            return $query->where('user_id', $request->user()->id);
        }

        return $query->where('supervisor_id', $request->user()->id)->whereIn('user_id', DB::table('users')->where('supervisor_id', $request->user()->id)->where('role', 'intern')->select('id'));
    }

    public function index(Request $request): View
    {
        Gate::authorize('logbook.view');
        $documents = $this->documents($request)->orderByDesc('id')->paginate(10, ['*'], 'documents_page');
        $logs = DB::table('daily_logs')->where('user_id', $request->user()->id)->whereRaw($request->user()->role === 'intern' ? '1=1' : '1=0')->orderByDesc('activity_date')->paginate(10, ['*'], 'logs_page');
        $edit = $request->filled('edit') ? DB::table('daily_logs')->where('user_id', $request->user()->id)->where('id', $request->integer('edit'))->firstOrFail() : null;
        $names = DB::table('users')->whereIn('id', $documents->pluck('user_id'))->pluck('name', 'id');
        $suggestions = DB::table('work_tasks')->where('assigned_to', $request->user()->id)->whereNull('archived_at')->orderByDesc('updated_at')->limit(5)->pluck('title');
        $resubmittedIds = DB::table('logbook_documents')->whereIn('previous_document_id', $documents->pluck('id'))->pluck('previous_document_id')->all();

        return view('management.logbook', compact('documents', 'logs', 'edit', 'names', 'suggestions', 'resubmittedIds'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('log.write');
        $data = $request->validate([
            'activity_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Kuala_Lumpur')->toDateString(),
            'points' => 'required|string|max:10000',
            'summary' => 'nullable|string|max:12000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_photo' => 'nullable|boolean',
        ]);
        $old = DB::table('daily_logs')->where('user_id', $request->user()->id)->where('activity_date', $data['activity_date'])->first();
        $lines = array_filter(array_map('trim', preg_split('/\R/u', $data['points'])));
        $summary = trim($data['summary'] ?? '') ?: implode(' ', array_map(fn (string $line): string => rtrim(ltrim($line, '-• '), '. ').'.', $lines));
        $path = $request->hasFile('photo') ? $request->file('photo')->store('logbook-photos', 'local') : ($request->boolean('remove_photo') ? null : $old?->photo_path);
        DB::table('daily_logs')->updateOrInsert(['user_id' => $request->user()->id, 'activity_date' => $data['activity_date']], [
            'points' => $data['points'], 'summary' => $summary, 'photo_path' => $path, 'status' => 'private', 'reviewed_by' => null, 'review_note' => null,
            'created_at' => $old?->created_at ?? now(), 'updated_at' => now(),
        ]);
        if ($old?->photo_path && $old->photo_path !== $path) {
            Storage::disk('local')->delete($old->photo_path);
        }

        return to_route('workspace.logbook')->with('success', 'Private entry saved. / Catatan peribadi disimpan.');
    }

    public function photo(Request $request, int $id): Response
    {
        Gate::authorize('log.photo');
        $log = DB::table('daily_logs')->where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();
        abort_unless($log->photo_path && Storage::disk('local')->exists($log->photo_path), 404);

        return Storage::disk('local')->response($log->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function submit(Request $request): RedirectResponse
    {
        Gate::authorize('logbook.create');
        $data = $request->validate(['title' => 'required|string|max:180', 'document' => 'required|file|mimes:pdf,doc,docx|max:10240']);
        $supervisor = DB::table('users')->where('id', $request->user()->supervisor_id)->where('role', 'supervisor')->where('is_active', true)->first();
        if (! $supervisor) {
            throw ValidationException::withMessages(['document' => 'Ask management to assign an active supervisor first. / Minta management tetapkan SV aktif dahulu.']);
        }
        $file = $request->file('document');
        DB::table('logbook_documents')->insert([
            'user_id' => $request->user()->id, 'supervisor_id' => $supervisor->id, 'title' => $data['title'],
            'path' => $file->store('logbook-documents', 'local'), 'original_name' => $file->getClientOriginalName(),
            'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now(), 'updated_at' => now(),
        ]);
        WorkspaceActivity::notify([$supervisor->id], $request->user()->id, 'Logbook document submitted / Dokumen logbook dihantar');

        return back()->with('success', 'Document sent to your supervisor. / Dokumen dihantar kepada SV.');
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('logbook.verify');
        $data = $request->validate(['status' => ['required', Rule::in(['checked', 'verified'])]]);
        DB::transaction(function () use ($request, $id, $data): void {
            $document = $this->documents($request)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($document->status === 'pending', 409);
            $source = $request->user()->signature_path;
            if (! $source || ! Storage::disk('local')->exists($source)) {
                throw ValidationException::withMessages(['signature' => 'Upload your signature in Edit profile first. / Muat naik tandatangan di Edit profil dahulu.']);
            }
            $signature = 'logbook-signatures/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
            abort_unless(Storage::disk('local')->copy($source, $signature), 500);
            DB::table('logbook_documents')->where('id', $id)->update([
                'status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewer_name' => $request->user()->name,
                'signature_path' => $signature, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            WorkspaceActivity::notify([$document->user_id], $request->user()->id, 'Logbook '.$data['status'].' / Semakan dokumen logbook selesai');
        });

        return back()->with('success', 'Review saved with signature and date. / Semakan disimpan bersama tandatangan dan tarikh.');
    }

    public function returnForChanges(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('logbook.verify');
        $data = $request->validate(['review_note' => 'required|string|max:2000']);
        DB::transaction(function () use ($request, $id, $data): void {
            $document = $this->documents($request)->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($document->status === 'pending', 409);
            DB::table('logbook_documents')->where('id', $id)->update([
                'status' => 'returned', 'review_note' => $data['review_note'],
                'reviewed_by' => $request->user()->id, 'reviewer_name' => $request->user()->name,
                'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            WorkspaceActivity::notify([$document->user_id], $request->user()->id, 'Logbook returned for changes / Logbook dipulangkan untuk pembetulan');
        });

        return back()->with('success', 'Document returned with feedback. / Dokumen dipulangkan bersama komen.');
    }

    public function resubmit(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('logbook.resubmit');
        $request->validate(['document' => 'required|file|mimes:pdf,doc,docx|max:10240']);
        $file = $request->file('document');
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store('logbook-documents', 'local');
        try {
            DB::transaction(function () use ($request, $id, $file, $hash, $path): void {
                $document = $this->documents($request)->where('id', $id)->lockForUpdate()->firstOrFail();
                abort_unless($document->status === 'returned', 409);
                abort_if(DB::table('logbook_documents')->where('previous_document_id', $id)->exists(), 409);
                $supervisor = DB::table('users')->where('id', $request->user()->supervisor_id)->where('role', 'supervisor')->where('is_active', true)->first();
                if (! $supervisor) {
                    throw ValidationException::withMessages(['document' => 'Ask management to assign an active supervisor. / Minta management tetapkan SV aktif.']);
                }
                DB::table('logbook_documents')->insert([
                    'user_id' => $document->user_id, 'supervisor_id' => $supervisor->id,
                    'previous_document_id' => $id, 'title' => $document->title,
                    'path' => $path, 'original_name' => $file->getClientOriginalName(), 'sha256' => $hash,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                WorkspaceActivity::notify([$supervisor->id], $request->user()->id, 'Logbook resubmitted / Logbook dihantar semula');
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return back()->with('success', 'Revised document sent. / Dokumen pembetulan dihantar.');
    }

    public function file(Request $request, int $id, string $kind): Response
    {
        Gate::authorize('logbook.view');
        $document = $this->documents($request)->where('id', $id)->firstOrFail();
        abort_unless(in_array($kind, ['document', 'signature']), 404);
        $path = $kind === 'document' ? $document->path : $document->signature_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $kind === 'document' ? DocumentPreview::show($path, $document->original_name, $request->boolean('download')) : Storage::disk('local')->response($path, null, $headers);
    }

    public function receipt(Request $request, int $id): View
    {
        Gate::authorize('logbook.view');
        $document = $this->documents($request)->where('id', $id)->firstOrFail();
        abort_unless(in_array($document->status, ['checked', 'verified']) && $document->reviewed_at, 404);
        $intern = DB::table('users')->where('id', $document->user_id)->value('name');

        return view('management.logbook-receipt', compact('document', 'intern'));
    }
}
