<?php

use App\Actions\SaveCanonicalDefinition;
use App\Actions\SaveConfiguratorDefinition;
use App\Models\CatalogAsset;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Option;
use App\Models\Product;
use App\Models\RuleEffect;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'rules') {
    throw new RuntimeException('Rules Writing MySQL tests require the dedicated rules profile.');
}

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

function writingFixture(): array
{
    [$owner, $graph] = canonicalDefinitionFixture();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $graph['rules'] = [fixtureMapping(), [...fixtureAdvanced('advanced', [fixtureCondition('when', 'A')], 'C'), 'priority' => 1]];
    app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $graph);

    return [$actor, $owner, app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh())];
}

function writingRaw(): array
{
    $tables = ['configurators', 'configurator_attributes', 'configurator_options', 'configurator_rules', 'rule_condition_groups', 'rule_conditions', 'rule_condition_options', 'rule_effects', 'rule_effect_options', 'mapping_sets', 'mapping_set_sources', 'mapping_set_targets', 'groups'];

    return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
}

test('Writing changes preserve Group attributes settings tags and Product properties and image pointers', function () {
    expect(DB::getDriverName())->toBe('sqlite');
    [$actor, $owner, $graph] = writingFixture();
    $images = [];
    foreach (['group.main_image', 'product.main_image'] as $target) {
        $sourceId = (string) Str::ulid();
        $entry = ['entry_id' => hash('sha256', $target), 'sha256' => hash('sha256', $target.' bytes'), 'bytes' => 100, 'width' => 40, 'height' => 20, 'mime_type' => 'image/png'];
        $asset = CatalogAsset::factory()->create(['sha256' => $entry['sha256'], 'source' => ['target_key' => $target, 'source_id' => $sourceId, 'scan_version' => 1, 'entry' => $entry]]);
        $media = Media::query()->create(['model_type' => $asset->getMorphClass(), 'model_id' => $asset->id,
            'collection_name' => 'original', 'name' => 'Writing SQL sentinel', 'file_name' => 'synthetic.png', 'mime_type' => 'image/png',
            'disk' => 'catalog-assets', 'conversions_disk' => 'catalog-assets', 'size' => 100, 'manipulations' => [],
            'custom_properties' => ['asset_id' => $asset->id, 'sha256' => $asset->sha256, 'preparation_token' => (string) Str::ulid(), 'source_id' => $sourceId, 'scan_version' => 1, 'entry_id' => $entry['entry_id']],
            'generated_conversions' => [], 'responsive_images' => []]);
        $asset->forceFill(['preparation_status' => 'ready', 'prepared_at' => now(), 'original_media_id' => $media->id,
            'verified_metadata' => [...$entry, 'source_id' => $sourceId, 'scan_version' => 1]])->save();
        $images[$target] = app(CatalogAssetAssignments::class)->validatedAsset($target, $asset->id)->id;
    }
    $group = Group::factory()->create([
        'configurator_id' => $owner->id,
        'main_image_id' => $images['group.main_image'],
        'catalog_attributes' => ['application' => ['water', '0', '001']],
        'result_settings' => ['future_exact' => ['0', '001', false]],
    ]);
    $product = Product::factory()->for($group)->create(['main_image_id' => $images['product.main_image'], 'profile_fields' => ['Connection Size' => '001', 'pressure' => '0']]);
    $tag = GroupTagValue::factory()->create();
    $group->tagValues()->attach($tag, ['position' => 3]);
    $beforeGroup = $group->fresh()->getRawOriginal();
    $beforeProduct = $product->fresh()->getRawOriginal();
    expect($beforeGroup['main_image_id'])->not->toBeNull()
        ->and($beforeProduct['main_image_id'])->not->toBeNull()
        ->and($beforeProduct['main_image_id'])->not->toBe($beforeGroup['main_image_id']);
    $tags = DB::table('group_tag_value')->where('group_id', $group->id)->get()->toJson();
    $assetRows = DB::table('catalog_assets')->whereIn('id', $images)->orderBy('id')->get()->toJson();
    $mediaRows = DB::table('media')->whereIn('model_id', $images)->where('model_type', (new CatalogAsset)->getMorphClass())->orderBy('id')->get()->toJson();
    $rule = $graph['rules'][0];

    app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, [...$rule, 'label' => 'Writing-only rename'], []);

    expect($group->fresh()->getRawOriginal())->toBe($beforeGroup)
        ->and($product->fresh()->getRawOriginal())->toBe($beforeProduct)
        ->and(DB::table('group_tag_value')->where('group_id', $group->id)->get()->toJson())->toBe($tags)
        ->and(DB::table('catalog_assets')->whereIn('id', $images)->orderBy('id')->get()->toJson())->toBe($assetRows)
        ->and(DB::table('media')->whereIn('model_id', $images)->where('model_type', (new CatalogAsset)->getMorphClass())->orderBy('id')->get()->toJson())->toBe($mediaRows);
});

