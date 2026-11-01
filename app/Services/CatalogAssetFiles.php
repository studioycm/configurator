<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

class CatalogAssetFiles
{
    public function __construct(private CatalogAssetSourceFiles $files) {}

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $policy
     * @return array{path: string, metadata: array<string, mixed>}
     */
    public function workingCopy(array $selection, string $destination, array $policy): array
    {
        $disk = Storage::disk('catalog-asset-imports');
        if (config('filesystems.disks.catalog-asset-imports.driver') !== 'local') {
            throw new LogicException('Preparation requires local private storage.');
        }
        if (! $disk->makeDirectory(dirname($destination))) {
            throw new RuntimeException('Private working storage is unavailable.');
        }
        $root = $this->files->root($disk->path(''));
        $this->files->safePath($root, dirname($destination), true);
        $sourcePath = $this->files->safePath($selection['root'], $selection['relative_path']);
        clearstatcache(true, $sourcePath);
        $before = lstat($sourcePath);
        if ($before === false || ($before['mode'] & 0170000) !== 0100000 || $before['size'] > $policy['max_bytes']) {
            throw new DomainException('source_stale');
        }
        $input = @fopen($sourcePath, 'rb');
        $output = @fopen($disk->path($destination), 'x+b');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException('An immutable private working copy could not be opened.');
        }
        try {
            if (! chmod($disk->path($destination), 0600)) {
                throw new RuntimeException('Private working permissions could not be set.');
            }
            $opened = fstat($input);
            if (! $this->sameIdentity($before, $opened)) {
                throw new DomainException('source_stale');
            }
            $read = 0;
            while (! feof($input) && $read <= $policy['max_bytes']) {
                $chunk = $this->readSourceChunk($input, min(65536, $policy['max_bytes'] + 1 - $read));
                $read += strlen($chunk);
                if ($read > $policy['max_bytes']) {
                    throw new DomainException('image_too_large');
                }
                $offset = 0;
                while ($offset < strlen($chunk)) {
                    $written = fwrite($output, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new RuntimeException('A private working write could not complete.');
                    }
                    $offset += $written;
                }
            }
            if (! $this->sameIdentity($before, fstat($input)) || $read !== $before['size'] || ! fflush($output)) {
                throw new DomainException('source_stale');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        clearstatcache(true, $sourcePath);
        if ($this->files->safePath($selection['root'], $selection['relative_path']) !== $sourcePath || ! $this->sameIdentity($before, lstat($sourcePath))) {
            throw new DomainException('source_stale');
        }
        $metadata = $this->verify($root, $destination, $selection['entry'], $policy, $selection['entry']['name'], true);

        return ['path' => $disk->path($destination), 'metadata' => $metadata];
    }

    /** @param resource $handle */
    protected function readSourceChunk(mixed $handle, int $length): string
    {
        $chunk = fread($handle, $length);
        if ($chunk === false || ($chunk === '' && ! feof($handle))) {
            throw new RuntimeException('A source read could not complete.');
        }

        return $chunk;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function verify(string $root, string $relativePath, array $expected, array $policy, string $displayName, bool $decode = false): array
    {
        $actual = $this->files->inspect($root, $relativePath, $policy, $decode, $displayName);
        if ($actual['status'] !== 'valid' || ! is_string($actual['sha256']) || ! hash_equals($expected['sha256'], $actual['sha256'])
            || $actual['bytes'] !== $expected['bytes'] || $actual['mime_type'] !== $expected['mime_type']
            || $actual['width'] !== $expected['width'] || $actual['height'] !== $expected['height']) {
            throw new DomainException('image_invalid');
        }
        if ($decode) {
            $image = @imagecreatefromstring($actual['contents']);
            if ($image === false) {
                throw new DomainException('image_decode_failed');
            }
            try {
                if (imagesx($image) !== $actual['width'] || imagesy($image) !== $actual['height']) {
                    throw new DomainException('image_decode_failed');
                }
            } finally {
                imagedestroy($image);
            }
        }

        return array_intersect_key($actual, array_flip(['bytes', 'mime_type', 'width', 'height', 'sha256']));
    }

    /** @param array<string|int, mixed>|false $before
     * @param  array<string|int, mixed>|false  $after
     */
    private function sameIdentity(array|false $before, array|false $after): bool
    {
        if ($before === false || $after === false || ($after['mode'] & 0170000) !== 0100000) {
            return false;
        }
        foreach (['dev', 'ino', 'size', 'mtime', 'ctime'] as $key) {
            if ($before[$key] !== $after[$key]) {
                return false;
            }
        }

        return true;
    }
}
