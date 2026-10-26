<?php

namespace App\Filament\Exports;

use App\Services\CatalogCsv;
use App\Services\CatalogDataExchangePolicy;
use Filament\Actions\Exports\Models\Export;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Writer;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Private immutable evidence and checked I/O. CatalogDataExchangePolicy owns readiness decisions.
 *
 * @phpstan-type Artifact array{path: string, sha256: string, bytes: int}
 * @phpstan-type PagePlan array{page: int, records: list<string>, binding: string}
 * @phpstan-type Capture array{page: int, binding: string, successful: list<string>, rejected: list<array{id: string, reason: string}>, artifact: Artifact}
 */
class CatalogExportFiles
{
    public function __construct(private Export $export) {}

    /** @param array<string, string> $columns @param array<string, mixed> $options @param list<string> $formats @param iterable<list<int|string>> $pages */
    public function seal(array $columns, array $options, array $formats, string $query, iterable $pages): void
    {
        DB::transaction(function () use ($columns, $options, $formats, $query, $pages): void {
            $this->lockedExport();
            $context = ['version' => CatalogDataExchangePolicy::VERSION, 'export_id' => $this->export->id, 'exporter' => $this->export->exporter, 'user_id' => $this->export->user_id, 'file_name' => $this->export->file_name, 'file_disk' => $this->export->file_disk, 'columns' => $columns, 'options' => $options, 'formats' => $formats, 'query_hash' => hash('sha256', $query), 'delimiter' => $this->export->exporter::getCsvDelimiter(), 'total_rows' => $this->export->total_rows];
            $binding = $this->digest($context);
            $header = app(CatalogCsv::class)->writer($context['delimiter'], bom: true);
            $header->insertOne(array_values($columns));
            $headerArtifact = $this->writeWriter($this->path('header.csv'), $header);
            $index = [];
            $count = 0;
            foreach ($pages as $records) {
                $page = count($index) + 1;
                $records = array_map('strval', array_values($records));
                if ($records === [] || count(array_unique($records)) !== count($records)) {
                    throw new RuntimeException('Invalid selected export identities.');
                }
                $data = ['page' => $page, 'records' => $records, 'binding' => $this->digest([$binding, $page, $records])];
                $this->writeJson($this->path('plans/'.$page.'.json'), $data);
                $index[] = ['page' => $page, 'sha256' => $this->digest($data), 'count' => count($records)];
                $count += count($records);
            }
            $plan = ['context' => $context, 'binding' => $binding, 'header' => $headerArtifact, 'pages' => $index, 'planned_rows' => $count, 'sealed' => true];
            $this->writeJson($this->path('plan.json'), $plan);
            $counts = $this->counts();
            $this->lockedExport()->forceFill(['processed_rows' => $counts['successful'] + $counts['rejected'], 'successful_rows' => $counts['successful']])->save();
            DB::afterCommit(fn () => $this->publishHeader());
        });
    }

    /** @return array<string, mixed> */
    public function plan(): array
    {
        $plan = $this->readJson($this->path('plan.json'));
        $context = $plan['context'] ?? [];
        if (($plan['sealed'] ?? false) !== true || ($context['version'] ?? null) !== CatalogDataExchangePolicy::VERSION
            || ($context['export_id'] ?? null) !== $this->export->id || ($context['exporter'] ?? null) !== $this->export->exporter
            || ($context['file_disk'] ?? null) !== $this->export->file_disk || ($context['user_id'] ?? null) !== $this->export->user_id || ($context['file_name'] ?? null) !== $this->export->file_name
            || ($plan['binding'] ?? null) !== $this->digest($context) || ! is_array($plan['pages'] ?? null)) {
            throw new RuntimeException('Export preparation is absent or inconsistent.');
        }

        return $plan;
    }

