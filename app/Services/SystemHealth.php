<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Everything an operator needs to answer "is this install healthy?" in one place.
 *
 * Deliberately read-only: nothing here retries, restarts or deletes. The page
 * shows what is wrong and the exact command that fixes it.
 */
class SystemHealth
{
    /** Anything older than this in the queue means the worker is probably down. */
    private const STALE_JOBS_MINUTES = 15;

    /** The scheduler writes a heartbeat every five minutes; allow three misses. */
    private const STALE_HEARTBEAT_MINUTES = 16;

    /** Files are counted up to this many before the walk gives up and reports a floor. */
    private const MAX_FILES_WALKED = 20000;

    public function report(): array
    {
        $queue = $this->queue();
        $mail = $this->mail();
        $scheduler = $this->scheduler();
        $storage = $this->storage();

        return [
            'status' => $this->status($checks = $this->checks($queue, $mail, $scheduler, $storage)),
            'checks' => $checks,
            'queue' => $queue,
            'mail' => $mail,
            'scheduler' => $scheduler,
            'storage' => $storage,
            'app' => [
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
                'session' => config('session.driver'),
            ],
        ];
    }

    private function queue(): array
    {
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $oldest = Schema::hasTable('jobs') ? DB::table('jobs')->min('created_at') : null;

        return [
            'connection' => config('queue.default'),
            'pending' => $pending,
            'oldest_minutes' => $oldest ? (int) round((time() - (int) $oldest) / 60) : null,
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'failed_rows' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->orderByDesc('id')->limit(10)->get()
                : collect(),
        ];
    }

    private function mail(): array
    {
        $invitations = Schema::hasTable('supervisor_invitations')
            ? DB::table('supervisor_invitations')
                ->selectRaw('delivery_status, count(*) as total')
                ->groupBy('delivery_status')
                ->pluck('total', 'delivery_status')
                ->all()
            : [];

        return [
            'mailer' => config('mail.default'),
            'queued' => (int) ($invitations['queued'] ?? 0),
            'sent' => (int) ($invitations['sent'] ?? 0),
            'failed' => (int) ($invitations['failed'] ?? 0),
            'failed_rows' => Schema::hasTable('supervisor_invitations')
                ? DB::table('supervisor_invitations')->where('delivery_status', 'failed')
                    ->orderByDesc('id')->limit(10)->get(['id', 'name', 'email', 'updated_at'])
                : collect(),
        ];
    }

    private function scheduler(): array
    {
        $heartbeat = Cache::get('schedule_heartbeat');
        $age = null;

        if (is_string($heartbeat)) {
            try {
                $age = (int) round(now()->diffInMinutes(Carbon::parse($heartbeat), true));
            } catch (Throwable) {
                $heartbeat = null;
            }
        }

        return [
            'heartbeat' => is_string($heartbeat) ? $heartbeat : null,
            'age_minutes' => $age,
            'events' => $this->scheduleList(),
        ];
    }

    /**
     * `schedule:list` boots the console kernel and reads routes/console.php, so it
     * reflects what would really run rather than a copy of the schedule.
     */
    private function scheduleList(): string
    {
        try {
            Artisan::call('schedule:list');
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            return 'Could not read the schedule: '.$e->getMessage();
        }

        return $output === '' ? 'No scheduled commands registered.' : $output;
    }

