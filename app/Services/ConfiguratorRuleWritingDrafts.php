<?php

namespace App\Services;

use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use App\RuleKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

class ConfiguratorRuleWritingDrafts
{
    public function __construct(private ConfiguratorDefinitionLoader $loader, private ConfiguratorRuleWritingCodec $codec) {}

    /** @param array<string, mixed> $authoring @return array<string, mixed> */
    public function create(User $actor, Configurator $configurator, string $id, array $authoring, int $sequence): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $id = $this->uuid($id);
        $this->sequence($sequence);
        $data = $this->authoring($authoring, creating: true);

        return DB::transaction(function () use ($actor, $configurator, $id, $data, $sequence): array {
            $owner = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $draft = $this->scoped($owner)->whereKey($id)->lockForUpdate()->first();
            if ($draft !== null) {
                if ($draft->origin_rule_id !== null || $draft->kind->value !== $data['kind'] || $this->content($draft) !== $this->contentFrom($data)) {
                    throw ValidationException::withMessages(['draft' => 'This draft identifier already belongs to different writing.']);
                }
            } else {
                $draft = new ConfiguratorRuleWritingDraft;
                $draft->forceFill(['id' => $id, 'configurator_id' => $owner->id, ...$data, 'format_version' => 1, 'revision' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id])->save();
                $draft->refresh();
            }

            return $this->acknowledged($draft, $sequence);
        });
    }

    /** @param array<string, mixed> $authoring @return array<string, mixed> */
    public function update(User $actor, Configurator $configurator, string $id, int $expectedRevision, array $authoring, int $sequence): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $id = $this->uuid($id);
        $this->sequence($sequence);
        if ($expectedRevision < 1) {
            throw ValidationException::withMessages(['draft_revision' => 'A positive draft revision is required.']);
        }
        $data = $this->authoring($authoring);

        return DB::transaction(function () use ($actor, $configurator, $id, $expectedRevision, $data, $sequence): array {
            $owner = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            $draft = $this->scoped($owner)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($draft->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['draft_revision' => 'This draft changed in another session. Keep your writing and open the current draft separately.']);
            }
            if ($this->content($draft) !== $data) {
                $draft->forceFill([...$data, 'revision' => $draft->revision + 1, 'updated_by' => $actor->id])->save();
                $draft->refresh();
            }

            return $this->acknowledged($draft, $sequence);
        });
    }

    /** @return array<string, mixed> */
    public function openRule(User $actor, Configurator $configurator, int $ruleId, string $newDraftId, bool $fresh = false): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $newDraftId = $this->uuid($newDraftId);
        $ambient = DB::transactionLevel() > 0;

        if (! $fresh) {
            $existing = DB::transaction(function () use ($configurator, $ruleId): ?array {
                $owner = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
                $draft = $this->linkedDraft($owner, $ruleId);

                return $draft === null ? null : $this->opened($draft);
            });
            if ($existing !== null) {
                return $existing;
            }
        }
        if ($ambient) {
            throw new LogicException('A fresh linked Writing draft must be opened outside an ambient transaction.');
        }

        return DB::transaction(function () use ($actor, $configurator, $ruleId, $newDraftId, $fresh): array {
            $owner = Configurator::whereKey($configurator->id)->lockForUpdate()->firstOrFail();
            CatalogContextSettings::current(lock: true);
            $graph = $this->loader->draft($owner);
            [$attributes, $options] = $this->loader->canonical($graph, lock: true);
            if (! $fresh) {
                $existing = $this->linkedDraft($owner, $ruleId);
                if ($existing !== null) {
                    return $this->opened($existing);
                }
            }
            $rule = collect($graph['rules'])->first(fn (array $row): bool => (int) $row['id'] === $ruleId);
            if ($rule === null) {
                throw (new ModelNotFoundException)->setModel(ConfiguratorRule::class, [$ruleId]);
            }
            $dictionary = $this->codec->dictionary($graph, $attributes, $options);
            $opening = $this->codec->open($rule, $dictionary);
            if ($opening['unsupported'] !== []) {
                throw ValidationException::withMessages(['rule' => $opening['unsupported']]);
            }
            $proof = $this->codec->edit($opening['source'], $opening, $dictionary, reconstruct: true);
            if ($proof['diagnostics'] !== [] || $proof['proposed_rule'] !== $rule) {
                throw ValidationException::withMessages(['rule' => 'The saved Rule cannot be opened losslessly in Writing. Use the regular Edit form.']);
            }
            $bindings = ['source' => $opening['source'], ...$opening['bindings']];
            $draft = $this->scoped($owner)->whereKey($newDraftId)->lockForUpdate()->first();
            if ($draft !== null) {
                if ($draft->origin_rule_id !== $ruleId || $draft->kind->value !== $rule['kind'] || $draft->label !== $rule['label'] || $draft->description !== null || $draft->last_saved_authoring !== null || $draft->source !== $opening['source'] || $this->orderedObject($draft->base_rule) !== $this->orderedObject($rule) || $this->orderedObject($draft->bindings) !== $this->orderedObject($bindings)) {
                    throw ValidationException::withMessages(['draft' => 'This draft identifier already belongs to a different opening.']);
                }
            } else {
                $draft = new ConfiguratorRuleWritingDraft;
                $draft->forceFill(['id' => $newDraftId, 'configurator_id' => $owner->id, 'kind' => $rule['kind'], 'source' => $opening['source'], 'label' => $rule['label'], 'description' => null, 'origin_rule_id' => $ruleId, 'base_rule' => $rule, 'bindings' => $bindings, 'format_version' => 1, 'revision' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id])->save();
                $draft->refresh();
            }

            return $this->opened($draft);
        });
    }

    /** @return array<string, mixed> */
    public function get(User $actor, Configurator $configurator, string $id): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return $this->opened($this->scoped($configurator)->whereKey($this->uuid($id))->firstOrFail());
    }

    /** @return list<array<string, mixed>> */
    public function listing(User $actor, Configurator $configurator): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');

        return $this->scoped($configurator)->orderBy('created_at')->orderBy('id')->get()->map(fn (ConfiguratorRuleWritingDraft $draft): array => $this->snapshot($draft))->all();
    }

    private function linkedDraft(Configurator $owner, int $ruleId): ?ConfiguratorRuleWritingDraft
    {
        $id = $this->scoped($owner)->where('origin_rule_id', $ruleId)
            ->orderBy('created_at')->orderBy('id')->lockForUpdate()->value('id');

        return $id === null ? null : $this->scoped($owner)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return Builder<ConfiguratorRuleWritingDraft> */
    private function scoped(Configurator $owner): Builder
    {
        return ConfiguratorRuleWritingDraft::where('configurator_id', $owner->id);
    }

    private function uuid(string $id): string
    {
        Validator::make(['id' => $id], ['id' => ['required', 'uuid']])->validate();

        return strtolower($id);
    }

    private function sequence(int $sequence): void
    {
        if ($sequence < 0) {
            throw ValidationException::withMessages(['sequence' => 'A nonnegative client sequence is required.']);
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function authoring(array $input, bool $creating = false): array
    {
        $allowed = ['source', 'label', 'description', ...($creating ? ['kind'] : [])];
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw ValidationException::withMessages(['draft' => 'Only Writing source and optional metadata may be changed.']);
        }
        $rules = ['source' => ['present', 'string'], 'label' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000']];
        if ($creating) {
            $rules['kind'] = ['required', Rule::in(array_column(RuleKind::cases(), 'value'))];
        }
        Validator::make($input, $rules)->validate();
        foreach (['source', 'label', 'description'] as $key) {
            if (isset($input[$key]) && ! mb_check_encoding($input[$key], 'UTF-8')) {
                throw ValidationException::withMessages([$key => 'Writing must be valid UTF-8.']);
            }
        }
        if (strlen($input['source']) > 1048576) {
            throw ValidationException::withMessages(['source' => 'Writing exceeds the 1 MiB draft limit.']);
        }

        return [...($creating ? ['kind' => $input['kind']] : []), ...$this->contentFrom($input)];
    }

    /** @param array<string, mixed> $data @return array{source:string,label:?string,description:?string} */
    private function contentFrom(array $data): array
    {
        return ['source' => $data['source'], 'label' => $data['label'] ?? null, 'description' => $data['description'] ?? null];
    }

    /** @return array{source:string,label:?string,description:?string} */
    private function content(ConfiguratorRuleWritingDraft $draft): array
    {
        return $draft->only(['source', 'label', 'description']);
    }

    /** @return array<string, mixed> */
    private function snapshot(ConfiguratorRuleWritingDraft $draft): array
    {
        return $draft->toArray();
    }

    /** @return array<string, mixed> */
    private function opened(ConfiguratorRuleWritingDraft $draft): array
    {
        return ['draft' => $this->snapshot($draft), 'generated' => $draft->bindings !== null && $draft->source === ($draft->bindings['source'] ?? null) && $draft->last_saved_authoring === null];
    }

    /** @return array<string, mixed> Provisional when the caller owns an outer transaction. */
    private function acknowledged(ConfiguratorRuleWritingDraft $draft, int $sequence): array
    {
        return [...$this->opened($draft), 'acknowledgement' => ['sequence' => $sequence, 'revision' => $draft->revision, ...$this->content($draft)]];
    }

    private function orderedObject(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->orderedObject(...), $value);
    }
}
