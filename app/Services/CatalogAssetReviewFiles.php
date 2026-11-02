<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

class CatalogAssetReviewFiles
{
    private const MaxBytes = 16777216;

    public function __construct(private CatalogAssetSourceFiles $files) {}

    public function digest(array $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    private function encode(array $value): string
    {
        $sort = function (mixed $input) use (&$sort): mixed {
            if (! is_array($input)) {
                return $input;
            }
            if (! array_is_list($input)) {
                ksort($input);
            }

            return array_map($sort, $input);
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function write(string $reviewId, int $version, string $kind, array $value): array
    {
        $this->identity($reviewId, $version, $kind);
        $body = $this->encode($value);
        if (strlen($body) > self::MaxBytes) {
            throw new RuntimeException('The private review artifact exceeds its safe storage bound.');
        }
        $disk = Storage::disk('catalog-asset-imports');
        $root = $this->root();
        $relative = 'reviews/'.$reviewId.'/v'.$version.'/'.$kind.'/'.Str::ulid().'.json';
        $disk->makeDirectory(dirname($relative));
        $this->files->safePath($root, dirname($relative), true);
        $handle = @fopen($disk->path($relative), 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Private review storage is unavailable.');
        }
        try {
            if (! chmod($disk->path($relative), 0600)) {
                throw new RuntimeException('Private review storage could not be protected.');
            }
            $offset = 0;
            while ($offset < strlen($body)) {
                $written = fwrite($handle, substr($body, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Private review storage could not be written.');
                }
                $offset += $written;
            }
            if (! fflush($handle)) {
                throw new RuntimeException('Private review storage could not be flushed.');
            }
        } finally {
            fclose($handle);
        }
        $this->files->safePath($root, $relative);

        return ['path' => $relative, 'sha256' => hash('sha256', $body)];
    }

    public function read(string $reviewId, int $version, string $kind, string $path, string $sha256): array
    {
        $this->identity($reviewId, $version, $kind);
        if (! str_starts_with($path, 'reviews/'.$reviewId.'/v'.$version.'/'.$kind.'/') || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new RuntimeException('The private review evidence is invalid.');
        }
        $absolute = $this->files->safePath($this->root(), $path);
        $handle = @fopen($absolute, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The private review evidence is unavailable.');
        }
        try {
            $body = stream_get_contents($handle, self::MaxBytes + 1);
        } finally {
            fclose($handle);
        }
        if ($body === false || strlen($body) > self::MaxBytes || ! hash_equals($sha256, hash('sha256', $body))) {
            throw new RuntimeException('The private review evidence is unavailable or changed.');
        }
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('The private review evidence is invalid.');
        }

        return $decoded;
    }

    public function launchToken(User $actor, string $reviewId, array $artifact): string
    {
        return Crypt::encryptString($this->encode(['actor_id' => $actor->id, 'review_id' => $reviewId, ...$artifact]));
    }

    public function openLaunch(User $actor, string $token): array
    {
        try {
            if (strlen($token) > 32768) {
                throw new RuntimeException;
            }
            $claims = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            if (($claims['actor_id'] ?? null) !== $actor->id || ! Str::isUlid($claims['review_id'] ?? '')) {
                throw new RuntimeException;
            }
            $scope = $this->read($claims['review_id'], 1, 'scope', $claims['path'], $claims['sha256']);
            if (($scope['actor_id'] ?? null) !== $actor->id || $scope['review_id'] !== $claims['review_id']) {
                throw new RuntimeException;
            }

            return [...$scope, 'scope_path' => $claims['path'], 'scope_sha256' => $claims['sha256']];
        } catch (\Throwable) {
            throw ValidationException::withMessages(['review' => 'This frozen review scope is unavailable. Return to records and choose it again.']);
        }
    }

    private function identity(string $reviewId, int $version, string $kind): void
    {
        if (! Str::isUlid($reviewId) || $version < 1 || ! in_array($kind, ['scope', 'manifest', 'index'], true)) {
            throw new RuntimeException('Choose a valid private review artifact.');
        }
    }

    private function root(): string
    {
        $disk = Storage::disk('catalog-asset-imports');
        if (! $disk->getAdapter() instanceof LocalFilesystemAdapter) {
            throw new RuntimeException('Private review artifacts require local import storage.');
        }

        return $this->files->root($disk->path(''));
    }
}
