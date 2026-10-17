@vite('resources/js/app.js')

@if (auth()->check())
    <span class="contents" x-data="adminBrowserProfile({{ \Illuminate\Support\Js::from(['user' => auth()->id(), 'variables' => app(\App\Services\AdminAppearance::class)->variables(), 'settings' => app(\App\Services\AdminAppearance::class)->current(), 'ranges' => app(\App\Services\AdminAppearance::class)->ranges()]) }})"></span>
@endif