    /** @return PagePlan */
    public function pagePlan(int $page): array
    {
        $plan = $this->plan();
        $reference = $plan['pages'][$page - 1] ?? null;
        $data = $this->readJson($this->path('plans/'.$page.'.json'));
        if (($reference['page'] ?? null) !== $page || ($data['page'] ?? null) !== $page || ! is_array($data['records'] ?? null)
            || ($reference['sha256'] ?? null) !== $this->digest($data) || ($data['binding'] ?? null) !== $this->digest([$plan['binding'], $page, $data['records']])) {
            throw new RuntimeException('Export page does not match the sealed plan.');
        }

        return $data;
    }

    /** @param list<int|string> $records @param array<string,string> $columns @param array<string,mixed> $options */
    public function assertPayload(int $page, array $records, string $query, array $columns, array $options): void
    {
        $plan = $this->plan();
        if ($this->pagePlan($page)['records'] !== array_map('strval', $records) || $plan['context']['query_hash'] !== hash('sha256', $query)
            || $plan['context']['columns'] !== $columns || $plan['context']['options'] !== $options) {
            throw new RuntimeException('Queued export payload conflicts with the sealed selection.');
        }
    }

    /** @return Capture|null */
    public function capture(int $page): ?array
    {
        if (! $this->disk()->exists($this->path('receipts/'.$page.'.json'))) {
            return null;
        }
        $capture = $this->readJson($this->path('receipts/'.$page.'.json'));
        $this->assertCapture($capture, $this->pagePlan($page));

        return $capture;
    }

    /** @param list<string> $successful @param list<array{id:string,reason:string}> $rejected */
    public function capturePage(int $page, Writer $csv, array $successful, array $rejected): void
    {
        $plan = $this->pagePlan($page);
        $artifact = $this->writeWriter($this->path('staged/'.Str::uuid().'.csv'), $csv);
        $capture = ['page' => $page, 'binding' => $plan['binding'], 'successful' => $successful, 'rejected' => $rejected, 'artifact' => $artifact];
        $this->assertCapture($capture, $plan);
        $this->assertCsvLayout($artifact, count($successful));
        $this->adopt($capture);
    }

    /** Reconcile the captured identity; never obtain newer source values. @param Capture $capture */
    public function adopt(array $capture): void
    {
        DB::transaction(function () use ($capture): void {
            $export = $this->lockedExport();
            $this->assertCapture($capture, $this->pagePlan($capture['page']));
            if (app(CatalogDataExchangePolicy::class)->retryDecision(['capture_verified' => $this->matches($capture['artifact'])]) !== 'reuse-verified-capture') {
                throw new RuntimeException('The captured export page cannot be verified.');
            }
            $this->writeJson($this->path('receipts/'.$capture['page'].'.json'), $capture);
            $counts = $this->counts();
            $export->forceFill(['processed_rows' => $counts['successful'] + $counts['rejected'], 'successful_rows' => $counts['successful']])->save();
            DB::afterCommit(fn () => $this->publishPage($capture['page']));
        });
    }

