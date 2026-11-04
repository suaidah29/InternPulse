@extends('management.shell')
@section('title','Task details')
@section('content')
<a class="template-link" href="{{ route('workspace.tasks') }}">← {{ $t('All tasks','Semua tugasan') }}</a>
@include('management.notices')
<section class="glass section-card"><div class="record-heading"><div><p class="record-meta">#{{ $task->id }} · {{ $task->department ?: '—' }}</p><h1>{{ $task->title }}</h1><p>{{ $owner->name }} · {{ $task->due_date ?: $t('No deadline','Tiada tarikh akhir') }}</p></div><span class="badge neutral">{{ str_replace('_',' ',$task->status) }}</span></div><p class="read-text">{{ $task->description }}</p>
@if($task->progress_note)<h2>{{ $t('Latest progress','Kemajuan terkini') }}</h2><p class="read-text">{{ $task->progress_note }}</p>@endif
@if($task->work_url)<a class="quiet-button" href="{{ $task->work_url }}" target="_blank" rel="noopener noreferrer">{{ $t('View work','Lihat hasil kerja') }} ↗</a>@endif
<div class="attachment-list">@foreach($files as $file)<a href="{{ route('workspace.files',['type'=>'task','id'=>$file->id]) }}">↓ {{ $file->original_name }}</a>@endforeach</div>
@if($canReview && $task->status==='review' && !$task->archived_at)<section class="record-card"><h2>{{ $t('Review this work','Semak hasil kerja') }}</h2><form method="post" action="{{ route('workspace.tasks.decision',$task->id) }}">@csrf<label>{{ $t('Feedback','Maklum balas') }}<textarea name="comment" required minlength="3" maxlength="4000" placeholder="What went well or needs changing / Maklum balas hasil kerja"></textarea></label><div class="form-actions"><button class="primary-button" name="decision" value="completed">{{ $t('Approve completion','Sahkan selesai') }}</button><button class="quiet-button" name="decision" value="in_progress">{{ $t('Request changes','Minta pembetulan') }}</button></div></form></section>@endif
@include('management.task-actions')
</section>
<section class="glass section-card"><h2>{{ $t('Conversation & history','Perbincangan & sejarah') }}</h2>
@unless($task->archived_at)<form method="post" action="{{ route('workspace.tasks.comment',$task->id) }}">@csrf<label>{{ $t('Add a comment','Tambah komen') }}<textarea name="comment" required maxlength="4000"></textarea></label><button class="primary-button">{{ $t('Post comment','Hantar komen') }}</button></form>@endunless
@forelse($history as $event)<article class="record-card"><strong>{{ $event->name }}</strong><p class="record-meta">{{ str_replace('_',' ',$event->kind) }} · {{ \Carbon\Carbon::parse($event->created_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }} MYT</p><p class="read-text">{{ $event->body }}</p></article>@empty<p class="muted">{{ $t('No updates yet. New activity will appear here.','Belum ada kemas kini. Aktiviti baharu akan dipaparkan di sini.') }}</p>@endforelse
<div class="pager">@if($history->previousPageUrl())<a href="{{ $history->previousPageUrl() }}">← {{ $t('Previous','Sebelumnya') }}</a>@endif<span>{{ $history->currentPage() }} / {{ $history->lastPage() }}</span>@if($history->nextPageUrl())<a href="{{ $history->nextPageUrl() }}">{{ $t('Next','Seterusnya') }} →</a>@endif</div></section>
@endsection
