<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LogStreak
{
    private const LOOKBACK_DAYS = 400;

    /**
     * Logbook consistency for one intern: a Monday-first week grid for the last
     * $weeks weeks plus the current and best streak.
     *
     * Streak rules: consecutive weekdays with an entry. Weekends never break a
     * streak (and count if the intern logged anyway). Today is not held against
     * the intern until the day is over.
     *
     * cell state: logged | missed | open | weekend | off | future   (+ today flag)
     *
     * @return array<string,mixed>
     */
    public static function for(object $user, ?Carbon $today = null, int $weeks = 12): array
    {
        $today = ($today ?? Carbon::now('Asia/Kuala_Lumpur'))->copy()->startOfDay();
        $lookbackFrom = $today->copy()->subDays(self::LOOKBACK_DAYS);

        $logged = DB::table('daily_logs')
            ->where('user_id', $user->id)
            ->where('activity_date', '>=', $lookbackFrom->toDateString())
            ->pluck('activity_date')
            ->mapWithKeys(fn ($d) => [Carbon::parse($d)->toDateString() => true])
            ->all();

        // Days outside the placement (or before the account existed) are not "missed".
        $from = $user->internship_start_date
            ? Carbon::parse($user->internship_start_date)->startOfDay()
            : Carbon::parse($user->created_at ?? $today)->startOfDay();
        $until = $user->internship_end_date ? Carbon::parse($user->internship_end_date)->startOfDay() : null;

        $gridStart = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $columns = [];
        $loggedInWindow = 0;
        $weekdaysInWindow = 0;

        for ($w = 0; $w < $weeks; $w++) {
            $column = [];
            for ($d = 0; $d < 7; $d++) {
                $date = $gridStart->copy()->addWeeks($w)->addDays($d);
                $key = $date->toDateString();
                $isWeekend = $date->isWeekend();
                $inRange = $date->gte($from) && (! $until || $date->lte($until));

                if ($date->gt($today)) {
                    $state = 'future';
                } elseif (isset($logged[$key])) {
                    $state = 'logged';
                } elseif (! $inRange) {
                    $state = 'off';
                } elseif ($isWeekend) {
                    $state = 'weekend';
                } elseif ($date->eq($today)) {
                    $state = 'open'; // the day is not over yet, so it is not "missed"
                } else {
                    $state = 'missed';
                }

                if ($state === 'logged' || $state === 'missed') {
                    $weekdaysInWindow++;
                    $loggedInWindow += isset($logged[$key]) ? 1 : 0;
                }

                $column[] = ['date' => $key, 'state' => $state, 'today' => $date->eq($today), 'label' => $date->locale('en')->translatedFormat('D j M')];
            }
            $columns[] = $column;
        }

        return [
            'columns' => $columns,
            'current' => self::current($logged, $today),
            'best' => self::best($logged, $today),
            'logged' => $loggedInWindow,
            'weekdays' => $weekdaysInWindow,
            'weeks' => $weeks,
            'loggedToday' => isset($logged[$today->toDateString()]),
        ];
    }

    private static function current(array $logged, Carbon $today): int
    {
        $streak = 0;
        $date = $today->copy();

        for ($i = 0; $i <= self::LOOKBACK_DAYS; $i++, $date->subDay()) {
            $key = $date->toDateString();
            if (isset($logged[$key])) {
                $streak++;
            } elseif ($date->eq($today) || $date->isWeekend()) {
                continue; // today is still open; weekends never break a streak
            } else {
                break;
            }
        }

        return $streak;
    }

    private static function best(array $logged, Carbon $today): int
    {
        $best = $run = 0;
        $date = $today->copy()->subDays(self::LOOKBACK_DAYS);

        while ($date->lte($today)) {
            $key = $date->toDateString();
            if (isset($logged[$key])) {
                $best = max($best, ++$run);
            } elseif (! $date->isWeekend() && ! $date->eq($today)) {
                $run = 0;
            }
            $date->addDay();
        }

        return $best;
    }
}
