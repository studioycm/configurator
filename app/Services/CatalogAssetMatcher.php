<?php

namespace App\Services;

class CatalogAssetMatcher
{
    public function __construct(private CatalogAssetRecipe $recipes) {}

    public function index(array $manifest, array $recipe): array
    {
        $index = ['entries' => [], 'groups' => []];
        foreach ($manifest['entries'] as $entry) {
            $relative = str_replace('\\', '/', $entry['relative_path'] ?? $entry['name']);
            if ($entry['status'] !== 'valid' || (! ($recipe['include_subfolders'] ?? true) && str_contains($relative, '/'))) {
                continue;
            }
            if (! $this->patterns($recipe['include_patterns'] ?? '', $entry['name'], $relative, true) || $this->patterns($recipe['exclude_patterns'] ?? '', $entry['name'], $relative, false)) {
                continue;
            }
            $key = $this->recipes->normalize($this->recipes->stem($entry['name']), $recipe);
            $index['entries'][$entry['entry_id']] = ['entry_id' => $entry['entry_id'], 'name' => $entry['name'], 'relative_path' => $relative, 'key' => $key];
            $index['groups'][$key][] = $entry['entry_id'];
        }

        return $index;
    }

    public function match(array $sourceIndex, string $identifier, array $recipe): array
    {
        $key = $this->recipes->normalize($identifier, $recipe);
        if ($key === '') {
            return ['match_status' => 'invalid', 'candidate_set_ref' => null, 'candidate_count' => 0, 'chosen_entry_id' => null, 'reason' => 'identifier_empty'];
        }
        $contains = $recipe['comparison'] === 'contains';
        $count = 0;
        $only = null;
        if (! $contains) {
            $ids = $sourceIndex['groups'][$key] ?? [];
            $count = count($ids);
            $only = $count === 1 ? $ids[0] : null;
        } else {
            foreach ($this->candidateIds($sourceIndex, $identifier, $recipe) as $id) {
                $count++;
                $only = $id;
            }
        }

        return ['match_status' => $count === 0 ? 'missing' : ($contains ? 'suggestion' : ($count === 1 ? 'ready' : 'ambiguous')),
            'candidate_set_ref' => hash('sha256', ($contains ? 'contains:' : 'exact:').$key), 'candidate_count' => $count, 'chosen_entry_id' => ! $contains && $count === 1 ? $only : null,
            'reason' => $count === 0 ? 'no_match' : ($contains ? 'confirm_suggestion' : ($count === 1 ? 'exact_match' : 'choose_match'))];
    }

    /** @return iterable<string> */
    public function candidateIds(array $index, string $identifier, array $recipe): iterable
    {
        $key = $this->recipes->normalize($identifier, $recipe);
        if ($key === '') {
            return;
        }
        if ($recipe['comparison'] === 'exact') {
            yield from $index['groups'][$key] ?? [];

            return;
        }
        foreach ($index['entries'] as $id => $entry) {
            if (str_contains($entry['key'], $key)) {
                yield $id;
            }
        }
    }

    public function reviewChunk(array $records, array $index, array $recipe, array $overrides): array
    {
        $rows = [];
        foreach ($records as $record) {
            $snapshot = ['entity' => $record['entity'] ?? 'product', 'title' => $record['title'] ?? 'Unavailable record', 'code' => $record['product_code'] ?? null, 'raw_value' => null, 'raw_display' => null, 'raw_truncated' => false, 'raw_sha256' => null, 'identifier' => null, 'candidate_set_ref' => null, 'candidate_count' => 0, 'chosen_entry' => null, 'filename' => null, 'reason' => 'record_unavailable', 'manual' => false];
            $status = 'invalid';
            if (($record['fingerprint'] ?? null) !== null && isset($recipe['source_field'])) {
                $snapshot = [...$snapshot, ...$this->recipes->identifier($record, $recipe)];
            }
            if (($record['fingerprint'] ?? null) !== null) {
                if (($recipe['mode'] ?? 'fill_missing') === 'fill_missing' && $record['main_image_id'] !== null) {
                    $status = 'existing';
                    $snapshot['reason'] = 'existing_kept';
                } elseif (isset($overrides[$record['id']])) {
                    $status = 'ready';
                    $choice = $overrides[$record['id']];
                    $snapshot = [...$snapshot, 'chosen_entry' => $choice['choice'], 'filename' => $choice['filename'], 'reason' => 'manually_chosen', 'manual' => true, 'candidate_count' => 1];
                } else {
                    $derived = $this->recipes->identifier($record, $recipe);
                    $snapshot = [...$snapshot, ...$derived];
                    if ($derived['reason'] === null) {
                        $matched = $this->match($index, $derived['identifier'], $recipe);
                        $status = $matched['match_status'];
                        $snapshot = [...$snapshot, ...array_intersect_key($matched, array_flip(['candidate_set_ref', 'candidate_count', 'reason']))];
                        if ($matched['chosen_entry_id'] !== null) {
                            $entry = $index['entries'][$matched['chosen_entry_id']];
                            $snapshot['chosen_entry'] = $entry['entry_id'];
                            $snapshot['filename'] = $entry['name'];
                        }
                    }
                }
            }
            $rows[] = ['record_id' => $record['id'], 'position' => $record['position'], 'record_fingerprint' => $record['fingerprint'] ?? null, 'before_asset_id' => $record['main_image_id'] ?? null, 'snapshot' => $snapshot, 'match_status' => $status];
        }

        return $rows;
    }

    private function patterns(string $patterns, string $name, string $relative, bool $empty): bool
    {
        $parts = array_filter(array_map('trim', preg_split('/[,;]/u', $patterns)));
        foreach ($parts as $pattern) {
            if ($this->glob($pattern, str_contains($pattern, '/') ? $relative : $name)) {
                return true;
            }
        }

        return $parts === [] && $empty;
    }

    /** Literal glob with no regular-expression evaluation or recursive backtracking. */
    private function glob(string $pattern, string $value): bool
    {
        $p = mb_str_split($pattern);
        $v = mb_str_split($value);
        $i = 0;
        $j = 0;
        $star = -1;
        $retry = 0;
        while ($j < count($v)) {
            if (isset($p[$i]) && ($p[$i] === '?' || $p[$i] === $v[$j])) {
                $i++;
                $j++;
            } elseif (($p[$i] ?? null) === '*') {
                $star = $i++;
                $retry = $j;
            } elseif ($star >= 0) {
                $i = $star + 1;
                $j = ++$retry;
            } else {
                return false;
            }
        }
        while (($p[$i] ?? null) === '*') {
            $i++;
        }

        return $i === count($p);
    }
}
