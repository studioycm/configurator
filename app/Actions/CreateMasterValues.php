<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateMasterValues
{
    public function __construct(private SaveCanonicalDefinition $saveCanonicalDefinition) {}

    /**
     * @param  list<array{ref: string, label?: string|null, semantic_id?: string|null, tags?: list<string>, description?: string|null}>  $rows
     * @param  list<string>|null  $referencedRefs
     * @return array<string, Value>
     */
    public function handle(User $actor, array $rows, ?array $referencedRefs = null): array
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        Validator::make(['master_values' => $rows, 'referenced_refs' => $referencedRefs], [
            'master_values' => ['present', 'array', 'list', 'max:500'],
            'master_values.*' => ['required', 'array:ref,label,semantic_id,tags,description'],
            'master_values.*.ref' => ['required', 'string', 'distinct:strict', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! str_starts_with($value, 'new:') || ! Str::isUuid(substr($value, 4))) {
                    $fail(__('catalog.creation.invalid_ref'));
                }
            }],
            'master_values.*.label' => ['sometimes', 'nullable', 'string'],
            'master_values.*.semantic_id' => ['sometimes', 'nullable', 'string'],
            'master_values.*.description' => ['sometimes', 'nullable', 'string'],
            'master_values.*.tags' => ['sometimes', 'array', 'list'],
            'master_values.*.tags.*' => ['string'],
            'referenced_refs' => ['nullable', 'array', 'list', 'max:500'],
            'referenced_refs.*' => ['required', 'string', 'distinct:strict'],
        ])->validate();
        $byRef = array_column($rows, null, 'ref');
        foreach ($referencedRefs ?? [] as $ref) {
            if (! isset($byRef[$ref]) || ! self::isPopulated($byRef[$ref])) {
                throw ValidationException::withMessages(['master_values' => __('catalog.creation.missing_ref')]);
            }
        }
        if ($referencedRefs === null && ! array_any($rows, self::isPopulated(...))) {
            throw ValidationException::withMessages(['master_values' => __('catalog.creation.required')]);
        }

        return DB::transaction(function () use ($actor, $rows, $referencedRefs): array {
            $created = [];
            foreach ($rows as $index => $row) {
                if ($referencedRefs !== null ? ! in_array($row['ref'], $referencedRefs, true) : ! self::isPopulated($row)) {
                    continue;
                }
                $data = [
                    'label' => $row['label'] ?? null,
                    'semantic_id' => $row['semantic_id'] ?? null,
                    'tags' => is_array($row['tags'] ?? []) ? array_map(fn (mixed $tag): mixed => is_string($tag) ? trim($tag) : $tag, $row['tags'] ?? []) : $row['tags'],
                    'description' => $row['description'] ?? null,
                ];
                try {
                    $created[$row['ref']] = $this->saveCanonicalDefinition->handle($actor, new Value, $data);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $field = preg_replace('/^definition\.?/', '', $field);
                        $errors['master_values.'.$index.($field === '' ? '' : '.'.$field)] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }
            }

            return $created;
        });
    }

    /** @param array<string, mixed> $row */
    public static function isPopulated(array $row): bool
    {
        foreach (['label', 'semantic_id', 'description', 'tags'] as $key) {
            $values = is_array($row[$key] ?? null) ? $row[$key] : [$row[$key] ?? null];
            foreach ($values as $value) {
                if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                    return true;
                }
            }
        }

        return false;
    }
}
