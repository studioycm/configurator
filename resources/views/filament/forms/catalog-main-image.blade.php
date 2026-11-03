@php
    $state = $this->catalogMainImageViewState();
    $progress = $this->catalogMainImageSourceProgress;
@endphp
<section class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10" aria-label="{{ __('catalog.images.main_image') }}">
    <h3 class="text-sm font-semibold">{{ __('catalog.images.main_image') }}</h3>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (['current' => __('catalog.images.current'), 'proposed' => __('catalog.images.proposed')] as $key => $label)
            @if ($key === 'current' || $state['operation'] !== 'keep')
                <div class="space-y-1"><strong class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</strong>
                    @if ($key === 'proposed' && $state['operation'] === 'remove')
                        <p class="text-sm">{{ __('catalog.images.remove_on_save') }}</p>
                    @elseif ($state[$key])
                        <div class="catalog-main-image-preview rounded-lg bg-white p-2"><img src="{{ $state[$key]['thumbnail_url'] }}" alt="{{ $label }}" class="h-28 w-full object-contain" width="{{ $state[$key]['width'] }}" height="{{ $state[$key]['height'] }}" /></div>
                        <p class="break-all text-xs text-gray-500 dark:text-gray-400">{{ $state[$key]['filename'] }} · {{ $state[$key]['width'] }} × {{ $state[$key]['height'] }}</p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('catalog.images.missing') }}</p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
    @if ($state['reference'])<p class="text-xs text-gray-500 dark:text-gray-400">{{ __('catalog.images.reference') }}: <code>{{ $state['reference'] }}</code></p>@endif
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageFind" x-on:click="stopScan = true">{{ __('catalog.images.find') }}</x-filament::button>
        @if ($state['reference'])<x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageUseReference">{{ __('catalog.images.find_reference') }}</x-filament::button>@endif
        <x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageRemove">{{ __('catalog.images.remove') }}</x-filament::button>
        <x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageRevert">{{ __('catalog.images.revert') }}</x-filament::button>
        @if ($this->catalogMainImageUploadBinding && $this->catalogMainImageUploadBinding['temporary_reference'])
            <x-filament::button type="button" color="gray" size="sm" x-on:click="stageUpload()" x-bind:disabled="scanning || uploading || saving">{{ __('catalog.images.resume_upload') }}</x-filament::button>
        @endif
        <x-filament::button type="button" color="gray" size="sm" x-show="unknown" x-on:click="reconcile()">{{ __('catalog.images.check_save') }}</x-filament::button>
    </div>
    <p x-cloak x-show="message" x-text="message" role="status" class="text-sm text-gray-500 dark:text-gray-400"></p>
    @error('data.image_intent')<p role="alert" class="text-sm text-danger-600">{{ $message }}</p>@enderror
    @if ($this->catalogMainImageFinderOpen)
        <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-white/10">
            <label class="block space-y-1 text-sm">{{ __('catalog.images.source') }}
                <x-filament::input.wrapper><x-filament::input.select x-on:change="if ($event.target.value) chooseSource($event.target.value)">
                    <option value="">{{ __('catalog.images.choose_source') }}</option>
                    @foreach ($this->catalogMainImageSourceOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </x-filament::input.select></x-filament::input.wrapper>
            </label>
            <div class="flex flex-wrap items-end gap-2">
                <label class="min-w-0 flex-1 text-sm">{{ __('catalog.images.source_id') }}<x-filament::input.wrapper><x-filament::input maxlength="26" wire:model="catalogMainImageRecoverySourceId" /></x-filament::input.wrapper></label>
                <x-filament::button type="button" color="gray" size="sm" x-on:click="chooseSource('source:' + $wire.catalogMainImageRecoverySourceId.trim())">{{ __('catalog.images.recover_source') }}</x-filament::button>
            </div>
            @if ($progress)
                <p class="break-all text-xs text-gray-500 dark:text-gray-400">{{ $progress['source_id'] }} · v{{ $progress['scan_version'] }}</p>
                <p role="status" class="text-sm">{{ $progress['message'] }} {{ number_format($progress['file_count']) }} {{ __('catalog.images.files') }}</p>
                @if ($progress['stage'] === 'scanning' || ($progress['stage'] === 'uploading' && $progress['file_count'] > 0 && $progress['unsettled_uploads'] === 0))
                    <div class="flex flex-wrap gap-2"><x-filament::button type="button" size="sm" x-on:click="scan()" x-bind:disabled="scanning">{{ __('catalog.images.scan') }}</x-filament::button><x-filament::button type="button" size="sm" color="gray" x-on:click="stopScan = true" x-bind:disabled="!scanning">{{ __('catalog.images.stop_scan') }}</x-filament::button></div>
                @elseif ($progress['stage'] !== 'ready')
                    @if ($progress['stage'] === 'uploading' && $progress['unsettled_uploads'] > 0)<p class="text-sm text-gray-500 dark:text-gray-400">{{ __('catalog.images.unsettled_upload') }}</p>@endif
                    <x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageSourceStatus">{{ __('catalog.images.source_status') }}</x-filament::button>
                @else
                    <label class="block text-sm">{{ __('catalog.images.filename') }}<x-filament::input.wrapper><x-filament::input type="search" maxlength="250" wire:model.live.debounce.300ms="catalogMainImageFilename" /></x-filament::input.wrapper></label>
                    <div class="flex flex-wrap gap-3 text-xs"><label><input type="checkbox" wire:model.live="catalogMainImageCaseSensitive" /> {{ __('catalog.images.case_sensitive') }}</label><label><input type="checkbox" wire:model.live="catalogMainImagePartialMatch" /> {{ __('catalog.images.partial') }}</label></div>
                    @php($candidates = $this->catalogMainImageCandidates())
                    <p class="text-xs">{{ number_format($candidates['total']) }} {{ __('catalog.images.candidates') }}</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($candidates['entries'] as $candidate)
                            <button type="button" class="space-y-1 rounded-lg border border-gray-200 p-2 text-left dark:border-white/10" wire:key="main-image-{{ $candidate['entry_id'] }}" wire:click="catalogMainImageSelectCandidate('{{ $candidate['entry_id'] }}')"><img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" class="h-20 w-full rounded bg-white object-contain" width="{{ $candidate['width'] }}" height="{{ $candidate['height'] }}" /><span class="block break-all text-xs">{{ $candidate['filename'] }}</span></button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2"><x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageCandidatePage(-1)" :disabled="$candidates['page'] <= 1">{{ __('catalog.browse.previous') }}</x-filament::button><span class="text-xs">{{ $candidates['page'] }} / {{ $candidates['pages'] }}</span><x-filament::button type="button" color="gray" size="sm" wire:click="catalogMainImageCandidatePage(1)" :disabled="$candidates['page'] >= $candidates['pages']">{{ __('catalog.browse.next') }}</x-filament::button></div>
                @endif
            @endif
        </div>
    @endif
</section>
