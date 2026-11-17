<aside class="workspace-sidebar glass" id="workspace-sidebar"><button type="button" class="sidebar-fold" aria-expanded="true" aria-controls="workspace-sidebar" aria-label="Collapse sidebar / Lipat sidebar"><span aria-hidden="true">‹</span></button><details class="sidebar-disclosure" open><summary>{{ $t('Workspace menu','Menu ruang kerja') }} <span aria-hidden="true">☰</span></summary><div class="sidebar-caption">{{ $t('WORKSPACE','RUANG KERJA') }}</div><nav class="module-nav" aria-label="Workspace navigation">
@php
    $active = fn (string $route): string => request()->routeIs($route) ? 'active' : '';
    $current = fn (string $route): string => request()->routeIs($route) ? 'aria-current="page"' : '';
@endphp
@if(auth()->user()->role==='superadmin')
<a href="{{ route('management.dashboard') }}" class="{{ $active('management.dashboard') }}" {!! $current('management.dashboard') !!}>{{ $t('Overview','Ringkasan') }}</a>
<a href="{{ route('management.people') }}" class="{{ $active('management.people') }}" {!! $current('management.people') !!}>{{ $t('People','Pengguna') }}</a>
<a href="{{ route('management.departments') }}" class="{{ $active('management.departments') }}" {!! $current('management.departments') !!}>{{ $t('Departments','Jabatan') }}</a>
@endif
@if(auth()->user()->role!=='superadmin')<a href="{{ route('workspace.overview') }}" class="{{ $active('workspace.overview') }}" {!! $current('workspace.overview') !!}>{{ $t('Dashboard','Papan pemuka') }}</a>@endif
<a href="{{ route('workspace.tasks') }}" class="{{ $active('workspace.tasks') }}" {!! $current('workspace.tasks') !!}>{{ (auth()->user()->role==='intern'?$t('My tasks','Tugasan saya'):$t('Team tasks','Tugasan pasukan')) }}</a>
@if(auth()->user()->role!=='intern')<a href="{{ route('workspace.reviews') }}" class="{{ $active('workspace.reviews') }}" {!! $current('workspace.reviews') !!}>{{ $t('Approvals','Kelulusan') }}</a>@endif
<a href="{{ route('workspace.records') }}" class="{{ $active('workspace.records') }}" {!! $current('workspace.records') !!}>{{ (auth()->user()->role==='intern'?$t('My attendance','Kehadiran saya'):$t('Team attendance','Kehadiran pasukan')) }}</a>
<a href="{{ route('workspace.guides') }}" class="{{ $active('workspace.guides') }}" {!! $current('workspace.guides') !!}>{{ $t('Guides','Panduan') }}</a>
@if(auth()->user()->role==='superadmin')
<a href="{{ route('management.reports') }}" class="{{ $active('management.reports') }}" {!! $current('management.reports') !!}>{{ $t('Reports','Laporan') }}</a>
<a href="{{ route('management.activity') }}" class="{{ $active('management.activity') }}" {!! $current('management.activity') !!}>{{ $t('Audit trail','Jejak audit') }}</a>
@endif
@if(auth()->user()->role==='supervisor')<a href="{{ route('workspace.team') }}" class="{{ $active('workspace.team') }}" {!! $current('workspace.team') !!}>{{ $t('My team','Pasukan saya') }}</a>@endif
@if(auth()->user()->role!=='superadmin')
<a href="{{ route('workspace.logbook') }}" class="{{ $active('workspace.logbook') }}" {!! $current('workspace.logbook') !!}>{{ $t('Logbook','Buku log') }}</a>
@endif
@if(auth()->user()->role === 'intern')
<a href="{{ route('workspace.evaluations.show',auth()->id()) }}">{{ $t('My evaluations','Penilaian saya') }}</a>
@endif
</nav>
@if(auth()->user()->role==='superadmin')<div class="sidebar-shortcuts"><p>{{ $t('TEAM MANAGEMENT','PENGURUSAN PASUKAN') }}</p><a href="{{ route('management.dashboard') }}#invitations">{{ $t('Invitations','Jemputan') }} ↗</a><a href="{{ route('management.dashboard') }}#import">{{ $t('Bulk import','Import pukal') }} ↗</a></div>@endif
</details></aside>