test('both rule kinds roundtrip generated writing and no-op saves preserve raw records', function () {
    [$actor, $owner, $graph] = writingFixture();
    $loader = app(ConfiguratorDefinitionLoader::class);
    [$attributes, $options] = $loader->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    foreach ($graph['rules'] as $rule) {
        $opened = $codec->open($rule, $dictionary);
        expect($opened['unsupported'])->toBe([]);
        $edited = $codec->edit($opened['source'], $opened, $dictionary);
        expect($edited['diagnostics'])->toBe([])->and($edited['proposed_rule'])->toBe($rule);
        $before = writingRaw();
        $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $edited['proposed_rule'], $edited['reference_bindings']);
        expect($result['operation'])->toBe('unchanged')->and(writingRaw())->toBe($before)
            ->and($result['identities']['rules'][(string) $rule['id']])->toBe((int) $rule['id']);
    }
});

test('new and updated rules return actual child identities without changing unselected rows', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][0];
    $rule['label'] = 'Changed';
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][0], $rule, []);
    expect($result['saved_rule']['label'])->toBe('Changed')->and($result['rule_id'])->toBe((int) $rule['id']);
    $new = $graph['rules'][1];
    $new['id'] = 'new:created';
    $new['conditions'] = [];
    $new['effects'][0]['id'] = 'new:effect';
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, null, $new, []);
    expect($result['identities']['rules']['new:created'])->toBe($result['rule_id'])
        ->and($result['identities']['effects']['new:effect'])->toBe((int) $result['saved_rule']['effects'][0]['id'])
        ->and(count(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh())['rules']))->toBe(3);
});

test('stale child changes reject while fresh activation and priority remain outside Writing ownership', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][0];
    DB::table('configurator_rules')->where('id', $rule['id'])->update(['is_active' => false, 'priority' => 9]);
    $proposal = [...$rule, 'label' => 'Renamed'];
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $proposal, []);
    expect($result['saved_rule']['is_active'])->toBeFalse()->and($result['saved_rule']['priority'])->toBe(9);
    $before = writingRaw();
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $proposal, []))->toThrow(ValidationException::class);
    expect(writingRaw())->toBe($before);
});

test('explicit actor denial and outer rollback leave every raw row unchanged', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][0];
    $before = writingRaw();
    $outsider = User::factory()->create();
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($outsider, $owner, $rule, [...$rule, 'label' => 'Denied'], []))->toThrow(AuthorizationException::class);
    DB::beginTransaction();
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, [...$rule, 'label' => 'Provisional'], []);
    expect($result['saved_rule']['label'])->toBe('Provisional');
    DB::rollBack();
    expect(writingRaw())->toBe($before);
});

test('codec refuses scope data that its generated syntax would change', function () {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $rule = $graph['rules'][1];
    $rule['effects'][0]['kind'] = 'HideOptions';
    $rule['effects'][0]['target_scope'] = 'Attribute';
    expect($codec->open($rule, $codec->dictionary($graph, $attributes, $options))['unsupported'])->not->toBeEmpty();
});

