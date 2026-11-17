<details class="account-menu">
    <summary aria-label="Account / Akaun">
        <span class="account-avatar">@if(auth()->user()->photo_path)<img src="{{ route('profile.image','photo') }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}@endif</span>
        <span class="account-name">{{ auth()->user()->name }}</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="account-dropdown">
        <div class="account-identity"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small><small>{{ auth()->user()->role==='superadmin' ? $t('Management','Pengurusan') : (auth()->user()->role==='supervisor' ? $t('Supervisor','Penyelia') : 'Intern') }}</small></div>
        <a href="{{ route('profile.edit') }}">{{ $t('My profile','Profil saya') }} <span aria-hidden="true">↗</span></a>
        <div class="account-settings">
            <p>{{ $t('Settings','Tetapan') }}</p>
            <span class="account-settings-label">{{ $t('Appearance','Paparan') }}</span>
            <div class="theme-switch" role="group" aria-label="Appearance / Paparan"><button type="button" data-theme-choice="light">{{ $t('Light','Cerah') }}</button><button type="button" data-theme-choice="dark">{{ $t('Dark','Gelap') }}</button></div>
            <span class="account-settings-label">{{ $t('Language','Bahasa') }}</span>
            <div class="language-switch" role="group" aria-label="Language / Bahasa"><button type="button" data-lang="en" aria-pressed="true">ENG</button><button type="button" data-lang="ms" aria-pressed="false">BM</button></div>
        </div>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">{{ $t('Sign out','Log keluar') }}</button></form>
    </div>
</details>
