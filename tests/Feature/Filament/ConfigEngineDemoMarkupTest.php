<?php

use Illuminate\Support\Collection;

it('renders the config engine modal and preview partials without inline sprintf html builders', function () {
    $pageSource = file_get_contents(app_path('Filament/Pages/ConfigEngineDemo.php'));

    expect($pageSource)
        ->toContain('filament.pages.config-engine-demo.partials.modal-image')
        ->toContain('filament.pages.config-engine-demo.partials.image-trigger')
        ->toContain('filament.pages.config-engine-demo.partials.image-grid')
        ->toContain('filament.pages.config-engine-demo.partials.file-links')
        ->toContain('->stickyModalHeader()')
        ->toContain('->stickyModalFooter()')
        ->not->toContain('->modalContent(fn (array $arguments): string => sprintf(');

    $modalHtml = view('filament.pages.config-engine-demo.partials.modal-image', [
        'url' => 'https://example.com/image.png',
    ])->render();

    $triggerHtml = view('filament.pages.config-engine-demo.partials.image-trigger', [
        'url' => 'https://example.com/image.png?x=1',
        'alt' => 'Main Image',
    ])->render();

    $gridHtml = view('filament.pages.config-engine-demo.partials.image-grid', [
        'urls' => Collection::make([
            'https://example.com/first.png',
            'https://example.com/second.png',
        ]),
    ])->render();

    expect($modalHtml)
        ->toContain('src="https://example.com/image.png"')
        ->toContain('object-contain')
        ->toContain('max-h-[calc(100dvh-12rem)]');

    expect($triggerHtml)
        ->toContain("mountAction('viewImage',")
        ->toContain('src="https://example.com/image.png?x=1"')
        ->toContain('Main Image')
        ->toContain('Enlarge');

    expect($gridHtml)
        ->toContain('https://example.com/first.png')
        ->toContain('https://example.com/second.png');
});

it('renders config engine file links from the blade partial', function () {
    $html = view('filament.pages.config-engine-demo.partials.file-links', [
        'files' => Collection::make([
            (object) [
                'file_path' => 'https://example.com/spec-sheet.pdf',
                'title' => 'Spec Sheet',
            ],
        ]),
        'withContainer' => true,
    ])->render();

    expect($html)
        ->toContain('href="https://example.com/spec-sheet.pdf"')
        ->toContain('Spec Sheet')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('Open')
        ->toContain('space-y-1');
});
