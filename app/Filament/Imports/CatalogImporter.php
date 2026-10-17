<?php

namespace App\Filament\Imports;

use App\Actions\ChangeCatalogStatus;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

abstract class CatalogImporter extends Importer
{
    protected static bool $shouldPreventFormulaInjection = true;

    protected static function importColumn(): ImportColumn
    {
        return ImportColumn::make('import')->label(__('data_exchange.fields.import'))
            ->exampleHeader('import')->example(1)->rules(['nullable', 'boolean'])
            ->helperText(__('data_exchange.help.import_flag'))
            ->fillRecordUsing(function (): void {});
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('update_existing')->label(__('data_exchange.actions.update_existing'))
                ->helperText(__('data_exchange.help.update_existing'))->default(false),
            Checkbox::make('restore_spreadsheet_text')->label(__('data_exchange.actions.restore_spreadsheet_text'))
                ->helperText(__('data_exchange.help.restore_spreadsheet_text'))->default(false),
        ];
    }

    protected static function textColumn(string $name): ImportColumn
    {
        return ImportColumn::make($name)->castStateUsing(function (mixed $originalState, array $options): mixed {
            if (($options['restore_spreadsheet_text'] ?? false)
                && is_string($originalState)
                && preg_match("/\\A'+[=+\\-@\\t\\r]/", $originalState)) {
                return substr($originalState, 1);
            }

            return $originalState;
        });
    }

    protected function actor(): User
    {
        $actor = $this->import->user;
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize('manage-catalog');

        return $actor;
    }

    protected function shouldSkipRow(): bool
    {
        $this->actor();
        $flag = $this->data['import'] ?? 1;
        Validator::make(['import' => $flag], ['import' => ['required', 'boolean']])->validate();

        return in_array($flag, [0, '0', false], true);
    }

    protected function beforeFill(): void
    {
        if ($this->record->exists && ! ($this->options['update_existing'] ?? false)) {
            throw new RowImportFailedException(__('data_exchange.validation.existing_record'));
        }
    }

    /** @param list<string> $fields @return array<string, mixed> */
    protected function mappedData(array $fields): array
    {
        return array_intersect_key($this->data, array_flip($fields), array_filter($this->columnMap, filled(...)));
    }

    protected function saveStatus(): void
    {
        $data = $this->mappedData(['is_active', 'is_hidden']);
        if ($data === []) {
            return;
        }
        $active = (bool) ($data['is_active'] ?? $this->record->is_active);
        $hidden = array_key_exists('is_hidden', $data) ? (bool) $data['is_hidden'] : null;
        if ($this->record->is_active !== $active || ($hidden !== null && $this->record->is_hidden !== $hidden)) {
            app(ChangeCatalogStatus::class)->handle($this->actor(), $this->record, $active, hidden: $hidden);
            $this->record->refresh();
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return __('data_exchange.notifications.import_completed', [
            'successful' => number_format($import->successful_rows),
            'failed' => number_format($import->getFailedRowsCount()),
        ]);
    }
}
