<div>
    @teleport($summaryTarget)
        <div class="my-3 flex items-start gap-2 rounded-lg border border-slate-600/60 bg-[#203448] px-3 py-2" role="group" aria-label="{{ __('Product context') }}">
            <dl class="grid min-w-0 flex-1 grid-cols-[auto_minmax(0,1fr)] items-baseline gap-x-2 gap-y-1 text-xs" aria-live="polite">
                @foreach (['territory' => 'Territory', 'application' => 'Application'] as $dimension => $label)
                    <dt class="text-slate-400">{{ __($label) }}</dt>
                    <dd class="break-words font-medium text-slate-100">{{ $selectedLabels[$dimension] }}</dd>
                @endforeach
            </dl>
            <flux:modal.trigger :name="'product-context-'.$this->getId()">
                <button type="button" class="-m-1 inline-flex size-7 shrink-0 items-center justify-center rounded-md text-slate-300 hover:bg-slate-600/50 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-400" aria-label="{{ __('Edit territory and application') }}" aria-haspopup="dialog">
                    <flux:icon.cog-6-tooth class="size-4" />
                </button>
            </flux:modal.trigger>
        </div>
    @endteleport

    <flux:modal :name="'product-context-'.$this->getId()" class="w-[calc(100vw-2rem)] min-w-0 max-w-lg" x-on:keydown.escape.stop>
        <div class="space-y-5">
            <div class="pr-7">
                <flux:heading size="lg" level="2" :id="'context-title-'.$this->getId()" x-init="$el.closest('dialog').setAttribute('aria-labelledby', $el.id)">{{ __('Territory and application') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Choices update your configuration immediately.') }}</flux:text>
            </div>
            @foreach (['territory' => 'Territory', 'application' => 'Application'] as $dimension => $label)
                <fieldset wire:key="context-{{ $dimension }}" class="min-w-0 space-y-2">
                    <legend class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ __($label) }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([['value' => 'All', 'label' => __('All')], ...$schema[$dimension]] as $choice)
                            <flux:button
                                wire:key="context-{{ $dimension }}-{{ $choice['value'] }}"
                                :data-dimension="$dimension"
                                :data-choice="$choice['value']"
                                x-on:click="$wire.$dispatch('context-changed', { dimension: $el.dataset.dimension, choice: $el.dataset.choice })"
                                :variant="$context[$dimension] === $choice['value'] ? 'primary' : 'outline'"
                                :aria-pressed="$context[$dimension] === $choice['value'] ? 'true' : 'false'"
                                :autofocus="$dimension === 'territory' && $context[$dimension] === $choice['value']"
                                size="sm"
                                class="h-auto! min-h-8 max-w-full whitespace-normal! py-1.5 [&>span]:min-w-0 [&>span]:break-words"
                            >{{ $choice['label'] }}</flux:button>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <div class="flex justify-end border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="primary" size="sm">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
