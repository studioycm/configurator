<?php

use App\Services\ConfiguratorRuleWritingParser;

test('writing preserves exact source and byte spans for a complete mapping', function () {
    $source = "// keep\r\n/pair @body -> @bolts\r\n/case #iron | #steel -> #steel\r\n/other hide\r\n";
    $parsed = (new ConfiguratorRuleWritingParser)->parse($source);
    expect($parsed['source'])->toBe($source)->and($parsed['diagnostics'])->toBe([])
        ->and($parsed['statements'][0]['kind'])->toBe('pair');
    foreach ($parsed['statements'] as $node) {
        expect(substr($source, $node['start'], $node['end'] - $node['start']))->toBe($node['raw']);
    }
});

test('writing reports incomplete or unsupported syntax without executable statements', function (string $source) {
    $parsed = (new ConfiguratorRuleWritingParser)->parse($source);
    expect($parsed['source'])->toBe($source)->and($parsed['diagnostics'])->not->toBeEmpty();
})->with(['/case #iron |', '/pair @body ->', '/when all {', '/unknown @body', '/when @body = #iron | #steel']);

test('text blocks preserve literal quotes line breaks and escaped closing delimiter', function () {
    $source = '/set-hint @body [[first "quote"'."\n".'second \]] end]]';
    $parsed = (new ConfiguratorRuleWritingParser)->parse($source);
    expect($parsed['diagnostics'])->toBe([])->and($parsed['statements'][0]['text'])->toBe('first "quote"'."\n".'second ]] end');
});

test('quoted Product keys remain exact literals with string operands', function () {
    $parsed = (new ConfiguratorRuleWritingParser)->parse('/when $"לחץ.size" in "001" | "0"');
    expect($parsed['diagnostics'])->toBe([])->and($parsed['statements'][0]['reference'])->toBe('לחץ.size')
        ->and($parsed['statements'][0]['values'])->toBe(['001', '0']);
});

test('portable writing grammar fixtures have independently specified outcomes', function () {
    $fixtures = json_decode(file_get_contents(dirname(__DIR__).'/RuleWritingFixtures.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($fixtures as $fixture) {
        $semantic = function (mixed $value) use (&$semantic): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                unset($value['start'], $value['end'], $value['raw']);
                ksort($value);
            }

            return array_map($semantic, $value);
        };
        $parsed = (new ConfiguratorRuleWritingParser)->parse($fixture['source']);
        expect($parsed['source'])->toBe($fixture['source']);
        if ($fixture['valid']) {
            expect($parsed['diagnostics'])->toBe([])->and(array_column($parsed['statements'], 'kind'))->toBe($fixture['kinds']);
            if (isset($fixture['ast'])) {
                expect($semantic($parsed['statements']))->toBe($semantic($fixture['ast']));
            }
            foreach ($fixture['spans'] ?? [] as $span) {
                $node = $parsed['statements'];
                foreach ($span['path'] as $part) {
                    $node = $node[$part];
                }
                expect($node['start'])->toBe($span['start'])->and($node['end'])->toBe($span['end'])->and($node['raw'])->toBe($span['raw']);
            }
            if (isset($fixture['text'])) {
                expect($parsed['statements'][0]['text'])->toBe($fixture['text']);
            }
        } else {
            expect($parsed['diagnostics'])->not->toBeEmpty()->and($parsed['statements'])->toBe([]);
            foreach ($fixture['diagnostic'] ?? [] as $key => $value) {
                expect($parsed['diagnostics'][0][$key])->toBe($value);
            }
        }
    }
});
