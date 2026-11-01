<?php

namespace App\Http\Controllers;

use App\Services\CatalogAssetSources;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogAssetSourcePreviewController extends Controller
{
    public function __invoke(Request $request, string $source, string $version, string $entry, CatalogAssetSources $sources): StreamedResponse
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        abort_unless((string) (int) $version === $version && (int) $version > 0, 404);
        $preview = $sources->preview($request->user(), $source, (int) $version, $entry);
        $stream = $preview['stream'];

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $preview['mime_type'],
            'Content-Length' => (string) $preview['bytes'],
            'Content-Disposition' => 'inline; filename="catalog-image"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
