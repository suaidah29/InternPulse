@php
$today = now('Asia/Kuala_Lumpur')->toDateString();
$columns = ['todo','in_progress','review','completed'];
$grouped = $boardTasks->groupBy('status');
$me = auth()->id();
@endphp
<section class="kanban" data-kanban data-url="{{ url('/workspace/tasks') }}" data-csrf="{{ csrf_token() }}" aria-label="{{ $t('Task board','Papan tugasan') }}">
<p class="kanban-help muted">@if(auth()->user()->role === 'intern'){{ $t('Drag your cards between To do, In progress and For review, or use the Move menu. Completed is set by your supervisor.','Seret kad anda antara Belum mula, Sedang dibuat dan Untuk semakan, atau guna menu Alih. Selesai ditetapkan oleh penyelia anda.') }}@else{{ $t('Overview of your team. Open a card to review or comment; interns move their own cards.','Gambaran pasukan anda. Buka kad untuk semak atau komen; intern mengalih kad mereka sendiri.') }}@endif</p>
<div class="kanban-status sr-only" role="status" aria-live="polite" data-kanban-live></div>
<div class="kanban-cols">
@foreach($columns as $status)
@php($cards = $grouped[$status] ?? collect())
<section class="kanban-col tone-{{ \App\Services\TaskStatus::tone($status) }}" data-status="{{ $status }}" aria-labelledby="kb-{{ $status }}">
<header><h2 id="kb-{{ $status }}">{{ $t(...\App\Services\TaskStatus::label($status)) }}</h2><span class="kanban-count" data-count>{{ $cards->count() }}</span></header>
<div class="kanban-list" data-dropzone @if($status==='completed') data-locked @endif>
@foreach($cards as $task)
@php($mine = $task->assigned_to === $me && $status !== 'completed')
@php($late = $task->due_date && $task->due_date < $today && $status !== 'completed')
<article class="kanban-card {{ $task->priority==='urgent'?'urgent':'' }}" data-id="{{ $task->id }}" data-status="{{ $status }}" @if($mine) draggable="true" data-mine @endif>
<a class="kanban-title" href="{{ route('workspace.tasks.show',$task->id) }}">{{ $task->title }}</a>
<p class="kanban-meta">{{ $names[$task->assigned_to] ?? '—' }}</p>
<div class="kanban-foot">
@if($task->due_date)<span class="kanban-due {{ $late?'late':'' }}">{{ \Illuminate\Support\Carbon::parse($task->due_date)->format('j M') }}@if($late) · {{ $t('Overdue','Lewat') }}@endif</span>@else<span class="kanban-due muted">{{ $t('No deadline','Tiada tarikh akhir') }}</span>@endif
@if($task->priority==='urgent')<span class="badge danger">{{ $t('Urgent','Segera') }}</span>@endif
</div>
@if($mine)<label class="kanban-move"><span class="sr-only">{{ $t('Move','Alih') }}: {{ $task->title }}</span><select data-move aria-label="{{ $t('Move to','Alih ke') }}">@foreach(['todo','in_progress','review'] as $opt)<option value="{{ $opt }}" @selected($opt===$status)>{{ \App\Services\TaskStatus::label($opt)[0] }} / {{ \App\Services\TaskStatus::label($opt)[1] }}</option>@endforeach</select></label>@endif
</article>
@endforeach
<p class="kanban-empty muted" @if($cards->count()) hidden @endif>{{ $t('Nothing here','Tiada apa-apa di sini') }}</p>
</div>
</section>
@endforeach
</div>
@if($boardTasks->count() >= 300)<p class="muted">{{ $t('Showing the first 300 tasks. Narrow the filters to see others.','Memaparkan 300 tugasan pertama. Sempitkan penapis untuk lihat yang lain.') }}</p>@endif
</section>
<script src="{{ asset('kanban.js') }}?v=1" defer></script>
