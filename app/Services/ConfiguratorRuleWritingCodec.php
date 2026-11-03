<?php

namespace App\Services;

use InvalidArgumentException;

class ConfiguratorRuleWritingCodec
{
    public function __construct(private ConfiguratorRuleWritingParser $parser) {}

    /** @param array $graph @param array $attributes @param array $options @return array */
    public function dictionary(array $graph, array $attributes, array $options): array
    {
        $dictionary = [];
        foreach ($graph['attributes'] as $attribute) {
            $key = $attributes[$attribute['attribute_id']]['key'];
            $values = [];
            foreach ($attribute['options'] as $option) {
                $semantic = $options[$option['option_id']]['semantic_id'] ?? null;
                if (is_string($semantic) && $semantic !== '') {
                    $values[$semantic][] = (string) $option['id'];
                }
            }
            $dictionary[$key][] = ['id' => (string) $attribute['id'], 'values' => $values];
        }

        return $dictionary;
    }

    /** @param array $rule @param array $dictionary @return array */
    public function open(array $rule, array $dictionary): array
    {
        $opened = ['source' => '', 'generated' => true, 'rule' => $rule, 'bindings' => ['dictionary' => $dictionary], 'unsupported' => []];
        try {
            if (! in_array($rule['kind'], ['Mapping', 'Advanced'], true)) {
                throw new InvalidArgumentException('Unsupported Rule kind.');
            }
            $lines = [];
            if ($rule['conditions'] !== []) {
                $lines[] = '/when all {';
                foreach ($rule['conditions'] as $condition) {
                    if (isset($condition['conditions'])) {
                        if (! in_array($condition['operator'], ['All', 'Any'], true)) {
                            throw new InvalidArgumentException('Unsupported group.');
                        }
                        $lines[] = '  '.strtolower($condition['operator']).' {';
                        foreach ($condition['conditions'] as $child) {
                            $lines[] = '    '.$this->conditionText($child, $dictionary);
                        }
                        $lines[] = '  }';
                    } else {
                        $lines[] = '  '.$this->conditionText($condition, $dictionary);
                    }
                }
                $lines[] = '}';
            }
            if ($rule['kind'] === 'Mapping') {
                if ($rule['effects'] !== []) {
                    throw new InvalidArgumentException('Mapping effects cannot be discarded.');
                }
                $driver = $this->attributeKey($rule['driver_configurator_attribute_id'], $dictionary);
                $target = $this->attributeKey($rule['target_configurator_attribute_id'], $dictionary);
                $lines[] = '/pair @'.$driver.' -> @'.$target;
                foreach ($rule['sets'] as $set) {
                    if ($set['label'] !== null) {
                        $lines[] = '// Retained set label '.$this->quote($set['label']).'; order '.$set['sort_order'];
                    }
                    $lines[] = '/case '.$this->valueText($set['source_option_ids'], $driver, $dictionary).' -> '.$this->valueText($set['target_option_ids'], $target, $dictionary);
                    $lines[] = '/other '.strtolower($set['disallowed_target_behavior']);
                }
            } else {
                if ($rule['sets'] !== [] || $rule['driver_configurator_attribute_id'] !== null || $rule['target_configurator_attribute_id'] !== null) {
                    throw new InvalidArgumentException('Unsupported Advanced mapping metadata.');
                }
                $commands = ['AllowOptions' => 'allow', 'ExcludeOptions' => 'exclude', 'HideOptions' => 'hide', 'DisableOptions' => 'disable', 'HideAttribute' => 'hide', 'SetLabel' => 'set-label', 'SetDisplayValue' => 'set-display-value', 'SetHint' => 'set-hint'];
                foreach ($rule['effects'] as $effect) {
                    if (! isset($commands[$effect['kind']])) {
                        throw new InvalidArgumentException('Unsupported effect.');
                    }
                    $key = $this->attributeKey($effect['target_configurator_attribute_id'], $dictionary);
                    $textEffect = str_starts_with($commands[$effect['kind']], 'set-');
                    if (! in_array($effect['target_scope'], ['Attribute', 'Options'], true)
                        || ($effect['target_scope'] === 'Attribute' && $effect['option_ids'] !== [])
                        || (! $textEffect && $effect['display_value'] !== null)
                        || ($effect['kind'] === 'HideAttribute' && $effect['target_scope'] !== 'Attribute')
                        || (! $textEffect && $effect['kind'] !== 'HideAttribute' && $effect['target_scope'] !== 'Options')) {
                        throw new InvalidArgumentException('Unsupported effect data.');
                    }
                    $line = '/'.$commands[$effect['kind']].' @'.$key;
                    if ($effect['target_scope'] === 'Options') {
                        $line .= ' '.$this->valueText($effect['option_ids'], $key, $dictionary);
                    }
                    if ($textEffect) {
                        $line .= ' '.$this->quote($effect['display_value']);
                    }
                    $lines[] = $line;
                }
            }
            $source = implode("\n", $lines);
            $parsed = $this->parser->parse($source);
            if ($parsed['diagnostics'] !== []) {
                throw new InvalidArgumentException('The saved Rule cannot be represented by this grammar.');
            }
            $opened['source'] = $source;
            $opened['bindings']['statements'] = $parsed['statements'];
        } catch (InvalidArgumentException $e) {
            $opened['unsupported'][] = $e->getMessage();
        }

        return $opened;
    }