    /** @return array{planned:int, successful:int, rejected:int, unconfirmed:int, partial:bool, csv:array{state:string,download:bool}, xlsx:array{state:string,download:bool}} */
    public function assessment(): array
    {
        $export = $this->export = $this->export->fresh();
        $facts = ['version' => $export->catalog_artifact_version === null ? null : (int) $export->catalog_artifact_version, 'planned' => $export->total_rows];
        if ($facts['version'] === null) {
            $facts += ['successful' => $export->successful_rows, 'rejected' => max(0, $export->processed_rows - $export->successful_rows), 'unconfirmed' => max(0, $export->total_rows - $export->processed_rows)];
            $consistent = $export->processed_rows === $export->total_rows && $export->successful_rows > 0 && $export->successful_rows <= $export->processed_rows;
            $facts['historical'] = ['consistent' => $consistent, ...$this->historicalArtifacts()];

            return app(CatalogDataExchangePolicy::class)->assessExport($facts);
        }
        try {
            $plan = $this->plan();
            $counts = $this->counts();
            $csv = $this->published($this->path('published/header.json'), $plan['header'], $plan['binding'], $export->getFileDirectory().'/headers.csv');
            $published = $csv['publication'] === 'published';
            foreach ($plan['pages'] as $reference) {
                $capture = $this->capture($reference['page']);
                $page = $capture === null ? ['verified' => false, 'publication' => 'unknown'] : $this->published($this->path('published/'.$reference['page'].'.json'), $capture['artifact'], $this->digest($capture), $this->pagePath($reference['page']));
                $csv['verified'] = $csv['verified'] && $page['verified'];
                $published = $published && $page['publication'] === 'published';
            }
            $csv['publication'] = $published ? 'published' : 'unknown';
            $workbook = $this->readJson($this->path('workbook.json'), required: false);
            $xlsx = ['verified' => false, 'publication' => $this->disk()->exists($this->path('workbook-failed.json')) ? 'failed' : 'staged'];
            if ($workbook !== null && ($workbook['input'] ?? null) === $this->captureBinding() && ($workbook['rows'] ?? null) === $counts['successful']) {
                $xlsx = $this->published($this->path('published/workbook.json'), $workbook['artifact'], $this->digest($workbook), $this->workbookPath());
            }
            $projection = $export->processed_rows === $counts['successful'] + $counts['rejected'] && $export->successful_rows === $counts['successful'] && $export->total_rows === $counts['planned'];
            $facts = [...$facts, ...$counts, 'sealed' => true, 'evidence_valid' => $plan['planned_rows'] === $counts['planned'], 'commit_outcome' => DB::transactionLevel() !== 0 ? 'pending-enclosing-commit' : ($projection && ($published || $xlsx['publication'] === 'published') ? 'known-committed' : 'unknown'), 'csv' => $csv, 'xlsx' => $xlsx];
        } catch (Throwable $exception) {
            report($exception);
        }

        return app(CatalogDataExchangePolicy::class)->assessExport($facts);
    }

    /** Basic readable-file/numbering checks only; historical bytes are never rewritten or claimed reverified. @return array{csv:bool,xlsx:bool} */
    private function historicalArtifacts(): array
    {
        $csv = $this->readable($this->export->getFileDirectory().'/headers.csv', nonempty: true);
        $pages = [];
        try {
            foreach ($this->disk()->files($this->export->getFileDirectory()) as $path) {
                if (preg_match('/\/([0-9]+)\.csv$/D', $path, $matches) === 1) {
                    $pages[] = (int) $matches[1];
                    $csv = $csv && $this->readable($path);
                } elseif (str_ends_with($path, '.csv') && basename($path) !== 'headers.csv') {
                    $csv = false;
                }
            }
        } catch (Throwable) {
            $csv = false;
        }
        sort($pages, SORT_NUMERIC);
        $csv = $csv && $pages !== [] && $pages === range(1, count($pages));

        return ['csv' => $csv, 'xlsx' => $this->readable($this->workbookPath(), nonempty: true)];
    }

