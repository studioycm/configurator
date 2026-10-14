<div class="space-y-6">
    <section>
        <h3 class="font-semibold">{{ __('admin.usage.configurators', ['count' => $usage['configurator_count']]) }}</h3>
        <ul class="mt-2 space-y-2">
            @forelse ($usage['configurators'] as $configurator)
                <li>
                    <a class="underline" href="{{ \App\Filament\Resources\Configurators\ConfiguratorResource::getUrl('edit', ['record' => $configurator['id']]) }}">{{ $configurator['name'] }}</a>
                    <span>{{ __('admin.usage.definition_counts', ['attributes' => $configurator['attributes_count'], 'rules' => $configurator['rules_count']]) }}</span>
                    <span>{{ __('admin.usage.group_names', ['groups' => implode(', ', array_column($configurator['groups'], 'name')) ?: __('admin.usage.unassigned')]) }}</span>
                </li>
            @empty <li>{{ __('admin.workspace.copy.no_configurator_inclusions') }}</li> @endforelse
        </ul>
    </section>
    <section>
        <h3 class="font-semibold">{{ __('admin.usage.defaults', ['count' => $usage['counts']['defaults']]) }}</h3>
        <ul class="mt-2 space-y-1">@forelse ($usage['defaults'] as $default)<li>{{ $default['configurator'] }} · {{ $default['attribute'] }} · {{ $default['code'] }}</li>@empty<li>{{ __('admin.workspace.copy.no_stored_defaults_use_these_values') }}</li>@endforelse</ul>
    </section>
    <section>
        <h3 class="font-semibold">{{ __('admin.usage.rules', ['count' => $usage['counts']['rules']]) }}</h3>
        <ul class="mt-2 space-y-1">@forelse ($usage['rules'] as $rule)<li>{{ $rule['configurator'] }} · {{ $rule['label'] }} · {{ $rule['kind'] }}</li>@empty<li>{{ __('admin.workspace.copy.no_related_rules') }}</li>@endforelse</ul>
    </section>
    <section>
        <h3 class="font-semibold">{{ __('admin.usage.options', ['count' => $usage['counts']['options']]) }}</h3>
        <ul class="mt-2 space-y-1">@forelse ($usage['options'] as $option)<li>{{ $option['code'] }} · {{ $option['attribute']['label'] }} · {{ $option['value']['label'] }}</li>@empty<li>{{ __('admin.workspace.copy.no_attribute_values_use_this_definition') }}</li>@endforelse</ul>
    </section>
    <p class="text-sm">{{ __('admin.usage.reference_limit_help') }}</p>
</div>
