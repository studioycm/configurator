<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Str;
use RuntimeException;

class CatalogAssetSourceFiles
{
    public function root(string $configuredPath): string
    {
        if ($configuredPath === '' || str_contains($configuredPath, "\0")) {
            throw new DomainException('path_invalid');
        }
        $absolute = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            ? $configuredPath : getcwd().DIRECTORY_SEPARATOR.$configuredPath;
        $parts = explode(DIRECTORY_SEPARATOR, rtrim($absolute, DIRECTORY_SEPARATOR));
        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === '.' || $part === '..') {
                throw new DomainException('path_invalid');
            }
            $current .= DIRECTORY_SEPARATOR.$part;
            clearstatcache(true, $current);
            if (is_link($current)) {
                throw new DomainException('path_invalid');
            }
        }
        $root = realpath($absolute);
        if ($root === false || ! is_dir($root) || ! is_readable($root)) {
            throw new DomainException('path_invalid');
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    public function safePath(string $root, string $relativePath, bool $directory = false): string
    {
        $root = $this->root($root);
        if ($relativePath === '' && $directory) {
            return $root;
        }
        if ($relativePath === '' || strlen($relativePath) > 1024
            || preg_match('/[\x00-\x1f\x7f\\\\]/', $relativePath)
            || preg_match('/^[A-Za-z]:/', $relativePath) || str_starts_with($relativePath, '/')) {
            throw new DomainException('path_invalid');
        }
        $path = $root;
        foreach (explode('/', $relativePath) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || preg_match('//u', $part) !== 1) {
                throw new DomainException('path_invalid');
            }
            $path .= DIRECTORY_SEPARATOR.$part;
            clearstatcache(true, $path);
            if (is_link($path)) {
                throw new DomainException('path_invalid');
            }
        }
        $canonical = realpath($path);
        if ($canonical === false || ! str_starts_with($canonical, $root.DIRECTORY_SEPARATOR)
            || ($directory ? ! is_dir($canonical) : ! is_file($canonical))) {
            throw new DomainException('path_invalid');
        }

        return $canonical;
    }

    /** @return array<string, mixed> */
    public function initialCursor(string $root): array
    {
        $identity = $this->identity($this->root($root));

        return [
            'recursive' => true,
            'frontier' => [['relative_dir' => '', ...$identity, 'ordinal' => 0, 'prefix_sha256' => hash('sha256', '')]],
            'directories' => ['' => $identity],
        ];
    }

    /**
     * @param  array<string, mixed>  $cursor
     * @param  array<string, int|list<string>>  $policy
     * @param  array<string, int>  $budgets
     * @param  array<string, mixed>  $counts
     * @return array{cursor: array<string, mixed>, entries: list<array<string, mixed>>, file_count: int, byte_count: int, visited_nodes: int, complete: bool, hashes: int, read_bytes: int}
     */
    public function scan(string $root, array $cursor, array $policy, array $budgets, array $counts): array
    {
        $root = $this->root($root);
        $this->verifyDirectories($root, $cursor);
        $fileCount = (int) $counts['file_count'];
        $byteCount = (int) $counts['byte_count'];
        $visited = (int) $counts['visited_nodes'];
        $newNodes = $hashes = $readBytes = $replayed = 0;
        $entries = $handles = [];
        $started = hrtime(true);
        if ($budgets['scan_new_nodes'] < 1 || $budgets['scan_hashes'] < 1) {
            throw new DomainException('unsupported_budget');
        }
        try {
            while ($cursor['frontier'] !== [] && $newNodes < $budgets['scan_new_nodes']) {
                $index = array_key_last($cursor['frontier']);
                $frame = $cursor['frontier'][$index];
                $relative = $frame['relative_dir'];
                $directory = $this->safePath($root, $relative, true);
                if (! isset($handles[$relative])) {
                    $handle = @opendir($directory);
                    if ($handle === false) {
                        throw new RuntimeException('Source directory is unavailable.');
                    }
                    $handles[$relative] = $handle;
                    $prefix = hash('sha256', '');
                    for ($ordinal = 0; $ordinal < $frame['ordinal']; $ordinal++) {
                        if (++$replayed > $budgets['replay_nodes']) {
                            throw new DomainException('unsupported_budget');
                        }
                        $name = $this->nextName($handle);
                        if ($name === false) {
                            throw new DomainException('source_stale');
                        }
                        $prefix = $this->prefix($prefix, $name);
                    }
                    if (! hash_equals($frame['prefix_sha256'], $prefix)) {
                        throw new DomainException('source_stale');
                    }
                }
                $name = $this->nextName($handles[$relative]);
                if ($name === false) {
                    closedir($handles[$relative]);
                    unset($handles[$relative]);
                    array_pop($cursor['frontier']);

                    continue;
                }
                $entryPath = $relative === '' ? $name : $relative.'/'.$name;
                $path = $directory.DIRECTORY_SEPARATOR.$name;
                clearstatcache(true, $path);
                $stat = @lstat($path);
                if ($stat === false) {
                    throw new DomainException('source_stale');
                }
                $type = $stat['mode'] & 0170000;
                $regular = $type === 0100000;
                $wouldRead = $regular && $stat['size'] > 0 && $stat['size'] <= $policy['max_bytes'] && $this->supportedExtension($name);
                if ($wouldRead && ($hashes >= $budgets['scan_hashes'] || $readBytes + $stat['size'] > $budgets['scan_bytes']
                    || (hrtime(true) - $started) / 1000000 >= $budgets['scan_soft_budget_ms'])) {
                    if ($newNodes === 0) {
                        throw new DomainException('unsupported_budget');
                    }
                    break;
                }
                if ($visited + 1 > $budgets['source_nodes']) {
                    throw new DomainException('source_limit');
                }
                $visited++;
                $newNodes++;
                $cursor['frontier'][$index]['ordinal']++;
                $cursor['frontier'][$index]['prefix_sha256'] = $this->prefix($frame['prefix_sha256'], $name);
                if ($type === 0040000) {
                    $path = $this->safePath($root, $entryPath, true);
                    $identity = $this->identity($path);
                    $cursor['directories'][$entryPath] = $identity;
                    if ($cursor['recursive'] ?? true) {
                        if (count($cursor['frontier']) > $budgets['source_depth']) {
                            throw new DomainException('source_limit');
                        }
                        $cursor['frontier'][] = ['relative_dir' => $entryPath, ...$identity, 'ordinal' => 0, 'prefix_sha256' => hash('sha256', '')];
                    }

                    continue;
                }
                if (! $regular) {
                    throw new DomainException('path_invalid');
                }
                if (++$fileCount > $policy['source_files'] || $byteCount + $stat['size'] > $policy['source_bytes']) {
                    throw new DomainException('source_limit');
                }
                $byteCount += $stat['size'];
                $entry = $this->inspect($root, $entryPath, $policy);
                if ($entry['bytes'] !== $stat['size']) {
                    throw new DomainException('source_stale');
                }
                $readBytes += $entry['read_bytes'];
                $hashes += $entry['read_bytes'] > 0 ? 1 : 0;
                unset($entry['read_bytes']);
                $entries[] = ['entry_id' => (string) Str::ulid(), ...$entry];
            }
            $this->verifyDirectories($root, $cursor);
        } finally {
            foreach ($handles as $handle) {
                closedir($handle);
            }
        }

        return [
            'cursor' => $cursor, 'entries' => $entries, 'file_count' => $fileCount, 'byte_count' => $byteCount,
            'visited_nodes' => $visited, 'complete' => $cursor['frontier'] === [], 'hashes' => $hashes, 'read_bytes' => $readBytes,
        ];
    }

    /**
     * @param  array<string, int|list<string>>  $policy
     * @return array<string, mixed>
     */
    public function inspect(string $root, string $relativePath, array $policy, bool $includeContents = false, ?string $displayName = null): array
    {
        $path = $this->safePath($root, $relativePath);
        $before = $this->fileIdentity($path);
        $name = $displayName ?? basename($relativePath);
        $result = [
            'name' => $name, 'relative_path' => $relativePath, 'bytes' => $before['size'], 'mime_type' => null,
            'width' => null, 'height' => null, 'sha256' => null, 'status' => 'invalid', 'code' => null, 'read_bytes' => 0,
        ];
        if ($before['size'] > $policy['max_bytes']) {
            return [...$result, 'code' => 'image_too_large'];
        }
        if ($before['size'] === 0 || ! $this->supportedExtension($name)) {
            return [...$result, 'code' => $before['size'] === 0 ? 'empty_file' : 'unsupported_extension'];
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Source file is unavailable.');
        }
        try {
            $opened = fstat($handle);
            if ($opened === false || ($opened['mode'] & 0170000) !== 0100000 || $this->statIdentity($opened) !== $before) {
                throw new DomainException('source_stale');
            }
            $contents = stream_get_contents($handle, $policy['max_bytes'] + 1);
            if ($contents === false) {
                throw new RuntimeException('Source file could not be read.');
            }
            if ($this->statIdentity(fstat($handle)) !== $before || strlen($contents) !== $before['size']) {
                throw new DomainException('source_stale');
            }
        } finally {
            fclose($handle);
        }
        if ($this->safePath($root, $relativePath) !== $path || $this->fileIdentity($path) !== $before) {
            throw new DomainException('source_stale');
        }
        $result['read_bytes'] = strlen($contents);
        $result['mime_type'] = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: null;
        if (! in_array($result['mime_type'], $policy['mime_types'], true)) {
            return [...$result, 'code' => 'unsupported_mime'];
        }
        $dimensions = @getimagesizefromstring($contents);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            return [...$result, 'code' => 'invalid_dimensions'];
        }
        [$result['width'], $result['height']] = $dimensions;
        if ($result['height'] > intdiv($policy['max_pixels'], $result['width'])) {
            return [...$result, 'code' => 'pixel_limit'];
        }
        $result['sha256'] = hash('sha256', $contents);
        $result['status'] = 'valid';
        if ($includeContents) {
            $result['contents'] = $contents;
        }

        return $result;
    }

    /** @param array<string, mixed> $cursor */
    public function verifyDirectories(string $root, array $cursor): void
    {
        foreach ($cursor['directories'] ?? [] as $relative => $identity) {
            $path = $this->safePath($root, $relative, true);
            if ($this->identity($path) != $identity) {
                throw new DomainException('source_stale');
            }
        }
    }

    /** @return array{dev: int, ino: int, mtime: int, ctime: int, size: int} */
    private function identity(string $path): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) {
            throw new DomainException('source_stale');
        }

        return $this->statIdentity($stat);
    }

    /** @return array{dev: int, ino: int, mtime: int, ctime: int, size: int} */
    private function fileIdentity(string $path): array
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000) {
            throw new DomainException('source_stale');
        }

        return $this->statIdentity($stat);
    }

    /**
     * @param  array<string|int, int>|false  $stat
     * @return array{dev: int, ino: int, mtime: int, ctime: int, size: int}
     */
    private function statIdentity(array|false $stat): array
    {
        if ($stat === false) {
            throw new DomainException('source_stale');
        }

        return array_intersect_key($stat, array_flip(['dev', 'ino', 'mtime', 'ctime', 'size']));
    }

    /** @param resource $handle */
    private function nextName(mixed $handle): string|false
    {
        do {
            $name = readdir($handle);
        } while ($name === '.' || $name === '..');

        return $name;
    }

    private function prefix(string $prefix, string $name): string
    {
        if (preg_match('//u', $name) !== 1) {
            throw new DomainException('path_invalid');
        }

        return hash('sha256', $prefix."\0".$name);
    }

    private function supportedExtension(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