    private function readable(string $path, bool $nonempty = false): bool
    {
        try {
            $stream = $this->disk()->readStream($path);
            if (! is_resource($stream)) {
                return false;
            }
            try {
                $firstByte = fread($stream, 1);

                return $firstByte !== false && (! $nonempty || $firstByte !== '');
            } finally {
                fclose($stream);
            }
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{planned:int,successful:int,rejected:int,unconfirmed:int} */
    private function counts(): array
    {
        $plan = $this->plan();
        $seen = [];
        $successful = $rejected = 0;
        foreach ($plan['pages'] as $reference) {
            $page = $this->pagePlan($reference['page']);
            foreach ($page['records'] as $id) {
                if (isset($seen[$id])) {
                    throw new RuntimeException('A selected identity occurs on more than one export page.');
                }
                $seen[$id] = true;
            }
            $capture = $this->capture($reference['page']);
            if ($capture !== null) {
                $successful += count($capture['successful']);
                $rejected += count($capture['rejected']);
            }
        }
        $planned = $plan['context']['total_rows'];
        if (count($seen) !== $plan['planned_rows'] || $successful + $rejected > $planned) {
            throw new RuntimeException('The export accounting does not match its sealed plan.');
        }

        return ['planned' => $planned, 'successful' => $successful, 'rejected' => $rejected, 'unconfirmed' => max(0, $planned - $successful - $rejected)];
    }

    /** @param Capture $capture @param PagePlan $page */
    private function assertCapture(array $capture, array $page): void
    {
        $successful = $capture['successful'] ?? [];
        $rejected = array_column($capture['rejected'] ?? [], 'id');
        $ids = [...$successful, ...$rejected];
        $expected = $page['records'];
        sort($ids, SORT_STRING);
        sort($expected, SORT_STRING);
        if (($capture['page'] ?? null) !== $page['page'] || ($capture['binding'] ?? null) !== $page['binding'] || $ids !== $expected
            || count(array_unique($ids)) !== count($ids) || ! is_array($capture['artifact'] ?? null)
            || ! str_starts_with($capture['artifact']['path'] ?? '', $this->path('staged/'))) {
            throw new RuntimeException('Captured page membership is inconsistent.');
        }
        if ($successful !== array_values(array_filter($page['records'], fn (string $id): bool => in_array($id, $successful, true)))) {
            throw new RuntimeException('Captured export row order is inconsistent.');
        }
    }

    private function publishHeader(): void
    {
        $plan = $this->plan();
        $this->publish($plan['header'], $this->export->getFileDirectory().'/headers.csv', $this->path('published/header.json'), $plan['binding']);
    }

    private function publishPage(int $page): void
    {
        $capture = $this->capture($page);
        if ($capture === null) {
            throw new RuntimeException('Cannot publish a missing capture.');
        }
        $this->publish($capture['artifact'], $this->pagePath($page), $this->path('published/'.$page.'.json'), $this->digest($capture));
    }

    /** @param Artifact $artifact */
    private function publish(array $artifact, string $destination, string $receipt, string $binding): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Publication requires the real enclosing commit.');
        }
        DB::transaction(function () use ($artifact, $destination, $receipt, $binding): void {
            $export = $this->lockedExport();
            $counts = $this->counts();
            if ($export->processed_rows !== $counts['successful'] + $counts['rejected'] || $export->successful_rows !== $counts['successful']) {
                throw new RuntimeException('Committed export projection cannot be verified.');
            }
            if (! $this->matches($artifact)) {
                throw new RuntimeException('Staged export bytes cannot be verified.');
            }
            $this->copyOnce($artifact, $destination);
            $this->writeJson($receipt, ['binding' => $binding, 'artifact' => [...$artifact, 'path' => $destination], 'projection_verified' => true]);
        });
    }

    /** @param Artifact $artifact @return array{verified:bool,publication:string} */
    private function published(string $receipt, array $artifact, string $binding, string $destination): array
    {
        $published = $this->readJson($receipt, required: false);
        if ($published === null || ($published['binding'] ?? null) !== $binding || ($published['projection_verified'] ?? false) !== true
            || ($published['artifact'] ?? null) !== [...$artifact, 'path' => $destination]) {
            return ['verified' => false, 'publication' => 'unknown'];
        }

        return ['verified' => $this->matches($published['artifact']), 'publication' => 'published'];
    }

