<?php

namespace App\Filament\Imports\Jobs;

use App\CatalogWriteReplan;
use App\Filament\Imports\AttributeImporter;
use App\Filament\Imports\AttributeValueImporter;
use App\Filament\Imports\CatalogImporter;
use App\Filament\Imports\ValueImporter;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\CanonicalUsage;
use App\Services\CatalogIntegrity;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDO;
use PDOException;
use Throwable;

/** Bounded Filament 5.9 chunk orchestration; native row mapping and lifecycle remain in Importer. */
class ImportCatalogCsv extends ImportCsv implements ShouldQueueAfterCommit
{
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Catalog import chunks must own their outer transaction.');
        }
        try {
            $rows = is_array($this->rows) ? $this->rows : unserialize(base64_decode($this->rows), ['allowed_classes' => false]);
            $rows = array_map($this->utf8Encode(...), $rows);
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $this->failedRows = [];
                $this->importer = $this->import->getImporter($this->columnMap, $this->options);
                $processedRows = $successfulRows = 0;
                try {
                    $user = $this->import->user;
                    abort_unless($user instanceof User, 403);
                    Gate::forUser($user)->authorize('manage-catalog');
                    auth()->setUser($user);
                    $plan = $this->plan($rows);
                    DB::beginTransaction();
                    $this->acquire($plan);
                    foreach ($rows as $row) {
                        try {
                            ($this->importer)($row);
                            $successfulRows++;
                        } catch (CatalogWriteReplan $exception) {
                            throw $exception;
                        } catch (RowImportFailedException $exception) {
                            $this->logFailedRow($row, $exception->getMessage());
                        } catch (ValidationException $exception) {
                            $this->logFailedRow($row, collect($exception->errors())->flatten()->implode(' '));
                        }
                        $processedRows++;
                    }
                    $this->import::whereKey($this->import)->lockForUpdate()->update([
                        'processed_rows' => new Expression('processed_rows + '.$processedRows),
                        'successful_rows' => new Expression('successful_rows + '.$successfulRows),
                    ]);
                    foreach (['processed_rows', 'successful_rows'] as $counter) {
                        $this->import::whereKey($this->import)->whereColumn($counter, '>', 'total_rows')->lockForUpdate()
                            ->update([$counter => new Expression('total_rows')]);
                    }
                    $this->import->failedRows()->createMany($this->failedRows);
                } catch (Throwable $exception) {
                    $rolledBack = $this->rollbackAttempt($this->driverCode($exception) === 1213);
                    if ($rolledBack && $attempt < 3 && ($exception instanceof CatalogWriteReplan || in_array($this->driverCode($exception), [1205, 1213, 3572], true))) {
                        continue;
                    }
                    $this->terminate($exception);

                    return;
                }
                try {
                    DB::commit();
                } catch (Throwable $exception) {
                    $this->rollbackAttempt();
                    $this->terminate($exception);

                    return;
                }
                try {
                    $this->import->refresh();
                    event(new ImportChunkProcessed($this->import, $this->columnMap, $this->options, $processedRows, $successfulRows));
                } catch (Throwable $exception) {
                    $this->terminate($exception);
                }

                return;
            }
        } finally {
            auth()->forgetGuards();
        }
    }

    /**
     * @param  array<array<string, mixed>>  $rows
     * @return array{parents: array{attributes: list<int>, values: list<int>, options: list<int>}, mutations: array{attributeIds: list<int>, valueIds: list<int>, optionIds: list<int>}, owners: list<int>, projections: array<array{data: array<string, mixed>, record: ?Model}>, status: bool}
     */
    private function plan(array $rows): array
    {
        if (! $this->importer instanceof AttributeImporter && ! $this->importer instanceof ValueImporter && ! $this->importer instanceof AttributeValueImporter) {
            throw new LogicException('This catalog chunk adapter supports the installed canonical importers only.');
        }
        $parents = ['attributes' => [], 'values' => [], 'options' => []];
        $mutations = ['attributeIds' => [], 'valueIds' => [], 'optionIds' => []];
        $projections = [];
        foreach ($rows as $row) {
            try {
                $projection = $this->import->getImporter($this->columnMap, $this->options)->projectRow($row);
            } catch (ValidationException) {
                continue;
            }
            $record = $projection['record'];
            if ($record === null) {
                continue;
            }
            $projections[] = $projection;
            if ($record instanceof Option) {
                if ($record->attribute_id !== null) {
                    $parents['attributes'][] = $record->attribute_id;
                }
                if ($record->value_id !== null) {
                    $parents['values'][] = $record->value_id;
                }
            }
            if ($record->exists) {
                $bucket = match (true) {
                    $record instanceof Attribute => 'attributes', $record instanceof Value => 'values', default => 'options'
                };
                $parents[$bucket][] = $record->id;
                $mutations[match (true) {
                    $record instanceof Attribute => 'attributeIds', $record instanceof Value => 'valueIds', default => 'optionIds'
                }][] = $record->id;
            }
        }
        $parents = array_map(fn (array $ids): array => array_values(array_unique($ids)), $parents);
        $mutations = array_map(fn (array $ids): array => array_values(array_unique($ids)), $mutations);

        return ['parents' => $parents, 'mutations' => $mutations,
            'owners' => app(CanonicalUsage::class)->configuratorIds(...$mutations), 'projections' => $projections,
            'status' => ! $this->importer instanceof ValueImporter && (filled($this->columnMap['is_active'] ?? null) || filled($this->columnMap['is_hidden'] ?? null))];
    }

    /** @param array{parents: array{attributes: list<int>, values: list<int>, options: list<int>}, mutations: array{attributeIds: list<int>, valueIds: list<int>, optionIds: list<int>}, owners: list<int>, projections: array<array{data: array<string, mixed>, record: ?Model}>, status: bool} $plan */
    private function acquire(array $plan): void
    {
        Configurator::whereKey($plan['owners'])->orderBy('id')->lockForUpdate()->get();
        $parents = $plan['parents'];
        foreach (['attributes' => Attribute::class, 'values' => Value::class, 'options' => Option::class] as $bucket => $class) {
            $locked = $parents[$bucket] === [] ? collect() : $class::whereKey($parents[$bucket])->orderBy('id')->lockForUpdate()->get();
            if ($locked->count() !== count($parents[$bucket])) {
                $this->replan();
            }
            if ($bucket === 'options' && $locked->contains(fn (Option $option): bool => ! in_array($option->attribute_id, $parents['attributes'], true) || ! in_array($option->value_id, $parents['values'], true))) {
                $this->replan();
            }
            foreach ($plan['projections'] as $projection) {
                $data = $projection['data'];
                $query = match ($bucket) {
                    'attributes' => $this->importer instanceof ValueImporter ? null
                        : $this->identityQuery(Attribute::class, 'key', $data[$this->importer instanceof AttributeValueImporter ? 'attribute_key' : 'key'] ?? null),
                    'values' => $this->importer instanceof AttributeImporter ? null
                        : $this->identityQuery(Value::class, 'semantic_id', $data[$this->importer instanceof AttributeValueImporter ? 'value_semantic_id' : 'semantic_id'] ?? null),
                    default => $projection['record'] instanceof Option && $projection['record']->attribute_id !== null && $projection['record']->value_id !== null
                        ? Option::where('attribute_id', $projection['record']->attribute_id)->where('value_id', $projection['record']->value_id) : null,
                };
                if ($query === null) {
                    continue;
                }
                $matches = $query->lock(DB::getDriverName() === 'mysql' ? 'for update nowait' : true)->get();
                if (array_diff($matches->modelKeys(), $parents[$bucket]) !== []) {
                    $this->replan();
                }
            }
        }
        $usage = app(CanonicalUsage::class);
        if ($plan['mutations']['valueIds'] !== [] || ($plan['status'] && $plan['mutations']['attributeIds'] !== [])) {
            Option::where(fn (Builder $query): Builder => $query->whereIn('value_id', $plan['mutations']['valueIds'])
                ->orWhereIn('attribute_id', $plan['status'] ? $plan['mutations']['attributeIds'] : []))->orderBy('id')->lockForUpdate()->get();
        }
        $usage->assertConfiguratorIds($plan['owners'], ...$plan['mutations']);
        if ($plan['status']) {
            app(CatalogIntegrity::class)->lockGroups();
        }
        /** @var CatalogImporter $importer */
        $importer = $this->importer;
        $importer->useParentPlan($parents);
    }

    /** @param class-string<Model> $class @return Builder<Model>|null */
    private function identityQuery(string $class, string $field, mixed $value): ?Builder
    {
        return is_string($value) ? $class::where($field, $value) : null;
    }

    private function replan(): never
    {
        throw CatalogWriteReplan::withMessages(['definition' => __('configurator.validation.changes_could_not_be_saved_your_draft')]);
    }

    private function rollbackAttempt(bool $deadlock = false): bool
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getRawPdo();
            if ($connection->transactionLevel() === 0) {
                return false;
            }
            if (! $pdo instanceof PDO) {
                DB::purge();

                return false;
            }
            $active = $pdo->inTransaction();
            $connection->rollBack();

            return $active || $deadlock;
        } catch (Throwable $exception) {
            report($exception);
            DB::purge();

            return false;
        }
    }

    private function driverCode(Throwable $exception): ?int
    {
        do {
            if ($exception instanceof PDOException && isset($exception->errorInfo[1])) {
                return (int) $exception->errorInfo[1];
            }
            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return null;
    }

    private function terminate(Throwable $exception): void
    {
        report($exception);
        if ($this->job !== null) {
            $this->fail($exception);

            return;
        }
        throw $exception;
    }
}
