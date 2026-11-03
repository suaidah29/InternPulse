@extends('management.shell')
@section('title','My profile')
@section('content')
<section class="glass panel"><h1>{{ $t('My profile','Profil saya') }}</h1>
@include('management.notices')
<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf
<div class="form-grid"><label>{{ $t('Full name','Nama penuh') }}<input name="name" value="{{ old('name',$person->name) }}" required maxlength="255"></label><label>Email<input type="email" name="email" value="{{ old('email',$person->email) }}" required></label>
<label>{{ $t('Current password (required to change email)','Kata laluan semasa (wajib untuk tukar e-mel)') }}<input type="password" name="current_password" autocomplete="current-password"></label>
@foreach(['photo'=>'Profile picture / Gambar profil','signature'=>'Signature / Tandatangan'] as $kind=>$label)
@if($kind==='photo'||$person->role!=='intern')<div><label>{{ $label }}<input type="file" name="{{ $kind }}" accept="image/png,image/jpeg,image/webp"></label><small>PNG, JPG, WebP · Max 2 MB</small>
@if($person->{$kind.'_path'})<div><img src="{{ route('profile.image',$kind) }}" alt="{{ $label }}" style="max-width:220px;max-height:100px;border-radius:12px;background:white;padding:8px"></div>@endif</div>@endif
@endforeach</div><button class="primary-button">{{ $t('Save profile','Simpan profil') }}</button></form></section>
@endsection
