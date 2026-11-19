@php
    try {
        $signedIn = auth()->check();
        $home = $signedIn ? route('dashboard') : route('home');
    } catch (\Throwable $e) {
        $signedIn = false;
        $home = '/';
    }
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }} · InternPulse</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('landing/styles.css') }}?v=9">
<link rel="stylesheet" href="{{ asset('landing/brand.css') }}?v=9">
<link rel="stylesheet" href="{{ asset('management.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('theme.css') }}?v=12">
<style>
.error-shell{position:relative;min-height:100vh;display:grid;place-items:center;padding:32px 20px}
.error-card{position:relative;z-index:2;max-width:560px;width:100%;padding:40px clamp(24px,5vw,48px);border-radius:22px;background:rgba(255,255,255,.94);box-shadow:0 24px 60px rgba(38,37,52,.16);text-align:left}
.error-card .logo{display:inline-block;margin-bottom:22px}
.error-card .logo img{height:34px;width:auto}
.error-code{font:800 clamp(46px,9vw,72px)/1 Manrope,Inter,sans-serif;letter-spacing:-.05em;color:#cb1736;margin:0 0 8px}
.error-card h1{font:700 clamp(20px,2.4vw,26px)/1.3 Manrope,Inter,sans-serif;margin:0 0 10px;color:#262534;font-size:22px}
.error-card p{margin:0 0 22px;color:#656474;font-size:15px;line-height:1.7}
.error-actions{display:flex;flex-wrap:wrap;gap:12px}
.error-actions a{display:inline-flex;align-items:center;min-height:44px;padding:0 20px;border-radius:999px;text-decoration:none;font-weight:600;font-size:14px}
.error-actions .primary{background:#cb1736;color:#fff}
.error-actions .secondary{border:1px solid rgba(38,37,52,.2);color:#262534}
@media (prefers-color-scheme:dark){.error-card{background:rgba(43,42,58,.97)}.error-card h1{color:#f6f4fb}.error-card p{color:#c3bdce}.error-actions .secondary{border-color:rgba(255,255,255,.24);color:#f6f4fb}}
</style>
</head>
<body class="management-page">
<div class="bg" aria-hidden="true"></div>
<main class="error-shell">
    <div class="error-card">
        <a class="logo" href="{{ $home }}" aria-label="InternPulse home"><img src="{{ asset('landing/assets/logo-transparent.png') }}" alt="InternPulse by Appvolusi"></a>
        <p class="error-code">{{ $code }}</p>
        <h1>{{ $heading }}</h1>
        <p>{{ $message }}</p>
        <div class="error-actions">
            <a class="primary" href="{{ $home }}">{{ $signedIn ? 'Back to dashboard' : 'Back to home' }} <span aria-hidden="true">→</span></a>
            @if($signedIn)
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="secondary" style="background:none;border:1px solid rgba(38,37,52,.2);cursor:pointer;font:600 14px/1 inherit;min-height:44px;padding:0 20px;border-radius:999px">Sign out</button></form>
            @else
            <a class="secondary" href="{{ route('login') }}">Sign in</a>
            @endif
        </div>
    </div>
</main>
</body>
</html>
