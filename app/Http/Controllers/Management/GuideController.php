<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Writing and removing guides.
 */
final class GuideController extends Controller
{
    public function saveGuide(Request $r, ?int $id = null): RedirectResponse
    {
        Gate::authorize('guide.save');
        $data = $r->validate(['title' => 'required|string|max:180', 'department' => ['nullable', Rule::exists('departments', 'name')], 'body' => 'required|string|max:20000', 'file' => 'nullable|file|mimes:pdf,docx,jpg,jpeg,png,webp|max:10240']);
        unset($data['file']);
        if ($id) {
            DB::table('intern_guides')->where('id', $id)->firstOrFail();
        }
        if ($r->hasFile('file')) {
            $data['file_path'] = $r->file('file')->store('guides', 'local');
            $data['file_name'] = $r->file('file')->getClientOriginalName();
        }if ($id) {
            DB::table('intern_guides')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            DB::table('intern_guides')->insert($data + ['created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        }Audit::record($r->user()->id, 'guide.saved', $data['title']);

        return back()->with('success', 'Guide saved. / Panduan disimpan.');
    }

    public function deleteGuide(Request $r, int $id): RedirectResponse
    {
        Gate::authorize('guide.delete');
        $guide = DB::table('intern_guides')->where('id', $id)->firstOrFail();
        DB::table('intern_guides')->where('id', $id)->delete();
        Audit::record($r->user()->id, 'guide.deleted', $guide->title);

        return back()->with('success', 'Guide deleted. / Panduan dipadam.');
    }
}
