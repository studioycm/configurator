@props(['heading', 'subtitle' => null])

<header {{ $attributes->class(['catalog-page-header']) }}>
    @isset($leading)
        <div class="shrink-0 lg:hidden">{{ $leading }}</div>
    @endisset
    <div class="min-w-0 flex-1">
        @isset($breadcrumbs)
            {{ $breadcrumbs }}
        @endisset
        <h1 class="catalog-page-heading">{{ $heading }}</h1>
        @if (filled($subtitle))
            <p class="catalog-page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="catalog-page-actions">{{ $actions }}</div>
    @endisset
</header>
