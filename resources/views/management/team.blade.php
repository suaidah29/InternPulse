@extends('management.shell')
@section('title','My team')
@section('content')
<section class="glass panel"><h1>{{ $t('My team','Pasukan saya') }}</h1><p>{{ $t('Your interns, together in one workspace.','Intern di bawah penyeliaan anda.') }}</p>
@include('management.notices')
<form method="get"><label>{{ $t('Find an intern','Cari intern') }}<input name="q" value="{{ request('q') }}" placeholder="Name / Email"></label><button class="quiet-button">{{ $t('Search','Cari') }}</button></form>
<p>{{ $people->total() }} {{ $t('interns','intern') }}</p>
@forelse($people as $person)<div class="record-card"><strong>{{ $person->name }}</strong><p>{{ $person->email }} · {{ $person->department }}</p><a class="quiet-button" href="{{ route('workspace.tasks',['assignee'=>$person->id]) }}">{{ $t('View tasks','Lihat tugasan') }}</a></div>@empty<p>{{ $t('No interns found. Invite your first intern below.','Tiada intern ditemui. Jemput intern di bawah.') }}</p>@endforelse
<div>{{ $people->links() }}</div></section>
<section class="glass panel"><h2>{{ $t('Invite intern','Jemput intern') }}</h2><form method="post" action="{{ route('workspace.team.invite') }}">@csrf<div class="form-grid"><label>{{ $t('Full name','Nama penuh') }}<input name="name" required maxlength="255"></label><label>Email<input type="email" name="email" required></label></div><p>{{ $t('The intern will join your department and team.','Intern akan menyertai jabatan dan pasukan anda.') }}</p><button class="primary-button">{{ $t('Send invitation','Hantar jemputan') }}</button></form></section>
@endsection
