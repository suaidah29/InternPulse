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
@if($kind==='signature')<p class="muted">{{ $t('A light background is removed automatically. Use dark ink on plain white paper and check the result after saving.','Latar cerah dibuang secara automatik. Gunakan dakwat gelap pada kertas putih dan semak hasil selepas simpan.') }}</p>@endif
@if($person->{$kind.'_path'})<div><img src="{{ route('profile.image',$kind) }}" alt="{{ $label }}" style="max-width:220px;max-height:100px;border-radius:12px;background:white;padding:8px"></div>@endif</div>@endif
@endforeach</div>
@if($person->role === 'intern')
<h2>{{ $t('Internship details','Maklumat latihan industri') }}</h2>
<div class="form-grid">
<label>{{ $t('University','Universiti') }}<input name="university" maxlength="255" value="{{ old('university',$person->university) }}"></label>
<label>{{ $t('Course','Kursus') }}<input name="course" maxlength="255" value="{{ old('course',$person->course) }}"></label>
<div><strong>{{ $t('Department','Jabatan') }}</strong><p>{{ $person->department ?: '—' }}</p></div>
<div><strong>{{ $t('Supervisor','Penyelia') }}</strong><p>{{ $supervisor ?: '—' }}</p></div>
<div><strong>{{ $t('Start date','Tarikh mula') }}</strong><p>{{ $person->internship_start_date ?: '—' }}</p></div>
<div><strong>{{ $t('End date','Tarikh tamat') }}</strong><p>{{ $person->internship_end_date ?: '—' }}</p></div>
<div><strong>{{ $t('Internship status','Status latihan industri') }}</strong><p>{{ $t(...(match($person->internship_status) { 'upcoming'=>['Upcoming','Akan bermula'], 'ongoing'=>['Ongoing','Sedang berlangsung'], 'completed'=>['Completed','Selesai'], 'withdrawn'=>['Withdrawn','Ditarik balik'], default=>['Not set','Belum ditetapkan'] })) }}</p></div>
</div><p class="muted">{{ $t('Contact management to update your supervisor, department, dates or status.','Hubungi management untuk mengubah penyelia, jabatan, tarikh atau status.') }}</p>
@endif
<button class="primary-button">{{ $t('Save profile','Simpan profil') }}</button></form></section>
@endsection
