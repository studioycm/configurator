<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportLegacyCanonicalLibrary
{
    public function __construct(private SaveCanonicalDefinition $saveDefinition, private SaveCanonicalOption $saveOption) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, string $path, bool $apply = false, ?string $expectedHash = null, bool $holdDuplicateCodes = false): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['source' => 'The legacy library file is not readable.']);
        }
        $contents = file_get_contents($path);
        $hash = hash('sha256', $contents);
        if ($apply && ($expectedHash === null || ! hash_equals($hash, $expectedHash))) {
            throw ValidationException::withMessages(['source' => 'Apply requires the SHA-256 from the reviewed dry-run.']);
        }
        $source = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $data = Validator::make(['source' => $source], [
            'source' => ['required', 'array:format,attributes,options'],
            'source.format' => ['required', Rule::in(['legacy-canonical-library-v1'])],
            'source.attributes' => ['required', 'array', 'list', 'max:1000'],
            'source.attributes.*' => ['required', 'array:id,key,label'],
            'source.attributes.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'source.attributes.*.key' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'source.attributes.*.label' => ['required', 'string', 'max:255'],
            'source.options' => ['present', 'array', 'list', 'max:10000'],
            'source.options.*' => ['required', 'array:id,attribute_id,label,code'],
            'source.options.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'source.options.*.attribute_id' => ['required', 'integer', Rule::in(array_column(is_array($source['attributes'] ?? null) ? $source['attributes'] : [], 'id'))],
            'source.options.*.label' => ['required', 'string', 'max:255'],
            'source.options.*.code' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{2}\z/D'],
        ])->validate()['source'];
        $duplicates = collect($data['options'])->groupBy(fn (array $row): string => 'code:'.$row['code'])
            ->filter(fn ($rows): bool => $rows->count() > 1)->flatten(1)->values();
        if ($duplicates->isNotEmpty() && ! $holdDuplicateCodes) {
            throw ValidationException::withMessages(['codes' => 'Duplicate legacy codes require review: '.$duplicates->pluck('code')->uniqueStrict()->implode(', ').'.']);
        }
        $pendingIds = $duplicates->pluck('id')->all();

        return DB::transaction(function () use ($actor, $data, $hash, $apply, $duplicates, $pendingIds): array {
            $totals = ['attributes_created' => 0, 'attributes_existing' => 0, 'options_created' => 0, 'options_existing' => 0, 'values_created' => 0, 'options_pending' => count($pendingIds)];
            $attributes = [];
            $options = [];
            foreach ($data['attributes'] as $row) {
                $attribute = Attribute::where('key', $row['key'])->when($apply, fn ($query) => $query->lockForUpdate())->first();
                if ($attribute !== null && ($attribute->key !== $row['key'] || $attribute->label !== $row['label'])) {
                    throw ValidationException::withMessages(['attributes' => 'Existing Attribute '.$row['key'].' differs from the legacy definition; review it before importing.']);
                }
                $attributes[$row['id']] = $attribute;
                $totals[$attribute === null ? 'attributes_created' : 'attributes_existing']++;
            }
            foreach ($data['options'] as $row) {
                if (in_array($row['id'], $pendingIds)) {
                    continue;
                }
                $option = Option::with('value')->where('code', $row['code'])->when($apply, fn ($query) => $query->lockForUpdate())->first();
                if ($option !== null && ($option->attribute_id !== $attributes[$row['attribute_id']]?->id || $option->value->label !== $row['label'])) {
                    throw ValidationException::withMessages(['options' => 'Existing code '.$row['code'].' belongs to a different Attribute or Value; nothing was imported.']);
                }
                $options[$row['id']] = $option;
                $totals[$option === null ? 'options_created' : 'options_existing']++;
                if ($option === null) {
                    $totals['values_created']++;
                }
            }
            if ($apply) {
                foreach ($data['attributes'] as $row) {
                    if ($attributes[$row['id']] === null) {
                        $attributes[$row['id']] = $this->saveDefinition->handle($actor, new Attribute, ['key' => $row['key'], 'label' => $row['label']]);
                    }
                }
                foreach ($data['options'] as $row) {
                    if (in_array($row['id'], $pendingIds) || $options[$row['id']] !== null) {
                        continue;
                    }
                    $value = $this->saveDefinition->handle($actor, new Value, ['label' => $row['label']]);
                    $options[$row['id']] = $this->saveOption->handle($actor, null, $attributes[$row['attribute_id']]->id, $value->id, $row['code']);
                }
            }

            return [
                'mode' => $apply ? 'applied' : 'dry-run',
                'source_hash' => $hash,
                'totals' => $totals,
                'pending_options' => $duplicates->all(),
                'attribute_ids' => array_map(fn (?Attribute $attribute): ?int => $attribute?->id, $attributes),
                'option_ids' => array_map(fn (?Option $option): ?int => $option?->id, $options),
            ];
        });
    }
}
