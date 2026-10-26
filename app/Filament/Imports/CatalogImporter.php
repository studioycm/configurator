<?php

namespace App\Filament\Imports;

use App\Actions\ChangeCatalogStatus;
use App\CatalogWriteReplan;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Actions\Imports\Downloaders\Contracts\Downloader;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;

abstract class CatalogImporter extends Importer
{
    protected static bool $shouldPreventFormulaInjection = true;

    /** @var array{attributes: list<int>, values: list<int>, options: list<int>}|null */
    private ?array $parentPlan = null;

    /** @param array<string, mixed> $row @return array{data: array<string, mixed>, record: ?Model} */
    public function projectRow(array $row): array
    {
        $this->originalData = $this->data = $row;
        $this->record = null;
        $this->remapData();
        $this->castData();

        return ['data' => $this->data, 'record' => $this->resolveRecord()];
    }

    /** @param array{attributes: list<int>, values: list<int>, options: list<int>} $parents */
    public function useParentPlan(array $parents): void
    {
        $this->parentPlan = $parents;
    }

    protected function assertPlannedRecord(): void
    {
        if ($this->parentPlan === null) {
            return;
        }
        $bucket = match (true) {
            $this->record instanceof Attribute => 'attributes',
            $this->record instanceof Value => 'values',
            default => 'options',
        };
        $unplanned = $this->record->exists && ! in_array($this->record->id, $this->parentPlan[$bucket], true);
        if ($this->record instanceof Option) {
            $unplanned = $unplanned || ! in_array($this->record->attribute_id, $this->parentPlan['attributes'], true)
                || ! in_array($this->record->value_id, $this->parentPlan['values'], true);
        }
        if ($unplanned) {
            throw CatalogWriteReplan::withMessages(['definition' => __('configurator.validation.changes_could_not_be_saved_your_draft')]);
        }
    }

    protected function rememberImportedRecord(): void
    {
        if ($this->parentPlan !== null) {
            $bucket = match (true) {
                $this->record instanceof Attribute => 'attributes',
                $this->record instanceof Value => 'values',
                default => 'options',
            };
            $this->parentPlan[$bucket][] = $this->record->id;
        }
    }

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
                ->helperText(__('data_exchange.help.update_existing'))->default(false)->live(),
            Checkbox::make('restore_spreadsheet_text')->label(__('data_exchange.actions.restore_spreadsheet_text'))
                ->helperText(__('data_exchange.help.restore_spreadsheet_text'))->default(false)->live(),
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
        $import->refresh();
        $rejected = $import->failedRows()->count();
        $body = __('data_exchange.notifications.import_results', [
            'successful' => number_format($import->successful_rows),
            'rejected' => number_format($rejected),
            'unconfirmed' => number_format(max(0, $import->total_rows - $import->processed_rows)),
            'processed' => number_format($import->processed_rows), 'total' => number_format($import->total_rows),
        ]);
        if ($import->processed_rows > $import->total_rows || $import->successful_rows > $import->processed_rows || $import->processed_rows - $import->successful_rows !== $rejected) {
            $body .= ' '.__('data_exchange.notifications.import_unreconciled');
        }

        return $body;
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return __('data_exchange.notifications.import_finished');
    }

    public static function modifyCompletedNotification(Notification $notification, Import $import): Notification
    {
        $body = static::getCompletedNotificationBody($import);
        $rejected = $import->failedRows()->count();
        $actions = $rejected === 0 ? [] : collect($notification->getActions())
            ->filter(fn ($action): bool => $action instanceof Action && $action->getName() === 'downloadFailedRowsCsv')
            ->map(fn (Action $action): Action => $action->label(trans_choice('filament-actions::import.notifications.completed.actions.download_failed_rows_csv.label', $rejected, ['count' => Number::format($rejected)])))
            ->values()->all();

        return $notification->title(static::getCompletedNotificationTitle($import))->body($body)->actions($actions)
            ->status($rejected > 0 || $import->processed_rows !== $import->total_rows || $import->successful_rows !== $import->processed_rows ? 'warning' : null);
    }

    public static function getFailedRowsDownloader(): Downloader
    {
        return app(CatalogFailedRowsCsvDownloader::class);
    }
}