    /** @param array $opening Trusted server opening, never an arbitrary browser payload. @param array $dictionary @return array */
    public function edit(string $source, array $opening, array $dictionary, bool $reconstruct = false): array
    {
        $parsed = $this->parser->parse($source);
        $result = ['source' => $source, 'proposed_rule' => null, 'bindings' => $opening['bindings'], 'reference_bindings' => null, 'diagnostics' => $parsed['diagnostics']];
        if ($result['diagnostics'] !== []) {
            return $result;
        }
        try {
            if ($opening['unsupported'] !== []) {
                throw new InvalidArgumentException('The Rule is unsupported. Reopen explicitly.');
            }
            $this->assertReferences($opening['bindings']['statements'], $opening['bindings']['dictionary'], $dictionary);
            if (! $reconstruct && ($source === $opening['source'] || $this->statementContent($parsed['statements']) === $this->statementContent($opening['bindings']['statements']))) {
                $result['reference_bindings'] = ['statements' => $parsed['statements'], 'dictionary' => $dictionary];
                $result['proposed_rule'] = $opening['rule'];

                return $result;
            }
            $rule = $opening['rule'];
            $conditions = [];
            $sets = [];
            $effects = [];
            $pair = null;
            $counter = 0;
            foreach ($parsed['statements'] as $node) {
                if ($node['kind'] === 'condition') {
                    $conditions[] = $this->conditionRow($node, $dictionary, $counter);
                } elseif ($node['kind'] === 'group') {
                    $children = [];
                    foreach ($node['children'] as $child) {
                        $children[] = $this->conditionRow($child, $dictionary, $counter);
                    }
                    $conditions[] = ['id' => 'new:w_group_'.++$counter, 'operator' => $node['operator'], 'conditions' => $children];
                } elseif ($node['kind'] === 'pair') {
                    if ($rule['kind'] !== 'Mapping' || $pair !== null) {
                        throw new InvalidArgumentException('One Mapping pair is supported.');
                    }
                    $pair = $node;
                    $rule['driver_configurator_attribute_id'] = $this->attribute($node['source'], $dictionary)['id'];
                    $rule['target_configurator_attribute_id'] = $this->attribute($node['target'], $dictionary)['id'];
                } elseif ($node['kind'] === 'case') {
                    if ($pair === null) {
                        throw new InvalidArgumentException('Choose a Mapping pair before its cases.');
                    }
                    $sets[] = ['id' => 'new:w_set_'.++$counter, 'label' => null, 'sort_order' => count($sets), 'source_option_ids' => $this->values($node['source_values'], $pair['source'], $dictionary), 'target_option_ids' => $this->values($node['target_values'], $pair['target'], $dictionary), 'disallowed_target_behavior' => 'Disable'];
                } elseif ($node['kind'] === 'other') {
                    if ($sets === []) {
                        throw new InvalidArgumentException('Other-target behavior follows a Mapping case.');
                    }
                    $sets[array_key_last($sets)]['disallowed_target_behavior'] = $node['behavior'];
                } else {
                    if ($rule['kind'] !== 'Advanced') {
                        throw new InvalidArgumentException('Advanced effects cannot be added to Mapping.');
                    }
                    $kinds = ['allow' => 'AllowOptions', 'exclude' => 'ExcludeOptions', 'disable' => 'DisableOptions', 'hide' => $node['values'] === [] ? 'HideAttribute' : 'HideOptions', 'set-label' => 'SetLabel', 'set-display-value' => 'SetDisplayValue', 'set-hint' => 'SetHint'];
                    $effects[] = ['id' => 'new:w_effect_'.++$counter, 'kind' => $kinds[$node['kind']], 'target_scope' => $node['values'] === [] ? 'Attribute' : 'Options', 'display_value' => $node['text'], 'target_configurator_attribute_id' => $this->attribute($node['attribute'], $dictionary)['id'], 'option_ids' => $this->values($node['values'], $node['attribute'], $dictionary)];
                }
            }
            if (($rule['kind'] === 'Mapping' && ($pair === null || $sets === [])) || ($rule['kind'] === 'Advanced' && $effects === [])) {
                throw new InvalidArgumentException('Complete the pair/cases or Advanced effects.');
            }
            $rule['conditions'] = $this->reconcile($conditions, $rule['conditions'], 'conditions');
            $rule['effects'] = $this->reconcile($effects, $rule['effects'], 'effects');
            $rule['sets'] = $this->reconcile($sets, $rule['sets'], 'sets');
            $result['reference_bindings'] = ['statements' => $parsed['statements'], 'dictionary' => $dictionary];
            $result['proposed_rule'] = $rule;
        } catch (InvalidArgumentException $e) {
            $result['diagnostics'][] = ['code' => 'reference_or_identity', 'severity' => 'error', 'start' => 0, 'end' => strlen($source), 'message' => $e->getMessage()];
        }

        return $result;
    }

