<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Departments, and the rename that has to follow the name everywhere.
 */
final class DepartmentController extends Controller
{
    public function departments(): View
    {
        return view('management.departments', ['departments' => DB::table('departments')->orderBy('name')->get()]);
    }

    public function saveDepartment(Request $r, ?int $id = null): RedirectResponse
    {
        Gate::authorize('department.save');
        $data = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($id)]]);
        DB::transaction(function () use ($r, $id, $data): void {
            if ($id) {
                $old = DB::table('departments')->where('id', $id)->firstOrFail();
                foreach (['users', 'supervisor_invitations', 'work_tasks', 'intern_guides'] as $table) {
                    DB::table($table)->where('department', $old->name)->update(['department' => $data['name']]);
                }DB::table('departments')->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                DB::table('departments')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            }Audit::record($r->user()->id, 'department.saved', $data['name']);
        });

        return back()->with('success', 'Department saved. / Jabatan disimpan.');
    }

    public function deleteDepartment(Request $r, int $id): RedirectResponse
    {
        Gate::authorize('department.delete');
        $d = DB::table('departments')->where('id', $id)->firstOrFail();
        foreach (['users', 'supervisor_invitations', 'work_tasks', 'intern_guides'] as $table) {
            if (DB::table($table)->where('department', $d->name)->exists()) {
                throw ValidationException::withMessages(['department' => 'This department is in use and cannot be deleted. / Jabatan ini masih digunakan.']);
            }
        }
        DB::table('departments')->where('id', $id)->delete();
        Audit::record($r->user()->id, 'department.deleted', $d->name);

        return back()->with('success', 'Department deleted. / Jabatan dipadam.');
    }
}
