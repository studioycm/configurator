<?php

use App\Services\CatalogCsv;
use App\Services\CatalogDataExchangePolicy;
use Illuminate\Validation\ValidationException;

function canonicalCsvRows(string $bytes, string $delimiter = ','): array
{
    $csv = app(CatalogCsv::class)->reader($bytes, $delimiter);
    app(CatalogCsv::class)->validate($csv);
    $csv->setHeaderOffset(0);

    return iterator_to_array($csv->getRecords(), false);
}

test('standard CSV preserves a quoted trailing backslash and the following record', function () {
    $bytes = <<<'CSV'
import,semantic_id,label
1,example_a,"Pressure, path\"
1,example_b,Next
CSV;
    expect(canonicalCsvRows($bytes))->toBe([
        ['import' => '1', 'semantic_id' => 'example_a', 'label' => 'Pressure, path\\'],
        ['import' => '1', 'semantic_id' => 'example_b', 'label' => 'Next'],
    ]);
});

test('canonical CSV roundtrips exact text with standard quotes and empty escape', function (string $text, string $delimiter) {
    $csv = app(CatalogCsv::class)->writer($delimiter, bom: true);
    $csv->insertOne(['label', 'code', 'empty']);
    $csv->insertOne([$text, '001', '']);
    expect(canonicalCsvRows($csv->toString(), $delimiter))->toBe([['label' => $text, 'code' => '001', 'empty' => '']]);
})->with(['He said "Open"', "quoted\nline", "quoted\r\nline", 'path\\', '0', '  ', 'שלום café', "'intentional", 'backslash\\"then quote'])
    ->with([',', ';', '|', "\t"]);

test('whole file grammar validation rejects legacy quoting and incomplete enclosures before reading rows', function (string $bytes) {
    $reader = app(CatalogCsv::class)->reader($bytes);
    expect(fn () => app(CatalogCsv::class)->validate($reader))->toThrow(ValidationException::class, 'CSV');
})->with([
    'legacy proprietary escape' => "label\n\"He said \\\"Open\\\"\"\n",
    'late invalid row' => "label\nValid\n\"unclosed",
    'text after enclosure' => "label\n\"value\"tail\n",
    'quote in unquoted field' => "label\nnot\"standard\n",
]);

test('validation streams across chunk boundaries without changing blank or missing field acceptance', function () {
    $bytes = "a,b\r\n\"".str_repeat('x', 8191)."\"\"last\",0\r\n\r\nmissing\r\n";
    $rows = canonicalCsvRows($bytes);
    expect($rows[0])->toBe(['a' => str_repeat('x', 8191).'"last', 'b' => '0']);
    expect($rows[1])->toBe(['a' => 'missing', 'b' => null]);
});

test('readiness policy requires sealed complete evidence committed projection and independent published formats', function () {
    $policy = app(CatalogDataExchangePolicy::class);
    $facts = ['version' => 1, 'sealed' => true, 'evidence_valid' => true, 'planned' => 100, 'successful' => 98, 'rejected' => 2, 'unconfirmed' => 0,
        'commit_outcome' => 'known-committed', 'csv' => ['verified' => true, 'publication' => 'published'], 'xlsx' => ['verified' => false, 'publication' => 'staged']];
    $result = $policy->assessExport($facts);
    expect($result['csv'])->toMatchArray(['state' => 'ready', 'download' => true]);
    expect($result['xlsx'])->toMatchArray(['state' => 'pending', 'download' => false]);
    expect($result['partial'])->toBeTrue();
    foreach (['sealed' => false, 'evidence_valid' => false, 'commit_outcome' => 'pending-enclosing-commit', 'unconfirmed' => 25, 'version' => 99] as $key => $value) {
        expect($policy->assessExport([...$facts, $key => $value])['csv']['download'])->toBeFalse();
    }
    expect($policy->assessExport([...$facts, 'csv' => ['verified' => false, 'publication' => 'unknown'], 'xlsx' => ['verified' => true, 'publication' => 'published']])['xlsx']['download'])->toBeTrue();
});

test('managed metadata cannot downgrade to historical and empty exports are no-data', function () {
    $policy = app(CatalogDataExchangePolicy::class);
    expect($policy->assessExport(['version' => 1])['csv'])->toMatchArray(['state' => 'unconfirmed', 'download' => false]);
    expect($policy->assessExport(['version' => null, 'historical' => ['consistent' => true, 'csv' => true, 'xlsx' => false]])['csv'])->toMatchArray(['state' => 'historical', 'download' => true]);
    $facts = ['version' => 1, 'sealed' => true, 'evidence_valid' => true, 'planned' => 2, 'successful' => 0, 'rejected' => 2, 'unconfirmed' => 0, 'commit_outcome' => 'known-committed'];
    expect($policy->assessExport($facts)['csv'])->toMatchArray(['state' => 'no-data', 'download' => false]);
});

test('explicit legacy quoting preserves the old parser text instead of guessing or stripping escapes', function () {
    $bytes = <<<'CSV'
semantic_id,label
legacy_value,"He said \"Open\""
CSV;
    $csv = app(CatalogCsv::class)->reader($bytes, ',', ['mode' => 'legacy']);
    app(CatalogCsv::class)->validate($csv);
    $csv->setHeaderOffset(0);
    expect(iterator_to_array($csv->getRecords(), false)[0]['label'])->toBe('He said \\"Open\\"');
});

test('explicit custom enclosure supports doubled single quotes and preserves following rows', function () {
    $bytes = "semantic_id;label\nsingle_quote;'He said ''Open'''\nnext_value;0\n";
    $csv = app(CatalogCsv::class)->reader($bytes, ';', ['mode' => 'custom', 'enclosure' => "'", 'escape' => '']);
    app(CatalogCsv::class)->validate($csv);
    $csv->setHeaderOffset(0);
    expect(array_column(iterator_to_array($csv->getRecords(), false), 'label'))->toBe(["He said 'Open'", '0']);
});

test('legacy conversion is explicit narrow and applied once despite repeated native Reader configuration', function () {
    $bytes = <<<'CSV'
label,path,slashes
"He said \"0\"",C:\Tools\Valve,"two\\\"quotes"
CSV;
    $service = app(CatalogCsv::class);
    $reader = $service->reader($bytes, ',', ['mode' => 'legacy_convert']);
    $service->configure($reader, ',', ['mode' => 'legacy_convert']);
    $service->validate($reader);
    $reader->setHeaderOffset(0);
    $row = iterator_to_array($reader->getRecords(), false)[0];
    expect($row['label'])->toBe('He said "0"');
    expect($row['path'])->toBe('C:\\Tools\\Valve');
    expect($row['slashes'])->toBe('two\\\\"quotes');
});
