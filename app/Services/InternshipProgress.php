<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class InternshipProgress
{
    /**
     * Where an intern is in their placement, from internship_start_date to
     * internship_end_date. Calendar days, Asia/Kuala_Lumpur.
     *
     * state: unset | upcoming | active | finished
     *
     * @return array<string,mixed>
     */
    public static function for(object $user, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now('Asia/Kuala_Lumpur'))->copy()->startOfDay();

        if (! $user->internship_start_date || ! $user->internship_end_date) {
            return ['state' => 'unset'];
        }

        $start = Carbon::parse($user->internship_start_date, 'Asia/Kuala_Lumpur')->startOfDay();
        $end = Carbon::parse($user->internship_end_date, 'Asia/Kuala_Lumpur')->startOfDay();

        if ($end->lt($start)) {
            return ['state' => 'unset'];
        }

        $totalDays = (int) $start->diffInDays($end) + 1;
        $weeks = max(1, (int) ceil($totalDays / 7));
        $base = ['start' => $start, 'end' => $end, 'total_days' => $totalDays, 'weeks' => $weeks];

        if ($today->lt($start)) {
            return $base + ['state' => 'upcoming', 'percent' => 0, 'day' => 0, 'week' => 0, 'days_left' => $totalDays, 'starts_in' => (int) $today->diffInDays($start)];
        }

        if ($today->gt($end)) {
            return $base + ['state' => 'finished', 'percent' => 100, 'day' => $totalDays, 'week' => $weeks, 'days_left' => 0];
        }

        $day = (int) $start->diffInDays($today) + 1;

        return $base + [
            'state' => 'active',
            'day' => $day,
            'week' => (int) ceil($day / 7),
            'percent' => (int) round($day / $totalDays * 100),
            'days_left' => $totalDays - $day,
        ];
    }
}
