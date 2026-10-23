<?php

use App\Actions\SaveCanonicalDefinition;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorOption;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('V22 legacy subtitle drafts normalize intent in returned definition and survive unchanged saves', function (?string $text, string $mode, string $resolved) {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['attributes'][0]['options'][0]['display_value_override'] = $text;
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $definition = $loader->load($configurator->id);
    $row = $definition->data['attributes'][0]['options'][0];

    expect($row)->toMatchArray(['subtitle_mode' => $mode, 'display_value_override' => $mode === 'none' ? null : $text]);
    expect($definition->attributes[$definition->data['attributes'][0]['id']]->options[$row['id']]->displayValue)->toBe($resolved);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator->fresh(), $definition->data);
    expect(ConfiguratorOption::findOrFail($row['id']))->subtitle_mode->toBe($mode)->display_value_override->toBe($mode === 'none' ? null : $text);
})->with(['null' => [null, 'none', ''], 'empty' => ['', 'none', ''], 'whitespace' => [" \t ", 'none', ''], 'zero' => ['0', 'custom', '0'], 'custom verbatim' => ['  Custom  ', 'custom', '  Custom  ']]);

test('V23 explicit modes preserve live links and independent main names through canonical rename and duplication', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $first = &$data['attributes'][0]['options'][0];
    $first['subtitle_mode'] = 'master';
    $first['display_value_override'] = 'discard this copied text';
    $first['label_override'] = 'Local main';
    $data['attributes'][1]['options'][0]['subtitle_mode'] = 'custom';
    $data['attributes'][1]['options'][0]['display_value_override'] = 'Independent subtitle';
    $shared = Option::with('value')->findOrFail($first['option_id'])->value;
    Option::findOrFail($data['attributes'][1]['options'][0]['option_id'])->update(['value_id' => $shared->id]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $before = $loader->load($configurator->id);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator->fresh(), $before->data);

    app(SaveCanonicalDefinition::class)->handle(auth()->user(), $shared, ['label' => 'Renamed Master', 'semantic_id' => $shared->semantic_id, 'description' => $shared->description, 'tags' => $shared->tags ?? []]);
    $after = $loader->load($configurator->id);
    $local = array_values($after->attributes)[0];
    expect(array_values($local->options)[0])->label->toBe('Local main')->displayValue->toBe('Renamed Master');
    expect(array_values(array_values($after->attributes)[1]->options)[0]->displayValue)->toBe('Independent subtitle');
    expect($after->data['attributes'][0]['options'][0])->toMatchArray(['subtitle_mode' => 'master', 'display_value_override' => null]);
    expect(array_values(array_values($before->attributes)[0]->options)[0]->displayValue)->not->toBe('Renamed Master');
    $copy = app(SaveConfiguratorDefinition::class)->duplicate(auth()->user(), $configurator->fresh(), 'Subtitle copy');
    expect($loader->draft($copy)['attributes'][0]['options'][0])->toMatchArray(['subtitle_mode' => 'master', 'label_override' => 'Local main', 'display_value_override' => null]);
});

test('V24 local patches distinguish omitted subtitle intent from legacy text replacement', function (array $patch, string $mode, ?string $text) {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['attributes'][0]['options'][0] += ['subtitle_mode' => 'master'];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $owner = $configurator->attributes()->orderBy('display_order')->firstOrFail();
    $option = $owner->options()->orderBy('display_order')->firstOrFail();

    app(SaveConfiguratorOption::class)->handle(auth()->user(), $owner, $option->id, $patch);

    expect($option->fresh())->subtitle_mode->toBe($mode)->display_value_override->toBe($text);
})->with(['hint only' => [['hint' => 'New hint'], 'master', null], 'legacy custom' => [['display_value_override' => '0'], 'custom', '0'], 'legacy clear' => [['display_value_override' => " \t "], 'none', null], 'none ignores text' => [['subtitle_mode' => 'none', 'display_value_override' => 'Discard'], 'none', null], 'explicit custom' => [['subtitle_mode' => 'custom', 'display_value_override' => '  Exact  '], 'custom', '  Exact  ']]);

test('V24 invalid explicit subtitle intent fails before any local patch is written', function (array $patch) {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['attributes'][0]['options'][0] = [...$data['attributes'][0]['options'][0], 'subtitle_mode' => 'custom', 'display_value_override' => 'Saved custom text that must not be borrowed'];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $owner = $configurator->attributes()->orderBy('display_order')->firstOrFail();
    $option = $owner->options()->orderBy('display_order')->firstOrFail();
    $before = $option->getAttributes();

    expect(fn () => app(SaveConfiguratorOption::class)->handle(auth()->user(), $owner, $option->id, ['hint' => 'Must roll back', ...$patch]))->toThrow(ValidationException::class);
    expect($option->fresh()->getAttributes())->toBe($before);
})->with(['null mode' => [['subtitle_mode' => null]], 'empty mode' => [['subtitle_mode' => '']], 'unknown mode' => [['subtitle_mode' => 'forged']], 'custom missing text' => [['subtitle_mode' => 'custom']], 'custom blank' => [['subtitle_mode' => 'custom', 'display_value_override' => '  ']], 'custom too long' => [['subtitle_mode' => 'custom', 'display_value_override' => str_repeat('s', 256)]], 'custom wrong type' => [['subtitle_mode' => 'custom', 'display_value_override' => 0]]]);

test('V22 additive upgrade backfills old rows without copying Master names or firing model events', function () {
    $migration = require glob(database_path('migrations/*_add_subtitle_mode_to_configurator_options_table.php'))[0];
    $migration->down();
    try {
        $ids = [];
        foreach ([null, '', " \t ", '0', '  Retained  '] as $index => $text) {
            $owner = ConfiguratorAttribute::factory()->create();
            $option = Option::factory()->create(['attribute_id' => $owner->attribute_id]);
            $ids[] = DB::table('configurator_options')->insertGetId(['configurator_attribute_id' => $owner->id, 'option_id' => $option->id, 'display_order' => 0, 'display_value_override' => $text]);
        }
        Event::fake(['eloquent.updated: '.ConfiguratorOption::class]);
        $migration->up();
        $rows = DB::table('configurator_options')->whereIn('id', $ids)->orderBy('id')->get();

        expect($rows->pluck('subtitle_mode')->all())->toBe(['none', 'none', 'none', 'custom', 'custom']);
        expect($rows->pluck('display_value_override')->all())->toBe([null, null, null, '0', '  Retained  ']);
        Event::assertNotDispatched('eloquent.updated: '.ConfiguratorOption::class);
    } finally {
        if (! Schema::hasColumn('configurator_options', 'subtitle_mode')) {
            $migration->up();
        }
    }
});
