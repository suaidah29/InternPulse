@extends('layout')
@section('content')
<p class="muted">{{ ['superadmin'=>'SUPERADMIN','supervisor'=>'PENYELIA','intern'=>'INTERN'][auth()->user()->role] }}</p><h2>Hai, {{ auth()->user()->name }}</h2><p class="muted">Anda berjaya log masuk ke InternPulse.</p><p>Modul tugasan dan logbook akan dibangunkan seterusnya.</p><form method="post" action="{{ route('logout') }}">@csrf<button>Log keluar</button></form>
@endsection

