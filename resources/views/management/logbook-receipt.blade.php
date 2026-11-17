@extends('management.shell')
@section('title','Logbook verification')
@section('content')
<section class="glass section-card"><h1>{{ $t('Logbook verification receipt','Slip pengesahan logbook') }} #{{ $document->id }}</h1><p>Intern: {{ $intern }}</p><p>{{ $t('Document','Dokumen') }}: {{ $document->title }} ({{ $document->original_name }})</p><p>Status: {{ $t(...($document->status === 'verified' ? ['Verified','Disahkan'] : ['Checked','Disemak'])) }}</p><p>SV: {{ $document->reviewer_name }}</p><p>{{ \Carbon\Carbon::parse($document->reviewed_at)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }} MYT</p><img src="{{ route('workspace.logbook.file',['id'=>$document->id,'kind'=>'signature']) }}" alt="Tandatangan SV" style="max-width:240px;max-height:120px;background:white"><p style="overflow-wrap:anywhere">SHA-256: {{ $document->sha256 }}</p><button onclick="window.print()">{{ $t('Print / save as PDF','Cetak / simpan PDF') }}</button></section>
@endsection