test('edited writing retains surviving condition and mapping set identities and metadata', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][0];
    DB::table('mapping_sets')->where('id', $rule['sets'][0]['id'])->update(['label' => '0', 'sort_order' => 17]);
    DB::table('rule_conditions')->where('id', $graph['rules'][1]['conditions'][0]['id'])->update(['sort_order' => 23]);
    $graph = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    $rule = $graph['rules'][0];
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($rule, $dictionary);
    $edited = $codec->edit(str_replace('/other disable', '/other hide', $opened['source']), $opened, $dictionary);
    expect($edited['diagnostics'])->toBe([])->and($edited['proposed_rule']['sets'][0]['id'])->toBe($rule['sets'][0]['id'])
        ->and($edited['proposed_rule']['sets'][0]['label'])->toBe('0')->and($edited['proposed_rule']['sets'][0]['sort_order'])->toBe(17);
    $before = writingRaw();
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $edited['proposed_rule'], $edited['reference_bindings']);
    $after = writingRaw();
    foreach (['configurators', 'configurator_attributes', 'configurator_options', 'rule_effects', 'rule_conditions', 'mapping_set_sources', 'mapping_set_targets'] as $table) {
        expect($after[$table])->toBe($before[$table]);
    }
    expect($result['saved_rule']['sets'][0]['sort_order'])->toBe(17);
});

test('foreign child IDs and whole graph cycles reject before persistence', function () {
    [$actor, $owner, $graph] = writingFixture();
    $otherOwner = app(SaveConfiguratorDefinition::class)->duplicate($actor, $owner, 'Other owner');
    $otherGraph = app(ConfiguratorDefinitionLoader::class)->draft($otherOwner);
    $rule = $graph['rules'][0];
    $before = writingRaw();
    $forged = $rule;
    $forged['sets'][0]['id'] = $otherGraph['rules'][0]['sets'][0]['id'];
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $forged, []))->toThrow(ValidationException::class);
    $cyclic = $graph['rules'][1];
    $cyclic['conditions'][0]['source_configurator_attribute_id'] = $rule['target_configurator_attribute_id'];
    $cyclic['conditions'][0]['option_ids'] = $rule['sets'][0]['target_option_ids'];
    $cyclic['effects'][0]['target_configurator_attribute_id'] = $rule['driver_configurator_attribute_id'];
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][1], $cyclic, []))->toThrow(ValidationException::class);
    expect(writingRaw())->toBe($before);
});

test('all Advanced effect kinds scopes and exact Product text survive generated conversion and edits', function (string $kind, string $scope) {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][1];
    $rule['effects'][0]['kind'] = $kind;
    $rule['effects'][0]['target_scope'] = $scope;
    $rule['effects'][0]['option_ids'] = $scope === 'Options' ? [$graph['attributes'][2]['options'][0]['id']] : [];
    $rule['effects'][0]['display_value'] = str_starts_with($kind, 'Set') ? '0' : null;
    $rule['conditions'][] = ['id' => 'new:property', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'לחץ.size', 'context_dimension' => null, 'operator' => 'In', 'operand' => ['001', '0'], 'option_ids' => []];
    $saved = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][1], $rule, [])['saved_rule'];
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($saved, $dictionary);
    expect($opened['unsupported'])->toBe([]);
    $edited = $codec->edit($opened['source']."\n// preserve metadata", $opened, $dictionary);
    expect($edited['diagnostics'])->toBe([])->and($edited['proposed_rule'])->toEqual($saved);
})->with([
    ['AllowOptions', 'Options'], ['ExcludeOptions', 'Options'], ['HideOptions', 'Options'], ['DisableOptions', 'Options'], ['HideAttribute', 'Attribute'],
    ['SetLabel', 'Attribute'], ['SetLabel', 'Options'], ['SetDisplayValue', 'Attribute'], ['SetDisplayValue', 'Options'], ['SetHint', 'Attribute'], ['SetHint', 'Options'],
]);

