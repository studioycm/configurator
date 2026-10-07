<div class="space-y-2 text-sm">
    @foreach ($impacts as $impact)
        <p>{{ $impact['name'] }}
            @if ($impact['groups'] || $impact['products'])
                · {{ $impact['groups'] }} assigned Groups · {{ $impact['products'] }} Products
            @endif
            @if ($impact['configurators'])
                · {{ $impact['configurators'] }} affected Configurators
            @endif
        </p>
    @endforeach
</div>
