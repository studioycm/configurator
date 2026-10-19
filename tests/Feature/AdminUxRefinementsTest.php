<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Filament\Resources\ValueSelectionTable;
use App\Livewire\Catalog\WorkspaceTablePicker;
use App\Models\Configurator;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('row bodies do not open editors and the explicit Edit action remains available', function () {
    $value = Value::factory()->create();
    $page = Livewire::test(ListValues::class);
    expect($page->instance()->getTable()->getRecordAction($value))->toBeNull();
    expect($page->instance()->getTable()->getRecordUrl($value))->toBeNull();
    $page->callAction(TestAction::make('select')->table($value))->assertSet('selectedRecord', (string) $value->id);
    $configurator = Configurator::factory()->create();
    expect(Livewire::test(ListConfigurators::class)->instance()->getTable()->getRecordUrl($configurator))->toBeNull();
});

test('Rules import is a direct toolbar action and rows have no implicit edit action', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $actions = collect($manager->instance()->getTable()->getHeaderActions())->filter(fn ($action): bool => $action instanceof Action)->map->getName()->all();
    expect($actions)->toContain('importRules');
    expect($manager->instance()->getTable()->getRecordAction($configurator->rules()->sole()))->toBeNull();
});

test('Values picker preferences survive remounts without restoring checked Values', function () {
    $value = Value::factory()->create();
    $parameters = ['tableConfiguration' => base64_encode(ValueSelectionTable::class), 'tableArguments' => ['owner_id' => 'create'], 'state' => []];
    $picker = Livewire::test(WorkspaceTablePicker::class, $parameters);
    $picker->call('restoreTableBrowserProfile', ['perPage' => 25, 'search' => $value->label])->assertHasNoErrors();
    $picker->set('state', [(string) $value->id]);
    Livewire::test(WorkspaceTablePicker::class, $parameters)->assertSet('tableRecordsPerPage', 25)->assertSet('tableSearch', $value->label)->assertSet('state', []);
});

test('Selected code rule summaries identify the compared Values', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $condition = [...fixtureCondition('code'), 'source_kind' => 'SelectionCode'];
    $data['rules'] = [fixtureAdvanced('code-summary', [$condition], 'B', 'SetHint', ['new:B1'], 'Hint')];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    expect($configurator->rules()->sole()->workspaceSummary())->toContain('A0');
});

test('the Values picker narrows its rows by tags', function () {
    $metal = Value::factory()->create(['tags' => ['metal']]);
    $polymer = Value::factory()->create(['tags' => ['polymer']]);
    Livewire::test(WorkspaceTablePicker::class, ['tableConfiguration' => base64_encode(ValueSelectionTable::class), 'tableArguments' => ['owner_id' => 'create'], 'state' => []])
        ->filterTable('tags', ['values' => ['metal']])->assertCanSeeTableRecords([$metal])->assertCanNotSeeTableRecords([$polymer]);
});
