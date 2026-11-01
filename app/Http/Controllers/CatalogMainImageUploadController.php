<?php

namespace App\Http\Controllers;

use App\Services\CatalogAssetTargets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Binds the native temporary upload response to its initiating editor ticket. */
class CatalogMainImageUploadController extends FileUploadController
{
    /** @return array{paths: list<string>} */
    public function __invoke(Request $request): array
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        abort_unless($request->hasValidRelativeSignature(), 401);
        Gate::authorize('manage-catalog');
        $pending = $request->session()->get('catalog_main_image_uploads', []);
        $nonce = $request->query('nonce');
        $ticket = is_string($nonce) ? ($pending[$nonce] ?? null) : null;
        abort_unless(is_array($ticket) && $ticket['actor_id'] === $request->user()->id && $ticket['expires_at'] >= now()->timestamp
            && $ticket['reference_hash'] === null, 403);
        $context = $ticket['context'];
        $query = app(CatalogAssetTargets::class)->eligibleQuery($request->user(), $context['target_key'], $context['owner_context']);
        if ($context['record_id'] !== null) {
            abort_unless($query->whereKey($context['record_id'])->exists(), 404);
        }
        abort_unless(FileUploadConfiguration::storage()->getAdapter() instanceof LocalFilesystemAdapter, 422);
        Validator::make(['files' => $request->file('files')], ['files' => ['required', 'array', 'size:1'],
            'files.*' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:3072']])->validate();
        $result = parent::handle();
        $paths = collect($result['paths'])->values()->all();
        abort_unless(count($paths) === 1 && is_string($paths[0]), 422);
        $nativePath = TemporaryUploadedFile::extractPathFromSignedPath($paths[0]);
        abort_unless(is_string($nativePath) && ($nativePath === basename($nativePath) || $nativePath === '/'.basename($nativePath)), 422);
        $paths[0] = TemporaryUploadedFile::signPath(basename($nativePath));
        $pending[$nonce]['reference_hash'] = hash('sha256', $paths[0]);
        $request->session()->put('catalog_main_image_uploads', $pending);

        return ['paths' => $paths];
    }
}
