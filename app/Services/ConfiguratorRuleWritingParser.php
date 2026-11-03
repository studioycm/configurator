<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

class ConfiguratorRuleWritingParser
{
    /** @return array{source:string,format_version:int,statements:array,diagnostics:array} */
    public function parse(string $source): array
    {
        $result = ['source' => $source, 'format_version' => 1, 'statements' => [], 'diagnostics' => []];
        $offset = 0;
        try {
            if (! mb_check_encoding($source, 'UTF-8') || strlen($source) > 1048576) {
                throw new InvalidArgumentException('Source must be UTF-8 within the authoring size limit.');
            }
            $tokens = $this->tokens($source);
            $lines = [];
            $line = [];
            foreach ($tokens as $token) {
                if ($token['type'] === 'newline') {
                    if ($line !== []) {
                        $lines[] = $line;
                        $line = [];
                    }
                } else {
                    $line[] = $token;
                }
            }
            if ($line !== []) {
                $lines[] = $line;
            }
            $root = false;
            $group = null;
            $flat = null;
            $sawOutcome = false;
            foreach ($lines as $tokens) {
                $offset = $tokens[0]['start'];
                $words = array_column($tokens, 'value');
                $command = $words[0];
                $node = ['start' => $offset, 'end' => end($tokens)['end'], 'raw' => substr($source, $offset, end($tokens)['end'] - $offset)];
                if ($words === ['/when', 'all', '{']) {
                    if ($root || $result['statements'] !== [] || $sawOutcome) {
                        throw new InvalidArgumentException('Use one outer All condition block before outcomes.');
                    }
                    $root = true;

                    continue;
                }
                if (in_array($command, ['all', 'any', '/all', '/any'], true) && ($words[1] ?? null) === '{' && count($words) === 2) {
                    if (! $root || $group !== null) {
                        throw new InvalidArgumentException('Only one child group level is supported.');
                    }
                    $group = [...$node, 'kind' => 'group', 'operator' => ucfirst(ltrim($command, '/')), 'children' => []];

                    continue;
                }
                if ($words === ['}']) {
                    if ($group !== null) {
                        if ($group['children'] === []) {
                            throw new InvalidArgumentException('A group needs conditions.');
                        }
                        $group['end'] = $node['end'];
                        $group['raw'] = substr($source, $group['start'], $group['end'] - $group['start']);
                        $result['statements'][] = $group;
                        $group = null;
                    } elseif ($root) {
                        $root = false;
                    } else {
                        throw new InvalidArgumentException('No group is open.');
                    }

                    continue;
                }
                if ($root || in_array($command, ['/when', '/and', '/or'], true)) {
                    if ($sawOutcome) {
                        throw new InvalidArgumentException('Conditions precede outcomes.');
                    }
                    if (! $root) {
                        if ($command === '/when') {
                            if ($flat !== null) {
                                throw new InvalidArgumentException('Start conditions once.');
                            } $flat = 'All';
                        } elseif ($flat === null) {
                            throw new InvalidArgumentException('Start conditions with /when.');
                        }
                        if ($command === '/or') {
                            if ($flat === 'And') {
                                throw new InvalidArgumentException('Use explicit groups for mixed joins.');
                            } $flat = 'Any';
                        }
                        if ($command === '/and') {
                            if ($flat === 'Any') {
                                throw new InvalidArgumentException('Use explicit groups for mixed joins.');
                            } $flat = 'And';
                        }
                        array_shift($tokens);
                    }
                    $condition = [...$node, ...$this->condition($tokens)];
                    if ($group !== null) {
                        $group['children'][] = $condition;
                    } else {
                        $result['statements'][] = $condition;
                    }

                    continue;
                }
                $sawOutcome = true;
                if ($command === '/pair') {
                    if (count($tokens) !== 4 || $tokens[1]['type'] !== 'attribute' || $words[2] !== '->' || $tokens[3]['type'] !== 'attribute') {
                        throw new InvalidArgumentException('Complete /pair @source -> @target.');
                    }
                    $node += ['kind' => 'pair', 'source' => $words[1], 'target' => $words[3]];
                } elseif ($command === '/case') {
                    $arrow = array_search('->', $words, true);
                    if ($arrow === false) {
                        throw new InvalidArgumentException('A case needs source -> target Values.');
                    }
                    $node += ['kind' => 'case', 'source_values' => $this->values(array_slice($tokens, 1, $arrow - 1), 'value'), 'target_values' => $this->values(array_slice($tokens, $arrow + 1), 'value')];
                } elseif ($command === '/other') {
                    if (count($tokens) !== 2 || ! in_array($words[1], ['hide', 'disable'], true)) {
                        throw new InvalidArgumentException('Use /other hide or disable after a case.');
                    }
                    $node += ['kind' => 'other', 'behavior' => ucfirst($words[1])];
                } elseif (in_array($command, ['/allow', '/exclude', '/hide', '/disable', '/set-label', '/set-display-value', '/set-hint'], true)) {
                    if (($tokens[1]['type'] ?? null) !== 'attribute') {
                        throw new InvalidArgumentException('Choose a target Attribute.');
                    }
                    $rest = array_slice($tokens, 2);
                    $text = null;
                    if (str_starts_with($command, '/set-')) {
                        $last = array_pop($rest);
                        if (($last['type'] ?? null) !== 'string') {
                            throw new InvalidArgumentException('Provide quoted text or a text block.');
                        }
                        $text = $last['value'];
                    }
                    $values = $rest === [] ? [] : $this->values($rest, 'value');
                    if ($values === [] && in_array($command, ['/allow', '/exclude', '/disable'], true)) {
                        throw new InvalidArgumentException('Select target Values.');
                    }
                    $node += ['kind' => ltrim($command, '/'), 'attribute' => $words[1], 'values' => $values, 'text' => $text];
                } else {
                    throw new InvalidArgumentException('Unknown or incomplete full command.');
                }
                $result['statements'][] = $node;
            }
            if ($root || $group !== null) {
                throw new InvalidArgumentException('Close the condition group.');
            }
            if ($flat === 'Any') {
                $conditions = array_values(array_filter($result['statements'], fn ($n) => $n['kind'] === 'condition'));
                if ($conditions !== []) {
                    $first = $conditions[0];
                    $last = end($conditions);
                    $result['statements'] = [['kind' => 'group', 'operator' => 'Any', 'children' => $conditions, 'start' => $first['start'], 'end' => $last['end'], 'raw' => substr($source, $first['start'], $last['end'] - $first['start'])], ...array_values(array_filter($result['statements'], fn ($n) => $n['kind'] !== 'condition'))];
                }
            }
        } catch (InvalidArgumentException|JsonException $e) {
            $result['statements'] = [];
            $result['diagnostics'][] = ['code' => 'syntax', 'severity' => 'error', 'start' => $offset, 'end' => strlen($source), 'message' => $e->getMessage()];
        }

        return $result;
    }

