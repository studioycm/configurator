<?php

use App\Actions\ImportLegacyCanonicalLibrary;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function legacyLibrarySource(array $options = []): string
{
    Storage::fake('local');
    Storage::disk('local')->put('library.json', json_encode([
        'format' => 'legacy-canonical-library-v1',
        'attributes' => [
            ['id' => 10, 'key' => 'body_material', 'label' => 'Body material'],
            ['id' => 20, 'key' => 'seal_material', 'label' => 'Seal material'],
        ],
        'options' => $options ?: [
            ['id' => 100, 'attribute_id' => 10, 'label' => 'Same label', 'code' => 'Aa'],
            ['id' => 200, 'attribute_id' => 20, 'label' => 'Same label', 'code' => 'AA'],
        ],
    ], JSON_THROW_ON_ERROR));

    return Storage::disk('local')->path('library.json');
}

test('legacy library dry run is read only and exact repeat preserves separate Value identities', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = legacyLibrarySource();
    $import = app(ImportLegacyCanonicalLibrary::class);
    $review = $import->handle($actor, $path);
    expect($review['totals'])->toMatchArray(['attributes_created' => 2, 'options_created' => 2, 'values_created' => 2]);
    expect(Attribute::count())->toBe(0);
    expect(Value::count())->toBe(0);
    $applied = $import->handle($actor, $path, true, $review['source_hash']);
    $original = Option::with('value')->orderBy('id')->get()->toArray();
    expect($applied['mode'])->toBe('applied');
    expect(Value::count())->toBe(2);
    expect(Option::pluck('code')->sort()->values()->all())->toBe(['AA', 'Aa']);
    expect($import->handle($actor, $path, true, $review['source_hash'])['totals'])
        ->toMatchArray(['attributes_created' => 0, 'options_created' => 0, 'values_created' => 0, 'options_existing' => 2]);
    expect(Option::with('value')->orderBy('id')->get()->toArray())->toBe($original);
});

test('every member of a duplicated legacy code is held only when explicitly requested', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = legacyLibrarySource([
        ['id' => 100, 'attribute_id' => 10, 'label' => 'Steel', 'code' => 'S6'],
        ['id' => 200, 'attribute_id' => 20, 'label' => 'Steel', 'code' => 'S6'],
        ['id' => 300, 'attribute_id' => 10, 'label' => 'Iron', 'code' => 'DI'],
    ]);
    $import = app(ImportLegacyCanonicalLibrary::class);
    expect(fn () => $import->handle($actor, $path, true, hash_file('sha256', $path)))
        ->toThrow(ValidationException::class, 'S6');
    expect(Attribute::count())->toBe(0);
    $result = $import->handle($actor, $path, true, hash_file('sha256', $path), true);
    expect($result['pending_options'])->toHaveCount(2);
    expect($result['totals'])->toMatchArray(['attributes_created' => 2, 'options_created' => 1, 'options_pending' => 2]);
    expect(Option::sole()->code)->toBe('DI');
    expect(Value::count())->toBe(1);
});

test('legacy import rejects hash drift and unauthorized actors before writing', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = legacyLibrarySource();
    $import = app(ImportLegacyCanonicalLibrary::class);
    expect(fn () => $import->handle($actor, $path, true, str_repeat('0', 64)))->toThrow(ValidationException::class, 'SHA-256');
    expect(fn () => $import->handle(User::factory()->create(), $path))->toThrow(AuthorizationException::class);
    expect(Attribute::count())->toBe(0);
});

test('legacy code collisions with an existing meaning reject the entire import', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $existing = Option::factory()->create(['code' => 'AA']);
    $path = legacyLibrarySource();
    expect(fn () => app(ImportLegacyCanonicalLibrary::class)->handle($actor, $path, true, hash_file('sha256', $path)))
        ->toThrow(ValidationException::class, 'different Attribute or Value');
    expect(Option::sole()->id)->toBe($existing->id);
    expect(Attribute::count())->toBe(1);
    expect(Value::count())->toBe(1);
});

test('legacy library rejects options referencing a missing source Attribute', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = legacyLibrarySource([['id' => 100, 'attribute_id' => 99, 'label' => 'Steel', 'code' => 'S6']]);
    expect(fn () => app(ImportLegacyCanonicalLibrary::class)->handle($actor, $path, true, hash_file('sha256', $path)))
        ->toThrow(ValidationException::class);
    expect(Attribute::count())->toBe(0);
});

test('legacy library command defaults to dry run and requires the reviewed hash to apply', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = legacyLibrarySource();
    $this->artisan('catalog:import-legacy-library', ['source' => $path, '--actor' => $actor->id])
        ->expectsOutputToContain('dry-run')->assertSuccessful();
    expect(Attribute::count())->toBe(0);
    $this->artisan('catalog:import-legacy-library', ['source' => $path, '--actor' => $actor->id, '--apply' => true])
        ->expectsOutputToContain('SHA-256')->assertFailed();
    expect(Attribute::count())->toBe(0);
});

test('a failed Option write rolls back every Attribute and Value in the legacy import', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = legacyLibrarySource();
    Option::creating(function (Option $option): void {
        if ($option->code === 'AA') {
            throw new RuntimeException('Simulated failure');
        }
    });
    try {
        expect(fn () => app(ImportLegacyCanonicalLibrary::class)->handle($actor, $path, true, hash_file('sha256', $path)))
            ->toThrow(RuntimeException::class, 'Simulated failure');
    } finally {
        Option::flushEventListeners();
    }
    expect(Attribute::count())->toBe(0);
    expect(Option::count())->toBe(0);
    expect(Value::count())->toBe(0);
});

test('legacy import preserves a locally edited Attribute instead of overwriting its label', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $attribute = Attribute::factory()->create(['key' => 'body_material', 'label' => 'Reviewed body material']);
    $path = legacyLibrarySource();
    expect(fn () => app(ImportLegacyCanonicalLibrary::class)->handle($actor, $path, true, hash_file('sha256', $path)))
        ->toThrow(ValidationException::class, 'differs from the legacy definition');
    expect($attribute->fresh()->label)->toBe('Reviewed body material');
    expect(Option::count())->toBe(0);
});
