<section class="my-10 space-y-6 rounded-xl border border-zinc-300 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="configuration-{{ $this->getId() }}" wire:loading.attr="aria-busy">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="configuration-{{ $this->getId() }}" class="text-xl font-semibold">{{ __('Configuration') }}</h2>
        <button type="button" wire:click="refreshDefinition" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('Refresh choices') }}</button>
    </div>
    @if ($result->definition)
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['territory', 'application'] as $dimension)
                <label class="grid gap-2 text-sm font-medium" wire:key="context-{{ $dimension }}">
                    {{ __(ucfirst($dimension)) }}
                    <select wire:change="changeContext('{{ $dimension }}', $event.target.value)" class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2">
                        <option value="All" @selected($result->context[$dimension] === 'All')>{{ __('All') }}</option>
                        @foreach ($result->definition->contextSchema[$dimension] as $choice)
                            <option value="{{ $choice['value'] }}" @selected($result->context[$dimension] === $choice['value'])>{{ $choice['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
        @foreach (collect($result->definition->attributes)->sortBy(['displayOrder', 'id']) as $attribute)
            @php($state = $result->attributes[$attribute->id])
            @if ($state['applicable'])
                <fieldset class="space-y-3" wire:key="attribute-{{ $attribute->id }}">
                    <legend class="font-semibold">{{ $state['label'] }}</legend>
                    @if ($state['hint'])<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $state['hint'] }}</p>@endif
                    @if ($state['display_value'] !== null)<p>{{ $state['display_value'] }}</p>@endif
                    @if ($attribute->inputType === 'select')
                        <select aria-label="{{ $state['label'] }}" wire:change="selectOption('{{ $attribute->id }}', $event.target.value)" class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 sm:max-w-md">
                            @if (! isset($result->selections[$attribute->id]))<option value="" selected disabled>{{ __('No available choice') }}</option>@endif
                            @foreach ($attribute->options as $option)
                                @if (! in_array($option->id, $state['hidden'], true))
                                    <option value="{{ $option->id }}" @selected(($result->selections[$attribute->id] ?? null) === $option->id) @disabled(! in_array($option->id, $state['legal'], true))>{{ $state['options'][$option->id]['label'] }} · {{ $option->code }}</option>
                                @endif
                            @endforeach
                        </select>
                        @if (isset($result->selections[$attribute->id]))
                            @php($selected = $state['options'][$result->selections[$attribute->id]])
                            @if ($selected['display_value'] !== $selected['label'])<p class="text-sm">{{ $selected['display_value'] }}</p>@endif
                            @if ($selected['hint'])<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $selected['hint'] }}</p>@endif
                        @endif
                    @else
                        <div class="flex flex-wrap gap-3">
                            @foreach ($attribute->options as $option)
                                @if (! in_array($option->id, $state['hidden'], true))
                                    @php($presentation = $state['options'][$option->id])
                                    <label @class(['flex max-w-full cursor-pointer items-start gap-3 rounded-lg border px-4 py-3', 'border-zinc-900 bg-zinc-100 dark:border-white dark:bg-zinc-800' => ($result->selections[$attribute->id] ?? null) === $option->id, 'border-zinc-300' => ($result->selections[$attribute->id] ?? null) !== $option->id, 'opacity-50' => ! in_array($option->id, $state['legal'], true)]) wire:key="option-{{ $option->id }}">
                                        <input class="mt-1" type="radio" name="configuration-{{ $this->getId() }}-{{ $attribute->id }}" value="{{ $option->id }}" wire:click="selectOption('{{ $attribute->id }}', '{{ $option->id }}')" @checked(($result->selections[$attribute->id] ?? null) === $option->id) @disabled(! in_array($option->id, $state['legal'], true)) />
                                        <span><span class="block text-sm font-medium">{{ $presentation['label'] }} <span class="font-mono">{{ $option->code }}</span></span>
                                            @if ($presentation['display_value'] !== $presentation['label'])<span class="block text-sm">{{ $presentation['display_value'] }}</span>@endif
                                            @if ($presentation['hint'])<span class="block text-sm text-zinc-600 dark:text-zinc-300">{{ $presentation['hint'] }}</span>@endif
                                        </span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </fieldset>
            @endif
        @endforeach
    @else
        <p>{{ __('Configuration is not available for this product.') }}</p>
    @endif
    <x-catalog.configuration-result :result="$result" />
    <p wire:loading.delay class="text-sm text-zinc-500" role="status">{{ __('Updating choices…') }}</p>
</section>
