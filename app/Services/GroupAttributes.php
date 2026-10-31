<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

final class GroupAttributes
{
    /** @return array<string, array{label: string}> */
    public function definitions(): array
    {
        return array_map(fn (array $definition): array => ['label' => __($definition['label'])], config('group_attributes.definitions'));
    }

    /** @return array<string, list<string>> */
    public function document(Group $group): array
    {
        $raw = $group->getRawOriginal('catalog_attributes');
        if ($raw === null) {
            return [];
        }
        try {
            $decoded = json_decode($raw, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject();
        }
        if (! $decoded instanceof stdClass) {
            $this->reject();
        }
        $values = (array) $decoded;
        $this->validateDocument($values);

        return $values;
    }

    /** @return array{values: array<string, list<string>>, editable: bool, message: ?string} */
    public function formState(Group $group): array
    {
        try {
            $values = $this->document($group);
        } catch (ValidationException) {
            return ['values' => [], 'editable' => false, 'message' => __('catalog.group_attributes.invalid_stored')];
        }
        $known = array_fill_keys(array_keys($this->definitions()), []);

        return ['values' => array_replace($known, array_intersect_key($values, $known)), 'editable' => true,
            'message' => array_diff_key($values, $known) === [] ? null : __('catalog.group_attributes.future_kept')];
    }

    public function fingerprint(Group $group): string
    {
        try {
            $values = $this->document($group);
            ksort($values, SORT_STRING);
            $document = ['values' => (object) $values];
        } catch (ValidationException) {
            $document = ['invalid' => hash('sha256', (string) $group->getRawOriginal('catalog_attributes'))];
        }

        return hash('sha256', json_encode([(string) $group->getKey(), $document], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, list<string>>  $changes
     * @return array{values: array<string, list<string>>, changed: bool}
     */
    public function prepare(Group $group, array $changes): array
    {
        $this->validateBytes($changes);
        $current = $this->document($group);
        $merged = $current;
        $definitions = $this->definitions();
        foreach ($changes as $key => $values) {
            if (! array_key_exists($key, $definitions)) {
                $this->reject();
            }
            $this->validateValues($values, (string) $key);
            $normalized = [];
            foreach ($values as $value) {
                $normalized[] = in_array($value, $current[$key] ?? [], true) ? $value : $this->trimNewEntry($value);
            }
            $this->validateValues($normalized, (string) $key);
            if ($normalized === []) {
                unset($merged[$key]);
            } else {
                $merged[$key] = $normalized;
            }
        }
        $this->validateDocument($merged);
        $before = $current;
        $after = $merged;
        ksort($before, SORT_STRING);
        ksort($after, SORT_STRING);

        return ['values' => $merged, 'changed' => $before !== $after];
    }

    /** @param array<string, list<string>> $values */
    private function validateDocument(array $values): void
    {
        $this->validateBytes($values);
        foreach ($values as $key => $entries) {
            if ((string) $key === '' || ! mb_check_encoding((string) $key, 'UTF-8') || mb_strlen((string) $key) > 100 || str_contains((string) $key, "\0")) {
                $this->reject();
            }
            $this->validateValues($entries, (string) $key);
        }
    }

    private function validateValues(mixed $values, string $key): void
    {
        if (! is_array($values) || ! array_is_list($values) || count($values) > config('group_attributes.limits.values_per_attribute')) {
            $this->reject($key);
        }
        foreach ($values as $value) {
            if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > config('group_attributes.limits.value_characters')
                || str_contains($value, "\0") || $this->trimNewEntry($value) === '') {
                $this->reject($key);
            }
        }
        if (count(array_unique($values, SORT_STRING)) !== count($values)) {
            $this->reject($key);
        }
    }

    /** @param array<string, mixed> $values */
    private function validateBytes(array $values): void
    {
        try {
            $encoded = json_encode((object) $values, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject();
        }
        if (strlen($encoded) > config('group_attributes.limits.document_bytes')) {
            $this->reject();
        }
    }

    private function trimNewEntry(string $value): string
    {
        $characters = '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

        return preg_replace('/\A['.$characters.']+|['.$characters.']+\z/u', '', $value);
    }

    private function reject(?string $key = null): never
    {
        throw ValidationException::withMessages([$key !== null && array_key_exists($key, $this->definitions()) ? 'catalog_attributes.'.$key : 'catalog_attributes' => __('catalog.group_attributes.invalid_values')]);
    }
}
