@extends('management.shell')
@section('title','Inbox')
@section('content')
@include('management.notices')
<x-page-header :eyebrow="$t('NEEDS YOU','PERLU ANDA')" :heading="$t('Inbox','Peti masuk')" :lede="$t('Everything waiting on your decision today — reviews, deadlines, logbooks, attendance and blockers — on one page, with the action beside it.','Semua yang menunggu keputusan anda hari ini — semakan, tarikh akhir, logbook, kehadiran dan halangan — di satu halaman, bersama tindakannya.')" />

@if($total === 0)
    <section class="glass section-card empty-state">
        <h2>{{ $t('Nothing is waiting on you.','Tiada apa menunggu anda.') }}</h2>
        <p class="muted">{{ $t('Reviews, logbook verifications, attendance checks and overdue tasks will appear here as soon as they arrive.','Semakan, pengesahan logbook, kehadiran dan tugasan lewat akan muncul di sini sebaik sahaja tiba.') }}</p>
    </section>
@endif

{{-- Internal verification needs a signature on file; say so before the form fails. --}}
@if(! $hasSignature && collect($sections)->firstWhere('key', 'logbook')['count'] > 0 && auth()->user()->role === 'supervisor')
    <section class="glass section-card inbox-notice">
        <p>{{ $t('Logbook documents clear only once your signature is on file.','Dokumen logbook hanya boleh disahkan selepas tandatangan anda ada dalam sistem.') }}
            <a href="{{ route('profile.edit') }}">{{ $t('Upload a signature in Edit profile','Muat naik tandatangan di Edit profil') }}</a>.</p>
    </section>
@endif

@foreach($sections as $section)
    @continue($section['count'] === 0)
    <section class="glass section-card inbox-section" id="inbox-{{ $section['key'] }}">
        <div class="inbox-head">
            <h2>{{ $t($section['label'], $section['label_ms']) }} <span class="inbox-count">{{ $section['count'] }}</span></h2>
            <a class="quiet-button" href="{{ $section['href'] }}">{{ $t('Open full page','Buka halaman penuh') }} ↗</a>
        </div>
        <p class="muted inbox-lede">{{ $t($section['lede'], $section['lede_ms']) }}</p>

        <div class="record-list">
            @foreach($section['rows'] as $row)
                <article class="record-card inbox-row">
                    <div>
                        <h3>
                            @if(! empty($row['href']))
                                <a href="{{ $row['href'] }}">{{ $row['title'] }}</a>
                            @else
                                {{ $row['title'] }}
                            @endif
                        </h3>
                        <div class="record-meta">{{ $row['who'] }} · {{ $row['meta'] }}</div>
                        @if(! empty($row['note']))
                            <p>{{ \Illuminate\Support\Str::limit($row['note'], 240) }}</p>
                        @endif
                        @if(! empty($row['photo']))
                            <a class="quiet-button" href="{{ $row['photo'] }}" target="_blank" rel="noopener" data-file-preview>{{ $t('View photo evidence','Lihat bukti gambar') }}</a>
                        @endif
                    </div>
                    <div class="inbox-actions">
                        @if(! empty($row['review_type']))
                            @include('management.review-form', ['type' => $row['review_type'], 'id' => $row['id']])
                        @elseif($section['key'] === 'logbook')
                            <form method="post" action="{{ route('workspace.logbook.verify', $row['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="verified">
                                <button class="primary-button" type="submit">{{ $t('Verify with signature','Sahkan dengan tandatangan') }}</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($section['count'] > $section['rows']->count())
            <p class="muted">{{ $t('Showing the first', 'Menunjukkan') }} {{ $section['rows']->count() }} {{ $t('of', 'daripada') }} {{ $section['count'] }} — {{ $t('open the full page for the rest.','buka halaman penuh untuk selebihnya.') }}</p>
        @endif
    </section>
@endforeach
@endsection
