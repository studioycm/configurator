<div>
    @teleport($summaryTarget)
        <div class="catalog-context-summary" role="group" aria-label="{{ __('configurator.copy.product_context') }}">
            <dl class="catalog-context-labels grid min-w-0 flex-1 grid-cols-[auto_minmax(0,1fr)] items-baseline gap-x-2 gap-y-1 text-xs" aria-live="polite">
                @foreach (['territory' => __('configurator.labels.territory'), 'application' => __('configurator.labels.application')] as $dimension => $label)
                    <dt class="text-slate-400">{{ $label }}</dt>
                    <dd class="break-words font-medium text-slate-100">{{ $selectedLabels[$dimension] }}</dd>
                @endforeach
            </dl>
            <flux:modal.trigger :name="'product-context-'.$this->getId()">
                <button type="button" class="catalog-shell-control inline-flex shrink-0" aria-label="{{ __('configurator.copy.edit_territory_and_application') }}" title="{{ __('configurator.copy.edit_territory_and_application') }}" aria-haspopup="dialog">
                    <flux:icon.cog-6-tooth class="size-4" />
                </button>
            </flux:modal.trigger>
        </div>
    @endteleport

    <flux:modal :name="'product-context-'.$this->getId()" class="w-[calc(100vw-2rem)] min-w-0 max-w-lg" x-on:keydown.escape.stop>
        <div class="space-y-5">
            <div class="pr-7">
                <flux:heading size="lg" level="2" :id="'context-title-'.$this->getId()" x-init="$el.closest('dialog').setAttribute('aria-labelledby', $el.id)">{{ __('configurator.copy.territory_and_application') }}</flux:heading>
                <flux:text class="mt-1">{{ __('configurator.copy.choices_update_your_configuration_immediately') }}</flux:text>
            </div>
            @foreach (['territory' => __('configurator.labels.territory'), 'application' => __('configurator.labels.application')] as $dimension => $label)
                <fieldset wire:key="context-{{ $dimension }}" class="min-w-0 space-y-2">
                    <legend class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $label }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([['value' => 'All', 'label' => __('ui.choices.all')], ...$schema[$dimension]] as $choice)
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
                    <flux:button variant="primary" size="sm">{{ __('configurator.copy.done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
