@php($ls = $streak)
<section class="glass section-card log-streak">
<div class="insight-head"><div><p class="eyebrow">{{ $t('LOGBOOK HABIT','TABIAT LOGBOOK') }}</p><h2>{{ $t('Last '.$ls['weeks'].' weeks','Lepas '.$ls['weeks'].' minggu') }}</h2></div>
<span class="streak-badge {{ $ls['current'] ? '' : 'idle' }}">@if($ls['current'])<span aria-hidden="true">🔥</span> {{ $ls['current'] }} {{ $t('day streak','hari berturut-turut') }}@else{{ $t('No streak yet','Belum ada streak') }}@endif</span></div>

<p class="sr-only">{{ $t($ls['logged'].' of '.$ls['weekdays'].' weekdays logged. Current streak '.$ls['current'].', best '.$ls['best'].'.',$ls['logged'].' daripada '.$ls['weekdays'].' hari bekerja direkod. Streak semasa '.$ls['current'].', terbaik '.$ls['best'].'.') }}</p>
<div class="heatmap" aria-hidden="true" style="grid-template-columns:repeat({{ $ls['weeks'] }},1fr)">
@foreach($ls['columns'] as $column)<div class="heatmap-col">@foreach($column as $cell)<span class="hm-cell {{ $cell['state'] }} {{ $cell['today'] ? 'today' : '' }}" title="{{ $cell['label'] }} · {{ $cell['state'] }}"></span>@endforeach</div>@endforeach
</div>
<div class="heatmap-legend" aria-hidden="true"><span><i class="logged"></i>{{ $t('Logged','Direkod') }}</span><span><i class="missed"></i>{{ $t('Missed weekday','Hari bekerja terlepas') }}</span><span><i></i>{{ $t('Weekend / outside placement','Hujung minggu / luar tempoh') }}</span></div>
<p class="insight-note">{{ $ls['logged'] }}/{{ $ls['weekdays'] }} {{ $t('weekdays logged','hari bekerja direkod') }} · {{ $t('best streak','streak terbaik') }} {{ $ls['best'] }}@if(! $ls['loggedToday']) · <a href="{{ route('workspace.logbook') }}#logbook-form">{{ $t('Log today','Isi hari ini') }} →</a>@endif</p>
</section>
