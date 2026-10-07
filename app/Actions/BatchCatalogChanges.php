<?php

namespace App\Actions;

use App\ConfigInputType;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\CanonicalUsage;
use App\Services\CatalogImportParser;
use App\Services\CatalogIntegrity;
use App\Services\CatalogPolicy;
use App\Services\CatalogRevisions;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\InclusionUsage;
use App\Services\StringFieldTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BatchCatalogChanges
{
    /** @return array<string, array{type: string, nullable: bool, rules: array, options?: array}> */
    public function fields(string $table): array
    {
        $text = fn (bool $nullable = false, int $max = 255): array => ['type' => 'text', 'nullable' => $nullable, 'rules' => [$nullable ? 'nullable' : 'required', 'string', 'max:'.$max]];
        $boolean = ['type' => 'boolean', 'nullable' => false, 'rules' => ['required', 'boolean']];

        return match ($table) {
            'values' => ['label' => $text(), 'description' => $text(true, 5000), 'tags' => ['type' => 'tags', 'nullable' => true, 'rules' => ['array', 'list', 'max:30']]],
            'attributes' => ['label' => $text()],
            'options', 'attribute-options' => ['code' => ['type' => 'text', 'nullable' => false, 'rules' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{2}\z/D']]],
            'configurators' => ['name' => $text(), 'description' => $text(true, 5000)],
            'groups' => ['description' => $text(true, 5000),
                'card_properties' => ['type' => 'multiple', 'nullable' => true, 'rules' => ['array', 'list'], 'options' => array_combine(CatalogImportParser::propertyKeys(), CatalogImportParser::propertyKeys())],
                'cards_per_row' => ['type' => 'integer', 'nullable' => false, 'rules' => ['required', 'integer', 'between:1,6']],
                'max_results' => ['type' => 'select', 'nullable' => false, 'rules' => ['required', Rule::in(['all', ...range(1, 24)])], 'options' => ['all' => 'All'] + array_combine(range(1, 24), range(1, 24))],
                'products_debounce_ms' => ['type' => 'integer', 'nullable' => false, 'rules' => ['required', 'integer', 'min:0', 'max:2147483600', 'multiple_of:100']],
                'card_only_differences' => $boolean, 'card_show_labels' => $boolean,
                'card_property_layout' => ['type' => 'select', 'nullable' => false, 'rules' => ['required', Rule::in(array_keys(CatalogPolicy::CARD_LAYOUTS))], 'options' => CatalogPolicy::CARD_LAYOUTS],
                'card_property_columns' => ['type' => 'select', 'nullable' => false, 'rules' => ['required', Rule::in([1, 2])], 'options' => [1 => '1', 2 => '2']],
                'card_padding_block' => ['type' => 'integer', 'nullable' => false, 'rules' => ['required', 'integer', 'between:0,16']],
                'card_padding_inline' => ['type' => 'integer', 'nullable' => false, 'rules' => ['required', 'integer', 'between:0,20']]],
            'configurator-attributes' => ['label_override' => $text(true), 'help_text' => $text(true, 1000), 'input_type' => ['type' => 'select', 'nullable' => false, 'rules' => ['required', Rule::enum(ConfigInputType::class)], 'options' => array_column(ConfigInputType::cases(), 'name', 'value')]],
            'configurator-options' => ['label_override' => $text(true), 'display_value_override' => $text(true), 'hint' => $text(true, 1000), 'hidden_by_default' => $boolean, 'disabled_by_default' => $boolean],
            'configurator-rules' => ['label' => $text(), 'is_active' => $boolean],
            default => throw ValidationException::withMessages(['batch' => 'This table does not support batch edits.']),
        };
    }

    public function canRemove(string $table): bool
    {
        return in_array($table, ['values', 'attributes', 'options', 'attribute-options', 'configurators', 'configurator-attributes', 'configurator-options', 'configurator-rules'], true);
    }

    /** @param list<int|string> $ids @param array<string, mixed> $intent @return array{token: string, rows: array} */
    public function preview(User $actor, string $table, array $ids, array $intent, ?int $ownerId = null): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $records = $this->records($table, $ids, $ownerId);
        $rows = [];
        $blockers = [];
        foreach ($records as $record) {
            $before = $this->state($record);
            $after = $this->transform($table, $record, $intent);
            $impact = null;
            if ($record instanceof Option && isset($after['code']) && $before['code'] !== $after['code']) {
                $owners = app(CanonicalUsage::class)->configurators($record);
                $impact = ['count' => (clone $owners)->count(), 'owners' => $owners->orderBy('id')->limit(5)->get(['id', 'name'])->toArray()];
            }
            $rows[] = ['id' => $record->id, 'name' => $this->recordName($record), 'before' => $before, 'after' => $after, 'changed' => ($intent['operation'] ?? '') === 'remove' || $before !== $after, 'impact' => $impact];
            if (($intent['operation'] ?? '') === 'remove') {
                $blockers = [...$blockers, ...$this->removalBlockers($record)];
            }
        }
        $this->validateSelectedCodes($table, array_column($rows, 'after'));

        return ['token' => $this->token($actor, $table, $records, $intent, $ownerId), 'rows' => $rows, 'blockers' => $blockers, 'blocked_count' => count(array_unique(array_column($blockers, 'parent_id')))];
    }

    /** @param list<int|string> $ids @param array<string, mixed> $intent */
    public function apply(User $actor, string $table, array $ids, array $intent, string $token, ?int $ownerId = null): void
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        DB::transaction(function () use ($actor, $table, $ids, $intent, $token, $ownerId): void {
            app(CatalogRevisions::class)->batch(function () use ($actor, $table, $ids, $intent, $token, $ownerId): void {
                if (in_array($table, ['groups', 'configurators'], true)) {
                    app(CatalogIntegrity::class)->lockGroups();
                }
                $initial = $this->records($table, $ids, $ownerId);
                $owners = [];
                foreach ($initial as $record) {
                    $owners = [...$owners, ...$this->ownerIds($record)];
                }
                Configurator::whereIn('id', array_unique($owners))->orderBy('id')->lockForUpdate()->get();
                $records = $this->records($table, $ids, $ownerId, lock: true);
                if (! hash_equals($this->token($actor, $table, $records, $intent, $ownerId), $token)) {
                    throw ValidationException::withMessages(['preview' => 'Selected records changed, or your operation changed. Refresh the preview before applying. Your inputs were kept.']);
                }
                $after = [];
                foreach ($records as $record) {
                    $after[$record->id] = $this->transform($table, $record, $intent);
                    if (($intent['operation'] ?? '') === 'remove' && $this->removalBlockers($record) !== []) {
                        throw ValidationException::withMessages(['preview' => 'Dependencies block this selection. Open the blocking records and repair them before removing anything.']);
                    }
                }
                $this->validateSelectedCodes($table, $after);
                if (str_starts_with($table, 'configurator-')) {
                    $this->saveLocal($actor, $table, $records, $after, $intent, $ownerId);

                    return;
                }
                foreach ($records as $record) {
                    if (($intent['operation'] ?? '') === 'remove') {
                        $record instanceof Configurator ? app(DeleteConfigurator::class)->handle($actor, $record) : app(DeleteCanonicalDefinition::class)->handle($actor, $record);
                    } elseif ($record instanceof Attribute || $record instanceof Value) {
                        app(SaveCanonicalDefinition::class)->handle($actor, $record, $after[$record->id]);
                    } elseif ($record instanceof Option) {
                        app(SaveCanonicalOption::class)->handle($actor, $record, $record->attribute_id, $record->value_id, $after[$record->id]['code']);
                    } elseif ($record instanceof Configurator) {
                        app(SaveConfiguratorDefinition::class)->change($actor, $record, fn (array $draft): array => [...$draft, ...$after[$record->id]]);
                    } elseif ($record instanceof Group) {
                        $state = $after[$record->id];
                        $before = $this->state($record);
                        $presentationChanges = array_filter($state, fn ($value, string $key): bool => $key !== 'description' && $value !== $before[$key], ARRAY_FILTER_USE_BOTH);
                        app(SaveCatalogGroup::class)->handle($actor, $record, [...$record->only(['name', 'parent_id', 'sort_order', 'configurator_id']), 'description' => $state['description']]);
                        if ($presentationChanges !== []) {
                            $settings = GroupForm::settingsState($record);
                            $settings['result_settings'] = $presentationChanges;
                            app(SaveGroupSettings::class)->handle($actor, $record, $settings);
                        }
                    }
                }
            });
        }, attempts: 3);
    }

    /** @param array<int, array<string, mixed>> $states */
    private function validateSelectedCodes(string $table, array $states): void
    {
        if (! in_array($table, ['options', 'attribute-options'], true)) {
            return;
        }
        $codes = array_column($states, 'code');
        if (count(array_unique($codes, SORT_STRING)) !== count($codes)) {
            throw ValidationException::withMessages(['code' => 'The resulting selection contains duplicate codes. Each code must remain unique.']);
        }
    }

    /** @return list<array{category: string, count: int, parent_id: int, name: string, list_key: string}> */
    private function removalBlockers(Model $record): array
    {
        $name = $this->recordName($record);
        $keys = [];
        if ($record instanceof Attribute || $record instanceof Value || $record instanceof Option) {
            $type = match (true) {
                $record instanceof Attribute => 'attribute', $record instanceof Value => 'value', default => 'option'
            };
            foreach (app(CanonicalUsage::class)->counts($record) as $category => $count) {
                if (! ($record instanceof Option && $category === 'options')) {
                    $keys[$category] = ['canonical-'.$type.'-'.$category, $count];
                }
            }
        } elseif ($record instanceof Configurator) {
            $keys['groups'] = ['configurator-groups', $record->groups()->count()];
        } elseif ($record instanceof ConfiguratorAttribute || $record instanceof ConfiguratorOption) {
            $keys['rules'] = [$record instanceof ConfiguratorAttribute ? 'inclusion-blocking-rules' : 'local-option-blocking-rules', app(InclusionUsage::class)->rules($record)->count()];
            if ($record instanceof ConfiguratorOption) {
                $keys['defaults'] = ['local-option-defaults', app(InclusionUsage::class)->defaults($record)->count()];
            }
        }
        $blockers = [];
        foreach ($keys as $category => [$key, $count]) {
            if ($count > 0) {
                $blockers[] = ['category' => $category, 'count' => $count, 'parent_id' => $record->id, 'name' => $name, 'list_key' => $key];
            }
        }

        return $blockers;
    }

    /** @param list<int|string> $ids */
    private function records(string $table, array $ids, ?int $ownerId, bool $lock = false): Collection
    {
        Validator::make(['ids' => $ids], ['ids' => ['required', 'array', 'list', 'min:1', 'max:500'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct']])->validate();
        $class = match ($table) {
            'attributes' => Attribute::class, 'values' => Value::class, 'options', 'attribute-options' => Option::class,
            'configurators' => Configurator::class, 'groups' => Group::class, 'configurator-attributes' => ConfiguratorAttribute::class,
            'configurator-options' => ConfiguratorOption::class, 'configurator-rules' => ConfiguratorRule::class,
            default => throw ValidationException::withMessages(['batch' => 'Unsupported table.']),
        };
        $query = $class::query()->whereKey($ids)->orderBy('id');
        if ($class === ConfiguratorAttribute::class) {
            $query->with('attribute');
        } elseif ($class === ConfiguratorOption::class) {
            $query->with('option.value');
        }
        if (in_array($table, ['attribute-options', 'configurator-attributes', 'configurator-options', 'configurator-rules'], true)) {
            if ($ownerId === null) {
                throw ValidationException::withMessages(['batch' => 'An owner is required.']);
            }
            $column = match ($table) {
                'attribute-options' => 'attribute_id', 'configurator-options' => 'configurator_attribute_id', default => 'configurator_id'
            };
            $query->where($column, $ownerId);
        }
        $records = $query->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->get();
        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages(['batch' => 'A selected record was removed or does not belong to this table. Refresh selection.']);
        }

        return $records;
    }

    private function recordName(Model $record): string
    {
        return match (true) {
            $record instanceof ConfiguratorAttribute => $record->label_override ?? $record->attribute->label,
            $record instanceof ConfiguratorOption => ($record->label_override ?? $record->option->value->label).' · '.$record->option->code,
            default => $record->name ?? $record->label ?? $record->code ?? '#'.$record->id,
        };
    }

    /** @return array<string, mixed> */
    private function state(Model $record): array
    {
        return match (true) {
            $record instanceof Attribute => $record->only(['key', 'label']),
            $record instanceof Value => $record->only(['label', 'description', 'tags']),
            $record instanceof Option => $record->only(['code']),
            $record instanceof Configurator => $record->only(['name', 'description']),
            $record instanceof Group => ['description' => $record->description, ...array_intersect_key(CatalogPolicy::resultSettings($record->result_settings), $this->fields('groups'))],
            $record instanceof ConfiguratorAttribute => $record->only(['label_override', 'help_text', 'input_type']),
            $record instanceof ConfiguratorOption => $record->only(['label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default']),
            $record instanceof ConfiguratorRule => $record->only(['label', 'is_active']),
        };
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function transform(string $table, Model $record, array $intent): array
    {
        $state = $this->state($record);
        $fields = $this->fields($table);
        $operation = $intent['operation'] ?? null;
        if ($operation === 'remove') {
            if (! $this->canRemove($table)) {
                throw ValidationException::withMessages(['batch' => 'Removal is not available for this table.']);
            }

            return [];
        }
        if ($operation === 'replace') {
            $field = $intent['field'] ?? '';
            if (($fields[$field]['type'] ?? '') !== 'text') {
                throw ValidationException::withMessages(['field' => 'Choose a permitted string field.']);
            }
            try {
                $state[$field] = app(StringFieldTransformer::class)->replace($state[$field], $intent);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['search' => $exception->getMessage()]);
            }
        } elseif ($operation === 'edit') {
            $changes = $intent['changes'] ?? [];
            Validator::make(['changes' => $changes], ['changes' => ['required', 'array', 'list'], 'changes.*' => ['array:field,mode,value'], 'changes.*.field' => ['required', 'distinct', Rule::in(array_keys($fields))], 'changes.*.mode' => ['required', Rule::in(['unchanged', 'set', 'clear', 'add', 'remove'])]])->validate();
            foreach ($changes as $change) {
                $field = $change['field'];
                $mode = $change['mode'];
                if ($mode === 'unchanged') {
                    continue;
                }
                if ($mode === 'clear') {
                    if (! $fields[$field]['nullable']) {
                        throw ValidationException::withMessages(['changes' => 'This field cannot be cleared.']);
                    }
                    $state[$field] = in_array($fields[$field]['type'], ['tags', 'multiple'], true) ? [] : null;
                } elseif (in_array($mode, ['add', 'remove'], true)) {
                    if ($field !== 'tags' || ! is_array($change['value'] ?? null)) {
                        throw ValidationException::withMessages(['changes' => 'Add/remove applies to tags only.']);
                    }
                    $tags = $state[$field] ?? [];
                    $state[$field] = $mode === 'add' ? array_values(array_unique([...$tags, ...$change['value']])) : array_values(array_diff($tags, $change['value']));
                } else {
                    $state[$field] = $change['value'] ?? null;
                }
            }
        } else {
            throw ValidationException::withMessages(['batch' => 'Choose a supported operation.']);
        }
        $rules = [];
        foreach ($fields as $key => $field) {
            $rules[$key] = $field['rules'];
            if ($field['type'] === 'tags') {
                $state[$key] = array_map(fn ($tag) => is_string($tag) ? trim($tag) : $tag, $state[$key] ?? []);
                $rules[$key.'.*'] = ['required', 'string', 'max:80', 'distinct'];
            }
            if ($field['type'] === 'multiple') {
                $rules[$key.'.*'] = ['required', 'distinct', Rule::in(array_keys($field['options']))];
            }
        }
        if ($record instanceof Option) {
            $rules['code'][] = Rule::unique('options', 'code')->ignore($record->id);
        }
        Validator::make($state, $rules)->validate();

        return $state;
    }

    /** @return list<int> */
    private function ownerIds(Model $record): array
    {
        if ($record instanceof Attribute || $record instanceof Value || $record instanceof Option) {
            return app(CanonicalUsage::class)->configurators($record)->pluck('id')->all();
        }

        return match (true) {
            $record instanceof Configurator => [$record->id],
            $record instanceof ConfiguratorAttribute, $record instanceof ConfiguratorRule => [$record->configurator_id],
            $record instanceof ConfiguratorOption => [$record->configuratorAttribute->configurator_id],
            default => [],
        };
    }

    /** @param array<string, mixed> $intent */
    private function token(User $actor, string $table, Collection $records, array $intent, ?int $ownerId): string
    {
        $ownerDrafts = [];
        $states = $records->map(function (Model $record) use (&$ownerDrafts): array {
            $related = match (true) {
                $record instanceof Group => [$record->filters()->orderBy('id')->get()->toArray(), $record->subGroups()->orderBy('id')->get()->toArray()],
                $record instanceof Attribute, $record instanceof Value, $record instanceof Option => app(CanonicalUsage::class)->counts($record),
                default => [],
            };
            $drafts = [];
            foreach ($this->ownerIds($record) as $id) {
                $drafts[$id] = $ownerDrafts[$id] ??= app(ConfiguratorDefinitionLoader::class)->draft(Configurator::findOrFail($id));
            }

            return [$record->getAttributes(), $related, $drafts];
        })->all();

        return hash_hmac('sha256', json_encode([$actor->id, $table, $ownerId, $records->modelKeys(), $intent, $states], JSON_THROW_ON_ERROR), config('app.key'));
    }

    /** @param array<int, array<string, mixed>> $after @param array<string, mixed> $intent */
    private function saveLocal(User $actor, string $table, Collection $records, array $after, array $intent, ?int $ownerId): void
    {
        $configuratorId = $table === 'configurator-options' ? ConfiguratorAttribute::findOrFail($ownerId)->configurator_id : $ownerId;
        $remove = ($intent['operation'] ?? '') === 'remove';
        app(SaveConfiguratorDefinition::class)->change($actor, Configurator::findOrFail($configuratorId), function (array $draft) use ($table, $records, $after, $remove): array {
            $ids = array_map('strval', $records->modelKeys());
            $transform = function (array $rows) use ($ids, $after, $remove): array {
                return array_values(array_filter(array_map(fn (array $row): ?array => ! in_array((string) $row['id'], $ids, true) ? $row : ($remove ? null : [...$row, ...$after[(int) $row['id']]]), $rows)));
            };
            if ($table === 'configurator-rules') {
                $draft['rules'] = $transform($draft['rules']);
            } elseif ($table === 'configurator-attributes') {
                $draft['attributes'] = $transform($draft['attributes']);
            } else {
                foreach ($draft['attributes'] as &$attribute) {
                    $attribute['options'] = $transform($attribute['options']);
                }
                unset($attribute);
            }

            return $draft;
        });
    }
}
