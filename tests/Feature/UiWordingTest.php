<?php

use App\Actions\BatchCatalogChanges;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveGroupSettings;
use App\Filament\Pages\AdminAppearance;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Livewire\Catalog\ConfiguratorGroups;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use App\Models\Value;
use App\Services\CatalogDiscovery;
use App\Services\CatalogPolicy;
use App\Services\ItemLists;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    foreach (['admin.appearance.title', 'catalog.frontend.messages', 'configurator.groups.assignment_counts', 'ui.choices.all'] as $key) {
        __($key);
    }
});

test('appearance dialog fields use explicit centralized labels', function () {
    app('translator')->addLines([
        'admin.appearance.fields.modal_width' => 'MODAL_LABEL_SENTINEL',
        'admin.appearance.fields.slide_over_width' => 'DRAWER_LABEL_SENTINEL',
    ], 'en');
    Livewire::test(AdminAppearance::class)
        ->assertSee('MODAL_LABEL_SENTINEL')->assertSee('DRAWER_LABEL_SENTINEL');
});

test('group and batch maximum results share the translated All option without changing its saved key', function () {
    app('translator')->addLines(['ui.choices.all' => 'ALL_LABEL_SENTINEL'], 'en');
    expect(app(BatchCatalogChanges::class)->fields('groups')['max_results']['options']['all'])->toBe('ALL_LABEL_SENTINEL');
    Livewire::test(EditGroup::class, ['record' => Group::factory()->create()->getRouteKey()])
        ->assertSee('ALL_LABEL_SENTINEL');
});

test('batch replacement translates transformer failures at the validation boundary', function (array $intent, string $key) {
    app('translator')->addLines(['admin.batch.validation.'.$key => 'PATTERN_ERROR_SENTINEL'], 'en');
    $record = Value::factory()->create();
    try {
        app(BatchCatalogChanges::class)->preview(auth()->user(), 'values', [$record->id], [
            'operation' => 'replace', 'field' => 'label', 'mode' => 'regex', 'search' => '(', ...$intent,
        ]);
        $this->fail('An invalid replacement must block the batch preview.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['search' => ['PATTERN_ERROR_SENTINEL']]);
    }
})->with([
    'invalid pattern' => [[], 'pattern_could_not_run'],
    'invalid options' => [['flags' => 'e'], 'choose_supported_replacement_options'],
]);

test('catalog markup supplies escaped client translations and independent initialization recovery text', function () {
    $message = 'Reload "quoted" $& <script> עברית';
    app('translator')->addLines(['catalog.frontend.messages.initialization_failed' => $message], 'en');
    $group = Group::factory()->create();
    $html = $this->get(route('catalog.groups.show', $group))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[@data-catalog-filters]')->item(0);
    $labels = json_decode($root->getAttribute('data-catalog-labels'), true, flags: JSON_THROW_ON_ERROR);
    expect($labels)->toBe(__('catalog.frontend.messages'))
        ->and($xpath->query('//*[@data-catalog-recovery]')->item(0)->textContent)->toBe($message);
    foreach ($labels as $label) {
        expect($label)->toBeString()->not->toBeEmpty();
    }
});

test('retained server discovery uses the same localized notices as the browser engine', function () {
    app('translator')->addLines([
        'catalog.frontend.messages.field_cleared' => 'FIELD :field',
        'catalog.frontend.messages.preset_controls' => 'CONTROLS :field',
        'catalog.frontend.messages.preset_cleared' => 'PRESET :preset',
    ], 'en');
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Cost $&']);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Connection_Type', 'label' => 'Connection']);
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '25', 'Connection_Type' => 'Flange']]);
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '16', 'Connection_Type' => 'Threaded']]);
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'allowed_values' => ['25'], 'label' => 'Preset $&']);
    $service = app(CatalogDiscovery::class);
    $initial = $service->prepare($group->id, [], 'filter', ['Working_Pressure', '16']);
    $controlled = $service->prepare($group->id, $initial->state->toArray(), 'subgroup', $preset->id);
    expect($controlled->notices)->toContain('CONTROLS Cost $&');
    $cleared = $service->prepare($group->id, $controlled->state->toArray(), 'filter', ['Connection_Type', 'Threaded']);
    expect($cleared->notices)->toContain('PRESET Preset $&');
    $conflict = $service->prepare($group->id, $initial->state->toArray(), 'filter', ['Connection_Type', 'Flange']);
    expect($conflict->notices)->toContain('FIELD Cost $&');
});

