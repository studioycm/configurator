<?php

use App\Services\CatalogAssetMatcher;
use App\Services\CatalogAssetRecipe;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

function reviewManifest(array $names): array
{
    return ['entries' => array_map(fn ($name, $id) => ['entry_id' => 'entry-'.$id, 'status' => 'valid', 'name' => basename($name), 'relative_path' => $name], $names, array_keys($names))];
}

test('recipe derives literal dotted keys and complete long paths without numeric coercion', function () {
    $service = app(CatalogAssetRecipe::class);
    $recipe = $service->validate(['field_key' => 'image.ref'], 'folder_matches', 'product');
    $raw = str_repeat('long-path/', 150).'D60EL01.jpg';
    $result = $service->identifier(['profile_fields' => ['image.ref' => $raw, 'image' => ['ref' => 'wrong']]], $recipe);
    expect($result['identifier'])->toBe('D60EL01')->and($result['raw_value'])->toBe($raw)->and($result['raw_truncated'])->toBeTrue();
    expect($service->identifier(['profile_fields' => ['image.ref' => 6]], $recipe)['reason'])->toBe('non_text_reference');
    expect($service->identifier(['profile_fields' => []], $recipe)['reason'])->toBe('reference_missing');
    expect($service->identifier(['profile_fields' => ['image.ref' => '001']], $recipe)['identifier'])->toBe('001');
    expect($service->identifier(['profile_fields' => ['image.ref' => 'https://x/a.jpg?q=1']], $recipe)['identifier'])->toBe('a.jpg?q=1');
});

test('code positions are ordered before formatting and invalid recipes or missing segments are explicit', function () {
    $service = app(CatalogAssetRecipe::class);
    $recipe = $service->validate(['source_field' => 'product_code', 'positions' => [1, 2, 4]], 'folder_matches', 'product');
    expect($service->identifier(['product_code' => 'D60-EL-LP6-01'], $recipe)['identifier'])->toBe('D60EL01');
    expect($service->identifier(['product_code' => 'D60-EL'], $recipe)['reason'])->toBe('missing_segment');
    expect(fn () => $service->validate(['positions' => [1, 1]], 'folder_matches', 'product'))->toThrow(ValidationException::class);
    expect(fn () => $service->validate([], 'folder_matches', 'group'))->toThrow(ValidationException::class);
});

test('exact collisions remain ambiguous and Contains singleton is only a suggestion', function () {
    $r = app(CatalogAssetRecipe::class)->validate(['include_subfolders' => true], 'folder_matches', 'product');
    $m = app(CatalogAssetMatcher::class);
    $index = $m->index(reviewManifest(['D60EL01.jpg', 'sub/d60el01.png', '001.webp']), $r);
    expect($m->match($index, 'D60EL01', $r)['match_status'])->toBe('ambiguous');
    expect($m->match($index, '001', $r)['match_status'])->toBe('ready')->and($m->match($index, '1', $r)['match_status'])->toBe('missing');
    expect($m->match($index, '001', [...$r, 'comparison' => 'contains'])['match_status'])->toBe('suggestion');
    $case = [...$r, 'case_sensitive' => true];
    expect($m->match($m->index(reviewManifest(['D60EL01.jpg', 'd60el01.png']), $case), 'D60EL01', $case)['match_status'])->toBe('ready');
});

test('file wildcards are literal except star and question, exclusion wins, and subfolders are explicit', function () {
    $service = app(CatalogAssetRecipe::class);
    $m = app(CatalogAssetMatcher::class);
    $r = $service->validate(['include_patterns' => 'a[1]+.*;sub/*', 'exclude_patterns' => '*.png', 'include_subfolders' => true], 'folder_matches', 'product');
    $index = $m->index(reviewManifest(['a[1]+.jpg', 'a11.jpg', 'a[1]+.png', 'sub/D60EL01.jpg']), $r);
    expect($m->match($index, 'a[1]+', $r)['candidate_count'])->toBe(1)->and($m->match($index, 'a11', $r)['candidate_count'])->toBe(0);
    $r['include_subfolders'] = false;
    expect($m->match($m->index(reviewManifest(['sub/D60EL01.jpg']), $r), 'D60EL01', $r)['match_status'])->toBe('missing');
});

test('many owners reuse collision references instead of repeating the file matrix', function () {
    $r = app(CatalogAssetRecipe::class)->validate(['include_subfolders' => true], 'folder_matches', 'product');
    $m = app(CatalogAssetMatcher::class);
    $index = $m->index(reviewManifest(array_map(fn ($n) => $n.'/same.jpg', range(1, 2000))), $r);
    $record = ['id' => 1, 'position' => 0, 'fingerprint' => 'valid', 'profile_fields' => ['product_pic' => 'same'], 'main_image_id' => null, 'title' => 'Owner'];
    $rows = $m->reviewChunk(array_map(fn ($id) => [...$record, 'id' => $id], range(1, 5000)), $index, $r, []);
    expect($rows)->toHaveCount(5000)->and($rows[0]['snapshot']['candidate_count'])->toBe(2000);
    expect(strlen(json_encode($rows)))->toBeLessThan(5000000)->and($rows[0]['snapshot']['candidate_set_ref'])->toBe($rows[4999]['snapshot']['candidate_set_ref']);
});

test('an effective empty identifier is invalid for exact and Contains while literal separators and leading zeros remain meaningful', function () {
    $recipes = app(CatalogAssetRecipe::class);
    $matcher = app(CatalogAssetMatcher::class);
    foreach (['exact', 'contains'] as $comparison) {
        $recipe = $recipes->validate(['comparison' => $comparison, 'ignore_separators' => true], 'folder_matches', 'product');
        $index = $matcher->index(reviewManifest(['---.png', '001.png']), $recipe);
        $result = $matcher->match($index, '---', $recipe);
        expect($result['match_status'])->toBe('invalid')->and($result['reason'])->toBe('identifier_empty')->and($result['candidate_count'])->toBe(0)->and($result['chosen_entry_id'])->toBeNull();
        expect(iterator_to_array($matcher->candidateIds($index, '---', $recipe), false))->toBe([]);
        expect($matcher->match($index, '001', $recipe)['match_status'])->toBe($comparison === 'exact' ? 'ready' : 'suggestion');
        $literal = [...$recipe, 'ignore_separators' => false];
        expect($matcher->match($matcher->index(reviewManifest(['---.png']), $literal), '---', $literal)['candidate_count'])->toBe(1);
        $rows = $matcher->reviewChunk([['id' => 1, 'position' => 0, 'fingerprint' => 'valid', 'main_image_id' => null, 'profile_fields' => ['product_pic' => '---']]], $index, $recipe, []);
        expect($rows[0]['match_status'])->toBe('invalid')->and($rows[0]['snapshot']['reason'])->toBe('identifier_empty')->and($rows[0]['snapshot']['chosen_entry'])->toBeNull();
    }
});
