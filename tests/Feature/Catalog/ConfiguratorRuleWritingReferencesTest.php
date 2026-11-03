<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorRuleWritingReferences;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new LogicException('Writing reference projection proof is SQLite in-memory only.');
}

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    expect(DB::getDriverName())->toBe('sqlite')->and(config('database.connections.sqlite.database'))->toBe(':memory:');
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    config(['configurator-writing.enabled' => true]);
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
    [$this->owner, $graph] = canonicalDefinitionFixture();
    $graph['attributes'][0]['label_override'] = '0';
    $graph['attributes'][0]['options'][0]['label_override'] = '0';
    $graph['rules'] = [fixtureAdvanced('property', [[...fixtureCondition('property'), 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Connection Size.מידה', 'operand' => 'Flange', 'option_ids' => []]], 'B')];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $this->owner, $graph);
    $this->referencesUrl = "/admin/configurators/{$this->owner->id}/writing/operations/references";
});

function requestWritingReferences(object $test, string $body = '{}'): TestResponse
{
    return $test->call('POST', $test->referencesUrl, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body);
}

test('references project only included identities and non-null effective labels without creating state', function () {
    $outside = Attribute::factory()->create(['key' => 'outside_only']);
    $before = [ConfiguratorRuleWritingDraft::count(), CatalogContextSettings::count(), DB::table('products')->count()];
    $response = requestWritingReferences($this)->assertOk()->assertJsonPath('ok', true)->assertJsonPath('data.configurator_id', (string) $this->owner->id);
    $data = $response->json('data');
    expect($data['attributes'])->toHaveCount(3);
    expect($data['attributes'][0]['label'])->toBe('0')->and($data['attributes'][0]['values'][0]['label'])->toBe('0');
    expect(array_column($data['attributes'], 'key'))->not->toContain($outside->key);
    expect($data['attributes'][0]['configurator_attribute_id'])->toBeString()->and($data['attributes'][0]['values'][0]['option_id'])->toBeString();
    expect(array_column($data['product_fields'], 'key'))->toContain('Connection Size.מידה');
    expect($data['product_fields'][0])->toHaveKeys(['key', 'label'])->not->toHaveKey('value');
    expect([ConfiguratorRuleWritingDraft::count(), CatalogContextSettings::count(), DB::table('products')->count()])->toBe($before);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('references fingerprint excludes its read timestamp and retains supported enum identities', function () {
    $service = app(ConfiguratorRuleWritingReferences::class);
    $a = $service->get($this->actor, $this->owner);
    $this->travel(1)->seconds();
    $b = $service->get($this->actor, $this->owner);
    expect($a['fingerprint'])->toBe($b['fingerprint'])->and($a['read_at'])->not->toBe($b['read_at']);
    expect(array_column($a['presentation']['sources'], 'value'))->toBe(['SelectionOption', 'ProductProperty']);
    expect(array_column($a['presentation']['effects'], 'value'))->toContain('SetHint', 'SetLabel', 'ExcludeOptions', 'HideAttribute');
    expect(array_column($a['presentation']['operators'], 'token'))->toContain('not-in', 'contains');
});

test('references reject ambient transactions instead of using a caller snapshot', function () {
    DB::beginTransaction();
    try {
        expect(fn () => app(ConfiguratorRuleWritingReferences::class)->get($this->actor, $this->owner))->toThrow(LogicException::class);
    } finally {
        DB::rollBack();
    }
});

test('references authorize the service before any projection query', function () {
    $reader = User::factory()->create(['email' => 'reader@example.test']);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        expect(fn () => app(ConfiguratorRuleWritingReferences::class)->get($reader, $this->owner))->toThrow(AuthorizationException::class);
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
});

test('references require an empty JSON object and cannot accept a different owner', function (string $body, int $status) {
    requestWritingReferences($this, $body)->assertStatus($status);
})->with([['[]', 400], ['{"configurator_id":"999"}', 422], ['{"source":"/hide @A"}', 422]]);

test('disabled or unauthorized references are refused before accessing a missing draft table', function () {
    Schema::drop('configurator_rule_writing_drafts');
    DB::enableQueryLog();
    DB::flushQueryLog();
    config(['configurator-writing.enabled' => false]);
    requestWritingReferences($this)->assertNotFound();
    config(['configurator-writing.enabled' => true]);
    $this->actingAs(User::factory()->create(['email' => 'reader@example.test']));
    requestWritingReferences($this)->assertForbidden();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(array_filter($queries, fn (array $query): bool => preg_match('~\b(?:from|join|update|into)\s+[`"]?(configurator_attributes|configurator_options|rule_conditions|groups|products|options|values|attributes)\b~', strtolower($query['query'])) === 1))->toBe([]);
});

test('reference identities and inactive flags survive identical labels and exclude foreign Options', function () {
    $included = $this->owner->attributes()->with('options')->orderBy('display_order')->get();
    DB::table('attributes')->whereIn('id', $included->pluck('attribute_id'))->update(['label' => 'Same label']);
    DB::table('values')->update(['label' => 'Same label']);
    DB::table('attributes')->where('id', $included[1]->attribute_id)->update(['is_active' => false]);
    DB::table('options')->where('id', $included[0]->options[0]->option_id)->update(['is_active' => false]);
    $foreign = Option::factory()->for(Attribute::factory()->create(['key' => 'foreign_option_parent']))->create();
    $data = requestWritingReferences($this)->assertOk()->json('data');
    foreach ($included as $index => $attribute) {
        $row = $data['attributes'][$index];
        expect($row['configurator_attribute_id'])->toBe((string) $attribute->id)->and($row['attribute_id'])->toBe((string) $attribute->attribute_id);
        foreach ($attribute->options->sortBy('display_order')->values() as $i => $option) {
            expect($row['values'][$i]['configurator_option_id'])->toBe((string) $option->id)
                ->and($row['values'][$i]['option_id'])->toBe((string) $option->option_id)
                ->and($row['values'][$i]['semantic_id'])->toBe($option->option->value->semantic_id);
        }
    }
    expect($data['attributes'][1]['active'])->toBeFalse()->and($data['attributes'][0]['values'][0]['active'])->toBeFalse();
    expect(collect($data['attributes'])->pluck('values')->flatten(1)->pluck('option_id')->all())->not->toContain((string) $foreign->id);
});

test('Product field references expose exact owner keys and labels without private values or foreign keys', function () {
    $ownKey = 'Owner Size.מידה "quoted"';
    $foreignKey = 'Foreign key.שדה';
    $group = Group::factory()->create(['configurator_id' => $this->owner->id]);
    Product::factory()->create(['group_id' => $group->id, 'profile_fields' => [$ownKey => 'PRIVATE_OWNER_SENTINEL']]);
    $other = Configurator::factory()->create();
    $foreignGroup = Group::factory()->create(['configurator_id' => $other->id]);
    Product::factory()->create(['group_id' => $foreignGroup->id, 'profile_fields' => [$foreignKey => 'PRIVATE_FOREIGN_SENTINEL']]);
    $response = requestWritingReferences($this)->assertOk();
    $fields = $response->json('data.product_fields');
    expect(array_column($fields, 'key'))->toContain($ownKey, 'Connection Size.מידה')->not->toContain($foreignKey);
    expect(collect($fields)->firstWhere('key', $ownKey))->toBe(['key' => $ownKey, 'label' => $ownKey]);
    foreach ($fields as $field) {
        expect(array_keys($field))->toBe(['key', 'label']);
    }
    expect($response->getContent())->not->toContain('PRIVATE_OWNER_SENTINEL', 'PRIVATE_FOREIGN_SENTINEL', $foreignKey);
});

test('mapping behavior presentation translates labels while preserving backed identities and tokens', function () {
    app('translator')->addLines(['configurator.copy.disable' => 'תרגום ביטול', 'configurator.copy.hide' => 'תרגום הסתרה'], 'en');
    $behaviors = requestWritingReferences($this)->assertOk()->json('data.presentation.mapping_behaviors');
    expect(array_column($behaviors, 'value'))->toBe(['Disable', 'Hide'])
        ->and(array_column($behaviors, 'token'))->toBe(['/other disable', '/other hide'])
        ->and(array_column($behaviors, 'label'))->toBe(['תרגום ביטול', 'תרגום הסתרה']);
});

test('references reject an absent owner without projecting another Configurator', function () {
    $this->referencesUrl = '/admin/configurators/999999999/writing/operations/references';
    requestWritingReferences($this)->assertNotFound();
});