    /** @param array $nodes @return array */
    private function statementContent(array $nodes): array
    {
        unset($nodes['start'], $nodes['end'], $nodes['raw']);
        if (! array_is_list($nodes)) {
            ksort($nodes);
        }

        return array_map(fn ($value) => is_array($value) ? $this->statementContent($value) : $value, $nodes);
    }

    /** Validate trusted accepted-source bindings against the dictionary acquired under canonical locks.
     * @param  array  $bindings  @param array $current
     */
    public function validateReferenceBindings(array $bindings, array $current): void
    {
        if (array_keys($bindings) !== ['statements', 'dictionary'] || ! is_array($bindings['statements']) || ! array_is_list($bindings['statements']) || $bindings['statements'] === [] || ! is_array($bindings['dictionary'])) {
            throw new InvalidArgumentException('Malformed accepted-source reference bindings.');
        }
        foreach ([$bindings['dictionary'], $current] as $dictionary) {
            foreach ($dictionary as $rows) {
                if (! is_array($rows) || ! array_is_list($rows)) {
                    throw new InvalidArgumentException('Malformed reference dictionary.');
                }
                foreach ($rows as $row) {
                    if (! is_array($row) || ! is_string($row['id'] ?? null) || ! is_array($row['values'] ?? null)) {
                        throw new InvalidArgumentException('Malformed Attribute binding.');
                    }
                    foreach ($row['values'] as $ids) {
                        if (! is_array($ids) || ! array_is_list($ids) || array_filter($ids, fn ($id) => ! is_string($id)) !== []) {
                            throw new InvalidArgumentException('Malformed Value binding.');
                        }
                    }
                }
            }
        }
        $this->validateNodes($bindings['statements']);
        $this->assertReferences($bindings['statements'], $bindings['dictionary'], $current);
    }