test('item previews use the singular remainder without adding another count query', function () {
    $group = Group::factory()->create();
    Product::factory()->count(6)->for($group)->create();
    $lines = app(ItemLists::class)->preview(auth()->user(), 'group-products', $group->id);
    expect($lines)->toHaveCount(6)->and($lines[5])->toBe('1 more item — open list');
});

test('group settings translate ownership stale value and page size validation', function (string $failure, string $key, string $field) {
    $group = Group::factory()->create();
    $input = ['filters' => [], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS];
    $row = ['property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => []];
    if ($failure === 'ownership') {
        $row['id'] = GroupFilter::factory()->create()->id;
        $input['filters'] = [$row];
    } elseif ($failure === 'stale') {
        $row['values'] = [['value' => 'missing', 'label' => null]];
        $input['filters'] = [$row];
    } else {
        $input['result_settings'] = ['allow_page_size_change' => true, 'default_page_size' => 10, 'page_size_options' => [1]];
    }
    app('translator')->addLines(['catalog.groups.validation.'.$key => 'GROUP_ERROR_SENTINEL'], 'en');
    try {
        app(SaveGroupSettings::class)->handle(auth()->user(), $group, $input);
        $this->fail('The invalid draft must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([$field => ['GROUP_ERROR_SENTINEL']]);
    }
})->with([
    ['ownership', 'row_not_owned', 'settings.filters.0.id'],
    ['stale', 'choose_current_distinct_values', 'settings.filters.0.values'],
    ['page size', 'include_default_page_size', 'settings.result_settings.page_size_options'],
]);

test('group product links and availability use localized singular counts', function () {
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    Product::factory()->for($group)->create();
    Group::factory()->create();
    app('translator')->addLines([
        'configurator.groups.product_count' => '{1} ONE_PRODUCT|[0,*] MANY_PRODUCTS',
        'configurator.groups.available_count' => '{1} ONE_GROUP|[0,*] MANY_GROUPS',
    ], 'en');
    $page = Livewire::test(ConfiguratorGroups::class, ['configuratorId' => $configurator->id]);
    $page->assertSee('Groups: 1 · Products: 1')->assertSee('ONE_PRODUCT')->assertSee('ONE_GROUP')->assertDontSee('{1}')->assertDontSee('[0,*]');
});

test('mapping editors bind translated summaries and literal accessible labels while rule summaries pluralize', function () {
    require_once __DIR__.'/../ConfiguratorFixtures.php';
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $rule = $configurator->rules()->sole();
    expect($rule->workspaceSummary())->toContain('1 set ·')->not->toContain('1 sets');
    app('translator')->addLines(['configurator.mapping.summary' => 'SUMMARY :sets / :memberships'], 'en');
    $manager = Livewire::test(RulesRelationManager::class, [
        'ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class,
    ])->call('selectRule', $rule->id);
    $document = new DOMDocument;
    @$document->loadHTML($manager->html());
    $xpath = new DOMXPath($document);
    $checkboxes = $xpath->query('//input[@type="checkbox"]/@*[name()=":aria-label"]');
    expect($checkboxes->length)->toBeGreaterThan(0);
    foreach ($checkboxes as $attribute) {
        expect($attribute->value)->toStartWith('uiInterpolate(')->toContain('label: label');
    }
    $manager->assertSee('SUMMARY')->assertSee('Edit rule')
        ->call('closeEditor')->call('createRule', 'Mapping')->assertSee('New mapping rule')
        ->call('closeEditor')->call('createRule', 'Advanced')->assertSee('New advanced rule');
});

test('preview diagnostics use translated status and count labels without changing rule kinds', function () {
    require_once __DIR__.'/../ConfiguratorFixtures.php';
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    app('translator')->addLines([
        'configurator.preview.rule_details' => 'RULE_TRANSLATED :kind :status :priority',
        'configurator.preview.hidden_and_disabled' => 'STATE_TRANSLATED :hidden :disabled',
        'configurator.preview.legal_values' => '{1} ONE_LEGAL_VALUE|[0,*] MANY_LEGAL_VALUES',
    ], 'en');
    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('chooseProduct', $product->id)->call('toggleTrace')
        ->assertSee('RULE_TRANSLATED Mapping Enabled 0')->assertSee('STATE_TRANSLATED')
        ->assertSee('ONE_LEGAL_VALUE')->assertSee('MANY_LEGAL_VALUES');
    expect($configurator->rules()->sole()->kind)->toBe('Mapping');
});
