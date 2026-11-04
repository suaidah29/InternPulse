@auth
<form class="header-search" method="get" action="{{ route('workspace.tasks') }}" role="search"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><input type="search" name="q" maxlength="180" value="{{ request()->routeIs('workspace.tasks') ? request('q') : '' }}" placeholder="Search tasks / Cari tugasan" aria-label="Search tasks / Cari tugasan"><button type="submit" aria-label="Search / Cari">↵</button></form>
@endauth
