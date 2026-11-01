@php($inspector = $this->imageInspector())
<aside class="catalog-images-inspector" aria-label="{{ __('catalog-images.main_image') }}">
    @if ($inspector && ! $inspector['missing_record'])
        <div class="catalog-images-inspector-heading">
            <h2 x-ref="inspectorHeading" tabindex="-1" class="font-semibold">{{ $inspector['title'] }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $inspector['position'] ? __('catalog-images.position', ['position' => $inspector['position']['position'], 'total' => $inspector['position']['total']]) : __('catalog-images.outside') }}</p>
            <div class="catalog-images-bar" role="group" aria-label="{{ __('catalog-images.main_image') }}">
                @foreach (['results', 'checked'] as $mode)
                    <x-filament::button size="sm" color="{{ $imageNavigationMode === $mode ? 'primary' : 'gray' }}" :aria-pressed="$imageNavigationMode === $mode ? 'true' : 'false'"
                        x-on:click="command('setImageNavigationMode', {{ \Illuminate\Support\Js::from($mode) }})">{{ __('catalog-images.'.$mode) }}</x-filament::button>
                @endforeach
                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" x-on:click="command('navigateImageRecord', 'previous')">{{ __('catalog-images.previous') }}</x-filament::button>
                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" x-on:click="command('navigateImageRecord', 'next')">{{ __('catalog-images.next') }}</x-filament::button>
            </div>
        </div>
        <div class="catalog-images-inspector-body" wire:key="inspector-{{ $imageTarget }}-{{ $inspector['id'] }}">
            <h3 class="font-semibold">{{ __('catalog-images.main_image') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $inspector['description'] }}</p>
            @if ($inspector['unavailable'])<p role="status">{{ __('catalog-images.unavailable') }}</p>@endif
            <x-catalog.main-image :image="$inspector['image']" :decorative="false" :alt="$inspector['title']" />
            @if (! $inspector['image'])<p>{{ __('catalog-images.no_image') }}</p>@endif
            @if ($inspector['image'])
                <p class="text-sm">{{ $inspector['image']['filename'] }} · {{ $inspector['image']['width'] }} × {{ $inspector['image']['height'] }} · {{ number_format($inspector['image']['bytes'] / 1024) }} KiB</p>
                @foreach ($inspector['image']['warnings'] as $warning)<p role="status">{{ __('catalog-images.conversion_warning') }}</p>@endforeach
            @endif
            @if ($inspector['reference'] !== null)<p class="text-sm">{{ __('catalog-images.reference') }}: {{ $inspector['reference'] }}</p>@endif
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('catalog-images.one_image') }}</p>
            <form x-data="catalogImagesEditor()" wire:key="image-edit-{{ $imageTarget }}-{{ $inspector['id'] }}" x-on:submit.prevent="save()" class="catalog-images-edit-form">
                <div x-bind:inert="unknown">{{ $this->form }}</div>
                @include('filament.resources.catalog-images-leave-guard')
                @if ($imagePendingTransition === null)
                    <x-filament::button type="submit" x-bind:disabled="saving || unknown">{{ __('catalog-images.save_image') }}</x-filament::button>
                @endif
            </form>
            <x-filament::button size="sm" color="gray" x-on:click="$refs.records.focus()">{{ __('catalog-images.back') }}</x-filament::button>
        </div>
    @else
        <p class="catalog-images-inspector-body">{{ $inspector ? __('catalog-images.changed') : __('catalog-images.select_record') }}</p>
        @if ($imageEditorSession !== null)
            <div x-data="catalogImagesEditor()" wire:key="image-edit-{{ $imageTarget }}-{{ $imageRecordId }}" class="catalog-images-edit-form catalog-images-inspector-body">
                @include('filament.resources.catalog-images-leave-guard')
                @if ($imagePendingTransition === null)
                    <x-filament::button type="button" color="gray" size="sm" x-on:click="choose('discard')" x-bind:disabled="saving">{{ __('catalog-images.discard') }}</x-filament::button>
                @endif
            </div>
        @endif
    @endif
</aside>
