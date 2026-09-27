<?php

namespace App\Services;

use App\DTO\CatalogSnapshot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogSnapshotMatcher
{
    public function __construct(private CatalogFilterReconciler $reconciler) {}

    /** @param array<string, mixed> $criteria @return list<string> */
    public function ids(CatalogSnapshot $snapshot, array $criteria): array
    {
        $fields = array_column($snapshot->data['fields'], null, 'key');
        $presets = array_column($snapshot->data['presets'], null, 'id');
        Validator::make(['criteria' => $criteria], [
            'criteria' => ['required', 'array:version,filters,subGroupId,precedence'],
            'criteria.version' => ['required', Rule::in([1])],
            'criteria.filters' => ['present', 'array', 'max:'.count($fields)],
            'criteria.filters.*' => ['string'],
            'criteria.subGroupId' => ['present', 'nullable', 'string', 'regex:/^[1-9][0-9]{0,19}$/'],
            'criteria.precedence' => ['present', 'array', 'list', 'max:'.(count($fields) + 1)],
            'criteria.precedence.*' => ['required', 'string', 'max:150', 'distinct'],
        ])->validate();
        $constraints = [];
        foreach ($criteria['filters'] as $key => $value) {
            $field = $fields[$key] ?? null;
            $option = $field ? array_search($value, array_column($field['options'], 'value'), true) : false;
            if ($option === false) {
                throw ValidationException::withMessages(['criteria.filters' => 'A filter choice is not valid for this Group.']);
            }
            $constraints['filter:'.$key] = ['column' => $field['column'], 'codes' => [$field['options'][$option]['code']]];
        }
        $preset = $criteria['subGroupId'] === null ? null : ($presets[$criteria['subGroupId']] ?? null);
        if ($criteria['subGroupId'] !== null && $preset === null) {
            throw ValidationException::withMessages(['criteria.subGroupId' => 'This preset does not belong to this Group.']);
        }
        if ($preset !== null) {
            $constraints['subgroup:'.$preset['id']] = ['column' => $preset['column'], 'codes' => $preset['codes']];
        }
        $precedence = $criteria['precedence'];
        if (count($precedence) !== count($constraints) || array_diff($precedence, array_keys($constraints)) !== []) {
            throw ValidationException::withMessages(['criteria.precedence' => 'The selection order must name every choice exactly once.']);
        }
        if ($preset !== null && count($preset['values']) === 1) {
            unset($constraints['filter:'.$preset['key']]);
            $precedence = array_values(array_diff($precedence, ['filter:'.$preset['key']]));
        }
        $rows = $snapshot->data['rows'];
        $replayed = $this->reconciler->reconcile($constraints, $precedence, function (array $accepted) use ($rows): bool {
            foreach ($rows as $row) {
                if ($this->matches($row, $accepted)) {
                    return true;
                }
            }

            return false;
        });
        $accepted = array_intersect_key($constraints, array_flip($replayed['kept']));

        return array_values(array_map(fn ($row) => $row[0], array_filter($rows, fn ($row) => $this->matches($row, $accepted))));
    }

    /** @param list<string|int> $row @param array<string, array{column: int, codes: list<int>}> $constraints */
    private function matches(array $row, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if (! in_array($row[$constraint['column'] + 1], $constraint['codes'], true)) {
                return false;
            }
        }

        return true;
    }
}
