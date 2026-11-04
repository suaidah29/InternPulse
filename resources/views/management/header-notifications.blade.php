@php
$notificationQuery = \Illuminate\Support\Facades\DB::table('workspace_notifications')->where('user_id', auth()->id());
$unreadNotifications = (clone $notificationQuery)->whereNull('read_at')->count();
$recentNotifications = $notificationQuery->orderByDesc('id')->limit(5)->get();
@endphp
<details class="notification-menu"><summary class="header-notifications" aria-label="Notifications / Notifikasi ({{ $unreadNotifications }})" aria-expanded="false" aria-controls="notification-panel"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
@if($unreadNotifications > 0)<span class="notification-badge" aria-hidden="true">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif
</summary><div class="notification-panel" id="notification-panel"><div class="notification-panel-heading"><strong>{{ $t('Notifications','Notifikasi') }}</strong><span>{{ $unreadNotifications }} {{ $t('unread','belum dibaca') }}</span></div>
@forelse($recentNotifications as $notification)
<a class="notification-item {{ !$notification->read_at?'unread':'' }}" href="{{ $notification->task_id ? route('workspace.tasks.show',$notification->task_id) : route('workspace.notifications') }}"><span>{{ $notification->message }}</span><small>{{ \Carbon\Carbon::parse($notification->created_at)->timezone('Asia/Kuala_Lumpur')->format('d M, H:i') }}</small></a>
@empty<p class="notification-empty">{{ $t('You’re all caught up. New updates will appear here.','Tiada kemas kini. Notifikasi baharu akan dipaparkan di sini.') }}</p>
@endforelse
<a class="notification-all" href="{{ route('workspace.notifications') }}">{{ $t('View all notifications','Lihat semua notifikasi') }} →</a></div></details>