    /** Ordered captured CSV only; no file enumeration or current-source reconstruction. @return Generator<string> */
    public function csvChunks(): Generator
    {
        $plan = $this->plan();
        $paths = [$this->export->getFileDirectory().'/headers.csv'];
        foreach ($plan['pages'] as $reference) {
            $paths[] = $this->pagePath($reference['page']);
        }
        foreach ($paths as $path) {
            $stream = $this->disk()->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Cannot read the verified export.');
            }
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        throw new RuntimeException('Export read failed.');
                    }
                    yield $chunk;
                }
            } finally {
                fclose($stream);
            }
        }
    }

    public function captureBinding(): string
    {
        $plan = $this->plan();
        $captures = [];
        foreach ($plan['pages'] as $reference) {
            $capture = $this->capture($reference['page']);
            if ($capture === null) {
                throw new RuntimeException('Cannot bind a workbook to incomplete capture.');
            }
            $captures[] = $this->digest($capture);
        }

        return $this->digest([$plan, $captures]);
    }

    /** @param resource $stream */
    public function captureWorkbook(mixed $stream, int $rows): void
    {
        if ($rows !== $this->counts()['successful']) {
            throw new RuntimeException('Workbook row count does not match captured rows.');
        }
        $this->assertWorkbookLayout($stream, $rows);
        $artifact = $this->writeStream($this->path('staged/'.Str::uuid().'.xlsx'), $stream);
        $workbook = ['input' => $this->captureBinding(), 'rows' => $rows, 'artifact' => $artifact];
        DB::transaction(function () use ($workbook): void {
            $this->lockedExport();
            $this->writeJson($this->path('workbook.json'), $workbook);
            DB::afterCommit(fn () => $this->publish($workbook['artifact'], $this->workbookPath(), $this->path('published/workbook.json'), $this->digest($workbook)));
        });
    }

    /** @return bool True if an existing immutable workbook was reconciled; never regenerate an adopted workbook. */
    public function reuseWorkbook(): bool
    {
        $workbook = $this->readJson($this->path('workbook.json'), required: false);
        if ($workbook === null) {
            return false;
        }
        if (($workbook['input'] ?? null) !== $this->captureBinding()) {
            throw new RuntimeException('Workbook input evidence conflicts.');
        }
        $this->publish($workbook['artifact'], $this->workbookPath(), $this->path('published/workbook.json'), $this->digest($workbook));

        return true;
    }

    public function workbookFailed(): void
    {
        $this->writeJson($this->path('workbook-failed.json'), ['failed' => true]);
    }

    /** Claim before sending; an ambiguous notification attempt is not blindly repeated. */
    public function claimNotification(string $digest): bool
    {
        return DB::transaction(function () use ($digest): bool {
            $this->lockedExport();
            $path = $this->path('notifications/'.$digest.'.json');
            if ($this->disk()->exists($path)) {
                return false;
            }
            $this->writeJson($path, ['attempted' => true]);

            return true;
        });
    }

    public function workbookPath(): string
    {
        return $this->export->getFileDirectory().'/'.$this->export->file_name.'.xlsx';
    }

    /** @param Artifact $artifact */
    private function assertCsvLayout(array $artifact, int $expectedRows): void
    {
        $stream = $this->disk()->readStream($artifact['path']);
        if (! is_resource($stream)) {
            throw new RuntimeException('Cannot inspect captured CSV layout.');
        }
        try {
            $plan = $this->plan();
            $reader = app(CatalogCsv::class)->reader($stream, $plan['context']['delimiter']);
            app(CatalogCsv::class)->validate($reader);
            $rows = 0;
            foreach ($reader->getRecords() as $record) {
                if (count($record) !== count($plan['context']['columns'])) {
                    throw new RuntimeException('Captured CSV column layout is inconsistent.');
                }
                $rows++;
            }
            if ($rows !== $expectedRows) {
                throw new RuntimeException('Captured CSV row count is inconsistent.');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function assertWorkbookLayout(mixed $stream, int $expectedRows): void
    {
        $temporary = tmpfile();
        if ($temporary === false) {
            throw new RuntimeException('Cannot inspect workbook layout.');
        }
        $reader = new Reader;
        try {
            rewind($stream);
            if (stream_copy_to_stream($stream, $temporary) === false) {
                throw new RuntimeException('Cannot inspect complete workbook bytes.');
            }
            $reader->open(stream_get_meta_data($temporary)['uri']);
            $header = array_values($this->plan()['context']['columns']);
            $rows = $sheets = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets++;
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    if (($rows === 0 && $values !== $header) || count($values) !== count($header)) {
                        throw new RuntimeException('Workbook column layout is inconsistent.');
                    }
                    $rows++;
                }
            }
            if ($sheets !== 1 || $rows !== $expectedRows + 1) {
                throw new RuntimeException('Workbook row layout is inconsistent.');
            }
        } finally {
            $reader->close();
            fclose($temporary);
        }
    }

    private function lockedExport(): Export
    {
        $this->export = $this->export::whereKey($this->export->id)->lockForUpdate()->firstOrFail();

        return $this->export;
    }

    private function pagePath(int $page): string
    {
        return $this->export->getFileDirectory().'/'.str_pad((string) $page, 16, '0', STR_PAD_LEFT).'.csv';
    }

    private function path(string $relative): string
    {
        return $this->export->getFileDirectory().'/.catalog/'.$relative;
    }

    private function disk(): Filesystem
    {
        return $this->export->getFileDisk();
    }

    /** @param array<mixed> $data */
    private function digest(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** @param array<mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $this->writeOnce($path, json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** @return array<mixed>|null */
    private function readJson(string $path, bool $required = true): ?array
    {
        if (! $this->disk()->exists($path)) {
            if ($required) {
                throw new RuntimeException('Required export evidence is missing.');
            }

            return null;
        }
        $data = json_decode($this->disk()->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid export evidence.');
        }

        return $data;
    }

    private function writeOnce(string $path, string $bytes): void
    {
        if ($this->disk()->exists($path)) {
            if ($this->disk()->get($path) !== $bytes) {
                throw new RuntimeException('Conflicting immutable export evidence.');
            }

            return;
        }
        if (! $this->disk()->put($path, $bytes, 'private') || $this->disk()->get($path) !== $bytes) {
            throw new RuntimeException('Export evidence could not be written and verified.');
        }
    }

    /** @return Artifact */
    private function writeWriter(string $path, Writer $writer): array
    {
        $stream = tmpfile();
        if ($stream === false) {
            throw new RuntimeException('Cannot stage export bytes.');
        }
        try {
            foreach ($writer->chunk(8192) as $chunk) {
                if (fwrite($stream, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Cannot stage complete export bytes.');
                }
            }

            return $this->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream @return Artifact */
    private function writeStream(string $path, mixed $stream): array
    {
        rewind($stream);
        $hash = hash_init('sha256');
        $bytes = hash_update_stream($hash, $stream);
        $artifact = ['path' => $path, 'sha256' => hash_final($hash), 'bytes' => $bytes];
        rewind($stream);
        if ($this->disk()->exists($path)) {
            if (! $this->matches($artifact)) {
                throw new RuntimeException('Conflicting export artifact.');
            }
        } elseif (! $this->disk()->put($path, $stream, 'private')) {
            throw new RuntimeException('Export artifact write failed.');
        }
        if (! $this->matches($artifact)) {
            throw new RuntimeException('Export artifact verification failed.');
        }

        return $artifact;
    }

    /** @param Artifact $artifact */
    private function matches(array $artifact): bool
    {
        if (! is_string($artifact['path'] ?? null) || ! str_starts_with($artifact['path'], $this->export->getFileDirectory().'/') || ! $this->disk()->exists($artifact['path'])) {
            return false;
        }
        $stream = $this->disk()->readStream($artifact['path']);
        if (! is_resource($stream)) {
            return false;
        }
        try {
            $hash = hash_init('sha256');
            $size = hash_update_stream($hash, $stream);

            return $size === ($artifact['bytes'] ?? null) && hash_final($hash) === ($artifact['sha256'] ?? null);
        } finally {
            fclose($stream);
        }
    }

    /** @param Artifact $artifact */
    private function copyOnce(array $artifact, string $destination): void
    {
        if ($this->disk()->exists($destination)) {
            if (! $this->matches([...$artifact, 'path' => $destination])) {
                throw new RuntimeException('Conflicting published export bytes.');
            }

            return;
        }
        $stream = $this->disk()->readStream($artifact['path']);
        if (! is_resource($stream)) {
            throw new RuntimeException('Cannot read captured export bytes.');
        }
        try {
            if (! $this->disk()->put($destination, $stream, 'private') || ! $this->matches([...$artifact, 'path' => $destination])) {
                throw new RuntimeException('Export publication failed.');
            }
        } finally {
            fclose($stream);
        }
    }
}
