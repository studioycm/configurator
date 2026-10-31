<?php

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupAttributes;
use App\Actions\SaveGroupSettings;
use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogPolicy;
use App\Services\CatalogRevisions;
use App\Services\GroupAttributes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

test('Group attributes have one nullable JSON column and six extensible definitions with empty own defaults', function () {
    expect(Schema::hasColumn('groups', 'catalog_attributes'))->toBeTrue();
    $fields = app(GroupAttributes::class);
    expect(array_keys($fields->definitions()))->toBe(['operating_pressure', 'application', 'system_water_flow_rate', 'required_function', 'fluid_type', 'installation_type'])
        ->and($fields->definitions()['fluid_type']['label'])->toBe('Fluid Type');
    $group = Group::factory()->create();
    expect($group->fresh()->getRawOriginal('catalog_attributes'))->toBeNull()->and($fields->document($group))->toBe([])
        ->and($fields->formState($group)['values']['fluid_type'])->toBe([]);
    config(['group_attributes.definitions.future_attribute' => ['label' => 'Future attribute']]);
    expect(array_keys($fields->definitions()))->toContain('future_attribute');
});

test('new values match actual ECMAScript trim while retained stored values bypass authoring normalization', function (string $input, string $expected) {
    $group = Group::factory()->create();
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $save->handle($this->actor, $group, ['application' => [$input]], $fields->fingerprint($group));
    expect($fields->document($group->fresh())['application'])->toBe([$expected]);
})->with([
    [' 0 ', '0'], ["\u{00A0}001\u{00A0}", '001'], ["\u{FEFF}é Multi Word\u{FEFF}", 'é Multi Word'],
    ["\u{200B}0\u{200B}", "\u{200B}0\u{200B}"], ["\u{200C}0\u{200C}", "\u{200C}0\u{200C}"],
    ["\u{200D}0\u{200D}", "\u{200D}0\u{200D}"], ["\u{00AD}0\u{00AD}", "\u{00AD}0\u{00AD}"],
]);

test('exact string lists future keys and omission survive readback and no-op native metadata saves', function () {
    $stored = ['application' => [' 0 ', '001', 'Case', 'case', 'מים Multi Word'], 'fluid_type' => ['Water'], 'future' => ['future fact']];
    $group = Group::factory()->create(['catalog_attributes' => $stored]);
    $fields = app(GroupAttributes::class);
    $before = $group->fresh()->getAttributes();
    app(SaveGroupAttributes::class)->handle($this->actor, $group, ['application' => $stored['application']], $fields->fingerprint($group));
    expect($group->fresh()->getAttributes())->toBe($before)->and($fields->formState($group)['values'])->not->toHaveKey('future');
    app(SaveCatalogGroup::class)->handle($this->actor, $group, ['name' => 'New metadata', 'parent_id' => null, 'configurator_id' => null]);
    expect($fields->document($group->fresh()))->toBe($stored);
    app(SaveGroupSettings::class)->handle($this->actor, $group, ['filters' => [], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS]);
    expect($fields->document($group->fresh()))->toBe($stored);
});

test('explicit clear removes one key and serializes an empty changed document as object without losing other domains', function () {
    $group = Group::factory()->create(['catalog_attributes' => ['application' => ['A'], 'fluid_type' => ['B']], 'result_settings' => ['card_properties' => ['pressure']]]);
    $tag = GroupTagValue::factory()->create();
    $group->tagValues()->attach($tag, ['position' => 0]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['pressure' => '001']]);
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $save->handle($this->actor, $group, ['application' => []], $fields->fingerprint($group));
    expect($fields->document($group->fresh()))->toBe(['fluid_type' => ['B']]);
    $save->handle($this->actor, $group, ['fluid_type' => []], $fields->fingerprint($group->fresh()));
    expect(json_decode($group->fresh()->getRawOriginal('catalog_attributes')))->toBeInstanceOf(stdClass::class)
        ->and($group->fresh()->result_settings)->toBe(['card_properties' => ['pressure']])
        ->and($group->tagValues()->pluck('group_tag_values.id')->all())->toBe([$tag->id])
        ->and($product->fresh()->profile_fields)->toBe(['pressure' => '001']);
});

