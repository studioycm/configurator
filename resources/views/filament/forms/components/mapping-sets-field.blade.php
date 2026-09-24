<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="matrix-{{ sha1($getStatePath().json_encode([$getSourceChoices(), $getTargetChoices()])) }}"
        x-data="{
            sets: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            sources: @js($getSourceChoices()),
            targets: @js($getTargetChoices()),
            keyPrefix: @js('new:'.Illuminate\Support\Str::uuid()),
            sequence: 0,
            add() { this.sets.push({id: this.keyPrefix + '-' + (++this.sequence), label: '', source_option_ids: [], target_option_ids: []}); },
            duplicate(id) { return this.sets.filter(set => set.source_option_ids.map(String).includes(String(id))).length > 1; },
            stale(ids, choices) { return ids.filter(id => !Object.hasOwn(choices, id)); },
            removeReference(set, key, id) { set[key] = set[key].filter(value => String(value) !== String(id)); }
        }"
        class="space-y-4"
    >
        <p class="text-sm text-gray-600 dark:text-gray-300">Unmapped driver options add no restriction. Each source can belong to one set; allowed targets may overlap.</p>
        <p class="text-sm font-medium" x-text="sets.length + ' sets · ' + sets.reduce((count, set) => count + set.source_option_ids.length, 0) + ' source memberships'"></p>
        <template x-for="(set, index) in sets" :key="set.id">
            <fieldset class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <legend class="px-2 font-semibold" x-text="'Set ' + (index + 1)"></legend>
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <label class="grid gap-1 text-sm">Set label (optional)
                        <input type="text" maxlength="255" x-model="set.label" :aria-label="'Set ' + (index + 1) + ' label'" class="rounded-lg border border-gray-300 px-3 py-2 dark:bg-gray-900" />
                    </label>
                    <button type="button" class="text-sm font-medium text-danger-600" :aria-label="'Remove set ' + (index + 1)" x-on:click="sets.splice(index, 1)">Remove set</button>
                </div>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="min-w-0">
                        <h4 class="sticky top-0 bg-white py-2 font-medium dark:bg-gray-900" x-text="'Driver options · ' + set.source_option_ids.length + ' selected'"></h4>
                        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700" tabindex="0" :aria-label="'Set ' + (index + 1) + ' driver options'">
                            <template x-for="(label, id) in sources" :key="id">
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" :value="String(id)" x-model="set.source_option_ids" :aria-label="'Set ' + (index + 1) + ' driver ' + label" class="mt-1" />
                                    <span><span x-text="label"></span><span x-show="duplicate(id)" class="block text-danger-600">Used in more than one set — repair before saving.</span></span>
                                </label>
                            </template>
                            <template x-for="id in stale(set.source_option_ids, sources)" :key="id">
                                <div class="text-sm text-danger-600"><span x-text="'Stale driver reference ' + id + ' — explicit repair required. '"></span><button type="button" class="underline" x-on:click="removeReference(set, 'source_option_ids', id)" :aria-label="'Remove stale driver reference ' + id">Remove reference</button></div>
                            </template>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <h4 class="sticky top-0 bg-white py-2 font-medium dark:bg-gray-900" x-text="'Allowed targets · ' + set.target_option_ids.length + ' selected'"></h4>
                        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700" tabindex="0" :aria-label="'Set ' + (index + 1) + ' allowed targets'">
                            <template x-for="(label, id) in targets" :key="id">
                                <label class="flex items-start gap-2 text-sm"><input type="checkbox" :value="String(id)" x-model="set.target_option_ids" :aria-label="'Set ' + (index + 1) + ' allowed target ' + label" class="mt-1" /><span x-text="label"></span></label>
                            </template>
                            <template x-for="id in stale(set.target_option_ids, targets)" :key="id">
                                <div class="text-sm text-danger-600"><span x-text="'Stale target reference ' + id + ' — explicit repair required. '"></span><button type="button" class="underline" x-on:click="removeReference(set, 'target_option_ids', id)" :aria-label="'Remove stale target reference ' + id">Remove reference</button></div>
                            </template>
                        </div>
                    </div>
                </div>
            </fieldset>
        </template>
        <x-filament::button type="button" color="gray" x-on:click="add()">Add set</x-filament::button>
    </div>
</x-dynamic-component>
