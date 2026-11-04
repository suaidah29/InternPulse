@extends('management.shell')
@section('title','Password recovery')
@section('content')
<main class="accept-main"><section class="glass accept-card"><a href="{{ route('login') }}">← {{ $t('Sign in','Log masuk') }}</a><h1>{{ $reset?$t('Set a new password','Tetapkan kata laluan baharu'):$t('Forgot password?','Lupa kata laluan?') }}</h1><p class="muted">{{ $reset?$t('Save your new password, then sign in again.','Simpan kata laluan baharu, kemudian log masuk semula.'):$t('Enter your account email to receive a reset link.','Masukkan e-mel akaun untuk menerima pautan pemulihan.') }}</p>
@include('management.notices')
<form method="post" action="{{ route($reset?'password.update':'password.email') }}">@csrf
<label>Email<input type="email" name="email" required value="{{ old('email',$email??'') }}" autocomplete="email"></label>
@if($reset)<input type="hidden" name="token" value="{{ $token }}"><label>{{ $t('New password','Kata laluan baharu') }}<input id="new-password" type="password" name="password" autocomplete="new-password" minlength="12" required></label><label>{{ $t('Confirm password','Sahkan kata laluan') }}<input id="confirm-password" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>@endif
<button class="primary-button full-width">{{ $reset?$t('Save password','Simpan kata laluan'):$t('Send reset link','Hantar pautan pemulihan') }}</button></form></section></main>
@endsection
