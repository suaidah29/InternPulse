@props([
    'eyebrow' => null,
    'heading',
    'lede' => null,
])
{{-- One frosted header band per page: eyebrow chip, page title, one line of
     context, and an optional actions slot. Pass $t(...) values straight in;
     HtmlString passes through {{ }} untouched. --}}
<section class="glass page-header">
    <div class="page-header-copy">
        @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1>{{ $heading }}</h1>
        @if($lede)<p class="page-header-lede">{{ $lede }}</p>@endif
    </div>
    @if(! $slot->isEmpty())
        <div class="page-header-actions">{{ $slot }}</div>
    @endif
</section>
