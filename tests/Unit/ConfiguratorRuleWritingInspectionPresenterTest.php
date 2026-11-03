<?php

use App\Services\ConfiguratorRuleWritingInspectionPresenter;
use Symfony\Component\Yaml\Yaml;

test('inspection formats preserve complete envelope types and large identifiers', function () {
    $envelope = ['configurator_id' => 9007199254740993, 'draft' => ['source' => "\xEF\xBB\xBF// שלום\r\n", 'label' => '0', 'description' => ''], 'current' => ['records' => ['rule' => ['id' => 9007199254740993, 'operand' => '{"x":"001"}', 'enabled' => false], 'effects' => []]], 'last_writing_capture' => null, 'status' => ['target' => 'saved', 'capture' => 'none', 'draft_changed_since_capture' => null], 'empty_object' => (object) []];
    $result = (new ConfiguratorRuleWritingInspectionPresenter)->present($envelope);
    expect(json_decode($result['json'], flags: JSON_THROW_ON_ERROR))->toEqual(json_decode(json_encode($envelope), flags: JSON_THROW_ON_ERROR));
    expect($result['json'])->toContain('9007199254740993')->and($result['yaml'])->toContain('"operand": "{\"x\":\"001\"}"')->and($result['records'][0]['rows'][0]['cells'][0]['value'])->toBe('9007199254740993');
    $yaml = Yaml::parse($result['yaml'], Yaml::PARSE_OBJECT_FOR_MAP);
    expect($yaml)->toEqual(json_decode(json_encode($envelope), flags: JSON_THROW_ON_ERROR));
});

test('inspection formatting rejects unsupported nonfinite values', function () {
    (new ConfiguratorRuleWritingInspectionPresenter)->present(['number' => INF]);
})->throws(InvalidArgumentException::class);

test('YAML quote escaping preserves non ASCII line separators and C1 controls', function () {
    $source = "first\u{0085}second\u{009F}third\u{2028}fourth\u{2029}";
    $result = (new ConfiguratorRuleWritingInspectionPresenter)->present(['source' => $source]);
    expect(Yaml::parse($result['yaml'])['source'])->toBe($source);
});