test('removed targets stale child changes and late allocation failure do not become creates or partial writes', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][0];
    DB::table('mapping_sets')->where('id', $rule['sets'][0]['id'])->update(['disallowed_target_behavior' => 'Hide']);
    $before = writingRaw();
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $rule, []))->toThrow(ValidationException::class);
    expect(writingRaw())->toBe($before);
    $new = $graph['rules'][1];
    $new['id'] = 'new:failing';
    $new['conditions'] = [];
    $new['effects'][0]['id'] = 'new:failingEffect';
    RuleEffect::creating(function () {
        throw new RuntimeException('Injected allocation failure');
    });
    try {
        expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, null, $new, []))->toThrow(RuntimeException::class, 'Injected allocation failure');
        expect(writingRaw())->toBe($before);
    } finally {
        RuleEffect::flushEventListeners();
    }
    ConfiguratorRule::findOrFail($rule['id'])->delete();
    $before = writingRaw();
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $rule, []))->toThrow(ValidationException::class);
    expect(writingRaw())->toBe($before);
});

test('only references actually bound in the opening writing fence dictionary changes', function () {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($graph['rules'][0], $dictionary);
    $changed = $dictionary;
    $changed['Unrelated'] = [['id' => '9999', 'values' => []]];
    expect($codec->edit($opened['source'], $opened, $changed)['diagnostics'])->toBe([]);
    $driver = 'A';
    $first = array_key_first($dictionary[$driver][0]['values']);
    $changed = $dictionary;
    $changed[$driver][0]['values'][$first] = ['9999'];
    expect($codec->edit($opened['source'], $opened, $changed)['proposed_rule'])->toBeNull();
});

test('group and predicate allocations are authoritative and grouped no-ops retain raw order gaps', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][1];
    $predicate = $rule['conditions'][0];
    $rule['conditions'] = [['id' => 'new:group', 'operator' => 'Any', 'conditions' => [$predicate]]];
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][1], $rule, []);
    $group = $result['saved_rule']['conditions'][0];
    expect($result['identities']['condition_groups']['new:group'])->toBe((int) $group['id'])
        ->and($result['identities']['conditions'][$predicate['id']])->toBe((int) $group['conditions'][0]['id']);
    DB::table('rule_condition_groups')->where('id', $group['id'])->update(['sort_order' => 17]);
    DB::table('rule_conditions')->where('id', $predicate['id'])->update(['sort_order' => 23]);
    $graph = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
    $saved = $graph['rules'][1];
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($saved, $dictionary);
    expect($opened['unsupported'])->toBe([]);
    $edit = $codec->edit($opened['source']."\n// unchanged grouped logic", $opened, $dictionary);
    expect($edit['diagnostics'])->toBe([]);
    $before = writingRaw();
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $saved, $edit['proposed_rule'], $edit['reference_bindings']);
    expect($result['operation'])->toBe('unchanged')->and(writingRaw())->toBe($before);
});

test('surviving effect reorder is rejected and new effects append with allocated identities', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][1];
    $rule['effects'][] = [...$rule['effects'][0], 'id' => 'new:hint', 'kind' => 'SetHint', 'display_value' => '0'];
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][1], $rule, []);
    $saved = $result['saved_rule'];
    expect($result['identities']['effects']['new:hint'])->toBe((int) $saved['effects'][1]['id']);
    $before = writingRaw();
    $reordered = [...$saved, 'effects' => array_reverse($saved['effects'])];
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $saved, $reordered, []))->toThrow(ValidationException::class);
    expect(writingRaw())->toBe($before);
});

test('explicit Mapping case reorder follows source order while preserving each case identity', function () {
    [$actor, $owner, $graph] = writingFixture();
    $rule = $graph['rules'][0];
    $rule['sets'][0]['sort_order'] = 3;
    $rule['sets'][] = [...$rule['sets'][0], 'id' => 'new:secondSet', 'sort_order' => 9, 'source_option_ids' => [$graph['attributes'][0]['options'][1]['id']]];
    $saved = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][0], $rule, [])['saved_rule'];
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($saved, $dictionary);
    $lines = explode("\n", $opened['source']);
    $source = implode("\n", [$lines[0], $lines[3], $lines[4], $lines[1], $lines[2]]);
    $edit = $codec->edit($source, $opened, $dictionary);
    expect($edit['diagnostics'])->toBe([]);
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $saved, $edit['proposed_rule'], $edit['reference_bindings']);
    expect(array_column($result['saved_rule']['sets'], 'id'))->toBe(array_reverse(array_column($saved['sets'], 'id')));
});

