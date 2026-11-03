<?php

namespace App\Services;

use InvalidArgumentException;
use stdClass;

class ConfiguratorRuleWritingInspectionPresenter
{
    /** @param array<string,mixed> $envelope @return array<string,mixed> */
    public function present(array $envelope): array
    {
        $yaml = $this->yaml($envelope)."\n";
        $groups = [];
        foreach ($envelope['current']['records'] ?? [] as $key => $records) {
            $rows = $key === 'rule' ? [$records] : $records;
            $groups[] = ['key' => $key, 'label' => str_replace('_', ' ', ucfirst($key)), 'rows' => array_map(fn (array $row): array => ['cells' => array_map(fn (string|int $name, mixed $value): array => ['name' => (string) $name, 'type' => get_debug_type($value), 'value' => is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)], array_keys($row), array_values($row))], $rows)];
        }

        return ['json' => json_encode($envelope, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), 'yaml' => $yaml, 'records' => $groups,
            'draft_json' => json_encode($envelope['draft'] ?? null, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'capture_json' => json_encode($envelope['last_writing_capture'] ?? null, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'debug' => ['status' => $envelope['status'] ?? [], 'inspected_at' => $envelope['inspected_at'] ?? null, 'current_fingerprint' => $envelope['current']['fingerprint'] ?? null, 'capture_fingerprint' => $envelope['last_writing_capture']['saved_fingerprint'] ?? null]];
    }

    private function yaml(mixed $value, int $depth = 0): string
    {
        if ($depth > 128 || (! is_array($value) && ! $value instanceof stdClass && ! is_scalar($value) && $value !== null) || (is_float($value) && ! is_finite($value))) {
            throw new InvalidArgumentException('Inspection accepts finite JSON data only.');
        }
        if (! is_array($value) && ! $value instanceof stdClass) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        $list = is_array($value) && array_is_list($value);
        $items = (array) $value;
        if ($items === []) {
            return $list ? '[]' : '{}';
        }
        $lines = [];
        foreach ($items as $key => $item) {
            $prefix = $list ? '-' : json_encode((string) $key, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).':';
            $child = $this->yaml($item, $depth + 1);
            if ((is_array($item) || $item instanceof stdClass) && (array) $item !== []) {
                $lines[] = $prefix."\n".preg_replace('/^/m', '  ', $child);
            } else {
                $lines[] = $prefix.' '.$child;
            }
        }

        return implode("\n", $lines);
    }
}
