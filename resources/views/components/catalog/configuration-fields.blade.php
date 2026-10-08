@props(['result'])
@foreach (collect($result->definition->attributes)->sortBy(['displayOrder', 'id']) as $attribute)
    @php($state = $result->attributes[$attribute->id])
    @if ($state['applicable'])
        <div class="grid min-w-0 items-start gap-x-3 gap-y-1 border-b border-slate-200 py-1.5 last:border-0 sm:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] dark:border-zinc-700" wire:key="attribute-{{ $attribute->id }}">
            <div class="min-w-0 space-y-1 sm:pt-0.5">
                <h3 id="attribute-label-{{ $this->getId() }}-{{ $attribute->id }}" class="font-semibold">{{ $state['label'] }}</h3>
                @if ($state['hint'])<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $state['hint'] }}</p>@endif
                @if ($state['display_value'] !== null)<p>{{ $state['display_value'] }}</p>@endif
            </div>
            <fieldset aria-labelledby="attribute-label-{{ $this->getId() }}-{{ $attribute->id }}" class="min-w-0 space-y-1.5">
                @if ($attribute->inputType === 'select')
                    <select aria-label="{{ $state['label'] }}" wire:change="selectOption('{{ $attribute->id }}', $event.target.value)" class="w-full border-0 border-b border-zinc-300 bg-transparent px-2 py-1 focus-visible:outline-2 focus-visible:outline-blue-600 sm:max-w-md dark:border-zinc-600">
                        @if (! isset($result->selections[$attribute->id]))<option value="" selected disabled>{{ __('No available choice') }}</option>@endif
                        @foreach ($attribute->options as $option)
                            @if (! in_array($option->id, $state['hidden'], true))
                                <option value="{{ $option->id }}" @selected(($result->selections[$attribute->id] ?? null) === $option->id) @disabled(! in_array($option->id, $state['legal'], true))>{{ $state['options'][$option->id]['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    @if (isset($result->selections[$attribute->id]))
                        @php($selected = $state['options'][$result->selections[$attribute->id]])
                        @if ($selected['display_value'] !== $selected['label'])<p class="text-sm">{{ $selected['display_value'] }}</p>@endif
                        @if ($selected['hint'])<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $selected['hint'] }}</p>@endif
                    @endif
                @else
                    <div class="flex flex-wrap gap-x-2 gap-y-1">
                        @foreach ($attribute->options as $option)
                            @if (! in_array($option->id, $state['hidden'], true))
                                @php($presentation = $state['options'][$option->id])
                                <label @class(['relative flex min-h-7 max-w-full cursor-pointer items-start border-b-2 px-2 py-0.5 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-blue-600 dark:has-focus-visible:outline-blue-400', 'border-blue-600 text-blue-900 dark:border-blue-400 dark:text-blue-100' => ($result->selections[$attribute->id] ?? null) === $option->id, 'border-transparent hover:border-zinc-300 dark:hover:border-zinc-600' => ($result->selections[$attribute->id] ?? null) !== $option->id && in_array($option->id, $state['legal'], true), 'cursor-not-allowed border-transparent opacity-50' => ! in_array($option->id, $state['legal'], true)]) wire:key="option-{{ $option->id }}">
                                    <input class="sr-only" type="radio" name="configuration-{{ $this->getId() }}-{{ $attribute->id }}" value="{{ $option->id }}" wire:click="selectOption('{{ $attribute->id }}', '{{ $option->id }}')" @checked(($result->selections[$attribute->id] ?? null) === $option->id) @disabled(! in_array($option->id, $state['legal'], true)) />
                                    <span class="min-w-0 break-words"><span class="block text-sm font-medium">{{ $presentation['label'] }}</span>
                                        @if ($presentation['display_value'] !== $presentation['label'])<span class="block text-sm">{{ $presentation['display_value'] }}</span>@endif
                                        @if ($presentation['hint'])<span class="block text-sm text-zinc-600 dark:text-zinc-300">{{ $presentation['hint'] }}</span>@endif
                                    </span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                @endif
            </fieldset>
        </div>
    @endif
@endforeach
