<?php

namespace App\Filament\Resources\Configurators\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Arrayable;

class RuleValidationSummary
{
    /** @param array<string, list<string>> $errors
     * @return list<array{path: string, label: string, target: ?string, messages: list<string>}>
     */
    public static function entries(Schema $schema, array $errors): array
    {
        $prefix = $schema->getStatePath().'.';
        $state = $schema->getRawState();
        $state = $state instanceof Arrayable ? $state->toArray() : $state;
        $fields = array_values(array_filter($schema->getFlatFields(), fn (Field $field): bool => ! $field instanceof Hidden && ! $field->isDisabled()));
        $entries = [];
        foreach ($errors as $path => $messages) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }
            $relative = substr($path, strlen($prefix));
            $field = collect($fields)->first(fn (Field $field): bool => $path === $field->getStatePath());
            $field ??= collect($fields)->filter(fn (Field $field): bool => ! $field instanceof Builder && ! $field instanceof Repeater && str_starts_with($path, $field->getStatePath().'.'))
                ->sortByDesc(fn (Field $field): int => strlen($field->getStatePath()))->first();
            if ($field === null && preg_match('/^condition_blocks\.[^.]+\.data(?:\.conditions\.[^.]+)?$/', $relative)) {
                $row = data_get($state, $relative, []);
                $staleOperand = in_array($row['operator'] ?? null, ['In', 'NotIn'], true) ? 'operand_text' : 'operand_list';
                $candidates = collect($fields)->filter(fn (Field $field): bool => ! $field instanceof Builder && ! $field instanceof Repeater && str_starts_with($field->getStatePath(), $path.'.'));
                $field = $candidates->first(fn (Field $field): bool => $field->getStatePath() === $path.'.'.$staleOperand && filled($field->getState())) ?? $candidates->first();
            }
            $parts = self::location($relative, $state);
            if ($field !== null && filled($field->getLabel())) {
                $parts[] = (string) $field->getLabel();
            }
            $entries[] = ['path' => $path, 'label' => implode(' → ', $parts), 'target' => $field?->getId(), 'messages' => $messages];
        }

        return $entries;
    }

    /** @param array<string, mixed> $state @return list<string> */
    private static function location(string $path, array $state): array
    {
        $segments = explode('.', $path);
        $bucket = $segments[0];
        if (! in_array($bucket, ['condition_blocks', 'effects', 'sets'], true) || ! isset($segments[1])) {
            return [];
        }
        $position = array_search($segments[1], array_map('strval', array_keys($state[$bucket] ?? [])), true);
        if ($position === false) {
            return [];
        }
        $parts = [__('admin-ux.rule_error_'.$bucket, ['number' => $position + 1])];
        if ($bucket === 'condition_blocks' && ($segments[3] ?? null) === 'conditions' && isset($segments[4])) {
            $children = $state['condition_blocks'][$segments[1]]['data']['conditions'] ?? [];
            $childPosition = array_search($segments[4], array_map('strval', array_keys($children)), true);
            if ($childPosition !== false) {
                $parts[] = __('admin-ux.rule_error_nested_condition', ['number' => $childPosition + 1]);
            }
        }

        return $parts;
    }
}