    /** @return array{conditions:?array{start:int,end:int},mapping:?array{start:int,end:int},effects:?array{start:int,end:int},newlines:list<array{start:int,end:int}>} */
    public function sections(string $source): array
    {
        $parsed = $this->parse($source);
        if ($parsed['diagnostics'] !== []) {
            throw new InvalidArgumentException('The source has no safe section boundaries.');
        }
        $result = ['conditions' => null, 'mapping' => null, 'effects' => null, 'newlines' => []];
        $lines = [];
        $line = [];
        foreach ($this->tokens($source) as $token) {
            if ($token['type'] === 'newline') {
                $result['newlines'][] = ['start' => $token['start'], 'end' => $token['end']];
                if ($line !== []) {
                    $lines[] = $line;
                    $line = [];
                }
            } else {
                $line[] = $token;
            }
        }
        if ($line !== []) {
            $lines[] = $line;
        }
        $outcome = null;
        $pair = false;
        $case = false;
        foreach ($lines as $tokens) {
            $first = $tokens[0];
            $command = $first['type'] === 'word' ? $first['value'] : null;
            if (! in_array($first['type'], ['word', 'attribute', 'product'], true)) {
                throw new InvalidArgumentException('Quoted text is not a structural command.');
            }
            $bucket = in_array($command, ['/pair', '/case', '/other'], true) ? 'mapping'
                : (in_array($command, ['/allow', '/exclude', '/hide', '/disable', '/set-label', '/set-display-value', '/set-hint'], true) ? 'effects' : 'conditions');
            if ($bucket !== 'conditions') {
                if ($outcome !== null && $outcome !== $bucket) {
                    throw new InvalidArgumentException('Mixed outcome sections cannot be refreshed.');
                }
                $outcome = $bucket;
            } elseif ($outcome !== null) {
                throw new InvalidArgumentException('Conditions must precede outcomes.');
            }
            if ($command === '/pair') {
                if ($pair || $tokens[2]['type'] !== 'word') {
                    throw new InvalidArgumentException('One structural Mapping pair is required.');
                }
                $pair = true;
            } elseif ($command === '/case') {
                if (! $pair) {
                    throw new InvalidArgumentException('Mapping cases require a pair.');
                }
                $case = true;
            } elseif ($command === '/other' && (! $case || $tokens[1]['type'] !== 'word')) {
                throw new InvalidArgumentException('Other behavior requires a Mapping case.');
            }
            $wordPositions = $first['type'] === 'word' ? [0] : [];
            $wrapper = $command === '/when' && array_column($tokens, 'value') === ['/when', 'all', '{'];
            if ($wrapper) {
                $wordPositions = [0, 1, 2];
            } elseif (in_array($command, ['all', 'any', '/all', '/any'], true)) {
                $wordPositions[] = 1;
            } elseif ($bucket === 'conditions' && $command !== '}') {
                $reference = $first['type'] === 'word' ? 1 : 0;
                $wordPositions[] = $reference + 1;
                for ($index = $reference + 3; $index < count($tokens); $index += 2) {
                    $wordPositions[] = $index;
                }
            } elseif ($command === '/case') {
                $arrow = array_search('->', array_column($tokens, 'value'), true);
                $wordPositions[] = $arrow;
                for ($index = 2; $index < $arrow; $index += 2) {
                    $wordPositions[] = $index;
                }
                for ($index = $arrow + 2; $index < count($tokens); $index += 2) {
                    $wordPositions[] = $index;
                }
            } elseif ($bucket === 'effects') {
                $end = count($tokens) - (str_starts_with($command, '/set-') ? 1 : 0);
                for ($index = 3; $index < $end; $index += 2) {
                    $wordPositions[] = $index;
                }
            }
            foreach ($wordPositions as $index) {
                if (($tokens[$index]['type'] ?? null) !== 'word') {
                    throw new InvalidArgumentException('Structural syntax must use actual command tokens.');
                }
            }
            $result[$bucket] ??= ['start' => $first['start'], 'end' => $first['end']];
            $result[$bucket]['end'] = $tokens[array_key_last($tokens)]['end'];
        }

        return $result;
    }

