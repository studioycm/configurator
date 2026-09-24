<div class="grid grid-cols-1 gap-2">
    @foreach ($urls as $url)
        @include('legacy::filament.pages.config-engine-demo.partials.image-trigger', [
            'url' => $url,
        ])
    @endforeach
</div>
