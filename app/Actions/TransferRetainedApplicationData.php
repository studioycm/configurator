<?php

namespace App\Actions;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferRetainedApplicationData
{
    /** @var list<string> */
    private const array USER_COLUMNS = [
        'id', 'name', 'email', 'email_verified_at', 'password',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'remember_token', 'created_at', 'updated_at',
    ];

    /** @return array{created: int, unchanged: int, total: int} */
    public function handle(string $sourceConnection, string $sourceAppKey): array
    {
        [$source, $target] = $this->connections($sourceConnection, $sourceAppKey);
        if ($source->getDriverName() === 'mysql') {
            $source->statement('SET TRANSACTION READ ONLY');
        }
        $users = $source->transaction(fn (): array => $source->table('users')
            ->orderBy('id')->get(self::USER_COLUMNS)->map(fn (object $row): array => (array) $row)->all());

        return $target->transaction(function () use ($target, $users): array {
            $existing = $target->table('users')->orderBy('id')->lockForUpdate()->get(self::USER_COLUMNS);
            $created = [];
            $unchanged = 0;
            foreach ($users as $row) {
                $match = $existing->first(fn (object $user): bool => $user->id === $row['id'] || $user->email === $row['email']);
                if ($match !== null) {
                    if ((array) $match !== $row) {
                        throw ValidationException::withMessages(['users' => 'Retained account ID '.$row['id'].' conflicts with existing target data.']);
                    }
                    $unchanged++;
                } else {
                    $created[] = $row;
                }
            }
            if ($created !== []) {
                $target->table('users')->insert($created);
            }

            return ['created' => count($created), 'unchanged' => $unchanged, 'total' => count($users)];
        }, attempts: 3);
    }

    /** @var list<string> */
    private const array RETAINED_TABLES = [
        'users', 'bolt_categories', 'bolt_collections', 'bolt_forms', 'bolt_sections',
        'bolt_fields', 'bolt_responses', 'bolt_field_responses',
        'workflows', 'workflow_states', 'workflow_transitions', 'notifications',
    ];

