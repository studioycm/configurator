<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Downloaders\ConfiguratorRulesXlsxDownloader;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Gate;
use League\Csv\Reader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ConfiguratorRuleExporter extends CatalogExporter
{
    protected static ?string $model = ConfiguratorRule::class;

    /** @var array<string, string> */
    protected array $currentValues = [];

    public function __construct(Export $export, array $columnMap, array $options)
    {
        parent::__construct($export, self::columnMap($options), $options);
        $this->record = new ConfiguratorRule;
    }

    /** @param array<string, mixed> $options @return array<string, string> */
    public static function columnMap(array $options): array
    {
        $columns = ConfiguratorRuleExchange::COLUMNS;
        foreach (ConfiguratorRuleExchange::HELPERS as $group => $helpers) {
            if ($options['helper_'.$group] ?? (($options['preset'] ?? 'review') === 'review')) {
                array_push($columns, ...$helpers);
            }
        }

        return array_combine($columns, $columns);
    }

    public static function getColumns(): array
    {
        return array_map(fn (string $name): ExportColumn => self::textColumn($name)->label($name)->state(fn (ConfiguratorRuleExporter $exporter): string => $exporter->currentValues[$name] ?? '')->default(fn (ConfiguratorRuleExporter $exporter): string => $exporter->currentValues[$name] ?? ''), [...ConfiguratorRuleExchange::COLUMNS, ...array_merge(...array_values(ConfiguratorRuleExchange::HELPERS))]);
    }

    public static function getOptionsFormComponents(): array
    {
        return self::optionsForm();
    }

    /** @return array<Component> */
    public static function optionsForm(bool $selectConfigurators = true): array
    {
        return [
            Select::make('preset')->label(__('data_exchange.rules.preset'))->options(['review' => __('data_exchange.rules.review'), 'import' => __('data_exchange.rules.import_template')])->default('review')->required()->live()->afterStateUpdated(function (?string $state, Set $set): void {
                foreach (array_keys(ConfiguratorRuleExchange::HELPERS) as $group) {
                    $set('helper_'.$group, $state === 'review');
                }
            }),
            Select::make('kind')->label(__('data_exchange.rules.kind'))->options(['all' => __('data_exchange.rules.both'), 'Mapping' => __('data_exchange.rules.mapping'), 'Advanced' => __('data_exchange.rules.advanced')])->default('all')->required(),
            Select::make('state')->label(__('data_exchange.rules.state'))->options(['all' => __('data_exchange.rules.all'), 'enabled' => __('data_exchange.rules.enabled'), 'disabled' => __('data_exchange.rules.disabled')])->default('all')->required(),
            Select::make('format')->label(__('data_exchange.rules.format'))->options(['csv' => 'CSV', 'xlsx' => 'XLSX', 'both' => 'CSV + XLSX'])->default('both')->required(),
            ...array_map(fn (string $group): Checkbox => Checkbox::make('helper_'.$group)->label(__('data_exchange.rules.helpers.'.$group))->default(true), array_keys(ConfiguratorRuleExchange::HELPERS)),
            Checkbox::make('lookups')->label(__('data_exchange.rules.lookups'))->helperText(__('data_exchange.rules.lookup_help'))->default(true),
            ...($selectConfigurators ? [Select::make('configurator_ids')->label(__('data_exchange.rules.configurators'))->multiple()->searchable()->options(fn (): array => Configurator::orderBy('name')->pluck('name', 'id')->all())->helperText(__('data_exchange.rules.all_configurators'))->default([])] : []),
        ];
    }

    /** @param array{rows: list<array<string, string>>, helpers: list<array<string, string>>, lookups: list<array<string, string>>} $snapshot @return list<list<string>> */
    public function formatRows(array $snapshot): array
    {
        $rows = [];
        foreach ($snapshot['rows'] as $index => $row) {
            $this->currentValues = [...$row, ...$snapshot['helpers'][$index]];
            $formatted = [];
            foreach (array_keys($this->columnMap) as $name) {
                $formatted[] = $this->getCachedColumns()[$name]->getFormattedState();
            }
            $rows[] = $formatted;
        }

        return $rows;
    }

    public function configureXlsxWriterAfterOpen(Writer $writer): Writer
    {
        Gate::forUser($this->export->user()->firstOrFail())->authorize('manage-catalog');
        $writer->getCurrentSheet()->setName('Rules');

        return $writer;
    }

    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        return $this->makeXlsxRow($values, $style);
    }

    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        return $this->xlsxRow(array_slice($values, 0, count(ConfiguratorRuleExchange::COLUMNS)), $style);
    }

    /** @param list<mixed> $values */
    private function xlsxRow(array $values, ?Style $style = null): Row
    {
        foreach ($values as $value) {
            if (is_string($value) && mb_strlen($value) > 32767) {
                throw new RuntimeException('XLSX cells cannot exceed 32,767 characters. Export CSV to preserve the full text.');
            }
        }

        return Row::fromValues($values, $style);
    }

    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        $disk = $this->export->getFileDisk();
        $files = $disk->files($this->export->getFileDirectory());
        sort($files);
        if (count($this->columnMap) > count(ConfiguratorRuleExchange::COLUMNS)) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Rules_helpers');
            $writer->addRow($this->xlsxRow(array_values($this->columnMap)));
            foreach ($files as $file) {
                if (! str_ends_with($file, '.csv') || str_ends_with($file, 'headers.csv')) {
                    continue;
                }
                $reader = Reader::from($disk->readStream($file));
                $reader->setEscape('');
                foreach ($reader->getRecords() as $values) {
                    $writer->addRow($this->xlsxRow($values));
                }
            }
        }
        if ($this->options['lookups'] ?? true) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Lookups');
            $writer->addRow($this->xlsxRow(['configurator_id', 'configurator_name', 'reference_type', 'attribute_key', 'attribute_label', 'value_semantic_id', 'value_label', 'reference_code', 'field', 'literal']));
            $seen = [];
            foreach ($files as $file) {
                if (! str_ends_with($file, '.json')) {
                    continue;
                }
                $snapshot = json_decode($disk->get($file), true, flags: JSON_THROW_ON_ERROR);
                foreach ($snapshot['lookups'] as $lookup) {
                    $key = json_encode($lookup, JSON_THROW_ON_ERROR);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $values = array_map(fn (string $value): string => self::textColumn('lookup')->exporter($this)->state($value)->default($value)->getFormattedState(), array_values($lookup));
                    $writer->addRow($this->xlsxRow($values));
                }
            }
        }

        return $writer;
    }

    public static function getDownloader(ExportFormatInterface $format): Downloader
    {
        return $format === ExportFormat::Xlsx ? app(ConfiguratorRulesXlsxDownloader::class) : parent::getDownloader($format);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return __('data_exchange.rules.completed', ['successful' => number_format($export->successful_rows), 'failed' => number_format($export->getFailedRowsCount())]);
    }
}
