@php($unreadNotifications = \Illuminate\Support\Facades\DB::table('workspace_notifications')->where('user_id', auth()->id())->whereNull('read_at')->count())
<a class="header-notifications" href="{{ route('workspace.notifications') }}" aria-label="Notifications / Notifikasi ({{ $unreadNotifications }})" title="Notifications / Notifikasi" @if(request()->routeIs('workspace.notifications')) aria-current="page" @endif>
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
    @if($unreadNotifications > 0)
        <span class="notification-badge" aria-hidden="true">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
    @endif
</a>
