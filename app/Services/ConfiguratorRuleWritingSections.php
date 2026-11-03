<?php

namespace App\Services;

use InvalidArgumentException;

class ConfiguratorRuleWritingSections
{
    public function __construct(private ConfiguratorRuleWritingParser $parser) {}

    /** @param list<string> $checked */
    public function replace(string $source, string $latest, array $checked, string $kind): string
    {
        $outcome = match ($kind) {
            'Mapping' => 'mapping',
            'Advanced' => 'effects',
            default => throw new InvalidArgumentException('Unsupported Writing kind.'),
        };
        if (! array_is_list($checked) || count(array_unique($checked)) !== count($checked) || array_diff($checked, ['conditions', $outcome]) !== []) {
            throw new InvalidArgumentException('Choose explicit coherent logic sections.');
        }
        if ($checked === []) {
            return $source;
        }
        $current = $this->parser->sections($source);
        $saved = $this->parser->sections($latest);
        $wrong = $outcome === 'mapping' ? 'effects' : 'mapping';
        if ($current[$wrong] !== null || $saved[$wrong] !== null) {
            throw new InvalidArgumentException('Sections must match the fixed Rule kind.');
        }
        $newline = $current['newlines'] === [] ? "\n" : substr($source, $current['newlines'][0]['start'], $current['newlines'][0]['end'] - $current['newlines'][0]['start']);
        $edits = [];
        foreach (['conditions', $outcome] as $section) {
            if (! in_array($section, $checked, true)) {
                continue;
            }
            $range = $current[$section];
            $replacement = '';
            if ($saved[$section] !== null) {
                $start = $saved[$section]['start'];
                $end = $saved[$section]['end'];
                $replacement = substr($latest, $start, $end - $start);
                foreach (array_reverse($saved['newlines']) as $token) {
                    if ($token['start'] >= $start && $token['end'] <= $end) {
                        $replacement = substr_replace($replacement, $newline, $token['start'] - $start, $token['end'] - $token['start']);
                    }
                }
            }
            if ($range !== null) {
                $edits[] = [...$range, 'text' => $replacement, 'rank' => 0];
            } elseif ($replacement !== '') {
                if ($section === 'conditions' && $current[$outcome] !== null) {
                    $position = $current[$outcome]['start'];
                    while ($position > 0 && ! in_array($source[$position - 1], ["\n", "\r"], true)) {
                        $position--;
                    }
                    $text = $replacement.$newline;
                } else {
                    $position = strlen($source);
                    $text = $replacement;
                }
                $edits[] = ['start' => $position, 'end' => $position, 'text' => $text, 'rank' => $section === 'conditions' ? 1 : 2];
            }
        }
        $insertions = [];
        $replacements = [];
        foreach ($edits as $edit) {
            if ($edit['start'] === $edit['end']) {
                $insertions[$edit['start']][] = $edit;
            } else {
                $replacements[] = $edit;
            }
        }
        foreach ($insertions as $position => $items) {
            usort($items, fn ($a, $b) => $a['rank'] <=> $b['rank']);
            $text = implode($newline, array_column($items, 'text'));
            if ($position === strlen($source) && $source !== '' && ! in_array(substr($source, -1), ["\r", "\n"], true)) {
                $text = $newline.$text;
            }
            $replacements[] = ['start' => $position, 'end' => $position, 'text' => $text, 'rank' => 1];
        }
        usort($replacements, fn ($a, $b) => ($b['start'] <=> $a['start']) ?: ($b['end'] <=> $a['end']));
        foreach ($replacements as $edit) {
            $source = substr_replace($source, $edit['text'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $source;
    }
}
