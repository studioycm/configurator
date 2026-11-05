@php($inspector = $this->imageInspector())
<aside class="catalog-images-inspector" data-tour="images.inspector" aria-label="{{ __('catalog-images.main_image') }}">
    @if ($inspector && ! $inspector['missing_record'])
        <div class="catalog-images-inspector-heading">
            <h2 data-catalog-images-inspector-heading tabindex="-1" class="font-semibold">{{ $inspector['title'] }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $inspector['position'] ? __('catalog-images.position', ['position' => $inspector['position']['position'], 'total' => $inspector['position']['total']]) : __('catalog-images.outside') }}</p>
            <div data-tour="images.single-navigation" class="catalog-images-bar" role="group" aria-label="{{ __('catalog-images.browse_sequence') }}">
                @foreach (['results', 'checked'] as $mode)
                    <x-filament::button size="sm" color="{{ $imageNavigationMode === $mode ? 'primary' : 'gray' }}" :aria-pressed="$imageNavigationMode === $mode ? 'true' : 'false'"
                        x-on:click="command('setImageNavigationMode', {{ \Illuminate\Support\Js::from($mode) }})">{{ __('catalog-images.'.$mode) }}</x-filament::button>
                @endforeach
                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" x-on:click="command('navigateImageRecord', 'previous')">{{ __('catalog-images.previous') }}</x-filament::button>
                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" x-on:click="command('navigateImageRecord', 'next')">{{ __('catalog-images.next') }}</x-filament::button>
            </div>
        </div>
        <div class="catalog-images-inspector-body" wire:key="inspector-{{ $imageTarget }}-{{ $inspector['id'] }}">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $inspector['description'] }}</p>
            @if ($inspector['unavailable'])<p role="status">{{ __('catalog-images.unavailable') }}</p>@endif
            @if ($inspector['image'])
                <details class="text-sm"><summary>{{ __('catalog-images.image_details') }}</summary>{{ $inspector['image']['filename'] }} · {{ $inspector['image']['width'] }} × {{ $inspector['image']['height'] }} · {{ number_format($inspector['image']['bytes'] / 1024) }} KiB</details>
                @foreach ($inspector['image']['warnings'] as $warning)<p role="status">{{ __('catalog-images.conversion_warning') }}</p>@endforeach
            @endif
            <form data-tour="images.single-editor" x-data="catalogImagesEditor()" wire:key="image-edit-{{ $imageTarget }}-{{ $inspector['id'] }}" x-on:submit.prevent="save()" class="catalog-images-edit-form">
                <div x-bind:inert="unknown">{{ $this->form }}</div>
                @include('filament.resources.catalog-images-leave-guard')
                @if ($imagePendingTransition === null)
                    <x-filament::button data-tour="images.single-save" type="submit" x-bind:disabled="saving || unknown">{{ __('catalog-images.save_image') }}</x-filament::button>
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
