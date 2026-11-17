@extends('management.shell')
@section('title','File preview')
@section('content')
<section class="glass section-card"><h1>{{ $t('File preview','Paparan fail') }}</h1><p>{{ $name }}</p>
@if($paragraphs === null)<p>{{ $t('This file cannot be previewed here. Download it to open in a compatible application.','Fail ini tidak dapat dipaparkan di sini. Muat turun untuk membukanya dengan aplikasi yang sesuai.') }}</p>
@else<p class="muted">{{ $t('Text-only preview. Formatting, images and some content may differ from the original.','Paparan teks sahaja. Format, gambar dan sebahagian kandungan mungkin berbeza daripada fail asal.') }}</p><div class="record-card" style="white-space:pre-wrap;overflow-wrap:anywhere">@foreach($paragraphs as $paragraph)<p>{{ $paragraph ?: ' ' }}</p>@endforeach</div>@endif
<a class="quiet-button" href="{{ request()->fullUrlWithQuery(['download'=>1]) }}">{{ $t('Download original','Muat turun fail asal') }}</a></section>
@endsection
