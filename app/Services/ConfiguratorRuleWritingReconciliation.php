<?php

namespace App\Services;

use App\CatalogWriteFailure;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Throwable;

class ConfiguratorRuleWritingReconciliation
{
    public function __construct(private ConfiguratorDefinitionLoader $loader, private ConfiguratorRuleWritingCodec $codec, private ConfiguratorRuleWritingParser $parser, private ConfiguratorRuleWritingSections $sections) {}

    /** @return array<string,mixed> Displayed data only, never a save or testing receipt. */
    public function capture(User $actor, Configurator $configurator, string $draftId, int $expectedRevision): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $draftId = $this->inputs($draftId, $expectedRevision);
        $this->key();

        return $this->transaction(function () use ($actor, $configurator, $draftId, $expectedRevision): array {
            [$owner, $dictionary] = $this->lockedReferences($configurator);
            $draft = $this->draft($owner, $draftId, $expectedRevision);
            $this->trusted($draft);
            $rule = ConfiguratorRule::where('configurator_id', $owner->id)->whereKey($draft->origin_rule_id)
                ->with(['conditionGroups', 'conditions.optionReferences', 'effects.optionReferences', 'mappingSets.sources', 'mappingSets.targets'])->firstOrFail();
            try {
                $latest = $this->loader->rule($rule);
            } catch (\ValueError $exception) {
                throw new InvalidArgumentException('The saved Rule contains unsupported stored values. Use regular Edit.', previous: $exception);
            }
            if ($latest['kind'] !== $draft->kind->value) {
                throw new InvalidArgumentException('The saved Rule kind changed. Keep the draft and use regular Edit.');
            }
            $opening = $this->codec->open($latest, $dictionary);
            if ($opening['unsupported'] !== []) {
                throw new InvalidArgumentException('The saved Rule is not supported by Writing.');
            }
            $proof = $this->codec->edit($opening['source'], $opening, $dictionary, reconstruct: true);
            if ($proof['diagnostics'] !== [] || $proof['proposed_rule'] !== $latest) {
                throw new InvalidArgumentException('The displayed Rule cannot be opened losslessly.');
            }
            $payload = ['purpose' => 'rule-writing-section-refresh', 'version' => 1, 'actor_id' => $actor->getAuthIdentifier(), 'configurator_id' => $owner->id,
                'draft_id' => $draft->id, 'draft_revision' => $draft->revision, 'origin_rule_id' => $draft->origin_rule_id, 'kind' => $draft->kind->value,
                'latest_rule' => $latest, 'latest_source' => $opening['source'], 'dictionary' => $dictionary];
            $snapshot = ['payload' => $payload, 'signature' => $this->signature($payload)];
            $this->snapshotSize($snapshot);

            return ['draft' => $draft->toArray(), 'snapshot' => $snapshot];
        });
    }

    /** @param array<string,mixed> $snapshot @param list<string> $checkedSections @return array<string,mixed> */
    public function updateDraft(User $actor, Configurator $configurator, string $draftId, int $expectedRevision, array $snapshot, array $checkedSections, int $sequence): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $draftId = $this->inputs($draftId, $expectedRevision);
        if ($sequence < 0) {
            throw ValidationException::withMessages(['sequence' => 'A nonnegative response sequence is required.']);
        }
        $payload = $this->verified($snapshot, $actor, $configurator, $draftId, $expectedRevision);
        $outcome = $payload['kind'] === 'Mapping' ? 'mapping' : 'effects';
        if (! array_is_list($checkedSections) || array_filter($checkedSections, fn ($section) => ! is_string($section)) !== [] || count(array_unique($checkedSections)) !== count($checkedSections) || array_diff($checkedSections, ['label', 'conditions', $outcome]) !== []) {
            throw ValidationException::withMessages(['sections' => 'Choose explicit, unique sections of this Rule.']);
        }
        $checked = array_values(array_diff($checkedSections, ['label']));

        return $this->transaction(function () use ($actor, $configurator, $draftId, $expectedRevision, $payload, $checkedSections, $checked, $sequence, $outcome): array {
            [$owner, $freshDictionary] = $this->lockedReferences($configurator);
            $draft = $this->draft($owner, $draftId, $expectedRevision);
            $identity = $this->trusted($draft);
            if ($draft->origin_rule_id !== $payload['origin_rule_id'] || $draft->kind->value !== $payload['kind']) {
                throw new InvalidArgumentException('The draft no longer matches the displayed saved Rule.');
            }
            $target = ConfiguratorRule::whereKey($draft->origin_rule_id)->first();
            if ($target !== null && $target->configurator_id !== $owner->id) {
                throw (new ModelNotFoundException)->setModel(ConfiguratorRule::class, [$draft->origin_rule_id]);
            }
            $latest = $payload['latest_rule'];
            $oldSource = $draft->bindings['source'];
            $this->sections->replace($oldSource, $payload['latest_source'], [], $draft->kind->value);
            $oldRanges = $this->parser->sections($oldSource);
            if ($oldRanges[$outcome === 'mapping' ? 'effects' : 'mapping'] !== null) {
                throw new InvalidArgumentException('The accepted opening has the wrong outcome kind.');
            }
            foreach (['conditions', $outcome] as $section) {
                if (! in_array($section, $checked, true)) {
                    $this->assertSurvivingIdentities($identity, $latest, $section);
                    $nodes = $this->sectionNodes($draft->bindings['statements'], $section);
                    $this->fence($nodes, $draft->bindings['dictionary'], $payload['dictionary']);
                }
            }
            $originalCurrent = $this->parser->parse($draft->source);
            if ($originalCurrent['diagnostics'] === []) {
                foreach (['conditions', $outcome] as $section) {
                    if (! in_array($section, $checked, true)) {
                        $this->fence($this->sectionNodes($originalCurrent['statements'], $section), $draft->bindings['dictionary'], $payload['dictionary']);
                    }
                }
            }
            $source = $this->sections->replace($draft->source, $payload['latest_source'], $checked, $draft->kind->value);
            $acceptedSource = $this->sections->replace($oldSource, $payload['latest_source'], $checked, $draft->kind->value);
            $this->sourceSize($source);
            $this->sourceSize($acceptedSource);
            $accepted = $this->parser->parse($acceptedSource);
            if ($accepted['diagnostics'] !== []) {
                throw new InvalidArgumentException('The accepted section replacement is ambiguous.');
            }
            $this->fence($accepted['statements'], $payload['dictionary'], $freshDictionary);
            $current = $this->parser->parse($source);
            if ($current['diagnostics'] === []) {
                $this->fence($current['statements'], $payload['dictionary'], $freshDictionary);
            }
            $hybrid = $identity;
            foreach (['id', 'kind', 'label', 'is_active', 'priority'] as $field) {
                $hybrid[$field] = $latest[$field];
            }
            if (in_array('conditions', $checked, true)) {
                $hybrid['conditions'] = $latest['conditions'];
            }
            if (in_array($outcome, $checked, true)) {
                foreach ($outcome === 'mapping' ? ['driver_configurator_attribute_id', 'target_configurator_attribute_id', 'sets'] : ['effects'] as $field) {
                    $hybrid[$field] = $latest[$field];
                }
            }
            $label = in_array('label', $checkedSections, true) ? $latest['label'] : $draft->label;
            $bindings = ['source' => $acceptedSource, 'statements' => $accepted['statements'], 'dictionary' => $payload['dictionary'], 'identity_rule' => $hybrid];
            $changed = $draft->source !== $source || $draft->label !== $label || $this->ordered($draft->base_rule) !== $this->ordered($latest) || $this->ordered($draft->bindings) !== $this->ordered($bindings);
            if ($changed) {
                $draft->forceFill(['source' => $source, 'label' => $label, 'base_rule' => $latest, 'bindings' => $bindings, 'revision' => $draft->revision + 1, 'updated_by' => $actor->id])->save();
                $draft->refresh();
            }

            return ['draft' => $draft->toArray(), 'acknowledgement' => ['sequence' => $sequence, 'revision' => $draft->revision, 'source' => $draft->source, 'label' => $draft->label, 'description' => $draft->description]];
        });
    }

    /** @return array{Configurator,array} */
    private function lockedReferences(Configurator $configurator): array
    {
        $owner = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
        CatalogContextSettings::current(lock: true);
        $owner->load('attributes.options');
        $graph = ['attributes' => $owner->attributes->map(fn ($attribute) => ['id' => (string) $attribute->id, 'attribute_id' => $attribute->attribute_id, 'options' => $attribute->options->map(fn ($option) => ['id' => (string) $option->id, 'option_id' => $option->option_id])->all()])->all()];
        [$attributes, $options] = $this->loader->canonical($graph, lock: true);

        return [$owner, $this->codec->dictionary($graph, $attributes, $options)];
    }

    private function draft(Configurator $owner, string $draftId, int $expectedRevision): ConfiguratorRuleWritingDraft
    {
        $draft = ConfiguratorRuleWritingDraft::where('configurator_id', $owner->id)->whereKey($draftId)->lockForUpdate()->firstOrFail();
        if ($draft->revision !== $expectedRevision) {
            throw ValidationException::withMessages(['draft_revision' => 'This shared draft changed. Keep your writing and reopen its current version separately.']);
        }

        return $draft;
    }

    /** @return array<string,mixed> */
    private function trusted(ConfiguratorRuleWritingDraft $draft): array
    {
        $base = $draft->base_rule;
        $bindings = $draft->bindings;
        $identity = $bindings['identity_rule'] ?? $base;
        if ($draft->format_version !== 1 || $draft->origin_rule_id === null || ! is_array($base) || ! is_array($bindings) || ! is_string($bindings['source'] ?? null) || ! is_array($bindings['statements'] ?? null) || ! is_array($bindings['dictionary'] ?? null)
            || ! is_array($identity) || ($base['id'] ?? null) !== (string) $draft->origin_rule_id || ($identity['id'] ?? null) !== $base['id'] || ($base['kind'] ?? null) !== $draft->kind->value || ($identity['kind'] ?? null) !== $base['kind']) {
            throw new InvalidArgumentException('A trusted linked Rule opening is required. Keep this draft.');
        }

        return $identity;
    }

    /** @param array $old @param array $latest */
    private function assertSurvivingIdentities(array $old, array $latest, string $section): void
    {
        $buckets = function (array $rule) use ($section): array {
            $out = ['conditions' => [], 'groups' => [], 'sets' => [], 'effects' => []];
            if ($section === 'conditions') {
                foreach ($rule['conditions'] as $row) {
                    if (isset($row['conditions'])) {
                        $out['groups'][] = $row['id'];
                        foreach ($row['conditions'] as $child) {
                            $out['conditions'][] = $child['id'];
                        }
                    } else {
                        $out['conditions'][] = $row['id'];
                    }
                }
            } else {
                $bucket = $section === 'mapping' ? 'sets' : 'effects';
                $out[$bucket] = array_column($rule[$bucket], 'id');
            }

            return $out;
        };
        $current = $buckets($latest);
        foreach ($buckets($old) as $bucket => $ids) {
            if (count(array_unique($ids, SORT_STRING)) !== count($ids) || array_diff($ids, $current[$bucket]) !== [] || count(array_unique($current[$bucket], SORT_STRING)) !== count($current[$bucket])) {
                throw new InvalidArgumentException('An unchecked owned identity disappeared or became ambiguous. Choose its complete latest section instead.');
            }
        }
    }

    /** @param array $nodes @return array */
    private function sectionNodes(array $nodes, string $section): array
    {
        return array_values(array_filter($nodes, fn ($node) => in_array($node['kind'], ['condition', 'group'], true) === ($section === 'conditions')));
    }

    /** @param array $nodes @param array $old @param array $fresh */
    private function fence(array $nodes, array $old, array $fresh): void
    {
        if ($nodes !== []) {
            $this->codec->validateReferenceBindings(['statements' => $nodes, 'dictionary' => $old], $fresh);
        }
    }

    private function inputs(string $draftId, int $expectedRevision): string
    {
        Validator::make(['id' => $draftId], ['id' => ['required', 'uuid']])->validate();
        if ($expectedRevision < 1) {
            throw ValidationException::withMessages(['draft_revision' => 'A positive shared revision is required.']);
        }

        return strtolower($draftId);
    }

    private function sourceSize(string $source): void
    {
        if (! mb_check_encoding($source, 'UTF-8') || strlen($source) > 1048576) {
            throw new InvalidArgumentException('Writing must remain valid UTF-8 within the 1 MiB limit.');
        }
    }

    private function key(): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw ValidationException::withMessages(['snapshot' => 'A configured application signing key is required.']);
        }

        return $key;
    }

    private function signature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($this->ordered($payload), JSON_THROW_ON_ERROR), $this->key());
    }

    private function snapshotSize(array $snapshot): void
    {
        try {
            $size = strlen(json_encode($this->ordered($snapshot), JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['snapshot' => 'The displayed snapshot must contain valid JSON text.']);
        }
        if ($size > 8388608) {
            throw ValidationException::withMessages(['snapshot' => 'The displayed snapshot exceeds the 8 MiB limit.']);
        }
    }

    /** @param array $snapshot @return array */
    private function verified(array $snapshot, User $actor, Configurator $owner, string $id, int $revision): array
    {
        $this->snapshotSize($snapshot);
        $payload = $snapshot['payload'] ?? null;
        if (array_diff(array_keys($snapshot), ['payload', 'signature']) !== [] || ! is_array($payload) || ! is_string($snapshot['signature'] ?? null) || ! hash_equals($this->signature($payload), $snapshot['signature'])
            || ($payload['purpose'] ?? null) !== 'rule-writing-section-refresh' || ($payload['version'] ?? null) !== 1 || ($payload['actor_id'] ?? null) !== $actor->getAuthIdentifier() || ($payload['configurator_id'] ?? null) !== $owner->id || ($payload['draft_id'] ?? null) !== $id || ($payload['draft_revision'] ?? null) !== $revision
            || ! in_array($payload['kind'] ?? null, ['Mapping', 'Advanced'], true) || ! is_array($payload['latest_rule'] ?? null) || ! is_string($payload['latest_source'] ?? null) || ! is_array($payload['dictionary'] ?? null)) {
            throw ValidationException::withMessages(['snapshot' => 'This displayed Rule snapshot is invalid or belongs to another actor, owner or draft revision.']);
        }

        return $payload;
    }

    private function ordered(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->ordered(...), $value);
    }

    /** @return array<string,mixed> */
    private function transaction(\Closure $operation): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Writing section reconciliation must own its outer transaction.');
        }
        $bodyFailure = null;
        try {
            return DB::transaction(function () use ($operation, &$bodyFailure): array {
                try {
                    return $operation();
                } catch (Throwable $exception) {
                    $bodyFailure = $exception;
                    throw $exception;
                }
            }, attempts: 1);
        } catch (Throwable $exception) {
            $rolledBack = $exception === $bodyFailure;
            if ($rolledBack && $exception instanceof InvalidArgumentException) {
                throw ValidationException::withMessages(['draft' => $exception->getMessage()]);
            }
            if ($rolledBack && ($exception instanceof ValidationException || $exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException)) {
                throw $exception;
            }
            throw new CatalogWriteFailure($rolledBack, $exception);
        }
    }
}
