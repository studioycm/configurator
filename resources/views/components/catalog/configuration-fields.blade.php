@props(['result' => null, 'projectedRows' => null, 'inputNamespace' => 'configuration', 'previewWorkspace' => false, 'showValueCodes' => false, 'showValueIds' => false])
@php
    $rows = $projectedRows;
    if ($rows === null) {
        $rows = [];
        foreach (collect($result->definition->attributes)->sortBy(['displayOrder', 'id']) as $attribute) {
            $state = $result->attributes[$attribute->id];
            $options = [];
            foreach ($attribute->options as $option) {
                $options[$option->id] = [
                    'id' => $option->id, 'label' => $state['options'][$option->id]['label'],
                    'hint' => $state['options'][$option->id]['hint'], 'display_value' => $state['options'][$option->id]['display_value'],
                    'code' => $option->code, 'semantic_id' => $option->semanticId,
                    'hidden' => in_array($option->id, $state['hidden'], true), 'legal' => in_array($option->id, $state['legal'], true),
                    'selected' => ($result->selections[$attribute->id] ?? null) === $option->id,
                ];
            }
            $rows[$attribute->id] = ['id' => $attribute->id, 'label' => $state['label'], 'hint' => $state['hint'], 'display_value' => $state['display_value'], 'input_type' => $attribute->inputType, 'applicable' => $state['applicable'], 'selected_id' => $result->selections[$attribute->id] ?? null, 'options' => $options];
        }
    }
@endphp
@foreach ($rows as $attribute)
    @if ($attribute['applicable'])
        @php($labelId = ($previewWorkspace ? 'preview-catalog-' : '').'attribute-label-'.$this->getId().'-'.$attribute['id'])
        <div class="catalog-configuration-attribute grid min-w-0 items-start gap-x-3 gap-y-1 border-b border-slate-200 py-1.5 last:border-0 sm:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] dark:border-zinc-700" wire:key="{{ $previewWorkspace ? 'preview-catalog-' : '' }}attribute-{{ $attribute['id'] }}">
            <div class="min-w-0 space-y-1 sm:pt-0.5">
                <h3 id="{{ $labelId }}" class="font-semibold">{{ $attribute['label'] }}</h3>
                @if ($attribute['hint'])<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $attribute['hint'] }}</p>@endif
                @if ($attribute['display_value'] !== null)<p>{{ $attribute['display_value'] }}</p>@endif
            </div>
            <fieldset aria-labelledby="{{ $labelId }}" class="min-w-0 space-y-1.5">
                @if ($attribute['input_type'] === 'select')
                    <select aria-label="{{ $attribute['label'] }}" @if ($previewWorkspace) x-bind:value="$wire.previewView?.inspection?.attributes?.[{{ \Illuminate\Support\Js::from($attribute['id']) }}]?.selected_id ?? ''" x-on:change="selectOption({{ \Illuminate\Support\Js::from($attribute['id']) }}, $event.target.value)" x-bind:disabled="busy" @else wire:change="selectOption('{{ $attribute['id'] }}', $event.target.value)" @endif class="w-full border-0 border-b border-zinc-300 bg-transparent px-2 py-1 focus-visible:outline-2 focus-visible:outline-blue-600 sm:max-w-md dark:border-zinc-600">
                        @if ($attribute['selected_id'] === null)<option value="" selected disabled>{{ __('configurator.copy.no_available_choice') }}</option>@endif
                        @foreach ($attribute['options'] as $option)
                            @if (! $option['hidden'])
                                <option value="{{ $option['id'] }}" @selected($option['selected']) @disabled($option['legal'] !== true) @if ($previewWorkspace) x-bind:selected="$wire.previewView?.inspection?.attributes?.[{{ \Illuminate\Support\Js::from($attribute['id']) }}]?.options?.[{{ \Illuminate\Support\Js::from($option['id']) }}]?.selected ?? false" x-text="{{ \Illuminate\Support\Js::from($option['label']) }} + (showCodes ? {{ \Illuminate\Support\Js::from(' · '.$option['code']) }} : '') + (showIds ? {{ \Illuminate\Support\Js::from($option['semantic_id'] !== null ? ' · '.$option['semantic_id'] : '') }} : '')" @endif>{{ $option['label'] }}@if ($showValueCodes) · {{ $option['code'] }}@endif @if ($showValueIds && $option['semantic_id'] !== null) · {{ $option['semantic_id'] }}@endif</option>
                            @endif
                        @endforeach
                    </select>
                    @if ($attribute['selected_id'] !== null && isset($attribute['options'][$attribute['selected_id']]))
                        @php($selected = $attribute['options'][$attribute['selected_id']])
                        @if ($selected['display_value'] !== $selected['label'])<p class="text-sm">{{ $selected['display_value'] }}</p>@endif
                        @if ($selected['hint'])<p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $selected['hint'] }}</p>@endif
                    @endif
                @else
                    <div class="flex flex-wrap gap-y-1">
                        @foreach ($attribute['options'] as $option)
                            @if (! $option['hidden'])
                                <label @class(['relative flex min-h-6 max-w-full cursor-pointer items-start border-b-2 px-1.5 py-0.5 after:absolute after:inset-y-1 after:end-0 after:border-e after:border-zinc-400 last:after:hidden has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-blue-600 dark:has-focus-visible:outline-blue-400 dark:after:border-zinc-500', 'border-blue-600 text-blue-900 dark:border-blue-400 dark:text-blue-100' => $option['selected'], 'border-transparent hover:border-zinc-300 dark:hover:border-zinc-600' => ! $option['selected'] && $option['legal'] === true, 'cursor-not-allowed border-transparent opacity-50' => $option['legal'] !== true]) wire:key="{{ $previewWorkspace ? 'preview-catalog-' : '' }}option-{{ $option['id'] }}">
                                    <input class="sr-only" type="radio" name="{{ $inputNamespace }}-{{ $this->getId() }}-{{ $attribute['id'] }}" value="{{ $option['id'] }}" @if ($previewWorkspace) x-bind:checked="$wire.previewView?.inspection?.attributes?.[{{ \Illuminate\Support\Js::from($attribute['id']) }}]?.options?.[{{ \Illuminate\Support\Js::from($option['id']) }}]?.selected ?? false" x-on:change="selectOption({{ \Illuminate\Support\Js::from($attribute['id']) }}, {{ \Illuminate\Support\Js::from($option['id']) }})" x-bind:disabled="busy || {{ $option['legal'] !== true ? 'true' : 'false' }}" @else wire:click="selectOption('{{ $attribute['id'] }}', '{{ $option['id'] }}')" @endif @checked($option['selected']) @disabled($option['legal'] !== true) />
                                    <span class="min-w-0 break-words"><span class="catalog-configuration-value-label block text-sm font-medium">{{ $option['label'] }}@if ($previewWorkspace)<span x-show="showCodes"> · {{ $option['code'] }}</span>@if ($option['semantic_id'] !== null)<span x-cloak x-show="showIds" class="block text-xs">{{ $option['semantic_id'] }}</span>@endif @else @if ($showValueCodes) · {{ $option['code'] }}@endif @if ($showValueIds && $option['semantic_id'] !== null)<span class="block text-xs">{{ $option['semantic_id'] }}</span>@endif @endif</span>
                                        @if ($option['display_value'] !== $option['label'])<span class="block text-sm">{{ $option['display_value'] }}</span>@endif
                                        @if ($option['hint'])<span class="block text-sm text-zinc-600 dark:text-zinc-300">{{ $option['hint'] }}</span>@endif
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