test('nullable and object empty documents preserve representation on no-op and parent values are not inherited', function () {
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $parent = Group::factory()->create(['catalog_attributes' => ['application' => ['Parent']]]);
    foreach ([null, '{}'] as $raw) {
        $group = Group::factory()->for($parent, 'parent')->create();
        DB::table('groups')->where('id', $group->id)->update(['catalog_attributes' => $raw]);
        $group->refresh();
        $before = $group->getAttributes();
        $save->handle($this->actor, $group, ['application' => []], $fields->fingerprint($group));
        expect($group->fresh()->getAttributes())->toBe($before)->and($fields->document($group->fresh()))->toBe([]);
    }
});

test('invalid document shapes and cells stay read-only and are preserved by ordinary Group updates', function (string $raw) {
    $group = Group::factory()->create();
    DB::table('groups')->where('id', $group->id)->update(['catalog_attributes' => $raw]);
    $group->refresh();
    $fields = app(GroupAttributes::class);
    expect(fn () => $fields->document($group))->toThrow(ValidationException::class)
        ->and($fields->formState($group)['editable'])->toBeFalse();
    app(SaveCatalogGroup::class)->handle($this->actor, $group, ['name' => 'Metadata only', 'parent_id' => null, 'configurator_id' => null]);
    expect($group->fresh()->getRawOriginal('catalog_attributes'))->toBe($raw);
    expect(fn () => app(SaveGroupAttributes::class)->handle($this->actor, $group, ['application' => ['Repair?']], $fields->fingerprint($group->fresh())))->toThrow(ValidationException::class);
})->with(['[]', '"text"', '1', 'true', '{"application":null}', '{"application":[0]}', '{"application":[""]}', '{"application":["x","x"]}', '{"application":[["x"]]}']);

test('typed malformed unregistered duplicate or blank changes reject without altering the owner', function (array $changes) {
    $group = Group::factory()->create();
    $before = $group->fresh()->getAttributes();
    $fields = app(GroupAttributes::class);
    expect(fn () => app(SaveGroupAttributes::class)->handle($this->actor, $group, $changes, $fields->fingerprint($group)))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
})->with([
    [['unknown' => ['x']]], [['application' => null]], [['application' => 'x']], [['application' => [0]]], [['application' => [false]]],
    [['application' => [['x']]]], [['application' => ['']]], [['application' => ["\u{00A0}\u{FEFF}"]]],
    [['application' => [' A ', 'A']]], 'NUL' => [['application' => ["bad\0value"]]], 'invalid UTF8' => [['application' => ["\xff"]]],
]);

test('character list and document bounds reject only out-of-range changes', function () {
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $group = Group::factory()->create();
    $save->handle($this->actor, $group, ['application' => [str_repeat('א', 200)]], $fields->fingerprint($group));
    expect($fields->document($group->fresh())['application'])->toBe([str_repeat('א', 200)]);
    foreach ([['application' => [str_repeat('א', 201)]], ['application' => array_map(fn ($i) => (string) $i, range(0, 25))],
        ['application' => array_map(fn ($i) => str_repeat('界', 198).sprintf('%02d', $i), range(0, 24)), 'fluid_type' => array_map(fn ($i) => str_repeat('界', 198).sprintf('%02d', $i), range(0, 24))]] as $changes) {
        expect(fn () => $save->handle($this->actor, $group, $changes, $fields->fingerprint($group->fresh())))->toThrow(ValidationException::class);
    }
    $save->handle($this->actor, $group, ['fluid_type' => array_map(fn ($i) => (string) $i, range(0, 24))], $fields->fingerprint($group->fresh()));
    expect($fields->document($group->fresh())['fluid_type'])->toHaveCount(25);
});

