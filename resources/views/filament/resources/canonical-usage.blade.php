<div class="space-y-6">
    <section>
        <h3 class="font-semibold">Used by {{ $usage['configurator_count'] }} Configurators</h3>
        <ul class="mt-2 space-y-2">
            @forelse ($usage['configurators'] as $configurator)
                <li>
                    <a class="underline" href="{{ \App\Filament\Resources\Configurators\ConfiguratorResource::getUrl('edit', ['record' => $configurator['id']]) }}">{{ $configurator['name'] }}</a>
                    <span> · {{ $configurator['attributes_count'] }} Attributes · {{ $configurator['rules_count'] }} rules</span>
                    <span> · Groups: {{ implode(', ', array_column($configurator['groups'], 'name')) ?: 'Unassigned' }}</span>
                </li>
            @empty <li>No Configurator inclusions.</li> @endforelse
        </ul>
    </section>
    <section>
        <h3 class="font-semibold">Stored defaults</h3>
        <ul class="mt-2 space-y-1">@forelse ($usage['defaults'] as $default)<li>{{ $default['configurator'] }} · {{ $default['attribute'] }} · {{ $default['code'] }}</li>@empty<li>No stored defaults use these Options.</li>@endforelse</ul>
    </section>
    <section>
        <h3 class="font-semibold">Rules involving these inclusions</h3>
        <ul class="mt-2 space-y-1">@forelse ($usage['rules'] as $rule)<li>{{ $rule['configurator'] }} · {{ $rule['label'] }} · {{ $rule['kind'] }}</li>@empty<li>No related rules.</li>@endforelse</ul>
    </section>
    <section>
        <h3 class="font-semibold">Canonical Options</h3>
        <ul class="mt-2 space-y-1">@forelse ($usage['options'] as $option)<li>{{ $option['code'] }} · {{ $option['attribute']['label'] }} · {{ $option['value']['label'] }}</li>@empty<li>No Options use this definition.</li>@endforelse</ul>
    </section>
    <p class="text-sm">Each list shows up to 100 references. Repair local defaults and rules before removing shared definitions.</p>
</div>
