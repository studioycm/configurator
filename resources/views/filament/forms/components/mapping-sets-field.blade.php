<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="matrix-{{ sha1($getStatePath().json_encode([$getSourceChoices(), $getTargetChoices()])) }}"
        x-data="{
            get sets() { return $wire.$get(@js($getStatePath())); },
            sources: @js($getSourceChoices()),
            targets: @js($getTargetChoices()),
            keyPrefix: @js('new:'.Illuminate\Support\Str::uuid()),
            sequence: 0,
            searches: {},
            showButtonOrder: true,
            showDragOrder: false,
            draggedSetId: null,
            dropTargetId: null,
            orderAnnouncement: '',
            add() { this.sets.push({id: this.keyPrefix + '-' + (++this.sequence), label: '', disallowed_target_behavior: 'Disable', source_option_ids: [], target_option_ids: []}); },
            reorderSet(id, position) {
                const previous = this.sets.findIndex(set => set.id === id);
                if (previous < 0 || position < 0 || position >= this.sets.length || previous === position) return;
                const [set] = this.sets.splice(previous, 1);
                this.sets.splice(position, 0, set);
                this.orderAnnouncement = @js(__('configurator.mapping.moved')).replace(':previous', previous + 1).replace(':position', position + 1);
            },
            moveSet(id, direction) { this.reorderSet(id, this.sets.findIndex(set => set.id === id) + direction); },
            startSetDrag(id, event) {
                if (! this.showDragOrder) { event.preventDefault(); return; }
                this.draggedSetId = id;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('application/x-aquestia-mapping-set', String(id));
            },
            overSet(id, event) {
                if (this.draggedSetId === null) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                this.dropTargetId = id;
            },
            dropSet(id, event) {
                if (this.draggedSetId === null) return;
                event.preventDefault();
                event.stopPropagation();
                if (event.dataTransfer.getData('application/x-aquestia-mapping-set') === String(this.draggedSetId)) {
                    this.reorderSet(this.draggedSetId, this.sets.findIndex(set => set.id === id));
                }
                this.clearSetDrag();
            },
            clearSetDrag() { this.draggedSetId = null; this.dropTargetId = null; },
            matches(label, query) { return String(label).toLocaleLowerCase().includes((query || '').toLocaleLowerCase()); },
            duplicate(id) { return this.sets.filter(set => set.source_option_ids.map(String).includes(String(id))).length > 1; },
            stale(ids, choices) { return ids.filter(id => !Object.hasOwn(choices, id)); },
            removeReference(set, key, id) { set[key] = set[key].filter(value => String(value) !== String(id)); }
        }"
        class="space-y-4"
    >
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-medium" x-text="sets.length + ' sets · ' + sets.reduce((count, set) => count + set.source_option_ids.length, 0) + ' source memberships'"></p>
            <x-filament::icon-button icon="heroicon-o-arrows-up-down" label="{{ __('admin.ordering.labels.show_or_hide_updown_controls') }}"
                x-bind:aria-pressed="showButtonOrder" x-on:click="showButtonOrder = ! showButtonOrder" />
            <x-filament::icon-button icon="heroicon-o-bars-3" label="{{ __('configurator.copy.show_or_hide_drag_handles_order_is_saved_with_the_rule') }}"
                x-bind:aria-pressed="showDragOrder" x-on:click="showDragOrder = ! showDragOrder; clearSetDrag()" />
        </div>
        <p class="sr-only" aria-live="polite" aria-atomic="true" x-text="orderAnnouncement"></p>
        <template x-for="(set, index) in sets" :key="set.id">
            <fieldset class="rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                x-bind:class="{ 'ring-2 ring-primary-500': draggedSetId !== null && draggedSetId !== set.id && dropTargetId === set.id }"
                x-on:dragover="overSet(set.id, $event)" x-on:drop="dropSet(set.id, $event)">
                <legend class="px-2 font-semibold" x-text="@js(__('configurator.mapping.set_number')).replace(':number', index + 1)"></legend>
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div class="flex items-center gap-1">
                        <div x-cloak x-show="showButtonOrder" class="flex items-center gap-1">
                            <x-filament::icon-button icon="heroicon-o-arrow-up" label="{{ __('configurator.copy.move_set_up') }}" x-bind:aria-label="{{ \Illuminate\Support\Js::from(__('configurator.mapping.move_up')) }}.replace(':number', index + 1)"
                                x-bind:disabled="index === 0" x-on:click="moveSet(set.id, -1)" />
                            <x-filament::icon-button icon="heroicon-o-arrow-down" label="{{ __('configurator.copy.move_set_down') }}" x-bind:aria-label="{{ \Illuminate\Support\Js::from(__('configurator.mapping.move_down')) }}.replace(':number', index + 1)"
                                x-bind:disabled="index === sets.length - 1" x-on:click="moveSet(set.id, 1)" />
                        </div>
                        <button type="button" x-cloak x-show="showDragOrder" x-bind:draggable="showDragOrder"
                            x-bind:aria-label="@js(__('configurator.mapping.drag')).replace(':number', index + 1)"
                            title="{{ __('configurator.copy.drag_to_reorder_arrow_keys_move_this_set') }}"
                            class="cursor-grab rounded-lg p-2 text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-primary-500 active:cursor-grabbing dark:text-gray-400 dark:hover:bg-gray-800"
                            x-on:dragstart.stop="startSetDrag(set.id, $event)" x-on:dragend.stop="clearSetDrag()"
                            x-on:keydown.up.prevent="moveSet(set.id, -1)" x-on:keydown.down.prevent="moveSet(set.id, 1)">
                            <x-filament::icon icon="heroicon-o-bars-3" class="size-4" />
                        </button>
                    </div>
                    <label class="grid gap-1 text-sm">{{ __('configurator.mapping.set_label_optional') }}
                        <input type="text" maxlength="255" x-model="set.label" :aria-label="@js(__('configurator.mapping.set_label')).replace(':number', index + 1)" class="rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-900" />
                    </label>
                    <fieldset class="text-sm">
                        <legend class="mb-1 font-medium">{{ __('configurator.copy.disallowed_targets') }}</legend>
                        <div class="flex flex-wrap gap-3">
                            <label class="flex items-center gap-1"><input type="radio" value="Disable" x-model="set.disallowed_target_behavior" :name="'mapping-mode-' + set.id" /> {{ __('configurator.copy.disable') }}</label>
                            <label class="flex items-center gap-1"><input type="radio" value="Hide" x-model="set.disallowed_target_behavior" :name="'mapping-mode-' + set.id" /> {{ __('configurator.copy.hide') }}</label>
                        </div>
                    </fieldset>
                    <button type="button" class="text-sm font-medium text-danger-600" :aria-label="@js(__('configurator.mapping.remove')).replace(':number', index + 1)" x-on:click="sets.splice(index, 1)">{{ __('configurator.copy.remove_set') }}</button>
                </div>
                <div class="catalog-mapping-lists grid gap-3">
                    <div class="min-w-0">
                        <h4 class="sticky top-0 bg-white py-2 font-medium dark:bg-gray-900" x-text="@js(__('configurator.mapping.driver_selected')).replace(':count', set.source_option_ids.length)"></h4>
                        <input type="search" x-model="searches[set.id + ':source']" :aria-label="@js(__('configurator.mapping.search_driver')).replace(':number', index + 1)" placeholder="{{ __('configurator.copy.search_driver_values') }}" class="mb-2 w-full rounded-lg border border-gray-300 px-2 py-1 dark:bg-gray-900" />
                        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700" tabindex="0" :aria-label="@js(__('configurator.mapping.driver_list')).replace(':number', index + 1)">
                            <template x-for="(label, id) in sources" :key="id">
                                <label class="flex items-start gap-2 text-sm" x-show="matches(label, searches[set.id + ':source'])">
                                    <input type="checkbox" :value="String(id)" x-model="set.source_option_ids" :aria-label="@js(__('configurator.mapping.driver_label')).replace(':number', index + 1).replace(':label', label)" class="mt-1" />
                                    <span><span x-text="label"></span><span x-show="duplicate(id)" class="block text-danger-600">{{ __('configurator.copy.used_in_more_than_one_set_repair_before_saving') }}</span></span>
                                </label>
                            </template>
                            <template x-for="id in stale(set.source_option_ids, sources)" :key="id">
                                <div class="text-sm text-danger-600"><span x-text="@js(__('configurator.mapping.stale_driver')).replace(':id', id)"></span><button type="button" class="underline" x-on:click="removeReference(set, 'source_option_ids', id)" :aria-label="@js(__('configurator.mapping.remove_stale_driver')).replace(':id', id)">{{ __('configurator.copy.remove_reference') }}</button></div>
                            </template>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <h4 class="sticky top-0 bg-white py-2 font-medium dark:bg-gray-900" x-text="@js(__('configurator.mapping.target_selected')).replace(':count', set.target_option_ids.length)"></h4>
                        <input type="search" x-model="searches[set.id + ':target']" :aria-label="@js(__('configurator.mapping.search_target')).replace(':number', index + 1)" placeholder="{{ __('configurator.copy.search_allowed_targets') }}" class="mb-2 w-full rounded-lg border border-gray-300 px-2 py-1 dark:bg-gray-900" />
                        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700" tabindex="0" :aria-label="@js(__('configurator.mapping.target_list')).replace(':number', index + 1)">
                            <template x-for="(label, id) in targets" :key="id">
                                <label class="flex items-start gap-2 text-sm" x-show="matches(label, searches[set.id + ':target'])"><input type="checkbox" :value="String(id)" x-model="set.target_option_ids" :aria-label="@js(__('configurator.mapping.target_label')).replace(':number', index + 1).replace(':label', label)" class="mt-1" /><span x-text="label"></span></label>
                            </template>
                            <template x-for="id in stale(set.target_option_ids, targets)" :key="id">
                                <div class="text-sm text-danger-600"><span x-text="@js(__('configurator.mapping.stale_target')).replace(':id', id)"></span><button type="button" class="underline" x-on:click="removeReference(set, 'target_option_ids', id)" :aria-label="@js(__('configurator.mapping.remove_stale_target')).replace(':id', id)">{{ __('configurator.copy.remove_reference') }}</button></div>
                            </template>
                        </div>
                    </div>
                </div>
            </fieldset>
        </template>
        <x-filament::button type="button" color="gray" icon="heroicon-o-plus" x-on:click="add()">{{ __('configurator.copy.add_set') }}</x-filament::button>
    </div>
</x-dynamic-component>
