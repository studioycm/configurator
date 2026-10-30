@if ($recovery !== null)
    <x-filament::section>
        <div role="status" aria-live="polite">
            <p>{{ __('admin.appearance.recovery.notice') }}</p>
            @can('manage-catalog')
                <x-filament::link :href="\App\Filament\Pages\AdminAppearance::getUrl()">
                    {{ __('admin.appearance.recovery.repair') }}
                </x-filament::link>
            @endcan
        </div>
    </x-filament::section>
@endif
