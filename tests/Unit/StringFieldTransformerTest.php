<?php

use App\Services\StringFieldTransformer;

test('literal replacements preserve null and treat pattern characters literally', function () {
    $transformer = new StringFieldTransformer;
    $intent = ['mode' => 'literal', 'search' => 'A.', 'replacement' => '$1', 'case_sensitive' => false, 'occurrences' => 'first', 'flags' => ''];
    expect($transformer->replace(null, $intent))->toBeNull()->and($transformer->replace('a. A. Ab', $intent))->toBe('$1 A. Ab');
    $intent['occurrences'] = 'all';
    expect($transformer->replace('a. A. Ab', $intent))->toBe('$1 $1 Ab');
});

test('regex supports captures but rejects invalid patterns and unsupported flags', function () {
    $transformer = new StringFieldTransformer;
    $intent = ['mode' => 'regex', 'search' => '(A)([0-9])', 'replacement' => '${2}$1', 'case_sensitive' => true, 'occurrences' => 'all', 'flags' => ''];
    expect($transformer->replace('A1 A2', $intent))->toBe('1A 2A');
    expect(fn () => $transformer->replace('A1', [...$intent, 'search' => '(']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $transformer->replace('A1', [...$intent, 'flags' => 'e']))->toThrow(InvalidArgumentException::class);
});