test('inserting an identical predicate does not guess which occurrence retains the saved identity', function () {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($graph['rules'][1], $dictionary);
    $lines = explode("\n", $opened['source']);
    array_splice($lines, 1, 0, [$lines[1]]);
    $edited = $codec->edit(implode("\n", $lines), $opened, $dictionary);
    expect($edited['proposed_rule'])->toBeNull()->and($edited['diagnostics'])->not->toBeEmpty();
});

test('locked writer rejects stale accepted-source references including newly introduced Values', function (string $scenario) {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($graph['rules'][0], $dictionary);
    $source = $opened['source'];
    if ($scenario === 'introduced') {
        $targetSemantic = array_keys($dictionary['B'][0]['values']);
        $source = str_replace('#'.$targetSemantic[1], '#'.$targetSemantic[0], $source);
    }
    $edit = $codec->edit($source, $opened, $dictionary);
    expect($edit['diagnostics'])->toBe([]);
    $index = $scenario === 'unused' ? 2 : 1;
    $optionIndex = $scenario === 'introduced' ? 0 : 1;
    $value = Option::findOrFail($graph['attributes'][$index]['options'][$optionIndex]['option_id'])->value;
    app(SaveCanonicalDefinition::class)->handle($actor, $value, ['label' => $value->label, 'semantic_id' => 'renamed_'.$value->id]);
    $before = writingRaw();
    $save = fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $graph['rules'][0], $edit['proposed_rule'], $edit['reference_bindings']);
    if ($scenario === 'unused') {
        expect($save()['operation'])->toBe('unchanged');
    } else {
        expect($save)->toThrow(ValidationException::class);
    }
    expect(writingRaw())->toBe($before);
})->with(['used', 'introduced', 'unused']);

test('source bindings must be structurally valid and the new API requires an explicit binding argument', function () {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $dictionary = app(ConfiguratorRuleWritingCodec::class)->dictionary($graph, $attributes, $options);
    $before = writingRaw();
    $rule = $graph['rules'][0];
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $rule))->toThrow(ArgumentCountError::class);
    foreach ([['statements' => [], 'dictionary' => $dictionary], ['statements' => [['kind' => 'case', 'source_values' => ['x'], 'target_values' => ['y']]], 'dictionary' => $dictionary], ['wrong' => []], ['statements' => [['kind' => 'unknown']], 'dictionary' => $dictionary], ['statements' => [['kind' => 'condition', 'reference' => []]], 'dictionary' => $dictionary]] as $bindings) {
        expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, $rule, $bindings))->toThrow(ValidationException::class);
    }
    expect(writingRaw())->toBe($before);
});

test('a case inserted before existing cases is read back before them with existing identity preserved', function () {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $saved = $graph['rules'][0];
    $opened = $codec->open($saved, $dictionary);
    $a = array_keys($dictionary['A'][0]['values']);
    $b = array_keys($dictionary['B'][0]['values']);
    $insert = '/case #'.$a[1].' -> #'.$b[0]."\n/other hide\n";
    $cut = strpos($opened['source'], "\n") + 1;
    $edit = $codec->edit(substr($opened['source'], 0, $cut).$insert.substr($opened['source'], $cut), $opened, $dictionary);
    expect($edit['diagnostics'])->toBe([]);
    $result = app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $saved, $edit['proposed_rule'], $edit['reference_bindings']);
    expect($result['saved_rule']['sets'][1]['id'])->toBe($saved['sets'][0]['id'])
        ->and($result['saved_rule']['sets'][0]['source_option_ids'])->toBe([$graph['attributes'][0]['options'][1]['id']]);
});

test('inserting an identical effect declines rather than transfer its saved identity', function () {
    [$actor, $owner, $graph] = writingFixture();
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $opened = $codec->open($graph['rules'][1], $dictionary);
    $lines = explode("\n", $opened['source']);
    $edit = $codec->edit($opened['source']."\n".end($lines), $opened, $dictionary);
    expect($edit['proposed_rule'])->toBeNull()->and($edit['diagnostics'])->not->toBeEmpty();
});
