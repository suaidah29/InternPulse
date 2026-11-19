<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesWorkspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The bell.
 */
final class NotificationController extends Controller
{
    use ScopesWorkspace;

    public function notifications(Request $r): View
    {
        return view('management.notifications', ['notifications' => DB::table('workspace_notifications')->where('user_id', $r->user()->id)->when($r->query('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))->orderByDesc('id')->paginate(20)->withQueryString(), 'deadlines' => $this->tasksQuery($r)->whereNull('archived_at')->where('status', '!=', 'completed')->whereNotNull('due_date')->where('due_date', '<=', now('Asia/Kuala_Lumpur')->addDays(2)->toDateString())->orderBy('due_date')->limit(20)->get()]);
    }

    public function readNotifications(Request $r): RedirectResponse
    {
        DB::table('workspace_notifications')->where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Marked as read. / Ditandakan dibaca.');
    }
}
