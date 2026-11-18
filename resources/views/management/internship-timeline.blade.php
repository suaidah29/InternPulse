@php($tl = $timeline)
<section class="glass section-card internship-timeline">
<div class="insight-head"><div><p class="eyebrow">{{ $t('YOUR INTERNSHIP','LATIHAN ANDA') }}</p><h2>{{ $t('Placement timeline','Garis masa latihan') }}</h2></div>
@if($tl['state'] === 'active')<p class="insight-figure">{{ $tl['percent'] }}%<small>{{ $t('complete','selesai') }}</small></p>@endif</div>

@if($tl['state'] === 'unset')
<p class="insight-note">{{ $t('Your internship dates have not been set yet. Ask your supervisor or admin to add them and your progress will appear here.','Tarikh latihan anda belum ditetapkan. Minta penyelia atau admin menambahnya dan kemajuan anda akan dipaparkan di sini.') }}</p>
@else
<div class="timeline-track" style="--weeks:{{ min($tl['weeks'], 52) }}" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tl['percent'] }}" aria-label="Internship progress"><span style="width:{{ $tl['percent'] }}%"></span></div>
<p class="timeline-ends"><span><strong>{{ $tl['start']->format('j M Y') }}</strong></span><span><strong>{{ $tl['end']->format('j M Y') }}</strong></span></p>

@if($tl['state'] === 'active')
<div class="timeline-stats">
<div><strong>{{ $tl['day'] }}<small> / {{ $tl['total_days'] }}</small></strong><span>{{ $t('Day','Hari') }}</span></div>
<div><strong>{{ $tl['week'] }}<small> / {{ $tl['weeks'] }}</small></strong><span>{{ $t('Week','Minggu') }}</span></div>
<div><strong>{{ $tl['days_left'] }}</strong><span>{{ $t('Days left','Hari berbaki') }}</span></div>
</div>
@elseif($tl['state'] === 'upcoming')
<p class="insight-note">{{ $t('Your placement starts in '.$tl['starts_in'].' day(s). It runs for '.$tl['weeks'].' week(s).','Latihan anda bermula dalam '.$tl['starts_in'].' hari. Tempohnya '.$tl['weeks'].' minggu.') }}</p>
@else
<p class="insight-note">{{ $t('Your placement has finished. Thank you for the work over '.$tl['weeks'].' week(s).','Latihan anda telah tamat. Terima kasih atas usaha sepanjang '.$tl['weeks'].' minggu.') }}</p>
@endif
@endif
</section>
