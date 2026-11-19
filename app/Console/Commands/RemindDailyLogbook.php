<?php

namespace App\Console\Commands;

use App\Mail\ReminderMail;
use App\Services\Reminders;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RemindDailyLogbook extends Command
{
    protected $signature = 'logbook:remind {--date= : Day to check, Y-m-d (defaults to today)}';

    protected $description = 'Remind interns who have no logbook entry for the day';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $interns = Reminders::internsWithoutLog($date);
        $sent = 0;

        foreach ($interns as $id) {
            $sent += Reminders::notifyOnce(
                $id,
                'Logbook reminder',
                'Logbook reminder / Peringatan buku log: no entry for '.$date->format('j M Y').'. Add it before the day ends.',
                null,
                Reminders::EMAIL_LOGBOOK,
                new ReminderMail(
                    subjectLine: 'Logbook reminder / Peringatan buku log — '.$date->format('j M Y'),
                    heading: 'Your logbook is empty for '.$date->format('j M Y'),
                    lines: [
                        'Log what you worked on so your supervisor can review it.',
                        'Catat kerja anda supaya penyelia dapat menyemaknya.',
                        'If you have already logged your day, you can ignore this email. / Jika anda sudah mencatat, abaikan e-mel ini.',
                    ],
                    actionLabel: 'Open my logbook / Buka buku log',
                    actionUrl: route('workspace.logbook'),
                ),
            ) ? 1 : 0;
        }

        $this->info("Logbook reminders: {$sent} sent, ".count($interns).' intern(s) without an entry on '.$date->toDateString().'.');

        return self::SUCCESS;
    }
}
