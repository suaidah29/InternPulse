@php
$pendingSecret = session('security.pending_two_factor_secret');
$recoveryCodes = session('two_factor_recovery_codes');
@endphp
<section class="glass panel" id="security">
<h2>{{ $t('Sign-in security','Keselamatan log masuk') }}</h2>

<h3>{{ $t('Password','Kata laluan') }}</h3>
<p class="muted">{{ $t('Every password in InternPulse follows the same rules. Changing it here signs out your other devices.','Setiap kata laluan dalam InternPulse mengikut peraturan yang sama. Menukarnya di sini akan melog keluar peranti lain anda.') }}</p>
<ul class="policy-list">@foreach(\App\Services\PasswordPolicy::summary() as [$english, $malay])<li>{{ $t($english, $malay) }}</li>@endforeach</ul>
<form method="post" action="{{ route('profile.password') }}">@csrf
<div class="form-grid">
<label>{{ $t('Current password','Kata laluan semasa') }}<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>{{ $t('New password','Kata laluan baharu') }}<input type="password" name="password" autocomplete="new-password" minlength="{{ \App\Services\PasswordPolicy::MIN_LENGTH }}" maxlength="{{ \App\Services\PasswordPolicy::MAX_LENGTH }}" required></label>
<label>{{ $t('Confirm new password','Sahkan kata laluan baharu') }}<input type="password" name="password_confirmation" autocomplete="new-password" minlength="{{ \App\Services\PasswordPolicy::MIN_LENGTH }}" required></label>
</div>
<button class="quiet-button">{{ $t('Change password','Tukar kata laluan') }}</button></form>

<hr class="security-divider">

<h3>{{ $t('Two-factor authentication','Pengesahan dua faktor') }}</h3>
@if($person->hasTwoFactor())
<p><span class="badge ok">{{ $t('On','Aktif') }}</span> <span class="muted">{{ $t('Enabled','Diaktifkan') }} {{ $person->two_factor_confirmed_at?->format('j M Y') }} · {{ count($person->recoveryCodes()) }} {{ $t('recovery codes left','kod pemulihan tinggal') }}</span></p>
<p class="muted">{{ $t('Signing in needs your password and a code from your authenticator app.','Log masuk memerlukan kata laluan anda dan kod daripada aplikasi pengesah.') }}</p>
<div class="security-actions">
<form method="post" action="{{ route('profile.two-factor.recovery') }}">@csrf<label class="inline-field">{{ $t('Confirm password','Sahkan kata laluan') }}<input type="password" name="current_password" autocomplete="current-password" required></label><button class="quiet-button">{{ $t('New recovery codes','Kod pemulihan baharu') }}</button></form>
<form method="post" action="{{ route('profile.two-factor.disable') }}">@csrf<label class="inline-field">{{ $t('Confirm password','Sahkan kata laluan') }}<input type="password" name="current_password" autocomplete="current-password" required></label><button class="danger-button">{{ $t('Turn off two-factor','Matikan dua faktor') }}</button></form>
</div>
@elseif($pendingSecret)
<p class="muted">{{ $t('Add this key to your authenticator app (Google Authenticator, 1Password, Authy, Microsoft Authenticator), then enter the 6-digit code it shows. QR scanning is not available yet, so enter the key manually.','Tambah kunci ini dalam aplikasi pengesah anda (Google Authenticator, 1Password, Authy, Microsoft Authenticator), kemudian masukkan kod 6 digit yang dipaparkan. Imbasan QR belum tersedia, jadi masukkan kunci secara manual.') }}</p>
<p class="secret-key" aria-label="Setup key / Kunci persediaan">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</p>
<p class="muted"><small>{{ $t('Account','Akaun') }}: {{ $person->email }} · {{ $t('Type','Jenis') }}: Time-based (TOTP), 30 {{ $t('seconds','saat') }}, 6 {{ $t('digits','digit') }}</small></p>
<form method="post" action="{{ route('profile.two-factor.enable') }}">@csrf
<label>{{ $t('Code from your app','Kod daripada aplikasi anda') }}<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required></label>
<button class="primary-button">{{ $t('Turn on two-factor','Aktifkan dua faktor') }}</button></form>
<form method="post" action="{{ route('profile.two-factor.cancel') }}">@csrf<button class="quiet-button">{{ $t('Cancel setup','Batal persediaan') }}</button></form>
@else
<p class="muted">{{ $t('Two-factor authentication is off. Turning it on adds a second step at sign-in, so a stolen password is not enough on its own.','Pengesahan dua faktor dimatikan. Mengaktifkannya menambah langkah kedua semasa log masuk, jadi kata laluan yang dicuri tidak memadai.') }}</p>
<form method="post" action="{{ route('profile.two-factor.start') }}">@csrf<label class="inline-field">{{ $t('Confirm password','Sahkan kata laluan') }}<input type="password" name="current_password" autocomplete="current-password" required></label><button class="primary-button">{{ $t('Set up two-factor','Sediakan dua faktor') }}</button></form>
@endif

@if($recoveryCodes)
<div class="recovery-block" role="group" aria-labelledby="recovery-heading">
<h4 id="recovery-heading">{{ $t('Save these recovery codes now','Simpan kod pemulihan ini sekarang') }}</h4>
<p class="muted">{{ $t('Each code signs you in once if you lose your phone. They are shown only this once; store them somewhere safe and offline.','Setiap kod boleh digunakan sekali untuk log masuk jika telefon hilang. Kod dipaparkan sekali sahaja; simpan di tempat selamat dan luar talian.') }}</p>
<ul class="recovery-codes">@foreach($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
</div>
@endif
</section>
