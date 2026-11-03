<?php

namespace App\Actions;

use App\CatalogWriteFailure;
use App\CatalogWriteReplan;
use App\Models\Configurator;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use App\Services\ConfiguratorRuleRecords;
use App\Services\ConfiguratorRuleWritingCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDOException;
use Throwable;

class SaveConfiguratorRuleWritingBatch
{
    public function __construct(private SaveConfiguratorDefinition $writer, private ConfiguratorRuleWritingCodec $codec, private ConfiguratorRuleRecords $recordReader) {}

    /** @param list<array{id:string,expected_revision:int,sequence:int}> $selection @return array<string,mixed> */
    public function handle(User $actor, Configurator $configurator, array $selection): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Writing batch saves must own the outer transaction.');
        }
        $selection = $this->selection($selection);
        $attempt = 0;
        while (true) {
            $attempt++;
            $bodyFailure = null;
            try {
                return DB::transaction(function () use ($actor, $configurator, $selection, &$bodyFailure): array {
                    try {
                        return $this->saveSelected($actor, $configurator, $selection);
                    } catch (Throwable $exception) {
                        $bodyFailure = $exception;
                        throw $exception;
                    }
                }, attempts: 1);
            } catch (Throwable $exception) {
                $rolledBack = $exception === $bodyFailure;
                if ($rolledBack && ($exception instanceof ValidationException || $exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException)) {
                    throw $exception;
                }
                $cause = $exception;
                $driverCode = null;
                do {
                    if ($cause instanceof PDOException && isset($cause->errorInfo[1])) {
                        $driverCode = (int) $cause->errorInfo[1];
                        break;
                    }
                    $cause = $cause->getPrevious();
                } while ($cause !== null);
                if ($rolledBack && $attempt < 3 && in_array($driverCode, [1205, 1213], true)) {
                    continue;
                }
                throw new CatalogWriteFailure($rolledBack, $exception);
            }
        }
    }

    /** @param list<array{id:string,expected_revision:int,sequence:int}> $selection @return array<string,mixed> */
    private function saveSelected(User $actor, Configurator $configurator, array $selection): array
    {
        $drafts = [];
        $accepted = [];
        $ruleDrafts = [];
        try {
            $result = $this->writer->changeRulesWithIdentities($actor, $configurator, function (array $current, array $dictionary, array $labels) use ($configurator, $selection, &$drafts, &$accepted, &$ruleDrafts): array {
                $ids = array_column($selection, 'id');
                sort($ids, SORT_STRING);
                foreach ($ids as $id) {
                    $drafts[$id] = ConfiguratorRuleWritingDraft::where('configurator_id', $configurator->id)->whereKey($id)->lockForUpdate()->firstOrFail();
                }
                $mutations = [];
                $targets = [];
                $nextRuleIndex = count($current['rules']);
                foreach ($selection as $item) {
                    $draft = $drafts[$item['id']];
                    if ($draft->revision !== $item['expected_revision']) {
                        throw ValidationException::withMessages(['drafts.'.$draft->id.'.revision' => 'This shared draft changed. Keep the writing and open its current version separately.']);
                    }
                    if ($draft->format_version !== 1) {
                        throw ValidationException::withMessages(['drafts.'.$draft->id.'.source' => 'This Writing format is unsupported.']);
                    }
                    $creating = $draft->origin_rule_id === null;
                    $ruleIndex = $creating ? $nextRuleIndex++ : array_search((string) $draft->origin_rule_id, array_column($current['rules'], 'id'), true);
                    if ($ruleIndex !== false) {
                        $ruleDrafts[$ruleIndex] = $draft->id;
                    }
                    if (! $creating) {
                        if (isset($targets[$draft->origin_rule_id])) {
                            throw ValidationException::withMessages(['drafts' => 'Select only one draft for each saved Rule.']);
                        }
                        $targets[$draft->origin_rule_id] = true;
                        if (! is_array($draft->base_rule) || ! is_array($draft->bindings) || ! is_string($draft->bindings['source'] ?? null) || ! isset($draft->bindings['statements'], $draft->bindings['dictionary']) || (string) ($draft->base_rule['id'] ?? '') !== (string) $draft->origin_rule_id || ($draft->base_rule['kind'] ?? null) !== $draft->kind->value) {
                            throw ValidationException::withMessages(['drafts.'.$draft->id => 'The trusted Rule opening is unavailable. Reopen without replacing this writing.']);
                        }
                    }
                    $base = $creating ? ['id' => 'new:root', 'kind' => $draft->kind->value, 'label' => 'Writing draft', 'is_active' => true, 'priority' => 0, 'driver_configurator_attribute_id' => null, 'target_configurator_attribute_id' => null, 'conditions' => [], 'effects' => [], 'sets' => []] : $draft->base_rule;
                    $identity = $creating ? $base : ($draft->bindings['identity_rule'] ?? $base);
                    if (! is_array($identity) || ($identity['id'] ?? null) !== $base['id'] || ($identity['kind'] ?? null) !== $base['kind']) {
                        throw ValidationException::withMessages(['drafts.'.$draft->id => 'The trusted authoring identity is unavailable. Keep this writing.']);
                    }
                    $opening = ['source' => $creating ? '' : $draft->bindings['source'], 'rule' => $identity, 'bindings' => $creating ? ['statements' => [], 'dictionary' => $dictionary] : ['statements' => $draft->bindings['statements'], 'dictionary' => $draft->bindings['dictionary']], 'unsupported' => []];
                    $edited = $this->codec->edit($draft->source, $opening, $dictionary, reconstruct: $creating);
                    if ($edited['diagnostics'] !== [] || $edited['proposed_rule'] === null) {
                        throw ValidationException::withMessages(['drafts.'.$draft->id.'.source' => array_column($edited['diagnostics'], 'message') ?: ['Complete the Rule before saving.']]);
                    }
                    $proposal = $this->namespaceNewIds($edited['proposed_rule'], $draft->id);
                    $label = $draft->label;
                    if ($label === null || trim($label) === '') {
                        $saved = collect($current['rules'])->firstWhere('id', (string) $draft->origin_rule_id);
                        if (! $creating) {
                            $label = $saved['label'] ?? $base['label'];
                        } elseif ($proposal['kind'] === 'Mapping') {
                            $label = $labels[$proposal['driver_configurator_attribute_id']].' → '.$labels[$proposal['target_configurator_attribute_id']];
                        } else {
                            $label = 'Advanced · '.$labels[$proposal['effects'][0]['target_configurator_attribute_id']];
                        }
                        $label = mb_substr($label, 0, 255);
                    }
                    $proposal['label'] = $label;
                    $accepted[$draft->id] = $edited['reference_bindings'];
                    $mutations[] = ['expected_rule' => $creating ? null : $base, 'proposed_rule' => $proposal, 'reference_bindings' => $edited['reference_bindings']];
                }

                return $mutations;
            });
        } catch (ValidationException $exception) {
            throw $this->draftErrors($exception, $ruleDrafts);
        }
        $items = [];
        foreach ($selection as $index => $selected) {
            $draft = $drafts[$selected['id']];
            $saved = $result['rules'][$index];
            $bindings = ['source' => $draft->source, ...$accepted[$draft->id]];
            $capture = ['format_version' => 1, 'draft_id' => $draft->id, 'kind' => $draft->kind->value, 'source' => $draft->source, 'label' => $draft->label, 'description' => $draft->description,
                'submitted_revision' => $selected['expected_revision'], 'revision' => $draft->revision + 1, 'rule_id' => $saved['rule_id'], 'saved_rule' => $saved['saved_rule'], 'records' => $saved['records'], 'identities' => $saved['identities'],
                'reference_bindings' => $accepted[$draft->id], 'saved_fingerprint' => $this->recordReader->fingerprint($saved['records']), 'saved_by' => $actor->id, 'saved_at' => now()->utc()->toIso8601String()];
            if ($draft->origin_rule_id !== $saved['rule_id'] || $this->orderedObjects($draft->base_rule) !== $this->orderedObjects($saved['saved_rule']) || $this->orderedObjects($draft->bindings) !== $this->orderedObjects($bindings) || $this->captureContent($draft->last_saved_authoring) !== $this->captureContent($capture)) {
                $draft->forceFill(['origin_rule_id' => $saved['rule_id'], 'base_rule' => $saved['saved_rule'], 'bindings' => $bindings, 'last_saved_authoring' => $capture, 'revision' => $draft->revision + 1, 'updated_by' => $actor->id])->save();
                $draft->refresh();
            }
            $items[] = [...$saved, 'draft' => $draft->toArray(), 'acknowledgement' => ['sequence' => $selected['sequence'], 'submitted_revision' => $selected['expected_revision'], 'revision' => $draft->revision, 'source' => $draft->source, 'label' => $draft->label, 'description' => $draft->description]];
        }

        return ['configurator_id' => $result['configurator_id'], 'items' => $items];
    }

    /** @param array<int,string> $ruleDrafts */
    private function draftErrors(ValidationException $exception, array $ruleDrafts): ValidationException
    {
        if ($exception instanceof CatalogWriteReplan) {
            return $exception;
        }
        $errors = [];
        foreach ($exception->errors() as $key => $messages) {
            if (preg_match('/^rules\\.([0-9]+)(?:\\.(.*))?$/D', $key, $match) && isset($ruleDrafts[(int) $match[1]])) {
                $field = $match[2] ?? '';
                $key = 'drafts.'.$ruleDrafts[(int) $match[1]].($field === 'label' ? '.label' : '.source'.($field === '' ? '' : '.'.$field));
            }
            $errors[$key] = [...($errors[$key] ?? []), ...$messages];
        }
        if ($errors === $exception->errors()) {
            return $exception;
        }

        return ValidationException::withMessages($errors)->errorBag($exception->errorBag)->status($exception->status);
    }

    /** @param array<mixed> $selection @return list<array{id:string,expected_revision:int,sequence:int}> */
    private function selection(array $selection): array
    {
        if (! array_is_list($selection) || $selection === [] || count($selection) > 500) {
            throw ValidationException::withMessages(['selection' => 'Choose a nonempty list of at most 500 drafts.']);
        }
        $seen = [];
        foreach ($selection as &$item) {
            if (! is_array($item) || count($item) !== 3 || array_diff(array_keys($item), ['id', 'expected_revision', 'sequence']) !== [] || ! is_int($item['expected_revision'] ?? null) || $item['expected_revision'] < 1 || ! is_int($item['sequence'] ?? null) || $item['sequence'] < 0) {
                throw ValidationException::withMessages(['selection' => 'Provide a draft identifier, positive revision and nonnegative sequence.']);
            }
            Validator::make($item, ['id' => ['required', 'uuid']])->validate();
            $item['id'] = strtolower($item['id']);
            if (isset($seen[$item['id']])) {
                throw ValidationException::withMessages(['selection' => 'Each draft must be selected only once.']);
            }
            $seen[$item['id']] = true;
        }
        unset($item);

        return $selection;
    }

    /** @param array<string,mixed> $rule @return array<string,mixed> */
    private function namespaceNewIds(array $rule, string $draftId): array
    {
        if (isset($rule['id']) && str_starts_with((string) $rule['id'], 'new:')) {
            $rule['id'] = 'new:w_'.str_replace('-', '', $draftId).'_'.substr($rule['id'], 4);
        }
        foreach (['conditions', 'effects', 'sets'] as $bucket) {
            if (isset($rule[$bucket])) {
                $rule[$bucket] = array_map(fn ($row) => $this->namespaceNewIds($row, $draftId), $rule[$bucket]);
            }
        }

        return $rule;
    }

    private function orderedObjects(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->orderedObjects(...), $value);
    }

    /** @param array<string,mixed>|null $capture */
    private function captureContent(?array $capture): mixed
    {
        if ($capture === null) {
            return null;
        }
        unset($capture['submitted_revision'], $capture['revision'], $capture['saved_by'], $capture['saved_at'], $capture['identities']);

        return $this->orderedObjects($capture);
    }
}
