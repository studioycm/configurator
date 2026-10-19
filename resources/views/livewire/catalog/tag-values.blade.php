<div class="catalog-tag-values">
    <header class="catalog-tag-editor-heading">
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __($this->tag === null ? 'tags.new_tag' : 'tags.editing_tag') }}</span>
            <h2>{{ $this->tag ?? __('tags.new_tag') }}</h2>
            @if ($this->tag !== null)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->usage }} {{ __('tags.usage') }}</span>
            @endif
        </div>
        <x-filament::icon-button icon="heroicon-o-x-mark" :label="__('tags.close_editor')" wire:click="$dispatch('close-tag-workspace')" data-tag-transition />
    </header>
    @if ($this->tag === null)
        <div class="catalog-tag-change">{{ $this->nameForm }}</div>
    @else
        <div class="catalog-tag-editor-toolbar">
            @if ($this->picking)
                <x-filament::button color="gray" icon="heroicon-o-arrow-left" wire:click="showPicker(false)">{{ __('tags.tagged_values') }}</x-filament::button>
            @else
                <x-filament::button icon="heroicon-o-plus" wire:click="showPicker" data-tag-requires-clean>{{ __('tags.add_values') }}</x-filament::button>
                <div class="catalog-tag-global-actions">
                    <x-filament::link tag="button" type="button" wire:click="beginGlobal('rename')" data-tag-requires-clean>{{ __('tags.rename') }}</x-filament::link>
                    <x-filament::link tag="button" type="button" wire:click="beginGlobal('merge')" data-tag-requires-clean>{{ __('tags.merge') }}</x-filament::link>
                    <x-filament::icon-button icon="heroicon-o-trash" color="danger" :label="__('tags.remove')" :tooltip="__('tags.remove')" wire:click="beginGlobal('remove')" data-tag-requires-clean />
                </div>
            @endif
        </div>
    @endif

    @if ($this->globalOperation !== null)
        <form wire:submit="applyGlobal" class="catalog-tag-change" wire:key="global-tags-{{ $this->globalOperation }}">
            <h3>{{ __('tags.'.$this->globalOperation) }}</h3>
            {{ $this->globalForm }}
            @include('filament.pages.tag-impact')
            <div class="catalog-tag-change-actions">
                <x-filament::button type="submit" :color="$this->globalOperation === 'remove' ? 'danger' : 'primary'" :disabled="empty($this->tagPreview['token'])" wire:loading.attr="disabled" data-tag-requires-clean>{{ __('tags.apply') }}</x-filament::button>
                <x-filament::link tag="button" type="button" wire:click="cancelGlobal">{{ __('ui.actions.cancel') }}</x-filament::link>
            </div>
        </form>
    @endif

    @if ($this->assignmentIds !== [])
        <form wire:submit="applyAssignment" class="catalog-tag-change">
            <h3>{{ __('tags.edit_count', ['count' => count($this->assignmentIds)]) }}</h3>
            {{ $this->assignmentForm }}
            @include('filament.forms.validation-summary', ['getLivewire' => fn () => $this])
            @if ($this->batchPreview['token'] ?? null)
                <div class="catalog-tag-impact" aria-live="polite">
                    @foreach (array_slice($this->batchPreview['rows'], 0, 5) as $row)
                        <div wire:key="tag-impact-{{ $row['id'] }}">
                            <strong>{{ $row['name'] }}</strong>
                            <span>{{ implode(', ', $row['before']['tags'] ?? []) ?: '—' }} → {{ implode(', ', $row['after']['tags'] ?? []) ?: '—' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="catalog-tag-change-actions">
                <x-filament::button type="submit" :disabled="empty($this->batchPreview['token'])" wire:loading.attr="disabled" data-tag-requires-clean>{{ __('tags.apply') }}</x-filament::button>
                <x-filament::link tag="button" type="button" wire:click="cancelAssignment">{{ __('ui.actions.cancel') }}</x-filament::link>
            </div>
        </form>
    @endif

    {{ $this->table }}
</div>