    private function validateNodes(array $nodes, bool $child = false): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node) || ! is_string($node['kind'] ?? null)) {
                throw new InvalidArgumentException('Malformed statement binding.');
            }
            $kind = $node['kind'];
            $strings = [];
            $lists = [];
            if ($kind === 'condition') {
                $strings = ['reference', 'operator', 'source_kind'];
                $lists = ['values'];
                if (! in_array($node['source_kind'] ?? null, ['SelectionOption', 'ProductProperty'], true) || ! in_array($node['operator'] ?? null, ['Equals', 'NotEquals', 'In', 'NotIn', 'Contains'], true)) {
                    throw new InvalidArgumentException('Unsupported condition binding.');
                }
            } elseif ($kind === 'group' && ! $child) {
                if (! in_array($node['operator'] ?? null, ['All', 'Any'], true) || ! is_array($node['children'] ?? null) || ! array_is_list($node['children'])) {
                    throw new InvalidArgumentException('Malformed group binding.');
                }
                $this->validateNodes($node['children'], true);
            } elseif ($child) {
                throw new InvalidArgumentException('Only conditions may occur in child groups.');
            } elseif ($kind === 'pair') {
                $strings = ['source', 'target'];
            } elseif ($kind === 'case') {
                $lists = ['source_values', 'target_values'];
            } elseif ($kind === 'other') {
                if (! in_array($node['behavior'] ?? null, ['Hide', 'Disable'], true)) {
                    throw new InvalidArgumentException('Malformed other-target binding.');
                }
            } elseif (in_array($kind, ['allow', 'exclude', 'hide', 'disable', 'set-label', 'set-display-value', 'set-hint'], true)) {
                $strings = ['attribute'];
                $lists = ['values'];
                if (! array_key_exists('text', $node) || ($node['text'] !== null && ! is_string($node['text']))) {
                    throw new InvalidArgumentException('Malformed text binding.');
                }
            } else {
                throw new InvalidArgumentException('Unknown statement binding.');
            }
            foreach ($strings as $field) {
                if (! is_string($node[$field] ?? null)) {
                    throw new InvalidArgumentException('Malformed statement reference.');
                }
            }
            foreach ($lists as $field) {
                if (! is_array($node[$field] ?? null) || ! array_is_list($node[$field]) || array_filter($node[$field], fn ($value) => ! is_string($value)) !== []) {
                    throw new InvalidArgumentException('Malformed statement Value list.');
                }
            }
        }
    }

    /** @param array $nodes @param array $old @param array $current */
    private function assertReferences(array $nodes, array $old, array $current): void
    {
        $pair = null;
        $check = function (string $key, array $values = []) use ($old, $current): void {
            if ($this->attribute($key, $old)['id'] !== $this->attribute($key, $current)['id']
                || $this->values($values, $key, $old) !== $this->values($values, $key, $current)) {
                throw new InvalidArgumentException('A bound reference changed. Reopen explicitly without replacing the writing.');
            }
        };
        foreach ($nodes as $node) {
            if ($node['kind'] === 'group') {
                $this->assertReferences($node['children'], $old, $current);
            } elseif ($node['kind'] === 'condition' && $node['source_kind'] === 'SelectionOption') {
                $check($node['reference'], $node['values']);
            } elseif ($node['kind'] === 'pair') {
                $pair = $node;
                $check($node['source']);
                $check($node['target']);
            } elseif ($node['kind'] === 'case' && $pair !== null) {
                $check($pair['source'], $node['source_values']);
                $check($pair['target'], $node['target_values']);
            } elseif ($node['kind'] === 'case') {
                throw new InvalidArgumentException('A case binding requires its preceding pair.');
            } elseif (isset($node['attribute'])) {
                $check($node['attribute'], $node['values']);
            }
        }
    }

    /** @param array $node @param array $dictionary @return array */
    private function conditionRow(array $node, array $dictionary, int &$counter): array
    {
        $product = $node['source_kind'] === 'ProductProperty';
        if ($product && ! ProductFields::validKey($node['reference'])) {
            throw new InvalidArgumentException('Invalid exact Product key.');
        }

        return ['id' => 'new:w_condition_'.++$counter, 'source_kind' => $node['source_kind'], 'source_configurator_attribute_id' => $product ? null : $this->attribute($node['reference'], $dictionary)['id'], 'property_key' => $product ? $node['reference'] : null, 'context_dimension' => null, 'operator' => $node['operator'], 'operand' => $product ? (in_array($node['operator'], ['In', 'NotIn'], true) ? $node['values'] : $node['values'][0]) : null, 'option_ids' => $product ? [] : $this->values($node['values'], $node['reference'], $dictionary)];
    }

    /** @param array $row @param array $dictionary */
    private function conditionText(array $row, array $dictionary): string
    {
        if (isset($row['conditions']) || ($row['context_dimension'] ?? null) !== null) {
            throw new InvalidArgumentException('Unsupported condition nesting/context.');
        }
        $operators = ['Equals' => '=', 'NotEquals' => '!=', 'In' => 'in', 'NotIn' => 'not-in', 'Contains' => 'contains'];
        if (! isset($operators[$row['operator']])) {
            throw new InvalidArgumentException('Unsupported comparison.');
        }
        if ($row['source_kind'] === 'SelectionOption') {
            if ($row['operand'] !== null || $row['property_key'] !== null) {
                throw new InvalidArgumentException('Unsupported selection operand.');
            }
            $key = $this->attributeKey($row['source_configurator_attribute_id'], $dictionary);

            return '@'.$key.' '.$operators[$row['operator']].' '.$this->valueText($row['option_ids'], $key, $dictionary);
        }
        if ($row['source_kind'] !== 'ProductProperty' || ! ProductFields::validKey($row['property_key']) || $row['option_ids'] !== [] || $row['source_configurator_attribute_id'] !== null) {
            throw new InvalidArgumentException('Unsupported condition source.');
        }
        $operands = in_array($row['operator'], ['In', 'NotIn'], true) ? $row['operand'] : [$row['operand']];
        if (! is_array($operands)) {
            throw new InvalidArgumentException('Unsupported operand.');
        }

        return '$'.$this->quote($row['property_key']).' '.$operators[$row['operator']].' '.implode(' | ', array_map($this->quote(...), $operands));
    }

    /** @param array $dictionary @return array */
    private function attribute(string $key, array $dictionary): array
    {
        if (! preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/D', $key) || count($dictionary[$key] ?? []) !== 1) {
            throw new InvalidArgumentException('Missing, ambiguous or unrepresentable Attribute key.');
        }

        return $dictionary[$key][0];
    }

    private function attributeKey(string $id, array $dictionary): string
    {
        $keys = [];
        foreach ($dictionary as $key => $rows) {
            foreach ($rows as $row) {
                if ($row['id'] === $id) {
                    $keys[] = (string) $key;
                }
            }
        }
        if (count($keys) !== 1) {
            throw new InvalidArgumentException('Included Attribute is missing or ambiguous.');
        }
        $this->attribute($keys[0], $dictionary);

        return $keys[0];
    }

    /** @param list<string> $keys @param array $dictionary @return list<string> */
    private function values(array $keys, string $attribute, array $dictionary): array
    {
        $row = $this->attribute($attribute, $dictionary);
        $ids = [];
        foreach ($keys as $key) {
            if (count($row['values'][$key] ?? []) !== 1) {
                throw new InvalidArgumentException('Value is missing or ambiguous for this Attribute.');
            }
            $ids[] = $row['values'][$key][0];
        }

        return $ids;
    }

    /** @param list<string> $ids @param array $dictionary */
    private function valueText(array $ids, string $attribute, array $dictionary): string
    {
        $row = $this->attribute($attribute, $dictionary);
        $text = [];
        foreach ($ids as $id) {
            $keys = [];
            foreach ($row['values'] as $key => $matches) {
                if ($matches === [$id]) {
                    $keys[] = (string) $key;
                }
            }
            if (count($keys) !== 1 || ! preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/D', $keys[0])) {
                throw new InvalidArgumentException('Value has no unique representable semantic ID.');
            }
            $text[] = '#'.$keys[0];
        }
        if ($text === []) {
            throw new InvalidArgumentException('Choose at least one Value.');
        }

        return implode(' | ', $text);
    }

    private function quote(mixed $text): string
    {
        if (! is_string($text)) {
            throw new InvalidArgumentException('Only exact string literals are supported.');
        }

        return json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array $rows @param array $old @return array */
    private function reconcile(array $rows, array $old, string $bucket): array
    {
        if (count($rows) === count($old) && array_map(fn ($row) => $this->shape($row, $bucket), $rows) === array_map(fn ($row) => $this->shape($row, $bucket), $old)) {
            return $old;
        }
        foreach ($old as $previous) {
            $shape = $this->shape($previous, $bucket);
            $before = count(array_filter($old, fn ($row) => $this->shape($row, $bucket) === $shape));
            $after = count(array_filter($rows, fn ($row) => $this->shape($row, $bucket) === $shape));
            if (max($before, $after) > 1 && $before !== $after) {
                throw new InvalidArgumentException('Duplicate insertion or removal has ambiguous existing identity.');
            }
        }
        $unused = array_keys($old);
        $assigned = [];
        foreach ($rows as $i => $row) {
            $matches = array_values(array_filter($unused, fn ($j) => $this->shape($row, $bucket) === $this->shape($old[$j], $bucket)));
            if (count($matches) > 1) {
                throw new InvalidArgumentException('Identical nodes have ambiguous identity; retain the writing and resolve explicitly.');
            }
            if (count($matches) === 1) {
                $assigned[$i] = $matches[0];
                $unused = array_values(array_diff($unused, $matches));
            }
        }
        $pending = array_values(array_diff(array_keys($rows), array_keys($assigned)));
        if (count($pending) === 1 && count($unused) === 1) {
            $assigned[$pending[0]] = $unused[0];
        } elseif ($unused !== [] && $pending !== []) {
            throw new InvalidArgumentException('Several edited nodes have ambiguous surviving identities.');
        }
        foreach ($assigned as $i => $j) {
            $rows[$i]['id'] = $old[$j]['id'];
            if ($bucket === 'sets') {
                $rows[$i]['label'] = $old[$j]['label'];
                $rows[$i]['sort_order'] = $old[$j]['sort_order'];
            }
            if (isset($rows[$i]['conditions']) && isset($old[$j]['conditions'])) {
                $rows[$i]['conditions'] = $this->reconcile($rows[$i]['conditions'], $old[$j]['conditions'], 'conditions');
            }
        }
        if ($bucket === 'sets') {
            ksort($assigned);
            $previousOrder = array_values($assigned);
            $sorted = $previousOrder;
            sort($sorted, SORT_NUMERIC);
            $hasMiddleInsert = false;
            $lastSurvivor = $assigned === [] ? -1 : max(array_keys($assigned));
            foreach (array_keys($rows) as $position) {
                if (! isset($assigned[$position]) && $position < $lastSurvivor) {
                    $hasMiddleInsert = true;
                }
            }
            if ($previousOrder !== $sorted || $hasMiddleInsert) {
                foreach ($rows as $position => &$row) {
                    $row['sort_order'] = $position;
                }
                unset($row);

                return $rows;
            }
            $used = array_column(array_intersect_key($rows, $assigned), 'sort_order');
            $next = $used === [] ? 0 : max($used) + 1;
            foreach ($rows as $i => &$row) {
                if (! isset($assigned[$i])) {
                    $row['sort_order'] = $next++;
                }
            }
        }

        return $rows;
    }

    /** @param array $row @return array */
    private function shape(array $row, string $bucket): array
    {
        unset($row['id']);
        if ($bucket === 'sets') {
            unset($row['label'], $row['sort_order']);
        }
        if (isset($row['conditions'])) {
            $row['conditions'] = array_map(fn ($child) => $this->shape($child, 'conditions'), $row['conditions']);
        }
        ksort($row);

        return $row;
    }
}
