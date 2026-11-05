<?php

namespace App\Filament\Resources;

use Illuminate\Support\Str;

class BatchPreviewPresentation
{
    /**
     * @param  array{before: array<string, mixed>, after: array<string, mixed>}  $row
     * @param  array<string, mixed>  $intent
     * @return list<array{field: string, label: string, mode: ?string, before: mixed, after: mixed, before_present: bool, after_present: bool}>
     */
    public static function differences(array $row, array $intent): array
    {
        $modes = [];
        foreach ($intent['changes'] ?? [] as $change) {
            $modes[$change['field']] = match ($change['mode']) {
                'clear' => __('admin.batch.choices.clear'),
                'add' => __('admin.batch.choices.add_tags'),
                'remove' => __('admin.batch.choices.remove_tags'),
                default => null,
            };
        }
        $result = [];
        foreach (array_unique([...array_keys($row['before']), ...array_keys($row['after'])]) as $field) {
            $beforePresent = array_key_exists($field, $row['before']);
            $afterPresent = array_key_exists($field, $row['after']);
            $before = $row['before'][$field] ?? null;
            $after = $row['after'][$field] ?? null;
            if ($beforePresent === $afterPresent && $before === $after) {
                continue;
            }
            $label = __('admin.batch.fields.'.$field);
            $result[] = ['field' => $field, 'label' => $label === 'admin.batch.fields.'.$field ? Str::headline($field) : $label,
                'mode' => $modes[$field] ?? null, 'before' => $before, 'after' => $after,
                'before_present' => $beforePresent, 'after_present' => $afterPresent];
        }

        return $result;
    }

    public static function value(mixed $value, bool $present = true): string
    {
        if (! $present) {
            return __('admin.batch.preview.not_present');
        }

        return match (true) {
            $value === null => __('admin.batch.preview.null_value'),
            $value === '' => __('admin.batch.preview.empty_text'),
            $value === [] => __('admin.batch.preview.empty_list'),
            $value === true => __('admin.batch.preview.true_value'),
            $value === false => __('admin.batch.preview.false_value'),
            default => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        };
    }
}
