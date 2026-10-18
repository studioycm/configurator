<?php

namespace App\Services;

use App\Actions\SaveConfiguratorDefinition;
use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ConfiguratorRuleImport
{
    public function __construct(private ConfiguratorRuleImportParser $parser, private ConfiguratorRuleImportBuilder $builder, private ConfiguratorDefinitionLoader $loader, private ConfiguratorDefinitionCompiler $compiler, private SaveConfiguratorDefinition $save) {}

    /** @param array<string, mixed> $options @return array<string, mixed> */
    public function previewFile(User $actor, string $absolutePath, array $options = [], ?int $scope = null): array
    {
        $actor = $this->authorizedActor($actor);

        return $this->previewRows($actor, $this->parser->parse($absolutePath), $options, $scope);
    }

    /** @param list<array<string, string>> $rows @param array<string, mixed> $options @return array<string, mixed> */
    public function previewRows(User $actor, array $rows, array $options = [], ?int $scope = null): array
    {
        $actor = $this->authorizedActor($actor);
        $options = $this->options($options, $scope);
        $rows = $this->parser->normalize($rows, $options['restore_spreadsheet_text']);
        [$result, $snapshots] = DB::transaction(fn (): array => $this->prepare($rows, $options, $scope));
        $token = bin2hex(random_bytes(32));
        Cache::put('rules-import:'.$token, ['actor' => $actor->id, 'scope' => $scope, 'rows' => $rows, 'options' => $options, 'snapshots' => $snapshots, 'can_import' => $result['can_import'], 'expires' => now()->addMinutes(15)->timestamp], now()->addMinutes(15));

        return ['token' => $token, ...$result];
    }

    /** @return array{created: int, updated: int, configurators: int} */
    public function apply(User $actor, string $token, ?int $scope = null): array
    {
        $actor = $this->authorizedActor($actor);
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw ValidationException::withMessages(['preview' => 'Invalid preview token. Generate a new preview.']);
        }
        $key = 'rules-import:'.$token;
        $preview = Cache::lock($key.':consume', 10)->block(3, function () use ($key, $actor, $scope): array {
            $preview = Cache::get($key);
            if (! is_array($preview) || $preview['actor'] !== $actor->id || $preview['scope'] !== $scope || $preview['expires'] <= now()->timestamp || ! $preview['can_import']) {
                throw ValidationException::withMessages(['preview' => 'Preview is invalid, expired, already used, or belongs to another user or scope. Generate a new preview.']);
            }
            Cache::forget($key);

            return $preview;
        });

        return DB::transaction(function () use ($actor, $scope, $preview): array {
            $ids = array_keys($preview['snapshots']);
            sort($ids, SORT_NUMERIC);
            $records = Configurator::whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($records->count() !== count($ids)) {
                throw ValidationException::withMessages(['preview' => 'A Configurator changed after preview. Generate a new preview.']);
            }
            $globalContext = CatalogContextSettings::current(lock: true)->choices;
            $this->lockReferences($ids);
            foreach ($ids as $id) {
                $draft = $this->loader->draft($records[$id]);
                [$attributes, $options] = $this->loader->canonical($draft, true);
                if (! hash_equals($preview['snapshots'][$id], $this->fingerprint($draft, $attributes, $options, $globalContext))) {
                    throw ValidationException::withMessages(['preview' => 'The definition, references or context changed after preview. Generate a new preview.']);
                }
            }
            [$result, , $drafts] = $this->prepare($preview['rows'], $preview['options'], $scope);
            if (! $result['can_import']) {
                throw ValidationException::withMessages(['preview' => $result['errors'] ?: ['The import is no longer valid. Generate a new preview.']]);
            }
            foreach ($drafts as $id => $draft) {
                $this->save->change($this->authorizedActor($actor), $records[$id], fn (array $current): array => [...$current, 'rules' => $draft['rules']]);
            }

            return ['created' => $result['create_count'], 'updated' => $result['update_count'], 'configurators' => count($drafts)];
        });
    }

    private function authorizedActor(User $actor): User
    {
        $actor = $actor->fresh() ?? throw new AuthorizationException;
        Gate::forUser($actor)->authorize('manage-catalog');

        return $actor;
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function options(array $options, ?int $scope): array
    {
        $defaults = ['allow_create' => true, 'allow_update' => false, 'keep_new_disabled' => true, 'restore_spreadsheet_text' => false, 'configurator_ids' => []];
        if (array_diff(array_keys($options), array_keys($defaults)) !== []) {
            throw ValidationException::withMessages(['options' => 'Unknown import option.']);
        }
        $options = array_replace($defaults, $options);
        Validator::make($options, ['allow_create' => ['required', 'boolean'], 'allow_update' => ['required', 'boolean'], 'keep_new_disabled' => ['required', 'boolean'], 'restore_spreadsheet_text' => ['required', 'boolean'], 'configurator_ids' => ['array'], 'configurator_ids.*' => ['integer', 'min:1', 'distinct']])->validate();
        if ($scope === null && $options['configurator_ids'] === []) {
            throw ValidationException::withMessages(['configurator_ids' => 'Select the allowed Configurators before importing.']);
        }
        foreach (array_keys($defaults) as $key) {
            if ($key !== 'configurator_ids') {
                $options[$key] = (bool) $options[$key];
            }
        }
        $options['configurator_ids'] = $scope === null ? array_map('intval', $options['configurator_ids']) : [$scope];

        return $options;
    }

    /** @param list<array<string, string>> $rows @param array<string, mixed> $options @return array{array<string, mixed>, array<int, string>, array<int, array<string, mixed>>} */
    private function prepare(array $rows, array $options, ?int $scope): array
    {
        $result = ['can_import' => false, 'row_count' => count($rows), 'skipped_groups' => 0, 'create_count' => 0, 'update_count' => 0, 'errors' => [], 'warnings' => [], 'configurators' => []];
        $groups = [];
        foreach ($rows as $index => $row) {
            try {
                $id = $this->builder->integer($row['configurator_id'], 'configurator_id', 1);
                if ($row['rule_key'] === '' || ! in_array($row['import'], ['0', '1'], true)) {
                    throw ValidationException::withMessages(['rules' => 'rule_key is required and import must be 0 or 1.']);
                }
                $groups[$id][$row['rule_key']][] = $row;
            } catch (ValidationException $exception) {
                $result['errors'][] = 'Row '.($index + 2).': '.implode(' ', $exception->validator->errors()->all());
            }
        }
        $snapshots = [];
        $drafts = [];
        $globalContext = CatalogContextSettings::current()->choices;
        foreach ($groups as $id => $ruleGroups) {
            $selected = [];
            foreach ($ruleGroups as $key => $group) {
                $inconsistent = [];
                foreach (array_slice(ConfiguratorRuleExchange::COLUMNS, 0, 11) as $field) {
                    if (count(array_unique(array_column($group, $field), SORT_STRING)) !== 1) {
                        $inconsistent[] = $field;
                    }
                }
                if ($inconsistent !== []) {
                    $result['errors'][] = 'Rule '.$key.': inconsistent '.implode(', ', $inconsistent).' within rule group.';

                } elseif ($group[0]['import'] === '0') {
                    $result['skipped_groups']++;
                } else {
                    $selected[$key] = $group;
                }
            }
            if ($selected === []) {
                continue;
            }
            if (! in_array($id, $options['configurator_ids'], true) || ($scope !== null && $id !== $scope)) {
                $result['errors'][] = 'Configurator '.$id.' is outside the allowed import scope.';

                continue;
            }
            $record = Configurator::find($id);
            if ($record === null) {
                $result['errors'][] = 'Configurator '.$id.' no longer exists.';

                continue;
            }
            $draft = $this->loader->draft($record);
            [$attributes, $canonicalOptions] = $this->loader->canonical($draft);
            $snapshots[$id] = $this->fingerprint($draft, $attributes, $canonicalOptions, $globalContext);
            $entry = ['id' => $id, 'name' => $record->name, 'changes' => [], 'errors' => [], 'warnings' => []];
            $updates = [];
            foreach ($selected as $key => $group) {
                try {
                    if ($group[0]['operation'] === 'Update') {
                        $updateId = $group[0]['rule_id'];
                        if (isset($updates[$updateId])) {
                            throw ValidationException::withMessages(['rules' => 'Duplicate Update groups target the same rule_id.']);
                        }
                        $updates[$updateId] = true;
                    }
                    $built = $this->builder->build($group, $draft, $attributes, $canonicalOptions, $options);
                    $rule = $built['rule'];
                    $index = array_search($rule['id'], array_column($draft['rules'], 'id'), true);
                    if ($index === false) {
                        $draft['rules'][] = $rule;
                        $result['create_count']++;
                    } else {
                        $draft['rules'][$index] = $rule;
                        $result['update_count']++;
                    }
                    $entry['changes'][] = $built['change'];
                    if ($built['change']['changes'] === []) {
                        $entry['warnings'][] = 'Rule '.$key.' has no changes.';
                    }
                } catch (ValidationException|InvalidArgumentException $exception) {
                    $messages = $exception instanceof ValidationException ? $exception->validator->errors()->all() : [$exception->getMessage()];
                    $entry['errors'][] = 'Rule '.$key.': '.implode(' ', $messages);
                }
            }
            try {
                $this->compiler->compile($draft, $attributes, $canonicalOptions, authoring: true, globalContext: $globalContext);
            } catch (ValidationException $exception) {
                $entry['errors'] = [...$entry['errors'], ...$exception->validator->errors()->all()];
            }
            $result['errors'] = [...$result['errors'], ...array_map(fn (string $error): string => 'Configurator '.$id.': '.$error, $entry['errors'])];
            $result['warnings'] = [...$result['warnings'], ...$entry['warnings']];
            $result['configurators'][] = $entry;
            $drafts[$id] = $draft;
        }
        $result['can_import'] = $result['errors'] === [] && $result['create_count'] + $result['update_count'] > 0;

        return [$result, $snapshots, $drafts];
    }

    /** @param list<int> $configuratorIds */
    private function lockReferences(array $configuratorIds): void
    {
        $includedAttributes = DB::table('configurator_attributes')->whereIn('configurator_id', $configuratorIds);
        Attribute::whereIn('id', (clone $includedAttributes)->select('attribute_id'))->orderBy('id')->lockForUpdate()->get();
        $includedOptions = DB::table('configurator_options')->whereIn('configurator_attribute_id', (clone $includedAttributes)->select('id'))->select('option_id');
        Option::whereIn('id', $includedOptions)->orderBy('id')->lockForUpdate()->get();
        Value::whereIn('id', Option::whereIn('id', $includedOptions)->select('value_id'))->orderBy('id')->lockForUpdate()->get();
    }

    /** @param array<string, mixed> $draft @param array<int, array<string, mixed>> $attributes @param array<int, array<string, mixed>> $options @param array<string, mixed> $context */
    private function fingerprint(array $draft, array $attributes, array $options, array $context): string
    {
        return hash('sha256', json_encode([$draft, $attributes, $options, $context], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