test('byte limits use standard encoded documents and guard both raw changes and merged facts', function () {
    $group = Group::factory()->create();
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $raw = str_repeat(' ', 33000).'{"application":["0"]}';
    DB::table('groups')->where('id', $group->id)->update(['catalog_attributes' => $raw]);
    $group->refresh();
    $before = $group->getAttributes();
    $save->handle($this->actor, $group, ['application' => ['0']], $fields->fingerprint($group));
    expect($group->fresh()->getAttributes())->toBe($before);

    config(['group_attributes.limits.document_bytes' => 80]);
    expect(fn () => $save->handle($this->actor, $group, ['application' => [str_repeat(' ', 100).'a']], $fields->fingerprint($group)))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
    $save->handle($this->actor, $group, ['application' => [str_repeat('a', 30)]], $fields->fingerprint($group));
    $before = $group->fresh()->getAttributes();
    expect(fn () => $save->handle($this->actor, $group, ['fluid_type' => [str_repeat('b', 30)]], $fields->fingerprint($group->fresh())))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('non-ECMAScript invisibles remain distinct and map order is semantic while value order is significant', function () {
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $group = Group::factory()->create();
    $values = ['0', "\u{200B}0\u{200B}", "\u{200C}0\u{200C}", "\u{200D}0\u{200D}", "\u{00AD}0\u{00AD}"];
    $save->handle($this->actor, $group, ['application' => $values, 'fluid_type' => ['Water']], $fields->fingerprint($group));
    $before = $group->fresh()->getAttributes();
    $fingerprint = $fields->fingerprint($group->fresh());
    $save->handle($this->actor, $group, ['fluid_type' => ['Water'], 'application' => $values], $fingerprint);
    expect($group->fresh()->getAttributes())->toBe($before);
    $save->handle($this->actor, $group, ['application' => array_reverse($values)], $fingerprint);
    expect($fields->document($group->fresh())['application'])->toBe(array_reverse($values));
});

test('CAS is owner scoped and protects the committed winner while normal metadata changes do not change its fingerprint', function () {
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $group = Group::factory()->create();
    $initial = $fields->fingerprint($group);
    $group->update(['description' => 'Other metadata']);
    expect($fields->fingerprint($group->fresh()))->toBe($initial);
    $save->handle($this->actor, $group, ['application' => ['Winner']], $initial);
    expect(fn () => $save->handle($this->actor, $group, ['application' => ['Stale']], $initial))->toThrow(ValidationException::class);
    $other = Group::factory()->create();
    expect(fn () => $save->handle($this->actor, $other, ['application' => ['Foreign hash']], $initial))->toThrow(ValidationException::class)
        ->and($fields->document($group->fresh())['application'])->toBe(['Winner']);
});

test('aggregate validation failure rolls back core edits and coalesced revisions without losing the saved attribute winner', function () {
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $group = Group::factory()->create();
    $save->handle($this->actor, $group, ['application' => ['Winner']], $fields->fingerprint($group));
    $before = $group->fresh()->getAttributes();
    expect(fn () => DB::transaction(fn () => app(CatalogRevisions::class)->batch(function () use ($group, $save, $fields): void {
        app(SaveCatalogGroup::class)->handle($this->actor, $group, ['name' => 'Should rollback', 'parent_id' => null, 'configurator_id' => null]);
        $save->handle($this->actor, $group, ['application' => ['duplicate', ' duplicate ']], $fields->fingerprint($group->fresh()));
    })))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
    DB::transaction(fn () => app(CatalogRevisions::class)->batch(function () use ($group, $save, $fields): void {
        app(SaveCatalogGroup::class)->handle($this->actor, $group, ['name' => 'Saved aggregate', 'parent_id' => null, 'configurator_id' => null]);
        $save->handle($this->actor, $group, ['application' => ['New']], $fields->fingerprint($group->fresh()));
    }));
    expect((int) $group->fresh()->catalog_revision)->toBe((int) $before['catalog_revision'] + 1);
});

test('unauthorized and deleted owners cannot change Group attributes', function () {
    $group = Group::factory()->create();
    $fields = app(GroupAttributes::class);
    $save = app(SaveGroupAttributes::class);
    $fingerprint = $fields->fingerprint($group);
    expect(fn () => $save->handle(User::factory()->create(), $group, ['application' => ['Denied']], $fingerprint))->toThrow(AuthorizationException::class);
    $group->delete();
    expect(fn () => $save->handle($this->actor, $group, ['application' => ['Deleted']], $fingerprint))->toThrow(ModelNotFoundException::class);
});
