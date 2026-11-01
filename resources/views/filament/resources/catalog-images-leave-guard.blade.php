@if ($imagePendingTransition !== null)
    <section class="catalog-images-leave-guard" aria-label="{{ __('catalog-images.unsaved') }}">
        <h3 class="font-semibold">{{ __('catalog-images.unsaved') }}</h3>
        <p>{{ __('catalog-images.leave_image', ['destination' => $imagePendingTransition['destination_label']]) }}</p>
        <div class="catalog-images-bar">
            <x-filament::button type="button" color="gray" size="sm" x-on:click="choose('stay')" x-bind:disabled="saving || unknown">{{ __('catalog-images.stay') }}</x-filament::button>
            <x-filament::button type="button" color="gray" size="sm" x-on:click="choose('discard')" x-bind:disabled="saving">{{ __('catalog-images.discard') }}</x-filament::button>
            @if (! ($inspector['missing_record'] ?? false))
                <x-filament::button type="button" size="sm" x-on:click="save({{ \Illuminate\Support\Js::from($imagePendingTransition['token']) }})" x-bind:disabled="saving || unknown">{{ __('catalog-images.save_continue') }}</x-filament::button>
            @endif
        </div>
    </section>
@endif
@error('imageEditor')<p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
<p x-cloak x-show="message" x-text="message" role="status" class="catalog-images-notice"></p>
<div x-cloak x-show="unknown" class="catalog-images-bar">
    <x-filament::button type="button" size="sm" x-on:click="reconcile()" x-bind:disabled="saving">{{ __('catalog-images.check_saved') }}</x-filament::button>
    <x-filament::button type="button" color="gray" size="sm" x-on:click="choose('discard')" x-bind:disabled="saving">{{ __('catalog-images.discard') }}</x-filament::button>
</div>
