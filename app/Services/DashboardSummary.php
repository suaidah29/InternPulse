<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardSummary
{
    public static function forUser(User $user): array
    {
        $query = DB::table('work_tasks')->whereNull('archived_at');
        if ($user->role === 'intern') {
            $query->where('assigned_to', $user->id);
        } elseif ($user->role === 'supervisor') {
            $query->whereIn('assigned_to', DB::table('users')->where('role', 'intern')->where('supervisor_id', $user->id)->select('id'));
        }
        $month = request()->query('month');
        $month = is_string($month) && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $month) ? $month : now('Asia/Kuala_Lumpur')->format('Y-m');
        $start = CarbonImmutable::parse($month.'-01', 'Asia/Kuala_Lumpur');

        return [
            'statuses' => (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'calendarMonth' => $start,
            'calendarCounts' => (clone $query)->whereBetween('due_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->selectRaw('due_date, count(*) as total')->groupBy('due_date')->pluck('total', 'due_date'),
        ];
    }
}
