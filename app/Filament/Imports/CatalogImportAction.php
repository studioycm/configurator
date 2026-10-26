<?php

namespace App\Filament\Imports;

use App\Services\CatalogCsv;
use App\Services\CatalogDataExchangePolicy;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use League\Csv\Reader;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogImportAction extends ImportAction
{
    /** @var array<string, resource> Request-local immutable uploaded snapshots. */
    private array $csvSnapshots = [];

    /** @var array<string, string> Proofs only for private snapshots owned by this instance, bound to every selected control. */
    private array $validatedSnapshots = [];

    protected function setUp(): void
    {
        parent::setUp();
        $nativeSchema = $this->schema;
        $resetMapping = fn (Set $set) => $set('columnMap', []);
        $this->schema(fn (): array => [
            Select::make('csv_quoting')->label(__('data_exchange.csv.quoting'))->options([
                'standard' => __('data_exchange.csv.standard'), 'legacy' => __('data_exchange.csv.legacy'), 'legacy_convert' => __('data_exchange.csv.legacy_convert'), 'custom' => __('data_exchange.csv.custom'),
            ])->default('standard')->required()->live()->afterStateUpdated($resetMapping)->helperText(__('data_exchange.csv.mapping_help')),
            Select::make('csv_separator')->label(__('data_exchange.csv.separator'))->options([
                'auto' => __('data_exchange.csv.auto'), ',' => __('data_exchange.csv.comma'), ';' => __('data_exchange.csv.semicolon'), '|' => __('data_exchange.csv.pipe'), 'tab' => __('data_exchange.csv.tab'),
            ])->default('auto')->required()->live()->afterStateUpdated($resetMapping),
            TextInput::make('csv_enclosure')->label(__('data_exchange.csv.enclosure'))->default('"')->maxLength(1)->required()
                ->visible(fn (Get $get): bool => $get('csv_quoting') === 'custom')->live(onBlur: true)->afterStateUpdated($resetMapping),
            TextInput::make('csv_escape')->label(__('data_exchange.csv.escape'))->default('')->maxLength(1)->nullable()
                ->visible(fn (Get $get): bool => $get('csv_quoting') === 'custom')->live(onBlur: true)->afterStateUpdated($resetMapping)
                ->helperText(__('data_exchange.csv.escape_help')),
            TextEntry::make('active_csv_rules')->label(__('data_exchange.csv.active_rules'))->state(fn (Get $get): array => array_map(
                fn (string $code): string => __('data_exchange.csv.rules.'.$code),
                app(CatalogDataExchangePolicy::class)->importRuleCodes(['mode' => $get('csv_quoting') ?? 'standard', 'update_existing' => (bool) $get('update_existing'), 'restore_spreadsheet_text' => (bool) $get('restore_spreadsheet_text')]),
            ))->listWithLineBreaks()->bulleted(),
            ...$this->evaluate($nativeSchema),
        ]);
        $this->registerModalActions([Action::make('downloadExample')->label(__('filament-actions::import.modal.actions.download_example.label'))->link()->action(fn (): StreamedResponse => $this->downloadConfiguredExample())]);
    }

    /** Preserve native encoding conversion; freeze bytes for this request's validation, mapping and dispatch. @return resource|false */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $key = $file->getRealPath();
        if (isset($this->csvSnapshots[$key])) {
            rewind($this->csvSnapshots[$key]);

            return $this->csvSnapshots[$key];
        }
        $source = parent::getUploadedFileStream($file);
        if (! is_resource($source)) {
            return false;
        }
        $snapshot = tmpfile();
        if ($snapshot === false) {
            throw new RuntimeException('Cannot stage the uploaded CSV.');
        }
        try {
            rewind($source);
            if (stream_copy_to_stream($source, $snapshot) === false || ! rewind($snapshot)) {
                throw new RuntimeException('Cannot copy the uploaded CSV.');
            }
        } finally {
            fclose($source);
        }
        $this->csvSnapshots[$key] = $snapshot;

        return $snapshot;
    }

    /** Installed native seam, shared by upload, headers, schema, validation, count and dispatch. */
    public function getCsvDelimiter(?Reader $reader = null): ?string
    {
        $settings = $this->quotingSettings();
        $delimiter = $this->separator();
        if ($reader === null) {
            return $delimiter ?? parent::getCsvDelimiter();
        }
        $csv = app(CatalogCsv::class);
        try {
            $csv->configure($reader, $delimiter, $settings);
        } catch (InvalidArgumentException) {
            $settings = [];
            $csv->configure($reader, $delimiter);
        }
        $delimiter ??= parent::getCsvDelimiter($reader) ?? ',';
        $csv->configure($reader, $delimiter, $settings);

        return $delimiter;
    }

    /** Validation errors belong to the upload field; modal rendering remains usable while the user corrects settings/file. */
    public function getFileValidationRules(): array
    {
        return ['bail', fn (): Closure => function (string $attribute, mixed $file, Closure $fail): void {
            if (! $file instanceof TemporaryUploadedFile) {
                return;
            }
            try {
                app(CatalogDataExchangePolicy::class)->csvControls($this->separator(), $this->quotingSettings());
                $stream = $this->getUploadedFileStream($file);
                if (! is_resource($stream)) {
                    $fail(__('data_exchange.validation.csv_unreadable'));

                    return;
                }
                $reader = Reader::from($stream);
                $this->getCsvDelimiter($reader);
                $path = $reader->getPathname();
                $proofKey = json_encode([$reader->getDelimiter(), $reader->getEnclosure(), $reader->getEscape()], JSON_THROW_ON_ERROR);
                $owned = array_any($this->csvSnapshots, fn ($stream): bool => stream_get_meta_data($stream)['uri'] === $path);
                if (! $owned || ($this->validatedSnapshots[$path] ?? null) !== $proofKey) {
                    app(CatalogCsv::class)->validate($reader);
                    if ($owned) {
                        $this->validatedSnapshots[$path] = $proofKey;
                    }
                }
            } catch (ValidationException $exception) {
                $fail(implode(' ', Arr::flatten($exception->errors())));
            } catch (InvalidArgumentException) {
                $fail(__('data_exchange.validation.csv_controls'));
            }
        }, ...parent::getFileValidationRules()];
    }

    /** @return array{mode:string,enclosure:string,escape:string} */
    private function quotingSettings(): array
    {
        $data = $this->currentCsvData();

        return ['mode' => (string) ($data['csv_quoting'] ?? 'standard'), 'enclosure' => (string) ($data['csv_enclosure'] ?? '"'), 'escape' => (string) ($data['csv_escape'] ?? '')];
    }

    private function separator(): ?string
    {
        $separator = $this->currentCsvData()['csv_separator'] ?? 'auto';

        return match ($separator) {
            'auto' => null, 'tab' => "\t", ',', ';', '|' => $separator,
            default => null,
        };
    }

    /** @return array<string,mixed> */
    private function currentCsvData(): array
    {
        return $this->getLivewire()?->mountedActions[$this->getNestingIndex() ?? 0]['data'] ?? [];
    }

    private function downloadConfiguredExample(): StreamedResponse
    {
        $columns = $this->getImporter()::getColumns();
        $csv = app(CatalogCsv::class)->writer($this->getCsvDelimiter(), bom: true, quoting: $this->quotingSettings());
        $csv->insertOne(array_map(fn ($column): string => $column->getExampleHeader(), $columns));
        $examples = array_map(fn ($column): array => $column->getExamples(), $columns);
        $count = array_reduce($examples, fn (int $count, array $values): int => max($count, count($values)), 0);
        for ($row = 0; $row < $count; $row++) {
            $csv->insertOne(array_map(fn (array $values): mixed => $values[$row] ?? '', $examples));
        }

        return response()->streamDownload(function () use ($csv): void {
            foreach ($csv->chunk(8192) as $chunk) {
                echo $chunk;
            }
        }, __('filament-actions::import.example_csv.file_name', ['importer' => (string) str($this->getImporter())->classBasename()->kebab()]), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