    /** @param array $tokens @return array */
    private function condition(array $tokens): array
    {
        $reference = $tokens[0] ?? [];
        if (! in_array($reference['type'] ?? null, ['attribute', 'product'], true)) {
            throw new InvalidArgumentException('Choose an Attribute or Product field.');
        }
        $operators = ['=' => 'Equals', '!=' => 'NotEquals', 'in' => 'In', 'not-in' => 'NotIn', 'contains' => 'Contains'];
        $operator = $operators[$tokens[1]['value'] ?? ''] ?? null;
        if ($operator === null || ($operator === 'Contains' && $reference['type'] !== 'product')) {
            throw new InvalidArgumentException('Choose a supported comparison.');
        }
        $values = $this->values(array_slice($tokens, 2), $reference['type'] === 'attribute' ? 'value' : 'string');
        if (! in_array($operator, ['In', 'NotIn'], true) && count($values) !== 1) {
            throw new InvalidArgumentException('Use in or not-in for multiple Values.');
        }

        return ['kind' => 'condition', 'source_kind' => $reference['type'] === 'attribute' ? 'SelectionOption' : 'ProductProperty', 'reference' => $reference['value'], 'operator' => $operator, 'values' => $values];
    }

    /** @param array $tokens @return list<string> */
    private function values(array $tokens, string $type): array
    {
        if ($tokens === []) {
            throw new InvalidArgumentException('Add the Value after the separator.');
        }
        $values = [];
        foreach ($tokens as $i => $token) {
            if ($i % 2 === 0) {
                if ($token['type'] !== $type) {
                    throw new InvalidArgumentException('Choose the expected Value or quoted text.');
                }
                $values[] = $token['value'];
            } elseif ($token['value'] !== '|') {
                throw new InvalidArgumentException('Separate Values with a pipe.');
            }
        }
        if (count($tokens) % 2 === 0) {
            throw new InvalidArgumentException('Add the Value after the separator.');
        }
        if (count(array_unique($values, SORT_STRING)) !== count($values)) {
            throw new InvalidArgumentException('Use distinct Values in this list.');
        }

        return $values;
    }

