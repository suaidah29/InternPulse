@extends('management.shell')
@section('title','System health')
@section('content')
<x-page-header :eyebrow="$t('OPERATIONS','OPERASI')" :heading="$t('System health','Kesihatan sistem')" :lede="$t('Queue, scheduler, mail and disk in one place. Read-only — the fix for each problem is printed with it.','Baris gilir, penjadual, e-mel dan cakera di satu tempat. Baca sahaja — cara membaikinya dipaparkan bersama.')" />

<section class="glass section-card health-status health-{{ $report['status'] }}">
    <p class="health-badge" role="status">
        @if($report['status'] === 'ok')
            {{ $t('All checks passing.','Semua semakan lulus.') }}
        @else
            {{ $t('Attention needed — see the failed checks below.','Perlu perhatian — lihat semakan yang gagal di bawah.') }}
        @endif
    </p>
    <ul class="health-checks">
        @foreach($report['checks'] as $check)
            <li class="health-check {{ $check['ok'] ? 'is-ok' : 'is-warn' }}">
                <span class="health-dot" aria-hidden="true">{{ $check['ok'] ? '✓' : '!' }}</span>
                <span class="health-label">{{ $check['label'] }}</span>
                <span class="health-detail">{{ $check['detail'] }}</span>
            </li>
        @endforeach
    </ul>
</section>

<div class="health-grid">
    <section class="glass section-card">
        <h2>{{ $t('Queue','Baris gilir') }}</h2>
        <dl class="health-facts">
            <div><dt>{{ $t('Connection','Sambungan') }}</dt><dd>{{ $report['queue']['connection'] }}</dd></div>
            <div><dt>{{ $t('Waiting jobs','Tugasan menunggu') }}</dt><dd>{{ $report['queue']['pending'] ?? '—' }}</dd></div>
            <div><dt>{{ $t('Oldest waiting','Paling lama menunggu') }}</dt><dd>{{ $report['queue']['oldest_minutes'] === null ? '—' : $report['queue']['oldest_minutes'].' min' }}</dd></div>
            <div><dt>{{ $t('Failed jobs','Tugasan gagal') }}</dt><dd>{{ $report['queue']['failed'] ?? '—' }}</dd></div>
        </dl>
        @if($report['queue']['failed'] > 0)
            <p class="muted">{{ $t('Inspect with','Periksa dengan') }} <code>php artisan queue:failed</code>, {{ $t('retry with','cuba semula dengan') }} <code>php artisan queue:retry all</code>.</p>
        @endif
    </section>

    <section class="glass section-card">
        <h2>{{ $t('Mail','E-mel') }}</h2>
        <dl class="health-facts">
            <div><dt>{{ $t('Mailer','Penghantar') }}</dt><dd>{{ $report['mail']['mailer'] }}</dd></div>
            <div><dt>{{ $t('Invitations sent','Jemputan dihantar') }}</dt><dd>{{ $report['mail']['sent'] }}</dd></div>
            <div><dt>{{ $t('Queued','Dalam baris gilir') }}</dt><dd>{{ $report['mail']['queued'] }}</dd></div>
            <div><dt>{{ $t('Failed','Gagal') }}</dt><dd>{{ $report['mail']['failed'] }}</dd></div>
        </dl>
    </section>

    <section class="glass section-card">
        <h2>{{ $t('Storage','Storan') }}</h2>
        <dl class="health-facts">
            <div><dt>{{ $t('Disk free','Cakera bebas') }}</dt><dd>{{ $bytes($report['storage']['free']) }}</dd></div>
            <div><dt>{{ $t('Private uploads','Muat naik peribadi') }}</dt><dd>{{ $bytes($report['storage']['private_bytes']) }} ({{ $report['storage']['private_files'] }} {{ $t('files','fail') }})</dd></div>
            <div><dt>{{ $t('Writable','Boleh tulis') }}</dt><dd>{{ $report['storage']['private_writable'] ? $t('Yes','Ya') : $t('No','Tidak') }}</dd></div>
            <div><dt>{{ $t('Log file','Fail log') }}</dt><dd>{{ $bytes($report['storage']['log_bytes']) }}</dd></div>
        </dl>
    </section>

    <section class="glass section-card">
        <h2>{{ $t('Application','Aplikasi') }}</h2>
        <dl class="health-facts">
            <div><dt>{{ $t('Environment','Persekitaran') }}</dt><dd>{{ $report['app']['environment'] }}</dd></div>
            <div><dt>PHP</dt><dd>{{ $report['app']['php'] }}</dd></div>
            <div><dt>Laravel</dt><dd>{{ $report['app']['laravel'] }}</dd></div>
            <div><dt>{{ $t('Timezone','Zon masa') }}</dt><dd>{{ $report['app']['timezone'] }}</dd></div>
            <div><dt>{{ $t('Cache','Cache') }}</dt><dd>{{ $report['app']['cache'] }}</dd></div>
            <div><dt>{{ $t('Session','Sesi') }}</dt><dd>{{ $report['app']['session'] }}</dd></div>
        </dl>
    </section>
</div>

@if($report['queue']['failed_rows']->isNotEmpty())
    <section class="glass section-card">
        <h2>{{ $t('Last failed jobs','Tugasan gagal terkini') }}</h2>
        <div class="table-scroll"><table>
            <thead><tr>
                <th scope="col">{{ $t('When','Bila') }}</th>
                <th scope="col">{{ $t('Queue','Baris gilir') }}</th>
                <th scope="col">{{ $t('Reason','Sebab') }}</th>
            </tr></thead>
            <tbody>
            @foreach($report['queue']['failed_rows'] as $job)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($job->failed_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }}</td>
                    <td>{{ $job->queue }}</td>
                    <td class="health-exception">{{ \Illuminate\Support\Str::limit(strtok((string) $job->exception, "\n"), 160) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
@endif

@if($report['mail']['failed_rows']->isNotEmpty())
    <section class="glass section-card">
        <h2>{{ $t('Invitations that never sent','Jemputan yang tidak terhantar') }}</h2>
        <div class="table-scroll"><table>
            <thead><tr>
                <th scope="col">{{ $t('Person','Pengguna') }}</th>
                <th scope="col">{{ $t('Email','E-mel') }}</th>
                <th scope="col">{{ $t('Last attempt','Percubaan terakhir') }}</th>
                <th scope="col"></th>
            </tr></thead>
            <tbody>
            @foreach($report['mail']['failed_rows'] as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->email }}</td>
                    <td>{{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') : '—' }}</td>
                    <td><form method="post" action="{{ route('management.invitations.resend', $row->id) }}">@csrf<button class="quiet-button" type="submit">{{ $t('Resend','Hantar semula') }}</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
@endif

<section class="glass section-card">
    <h2>{{ $t('Scheduled tasks','Tugasan berjadual') }}</h2>
    <p class="muted">{{ $t('Recorded heartbeat','Denyut terakhir') }}: {{ $report['scheduler']['heartbeat'] ?? $t('never','belum pernah') }}@if($report['scheduler']['age_minutes'] !== null) ({{ $report['scheduler']['age_minutes'] }} {{ $t('minutes ago','minit lalu') }})@endif</p>
    <pre class="health-schedule">{{ $report['scheduler']['events'] }}</pre>
    <p class="muted">{{ $t('The scheduler only runs if cron calls','Penjadual hanya berjalan jika cron memanggil') }} <code>php artisan schedule:run</code> {{ $t('every minute.','setiap minit.') }}</p>
</section>
@endsection
