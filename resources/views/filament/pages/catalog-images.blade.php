<x-filament-panels::page>
    <div class="catalog-images-workspace" x-data="catalogImagesBrowser({ user: @js(auth()->id()) })" x-on:catalog-images-inspect="inspect($event.detail)">
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('catalog-images.intro') }}</p>
        <div class="catalog-images-bar" role="group" aria-label="{{ __('catalog-images.title') }}">
            @foreach (['product.main_image' => 'products', 'group.main_image' => 'groups'] as $key => $label)
                <x-filament::button wire:key="images-target-{{ $key }}" color="{{ $imageTarget === $key ? 'primary' : 'gray' }}"
                    :aria-pressed="$imageTarget === $key ? 'true' : 'false'" x-on:click="command('switchImageTarget', {{ \Illuminate\Support\Js::from($key) }})">{{ __('catalog-images.'.$label) }}</x-filament::button>
            @endforeach
        </div>
        @if ($errors->any())
            <div role="alert" class="catalog-images-notice">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @if ($imageBrowserNotice)<p role="status" class="catalog-images-notice">{{ $imageBrowserNotice }}</p>@endif
        <div class="catalog-images-layout">
            <section aria-label="{{ __('catalog-images.results') }}" x-ref="records" tabindex="-1" class="catalog-images-records">
                @include('filament.forms.catalog-image-record-search')
                <div data-catalog-images-native-table wire:key="image-table-{{ $imageTarget }}">{{ $this->table }}</div>
                <div class="catalog-images-footer">
                    <div class="catalog-images-page-size" x-data="{ custom: false, size: '' }">
                        <label for="images-page-size" class="sr-only">{{ __('catalog-images.rows_per_page') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="images-page-size" title="{{ __('catalog-images.rows_per_page') }}" aria-label="{{ __('catalog-images.rows_per_page') }}"
                                x-on:change="if ($event.target.value === 'custom') { custom = true; $nextTick(() => $refs.customSize.focus()); } else { custom = false; command('setImagePageSize', $event.target.value); }">
                                @foreach ($this->imagePageOptions() as $size)
                                    <option value="{{ $size }}" @selected($this->getTableRecordsPerPage() === $size)>{{ $size }}</option>
                                @endforeach
                                <option value="custom">{{ __('catalog-images.custom') }}</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <form x-cloak x-show="custom" x-on:submit.prevent="command('setImagePageSize', size)" class="catalog-images-bar">
                            <label class="sr-only" for="images-custom-size">{{ __('catalog-images.custom_rows') }}</label>
                            <x-filament::input.wrapper><x-filament::input id="images-custom-size" x-ref="customSize" x-model="size" type="text" inputmode="numeric" maxlength="3" title="{{ __('catalog-images.custom_rows') }}" /></x-filament::input.wrapper>
                            <x-filament::button type="submit" size="sm">{{ __('catalog-images.apply') }}</x-filament::button>
                        </form>
                    </div>
                    <x-filament::pagination :paginator="$this->getTableRecords()" :page-options="[]" />
                </div>
            </section>
            @include('filament.resources.catalog-images-inspector')
        </div>
        <p role="status" x-cloak x-show="clientError" x-text="clientError" class="catalog-images-notice"></p>
    </div>
</x-filament-panels::page>