    /** @return list<array{type:string,value:string,start:int,end:int}> */
    private function tokens(string $source): array
    {
        $out = [];
        $i = 0;
        $length = strlen($source);
        while ($i < $length) {
            $start = $i;
            $char = $source[$i];
            if ($char === "\r" || $char === "\n") {
                $i += substr($source, $i, 2) === "\r\n" ? 2 : 1;
                $out[] = ['type' => 'newline', 'value' => "\n", 'start' => $start, 'end' => $i];

                continue;
            }
            if ($char === ' ' || $char === "\t") {
                $i++;

                continue;
            }
            if (substr($source, $i, 2) === '//') {
                while ($i < $length && ! in_array($source[$i], ["\r", "\n"], true)) {
                    $i++;
                }

                continue;
            }
            $prefix = in_array($char, ['@', '#', '$'], true) ? $char : '';
            if ($prefix !== '') {
                $i++;
            }
            if (($source[$i] ?? '') === '"') {
                $quote = $i++;
                $closed = false;
                while ($i < $length) {
                    if ($source[$i] === '\\') {
                        $i += 2;

                        continue;
                    }
                    if ($source[$i++] === '"') {
                        $closed = true;
                        break;
                    }
                }
                if (! $closed) {
                    throw new InvalidArgumentException('Close the quoted text.');
                }
                $value = json_decode(substr($source, $quote, $i - $quote), true, flags: JSON_THROW_ON_ERROR);
                if ($prefix !== '' && $prefix !== '$') {
                    throw new InvalidArgumentException('Only Product keys use quoted references.');
                }
                $type = $prefix === '$' ? 'product' : 'string';
            } elseif ($prefix === '' && substr($source, $i, 2) === '[[') {
                $i += 2;
                $value = '';
                $closed = false;
                while ($i < $length) {
                    if (substr($source, $i, 3) === '\\]]') {
                        $value .= ']]';
                        $i += 3;
                    } elseif (substr($source, $i, 2) === '\\\\') {
                        $value .= '\\';
                        $i += 2;
                    } elseif (substr($source, $i, 2) === ']]') {
                        $i += 2;
                        $closed = true;
                        break;
                    } else {
                        $value .= $source[$i++];
                    }
                }
                if (! $closed) {
                    throw new InvalidArgumentException('Close the text block with ]].');
                }
                $type = 'string';
            } elseif ($prefix !== '') {
                if (! preg_match('/\G[A-Za-z0-9_][A-Za-z0-9_.-]*/A', $source, $match, 0, $i)) {
                    throw new InvalidArgumentException('Complete the reference.');
                }
                $value = $match[0];
                $i += strlen($value);
                $type = ['@' => 'attribute', '#' => 'value', '$' => 'product'][$prefix];
            } elseif (preg_match('/\G(?:->|!=|[=|{}]|\/?[A-Za-z][A-Za-z-]*)/A', $source, $match, 0, $i)) {
                $value = $match[0];
                $i += strlen($value);
                $type = 'word';
            } else {
                throw new InvalidArgumentException('Unknown character in source.');
            }
            $out[] = ['type' => $type, 'value' => $value, 'start' => $start, 'end' => $i];
        }

        return $out;
    }
}
