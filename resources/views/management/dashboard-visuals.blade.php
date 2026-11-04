@php
$total = $statuses->sum();
$complete = (int) ($statuses['completed'] ?? 0);
$percent = $total ? (int) round($complete / $total * 100) : 0;
$statusLabels = ['todo'=>['To do','Belum mula'],'in_progress'=>['In progress','Sedang dibuat'],'review'=>['In review','Dalam semakan'],'completed'=>['Completed','Selesai']];
@endphp
<div class="dashboard-visuals">
<section class="glass section-card task-chart"><div class="section-heading"><div><p class="eyebrow">{{ $t('PROGRESS SNAPSHOT','RINGKASAN KEMAJUAN') }}</p><h2>{{ $t('Every step counts','Setiap langkah bermakna') }}</h2></div><span class="badge neutral">{{ $total }} {{ $t('tasks','tugasan') }}</span></div>
<div class="chart-content"><div class="completion-ring" style="--progress:{{ $percent }}%"><div><strong>{{ $percent }}%</strong><span>{{ $t('completed','selesai') }}</span></div></div><div class="chart-bars">
@foreach($statusLabels as $status=>$label)
<a href="{{ route('workspace.tasks',['status'=>$status]) }}"><div><span>{{ $t(...$label) }}</span><strong>{{ $statuses[$status] ?? 0 }}</strong></div><div class="bar-track"><span style="width:{{ $total ? (($statuses[$status]??0)/$total*100) : 0 }}%"></span></div></a>
@endforeach
</div></div><p class="chart-caption">{{ $total ? $t('Current tasks only · archived work excluded','Tugasan semasa sahaja · arkib tidak termasuk') : $t('No tasks yet. Your progress will appear here.','Belum ada tugasan. Kemajuan akan dipaparkan di sini.') }}</p></section>
<section class="glass section-card deadline-calendar"><div class="section-heading"><div><p class="eyebrow">{{ $t('PLAN AHEAD','RANCANG AWAL') }}</p><h2>{{ $t('Deadline calendar','Kalendar tarikh akhir') }}</h2></div></div>
<div class="calendar-toolbar"><a href="{{ request()->fullUrlWithQuery(['month'=>$calendarMonth->subMonth()->format('Y-m')]) }}" aria-label="Previous month / Bulan sebelumnya">‹</a><strong>{{ $t($calendarMonth->locale('en')->translatedFormat('F Y'),$calendarMonth->locale('ms')->translatedFormat('F Y')) }}</strong><a href="{{ request()->fullUrlWithQuery(['month'=>$calendarMonth->addMonth()->format('Y-m')]) }}" aria-label="Next month / Bulan seterusnya">›</a></div>
<div class="calendar-grid">@foreach([['M','I'],['T','S'],['W','R'],['T','K'],['F','J'],['S','S'],['S','A']] as $day)<span class="weekday">{{ $t(...$day) }}</span>@endforeach
@for($i=1;$i<$calendarMonth->dayOfWeekIso;$i++)<span></span>@endfor
@for($day=1;$day<=$calendarMonth->daysInMonth;$day++)
@php($date=$calendarMonth->day($day)->toDateString())
<a class="calendar-day {{ $date===now('Asia/Kuala_Lumpur')->toDateString()?'today':'' }} {{ isset($calendarCounts[$date])?'has-tasks':'' }}" href="{{ route('workspace.tasks',['due_date'=>$date]) }}" aria-label="{{ $date }} · {{ $calendarCounts[$date]??0 }} tasks / tugasan">{{ $day }}@if(isset($calendarCounts[$date]))<small>{{ $calendarCounts[$date] }}</small>@endif</a>
@endfor</div><p class="chart-caption">{{ $t('Select a date to see its tasks. Numbers show deadlines.','Pilih tarikh untuk lihat tugasan. Nombor kecil menunjukkan jumlah tugasan.') }}</p></section></div>
