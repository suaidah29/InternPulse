<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesWorkspace;
use App\Services\WorkspaceActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Attendance: what an intern submits and what they can review.
 */
final class AttendanceController extends Controller
{
    use ScopesWorkspace;

    public function records(Request $r): View
    {
        $ids = $this->teamIds($r);
        $suggestions = DB::table('task_activity as a')->join('work_tasks as t', 't.id', '=', 'a.task_id')->where('a.actor_id', $r->user()->id)->where('t.assigned_to', $r->user()->id)->where('a.kind', 'progress')->whereBetween('a.created_at', [now('Asia/Kuala_Lumpur')->startOfDay()->utc(), now('Asia/Kuala_Lumpur')->endOfDay()->utc()])->select('t.title')->distinct()->pluck('title');

        return view('management.records', ['suggestions' => $suggestions, 'logs' => DB::table('daily_logs')->where('user_id', $r->user()->role === 'intern' ? $r->user()->id : 0)->orderByDesc('activity_date')->paginate(10, ['*'], 'logs_page')->withQueryString(), 'attendance' => DB::table('attendance_records')->whereIn('user_id', $ids)->orderByDesc('attendance_date')->paginate(10, ['*'], 'attendance_page')->withQueryString(), 'names' => DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')]);
    }

    public function checkIn(Request $r): RedirectResponse
    {
        Gate::authorize('attendance.record');
        $r->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $date = now('Asia/Kuala_Lumpur')->toDateString();
        if (DB::table('attendance_records')->where('user_id', $r->user()->id)->where('attendance_date', $date)->exists()) {
            throw ValidationException::withMessages(['photo' => 'Already checked in today. / Kehadiran hari ini sudah direkod.']);
        }
        $path = $r->file('photo')->store('attendance', 'local');
        DB::table('attendance_records')->insert(['user_id' => $r->user()->id, 'attendance_date' => $date, 'checked_in_at' => now(), 'photo_path' => $path, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        WorkspaceActivity::notify(WorkspaceActivity::reviewers($r->user()->id), $r->user()->id, 'Attendance ready for review / Kehadiran untuk semakan');

        return back()->with('success', 'Attendance submitted for verification. / Kehadiran dihantar untuk pengesahan.');
    }
}