    private function storage(): array
    {
        $private = storage_path('app/private');
        $bytes = 0;
        $files = 0;

        if (is_dir($private)) {
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($private, \FilesystemIterator::SKIP_DOTS)
            ) as $file) {
                $bytes += $file->getSize();
                if (++$files >= self::MAX_FILES_WALKED) {
                    break;
                }
            }
        }

        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        return [
            'free' => $free === false ? null : (int) $free,
            'total' => $total === false ? null : (int) $total,
            'private_bytes' => $bytes,
            'private_files' => $files,
            'private_writable' => is_writable($private),
            'log_bytes' => is_file(storage_path('logs/laravel.log')) ? (int) filesize(storage_path('logs/laravel.log')) : 0,
        ];
    }

    /** @return list<array{label: string, ok: bool, detail: string}> */
    private function checks(array $queue, array $mail, array $scheduler, array $storage): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'Application debug',
            'ok' => ! (config('app.debug') && app()->environment('production')),
            'detail' => config('app.debug')
                ? (app()->environment('production')
                    ? 'APP_DEBUG is on in production — stack traces and env values leak to visitors.'
                    : 'APP_DEBUG is on. Fine locally; must be false in production.')
                : 'APP_DEBUG is off.',
        ];

        $workerDown = $queue['pending'] !== null
            && $queue['pending'] > 0
            && $queue['oldest_minutes'] !== null
            && $queue['oldest_minutes'] >= self::STALE_JOBS_MINUTES;

        $checks[] = [
            'label' => 'Queue worker',
            'ok' => ! $workerDown,
            'detail' => $queue['pending'] === null
                ? 'No jobs table on this connection ('.$queue['connection'].' runs inline).'
                : ($workerDown
                    ? $queue['pending'].' job(s) waiting, oldest for '.$queue['oldest_minutes'].' minutes — run `php artisan queue:work`.'
                    : ($queue['pending'] === 0 ? 'Queue is empty.' : $queue['pending'].' job(s) waiting, oldest '.$queue['oldest_minutes'].' minutes.')),
        ];

        $checks[] = [
            'label' => 'Failed jobs',
            'ok' => (int) ($queue['failed'] ?? 0) === 0,
            'detail' => (int) ($queue['failed'] ?? 0) === 0
                ? 'Nothing in the failed-jobs table.'
                : $queue['failed'].' failed job(s) — inspect with `php artisan queue:failed`, retry with `php artisan queue:retry all`.',
        ];

        $checks[] = [
            'label' => 'Invitation and reset mail',
            'ok' => $mail['failed'] === 0,
            'detail' => $mail['failed'] === 0
                ? $mail['sent'].' sent, '.$mail['queued'].' queued, none failed.'
                : $mail['failed'].' message(s) failed to send. Resend them from Overview → Invitations.',
        ];

        $staleHeartbeat = $scheduler['age_minutes'] === null || $scheduler['age_minutes'] >= self::STALE_HEARTBEAT_MINUTES;

        $checks[] = [
            'label' => 'Scheduler',
            'ok' => ! $staleHeartbeat,
            'detail' => $scheduler['heartbeat'] === null
                ? 'No heartbeat recorded yet — the cron entry running `php artisan schedule:run` may be missing.'
                : ($staleHeartbeat
                    ? 'Last heartbeat '.$scheduler['age_minutes'].' minutes ago — cron runs `schedule:run` every five minutes in a healthy install.'
                    : 'Last heartbeat '.$scheduler['age_minutes'].' minutes ago.'),
        ];

        $checks[] = [
            'label' => 'Disk space',
            'ok' => $storage['free'] === null || $storage['total'] === null || $storage['free'] > ($storage['total'] * 0.1),
            'detail' => $storage['free'] === null
                ? 'Disk usage is not reported by this platform.'
                : $this->bytes($storage['free']).' free of '.$this->bytes($storage['total']).'.',
        ];

        $checks[] = [
            'label' => 'Private uploads',
            'ok' => $storage['private_writable'],
            'detail' => $storage['private_writable']
                ? $this->bytes($storage['private_bytes']).' across '.$storage['private_files'].' file(s).'
                : 'storage/app/private is not writable — check-in photos and logbook files cannot be saved.',
        ];

        return $checks;
    }

    private function status(array $checks): string
    {
        foreach ($checks as $check) {
            if (! $check['ok']) {
                return 'warn';
            }
        }

        return 'ok';
    }

    public function bytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $bytes < 10 && $i > 0 ? 1 : 0).' '.$units[$i];
    }
}