    /** @return array<string, mixed> */
    public function transferAll(string $sourceConnection, string $sourceAppKey, bool $apply = false, ?string $sourceHash = null): array
    {
        [$source, $target] = $this->connections($sourceConnection, $sourceAppKey);
        if ($source->getDriverName() === 'mysql') {
            $source->statement('SET TRANSACTION READ ONLY');
        }
        $snapshot = $source->transaction(function () use ($source): array {
            $rows = [];
            foreach (self::RETAINED_TABLES as $table) {
                $rows[$table] = $source->table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
            }
            $this->validateReviewedContent($rows);

            return $rows;
        });
        $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        if ($apply && ($sourceHash === null || ! hash_equals($hash, $sourceHash))) {
            throw ValidationException::withMessages(['source' => 'The retained-data snapshot changed or has not been reviewed. Run the dry-run again.']);
        }

        return $target->transaction(function () use ($snapshot, $hash, $apply, $target): array {
            $tables = [];
            $pending = [];
            foreach ($snapshot as $table => $rows) {
                $existing = $target->table($table)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $unchanged = 0;
                $pending[$table] = [];
                foreach ($rows as $row) {
                    $match = $existing->get($row['id']);
                    $stored = $match === null ? [] : (array) $match;
                    $comparison = $row;
                    ksort($stored);
                    ksort($comparison);
                    if ($match === null) {
                        $pending[$table][] = $row;
                    } elseif ($stored === $comparison) {
                        $unchanged++;
                    } else {
                        throw ValidationException::withMessages([$table => 'Retained '.$table.' ID '.$row['id'].' conflicts with target data. No rows were transferred.']);
                    }
                }
                $tables[$table] = ['created' => count($pending[$table]), 'unchanged' => $unchanged, 'total' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
            }
            if ($apply) {
                foreach ($pending as $table => $rows) {
                    foreach (array_chunk($rows, 200) as $chunk) {
                        $target->table($table)->insert($chunk);
                    }
                }
            }

            return ['mode' => $apply ? 'applied' : 'dry-run', 'source_hash' => $hash, 'tables' => $tables];
        }, attempts: 3);
    }

    /** @param array<string, list<array<string, mixed>>> $rows */
    private function validateReviewedContent(array $rows): void
    {
        foreach (['bolt_categories', 'bolt_collections', 'bolt_responses', 'bolt_field_responses', 'workflows', 'workflow_states', 'workflow_transitions', 'notifications'] as $table) {
            if ($rows[$table] !== []) {
                throw ValidationException::withMessages([$table => 'Populated '.$table.' data requires a reviewed retention and reference mapping before transfer.']);
            }
        }
        $fieldTypes = ['\\LaraZeus\\Bolt\\Fields\\Classes\\TextInput', '\\LaraZeus\\Bolt\\Fields\\Classes\\Toggle', '\\LaraZeus\\BoltPro\\Fields\\MatrixGrid'];
        foreach ($rows['bolt_fields'] as $field) {
            if (! in_array($field['type'], $fieldTypes, true)) {
                throw ValidationException::withMessages(['bolt_fields' => 'An unreviewed Bolt field type requires classification before transfer.']);
            }
        }
        foreach ($rows['bolt_forms'] as $form) {
            $options = json_decode($form['options'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
            if ($form['extensions'] !== null || ! empty($options['logo']) || ! empty($options['cover'])) {
                throw ValidationException::withMessages(['bolt_forms' => 'Form extensions or files require a reviewed retention map before transfer.']);
            }
        }
        foreach ($rows as $table => $records) {
            if ($table === 'users') {
                continue;
            }
            foreach ($records as $row) {
                foreach ($row as $value) {
                    if (is_string($value) && preg_match('/App[\\\\]+Models[\\\\]+(?:CatalogGroup|ProductProfile|ProductConfiguration|ConfigProfile|ConfigAttribute|ConfigOption|OptionRule|Part|ConfigurationPart|ConfigurationSpecification|FileAttachment)\b/', $value)) {
                        throw ValidationException::withMessages([$table => 'An old-domain model reference requires explicit mapping before transfer.']);
                    }
                }
            }
        }
    }

    /** @return array{Connection, Connection} */
    private function connections(string $sourceConnection, string $sourceAppKey): array
    {
        $target = DB::connection();
        $safeSqlite = app()->environment('testing') && $target->getDriverName() === 'sqlite' && $target->getDatabaseName() === ':memory:';
        $safeMySql = $target->getDriverName() === 'mysql'
            && $target->getConfig('host') === '127.0.0.1'
            && (string) $target->getConfig('port') === '3307'
            && ((app()->environment('local') && in_array($target->getDatabaseName(), ['configurator_catalog_dev', 'configurator_catalog_rehearsal'], true))
                || (app()->environment('testing') && $target->getDatabaseName() === 'configurator_catalog_test'));
        if (! $safeSqlite && ! $safeMySql) {
            throw ValidationException::withMessages(['target' => 'Account transfer requires the isolated catalog database.']);
        }
        if ($sourceAppKey === '' || ! hash_equals((string) config('app.key'), $sourceAppKey)) {
            throw ValidationException::withMessages(['source' => 'Application encryption keys must match before account transfer.']);
        }
        if ($sourceConnection !== 'catalog_source') {
            throw ValidationException::withMessages(['source' => 'Use the reviewed catalog source connection.']);
        }
        $source = DB::connection($sourceConnection);
        $safeTestSource = app()->environment('testing') && $source->getDriverName() === 'sqlite' && $source->getDatabaseName() === ':memory:';
        if ($source === $target || (! $safeTestSource && ($source->getDriverName() !== 'mysql' || $source->getDatabaseName() !== 'configurator_local'
            || $source->getConfig('host') !== '127.0.0.1' || (string) $source->getConfig('port') !== '3307'))) {
            throw ValidationException::withMessages(['source' => 'The retained account source must be the reviewed local installation.']);
        }

        return [$source, $target];
    }
}
