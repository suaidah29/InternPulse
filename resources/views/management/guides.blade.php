@extends('management.shell')
@section('title','Guides')
@section('content')
@include('management.notices')
<div class="page-heading"><div><p class="eyebrow">{{ $t('START WITH CONFIDENCE','BERMULA DENGAN YAKIN') }}</p><h1>{{ $t('Intern guides','Panduan intern') }}</h1><p class="muted">{{ $t('Onboarding, do’s and don’ts, and department resources.','Pengenalan, perkara yang perlu dipatuhi dan sumber jabatan.') }}</p></div></div>
@if(auth()->user()->role==='superadmin')<section class="glass section-card"><details><summary>+ {{ $t('Add guide','Tambah panduan') }}</summary><form method="post" enctype="multipart/form-data" action="{{ route('management.guides.store') }}">@csrf
@include('management.guide-form',['guide'=>null])</form></details></section>@endif
<div class="record-list">@forelse($guides as $guide)<section class="glass section-card"><p class="eyebrow">{{ $guide->department?:$t('ALL DEPARTMENTS','SEMUA JABATAN') }}</p><h2>{{ $guide->title }}</h2><p class="read-text muted">{{ $guide->body }}</p>@if($guide->file_path)<a class="template-link" href="{{ route('workspace.files',['type'=>'guide','id'=>$guide->id]) }}">↓ {{ $guide->file_name }}</a>@endif @if(auth()->user()->role==='superadmin')<details><summary>{{ $t('Edit guide','Ubah panduan') }}</summary><form method="post" enctype="multipart/form-data" action="{{ route('management.guides.update',$guide->id) }}">@csrf
@include('management.guide-form',['guide'=>$guide])</form><form class="form-actions" method="post" action="{{ route('management.guides.delete',$guide->id) }}" data-confirm="Delete this guide? / Padam panduan ini?">@csrf<button class="text-button danger">{{ $t('Delete guide','Padam panduan') }}</button></form></details>@endif</section>@empty<section class="glass section-card empty-state">{{ $t('No guides published yet.','Belum ada panduan diterbitkan.') }}</section>@endforelse</div>
@endsection

