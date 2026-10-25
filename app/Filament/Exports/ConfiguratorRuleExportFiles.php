<?php

namespace App\Filament\Exports;

use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\Exports\Models\Export;
use League\Csv\Reader;
use RuntimeException;
use Throwable;

/**
 * @phpstan-type Page array{version:int,export_id:string,page:int,record_ids:list<string>,payload_hash:string,processed:int,successful:int,columns:list<string>,csv_sha256:string,staged_csv:string}
 * @phpstan-type Manifest array{lookups:list<array<string,string>>,failures:list<array{rule_id:string,message:string}>,recovery?:Page}
 */
class ConfiguratorRuleExportFiles
{
    public function __construct(private Export $export) {}

    public function base(int $page): string
    {
        return $this->export->getFileDirectory().DIRECTORY_SEPARATOR.str_pad((string) $page, 16, '0', STR_PAD_LEFT);
    }

    /** @return Manifest|null */
    public function page(int $page): ?array
    {
        $path = $this->base($page).'.json';
        $disk = $this->export->getFileDisk();
        if (! $disk->exists($path)) {
            return null;
        }
        $metadata = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($metadata)) {
            throw new RuntimeException('The Rules export page metadata cannot be reconciled.');
        }

        return $metadata;
    }

    /** @param array{path:string,metadata:Manifest}|null $candidate @return array{processed:int,successful:int,pages:array<string,Manifest>} */
    public function accounting(?array $candidate = null, bool $legacy = false): array
    {
        $disk = $this->export->getFileDisk();
        $pages = [];
        foreach ($disk->files($this->export->getFileDirectory()) as $path) {
            if (preg_match('/\A[0-9]{16}\.json\z/D', basename($path))) {
                $pages[$path] = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
            }
        }
        if ($candidate !== null) {
            $pages[$candidate['path']] = $candidate['metadata'];
        }
        ksort($pages, SORT_STRING);
        $processed = $successful = 0;
        $seen = [];
        $columns = null;
        foreach ($pages as $path => $metadata) {
            if (! is_array($metadata) || ! is_array($metadata['lookups'] ?? null) || ! is_array($metadata['failures'] ?? null) || ! array_is_list($metadata['failures'])) {
                throw new RuntimeException('The Rules export page metadata cannot be reconciled.');
            }
            $page = $metadata['recovery'] ?? null;
            if ($page === null && ! $legacy) {
                throw new RuntimeException('This older Rules export has no replay identity. Create a new export instead of replaying it.');
            }
            if ($page !== null) {
                $this->validatePage($path, $page);
                $pageColumns = $page['columns'];
                $source = $this->source($path, $metadata);
            } else {
                $pageColumns = $this->headers();
                $source = substr($path, 0, -5).'.csv';
            }
            if ($columns !== null && $columns !== $pageColumns) {
                throw new RuntimeException('The Rules export page layouts do not match.');
            }
            $columns = $pageColumns;
            $successIds = $this->csvRuleIds($source, $pageColumns);
            $failureIds = [];
            foreach ($metadata['failures'] as $failure) {
                if (! is_array($failure) || ! is_string($failure['rule_id'] ?? null) || ! ctype_digit($failure['rule_id']) || (int) $failure['rule_id'] < 1) {
                    throw new RuntimeException('The Rules export rejection metadata cannot be reconciled.');
                }
                $failureIds[] = (string) (int) $failure['rule_id'];
            }
            $ids = [...$successIds, ...$failureIds];
            if (count(array_unique($ids, SORT_STRING)) !== count($ids)) {
                throw new RuntimeException('A Rules export page accounts for the same Rule more than once.');
            }
            if ($page !== null) {
                $expected = $page['record_ids'];
                sort($expected, SORT_STRING);
                $actual = $ids;
                sort($actual, SORT_STRING);
                if ($expected !== $actual || $page['processed'] !== count($ids) || $page['successful'] !== count($successIds)) {
                    throw new RuntimeException('The Rules export page counts do not match its captured content.');
                }
            }
            foreach ($ids as $id) {
                if (isset($seen[$id])) {
                    throw new RuntimeException('Rules export pages overlap; accounting cannot be safely replayed.');
                }
                $seen[$id] = true;
            }
            $processed += count($ids);
            $successful += count($successIds);
        }
        if ($processed > $this->export->total_rows) {
            throw new RuntimeException('The Rules export pages exceed the selected export size.');
        }

        return compact('processed', 'successful', 'pages');
    }

    /** @param Manifest $metadata */
    public function source(string $manifestPath, array $metadata, bool $finalOnly = false): string
    {
        $disk = $this->export->getFileDisk();
        $final = substr($manifestPath, 0, -5).'.csv';
        $source = $disk->exists($final) ? $final : ($finalOnly ? $final : $metadata['recovery']['staged_csv']);
        if (! $disk->exists($source) || ! hash_equals($metadata['recovery']['csv_sha256'], hash('sha256', $disk->get($source)))) {
            throw new RuntimeException('The captured Rules export page is missing or has changed.');
        }

        return $source;
    }

    public function ready(): bool
    {
        try {
            $this->export->refresh();
            $headers = $this->headers();
            $accounting = $this->accounting(legacy: true);
            if ($accounting['processed'] !== $this->export->total_rows || $accounting['processed'] !== $this->export->processed_rows || $accounting['successful'] !== $this->export->successful_rows) {
                return false;
            }
            foreach ($accounting['pages'] as $path => $metadata) {
                if (isset($metadata['recovery'])) {
                    if ($headers !== $metadata['recovery']['columns']) {
                        return false;
                    }
                    $this->source($path, $metadata, finalOnly: true);
                }
            }
            foreach ($this->export->getFileDisk()->files($this->export->getFileDirectory()) as $path) {
                if (str_ends_with($path, '.csv') && basename($path) !== 'headers.csv' && ! array_key_exists(substr($path, 0, -4).'.json', $accounting['pages'])) {
                    return false;
                }
            }

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /** @param list<string> $columns @return list<string> */
    private function csvRuleIds(string $path, array $columns): array
    {
        $position = array_search('rule_id', $columns, true);
        if ($position === false || array_slice($columns, 0, count(ConfiguratorRuleExchange::COLUMNS)) !== ConfiguratorRuleExchange::COLUMNS) {
            throw new RuntimeException('The Rules export headers do not match its page format.');
        }
        $reader = Reader::fromString($this->export->getFileDisk()->get($path));
        $reader->setDelimiter(ConfiguratorRuleExporter::getCsvDelimiter());
        $reader->setEscape('');
        $ids = [];
        foreach ($reader->getRecords() as $row) {
            if (count($row) !== count($columns) || ! is_string($row[$position] ?? null) || ! ctype_digit($row[$position]) || (int) $row[$position] < 1) {
                throw new RuntimeException('The Rules export rows do not match its page format.');
            }
            $ids[(string) (int) $row[$position]] = true;
        }

        return array_map('strval', array_keys($ids));
    }

    /** @return list<string> */
    private function headers(): array
    {
        $reader = Reader::fromString($this->export->getFileDisk()->get($this->export->getFileDirectory().'/headers.csv'));
        $reader->setDelimiter(ConfiguratorRuleExporter::getCsvDelimiter());
        $reader->setEscape('');
        $headers = $reader->first();
        if (array_slice($headers, 0, count(ConfiguratorRuleExchange::COLUMNS)) !== ConfiguratorRuleExchange::COLUMNS) {
            throw new RuntimeException('The Rules export headers do not match its page format.');
        }

        return $headers;
    }

    /** @param Page $page */
    private function validatePage(string $path, array $page): void
    {
        if (($page['version'] ?? null) !== 1 || ($page['export_id'] ?? null) !== (string) $this->export->id || ! is_int($page['page'] ?? null) || $page['page'] < 1
            || $path !== $this->base($page['page']).'.json' || ! is_array($page['record_ids'] ?? null) || ! array_is_list($page['record_ids'])
            || ! is_int($page['processed'] ?? null) || ! is_int($page['successful'] ?? null) || ! is_array($page['columns'] ?? null) || ! array_is_list($page['columns'])
            || ! is_string($page['payload_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/D', $page['payload_hash'])
            || ! is_string($page['csv_sha256'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/D', $page['csv_sha256']) || ! is_string($page['staged_csv'] ?? null)) {
            throw new RuntimeException('The Rules export recovery metadata is invalid.');
        }
        $prefix = $this->base($page['page']).'.csv.pending.';
        if (! str_starts_with($page['staged_csv'], $prefix) || ! preg_match('/\A[a-f0-9-]{36}\z/D', substr($page['staged_csv'], strlen($prefix)))) {
            throw new RuntimeException('The Rules export staging path does not belong to this page.');
        }
        foreach ($page['record_ids'] as $id) {
            if (! is_string($id) || ! ctype_digit($id) || (string) (int) $id !== $id || (int) $id < 1) {
                throw new RuntimeException('The Rules export page identity is invalid.');
            }
        }
    }
}
