@extends('management.shell')
@section('title','My profile')
@section('content')
<x-page-header :eyebrow="$t('YOUR ACCOUNT','AKAUN ANDA')" :heading="$t('My profile','Profil saya')" :lede="$t('Your details appear on tasks, logbook entries and evaluations, so keep them current.','Maklumat anda muncul pada tugasan, logbook dan penilaian, jadi pastikan ia terkini.')" />
<section class="glass panel">
@include('management.notices')
<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf
<div class="form-grid"><label>{{ $t('Full name','Nama penuh') }}<input name="name" value="{{ old('name',$person->name) }}" required maxlength="255"></label><div><strong>Email</strong><p>{{ $person->email }}</p></div>
@foreach(['photo'=>'Profile picture / Gambar profil','signature'=>'Signature / Tandatangan'] as $kind=>$label)
@if($kind==='photo'||$person->role!=='intern')<div><label>{{ $label }}<input type="file" name="{{ $kind }}" accept="image/png,image/jpeg,image/webp"></label><small>PNG, JPG, WebP · Max 2 MB</small>
@if($kind==='signature')<p class="muted">{{ $t('A light background is removed automatically. Use dark ink on plain white paper and check the result after saving.','Latar cerah dibuang secara automatik. Gunakan dakwat gelap pada kertas putih dan semak hasil selepas simpan.') }}</p>@endif
@if($person->{$kind.'_path'})<div><img src="{{ route('profile.image',$kind) }}" alt="{{ $label }}" style="max-width:220px;max-height:100px;border-radius:12px;background:white;padding:8px"></div>@endif</div>@endif
@endforeach</div>
{{-- The password box lives in the Sign-in security card; this one only appears when the
     email is actually being changed, so the page does not ask for the password twice. --}}
<details class="email-change" @if(old('email') !== null && old('email') !== $person->email) open @endif>
<summary>{{ $t('Change email address','Tukar alamat e-mel') }}</summary>
<div class="form-grid">
<label>Email<input type="email" name="email" value="{{ old('email',$person->email) }}" maxlength="255"></label>
<label>{{ $t('Current password','Kata laluan semasa') }}<input type="password" name="current_password" autocomplete="current-password"></label>
</div>
<p class="muted">{{ $t('Your password is only needed when the email address changes.','Kata laluan hanya diperlukan apabila alamat e-mel ditukar.') }}</p>
</details>
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
<section class="glass panel" id="reminders">
<h2>{{ $t('Reminder emails','E-mel peringatan') }}</h2>
<p class="muted">{{ $t('Reminders always appear in the bell. Choose whether they also reach your email — in-app notices keep arriving either way.','Peringatan sentiasa muncul dalam loceng. Pilih sama ada ia turut dihantar ke e-mel anda — notifikasi dalam aplikasi tetap diterima.') }}</p>
<form method="post" action="{{ route('profile.reminders') }}">@csrf
<div class="form-grid">
@if($person->role === 'intern')
<label style="display:flex;gap:10px;align-items:flex-start"><input type="checkbox" name="email_logbook_reminders" value="1" @checked($person->email_logbook_reminders) style="width:auto;margin-top:4px"><span>{{ $t('Logbook reminder — weekdays 5:30pm','Peringatan buku log — hari bekerja 5:30 petang') }}<small class="muted"> {{ $t('Sent only on days you have not logged anything.','Dihantar hanya pada hari anda belum mencatat apa-apa.') }}</small></span></label>
@endif
<label style="display:flex;gap:10px;align-items:flex-start"><input type="checkbox" name="email_task_reminders" value="1" @checked($person->email_task_reminders) style="width:auto;margin-top:4px"><span>{{ $person->role === 'intern' ? $t('Overdue task reminder — weekdays 9am','Peringatan tugasan lewat — hari bekerja 9 pagi') : $t('Daily overdue digest — weekdays 9am','Ringkasan tugasan lewat — hari bekerja 9 pagi') }}<small class="muted"> {{ $person->role === 'intern' ? $t('Sent when one of your tasks is past its due date.','Dihantar apabila tugasan anda melepasi tarikh akhir.') : $t('One email listing the overdue tasks in your team.','Satu e-mel menyenaraikan tugasan lewat dalam pasukan anda.') }}</small></span></label>
</div>
<button class="primary-button">{{ $t('Save reminder settings','Simpan tetapan peringatan') }}</button>
</form></section>
@include('management.profile-security')
@endsection
