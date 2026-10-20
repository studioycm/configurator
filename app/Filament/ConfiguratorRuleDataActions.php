<?php

namespace App\Filament;

use App\Filament\Exports\ConfiguratorRuleExporter;
use App\Filament\Exports\Jobs\PrepareConfiguratorRulesExport;
use App\Models\Configurator;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ConfiguratorRuleDataActions
{
    public static function import(bool $selectConfigurators = true): Action
    {
        $invalidate = fn (Component $livewire) => $livewire->resetRuleImportPreview();

        return Action::make('importRules')->label(__('data_exchange.rules.import'))->icon(Heroicon::OutlinedArrowDownTray)
            ->authorize('manage-catalog')->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel(__('data_exchange.rules.confirm_action'))
            ->beforeFormFilled(fn (Component $livewire) => $livewire->resetRuleImportPreview())
            ->steps([
                Step::make(__('data_exchange.rules.upload_step'))->schema([
                    View::make('filament.forms.validation-summary'),
                    FileUpload::make('file')->label(__('data_exchange.rules.file'))->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)->visibility('private')->storeFiles(false)->previewable(false)
                        ->helperText(__('data_exchange.rules.file_help'))->live()->afterStateUpdated($invalidate),
                    ...($selectConfigurators ? [Select::make('configurator_ids')->label(__('data_exchange.rules.configurators'))
                        ->multiple()->searchable()->required()->minItems(1)
                        ->options(fn (): array => Configurator::orderBy('name')->pluck('name', 'id')->all())
                        ->helperText(__('data_exchange.rules.import_configurators_help'))->live()->afterStateUpdated($invalidate)] : []),
                    Checkbox::make('allow_create')->label(__('data_exchange.rules.allow_create'))->default(true)->helperText(__('data_exchange.rules.create_help'))->live()->afterStateUpdated($invalidate),
                    Checkbox::make('allow_update')->label(__('data_exchange.rules.allow_update'))->helperText(__('data_exchange.rules.update_help'))->default(false)->live()->afterStateUpdated($invalidate),
                    Checkbox::make('keep_new_disabled')->label(__('data_exchange.rules.keep_new_disabled'))->default(true)->live()->afterStateUpdated($invalidate),
                    Checkbox::make('restore_spreadsheet_text')->label(__('data_exchange.actions.restore_spreadsheet_text'))->helperText(__('data_exchange.help.restore_spreadsheet_text'))->default(false)->live()->afterStateUpdated($invalidate),
                ])->afterValidation(fn (Component $livewire) => $livewire->previewRuleImport()),
                Step::make(__('data_exchange.rules.preview_step'))->schema([
                    View::make('filament.forms.validation-summary'),
                    View::make('filament.forms.configurator-rule-import-preview'),
                ])->afterValidation(fn (Component $livewire) => $livewire->assertRuleImportPreview()),
                Step::make(__('data_exchange.rules.confirm_step'))->schema([
                    View::make('filament.forms.validation-summary'),
                    Checkbox::make('confirm_import')->label(__('data_exchange.rules.confirm_label'))
                        ->helperText(__('data_exchange.rules.confirm_help'))->accepted()->required(),
                ]),
            ])
            ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard->extraAlpineAttributes([
                'x-init' => e('$watch("step", (current, previous) => { if (previous && getStepIndex(current) < getStepIndex(previous)) { $wire.previewRuleImport() } })'),
            ]))
            ->action(fn (array $data, Component $livewire) => $livewire->applyRuleImport($data));
    }

    public static function export(?int $configuratorId = null): ExportAction
    {
        return ExportAction::make('exportRules')->label(__('data_exchange.rules.export'))
            ->exporter(ConfiguratorRuleExporter::class)->job(PrepareConfiguratorRulesExport::class)
            ->authorize('manage-catalog')->columnMapping(false)
            ->schema(ConfiguratorRuleExporter::optionsForm($configuratorId === null))
            ->formats(fn (array $data): array => match ($data['format'] ?? 'both') {
                'csv' => [ExportFormat::Csv],
                'xlsx' => [ExportFormat::Xlsx],
                default => [ExportFormat::Csv, ExportFormat::Xlsx],
            })
            ->modifyQueryUsing(function (Builder $query, array $options) use ($configuratorId): Builder {
                if ($configuratorId !== null) {
                    $query->where('configurator_id', $configuratorId);
                } elseif ($options['configurator_ids'] ?? []) {
                    $query->whereIn('configurator_id', $options['configurator_ids']);
                }
                if (in_array($options['kind'] ?? null, ['Mapping', 'Advanced'], true)) {
                    $query->where('kind', $options['kind']);
                }
                if (in_array($options['state'] ?? null, ['enabled', 'disabled'], true)) {
                    $query->where('is_active', $options['state'] === 'enabled');
                }

                return $query;
            });
    }
}
