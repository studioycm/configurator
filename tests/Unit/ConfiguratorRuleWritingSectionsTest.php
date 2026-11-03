<?php

use App\Services\ConfiguratorRuleWritingParser;
use App\Services\ConfiguratorRuleWritingSections;

test('checked sections replace syntax only and preserve unchecked bytes', function () {
    $mine = "// שלי\r\n  /when \$Pressure = \"old\" // retained boundary\r\n\r\n  /pair @A -> @B\r\n/case #one -> #two\r\n/other disable // keep tail\r\n";
    $latest = "/when all {\n  \$Pressure in \"new\" | \"other\"\n}\n/pair @A -> @B\n/case #three -> #four\n/other hide";
    $actual = (new ConfiguratorRuleWritingSections(new ConfiguratorRuleWritingParser))->replace($mine, $latest, ['conditions'], 'Mapping');
    expect($actual)->toBe("// שלי\r\n  /when all {\r\n  \$Pressure in \"new\" | \"other\"\r\n} // retained boundary\r\n\r\n  /pair @A -> @B\r\n/case #one -> #two\r\n/other disable // keep tail\r\n");
});

test('flat and explicit conditions include their wrappers and exclude outside comments', function (string $condition) {
    $source = "// before\n  ".$condition." // after\n/set-hint @C [[/when\n/pair @A -> @B\n}]]";
    $ranges = (new ConfiguratorRuleWritingParser)->sections($source);
    expect(substr($source, $ranges['conditions']['start'], $ranges['conditions']['end'] - $ranges['conditions']['start']))->toBe($condition);
    expect(substr($source, $ranges['effects']['start'], $ranges['effects']['end'] - $ranges['effects']['start']))->toBe('/set-hint @C [[/when'."\n".'/pair @A -> @B'."\n".'}]]');
})->with([
    'flat' => ['/when $P = "x"'."\n".'/or $Q != "y"'],
    'grouped' => ['/when all {'."\n".'  any {'."\n".'    $P = "x"'."\n".'    $Q != "y"'."\n".'  }'."\n".'}'],
]);

test('missing sections insert without moving original indentation or literals', function () {
    $splicer = new ConfiguratorRuleWritingSections(new ConfiguratorRuleWritingParser);
    $latest = '/when $P = "x"'."\n".'/set-hint @C [[first'."\n".'second]]';
    expect($splicer->replace("// pre\r\n  /hide @C", $latest, ['conditions'], 'Advanced'))->toBe("// pre\r\n/when \$P = \"x\"\r\n  /hide @C");
    expect($splicer->replace('', $latest, ['effects', 'conditions'], 'Advanced'))->toBe($latest);
    expect($splicer->replace('// pre', $latest, ['effects', 'conditions'], 'Advanced'))->toBe("// pre\n".$latest);
    expect($splicer->replace('/when $P = "mine"', $latest, ['effects'], 'Advanced'))->toBe('/when $P = "mine"'."\n".'/set-hint @C [[first'."\n".'second]]');
});

test('removing conditions and replacing effects preserves outside bytes and multiline text newlines', function () {
    $splicer = new ConfiguratorRuleWritingSections(new ConfiguratorRuleWritingParser);
    $mine = "/when \$P = \"old\"\r\n/hide @C // tail\r\n";
    $latest = "/set-hint @C [[line 1\nline 2]]\n/hide @A";
    expect($splicer->replace($mine, $latest, ['conditions', 'effects'], 'Advanced'))->toBe("\r\n/set-hint @C [[line 1\nline 2]]\r\n/hide @A // tail\r\n");
});

test('unsafe or wrong-kind boundaries decline rather than approximate', function (string $source, string $kind) {
    $splicer = new ConfiguratorRuleWritingSections(new ConfiguratorRuleWritingParser);
    expect(fn () => $splicer->replace($source, '/hide @C', ['conditions'], $kind))->toThrow(InvalidArgumentException::class);
})->with([
    'incomplete' => ['/when', 'Advanced'],
    'quoted command' => ['"/hide" @C', 'Advanced'],
    'quoted wrapper' => ['/when "all" {'."\n".'$P = "x"'."\n".'}'."\n".'/hide @C', 'Advanced'],
    'mixed outcome' => ['/pair @A -> @B'."\n".'/case #one -> #two'."\n".'/hide @C', 'Mapping'],
    'wrong kind' => ['/pair @A -> @B'."\n".'/case #one -> #two', 'Advanced'],
]);

test('keep mine does not require parsing unfinished current text', function () {
    $splicer = new ConfiguratorRuleWritingSections(new ConfiguratorRuleWritingParser);
    expect($splicer->replace('/when unfinished [[', '/hide @C', [], 'Advanced'))->toBe('/when unfinished [[');
});

test('literal Product keys and operands never become structural section tokens', function (string $conditions) {
    $source = $conditions."\n/hide @C";
    $parser = new ConfiguratorRuleWritingParser;
    expect($parser->parse($source)['diagnostics'])->toBe([]);
    $splicer = new ConfiguratorRuleWritingSections($parser);
    expect($splicer->replace($source, $conditions."\n/set-hint @C [[new]]", ['effects'], 'Advanced'))->toBe($conditions."\n/set-hint @C [[new]]");
})->with([
    'Product all' => ['/when $all = "x"'],
    'Attribute all' => ['/when @all = #one'],
    'Product command key' => ['/when all {'."\n".'  $"/hide" = "x"'."\n".'}'],
    'pipe text' => ['/when $P in "|" | "x"'],
    'arrow text' => ['/when $P in "->" | "x"'],
]);
