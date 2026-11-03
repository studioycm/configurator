<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\Pages\WriteConfiguratorRules;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Models\AdminAppearanceSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(fn () => config(['app.key' => 'base64:'.base64_encode(str_repeat('w', 32)), 'configurator-writing.enabled' => true]));

test('writing is a separate native page and GET creates no authoring draft', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $owner = Configurator::factory()->create();
    $this->get("/admin/configurators/{$owner->id}/writing")
        ->assertOk()->assertSee('Writing')->assertSee('Resulting data')->assertSee('Back to Rules');
    expect(ConfiguratorRuleWritingDraft::count())->toBe(0);
});

test('writing page refuses an authenticated non admin', function () {
    $this->actingAs(User::factory()->create(['email' => 'reader@example.test']));
    $owner = Configurator::factory()->create();
    $this->get("/admin/configurators/{$owner->id}/writing")->assertForbidden();
});

test('disabled Writing refuses stale requests without an installed draft table and keeps normal actions', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    [$owner, $graph] = canonicalDefinitionFixture();
    $graph['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $graph);
    config(['configurator-writing.enabled' => false]);
    Schema::drop('configurator_rule_writing_drafts');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->get("/admin/configurators/{$owner->id}/writing")->assertNotFound();
    $this->postJson("/admin/configurators/{$owner->id}/writing/operations/draft-summaries", [])->assertNotFound();
    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->assertActionHidden(TestAction::make('writing')->table())
        ->assertActionHidden(TestAction::make('write')->table($owner->rules()->sole()))
        ->assertActionVisible(TestAction::make('edit')->table($owner->rules()->sole()))
        ->assertActionVisible(TestAction::make('importRules')->table());
    expect(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'configurator_rule_writing_drafts')))->toBe([]);
});

test('an already mounted Writing page rechecks the feature boundary on hydration', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $owner = Configurator::factory()->create();
    $page = Livewire::test(WriteConfiguratorRules::class, ['record' => $owner->id])->assertOk();
    config(['configurator-writing.enabled' => false]);

    $page->call('$refresh')->assertForbidden();
});

test('Writing inherits cold appearance recovery and existing shell hooks without storing repairs', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $owner = Configurator::factory()->create();
    $appearance = AdminAppearanceSettings::findOrFail(1);
    $settings = $appearance->settings;
    $appearance->update(['settings' => [...$settings, 'sidebar_width' => 999, 'control_height' => 34]]);
    $before = $appearance->fresh()->getRawOriginal();

    $this->get("/admin/configurators/{$owner->id}/writing")->assertOk()
        ->assertSee(__('admin.appearance.recovery.notice'))
        ->assertSee(__('admin.appearance.recovery.repair'))
        ->assertSee('adminBrowserProfile', false)
        ->assertSee('--aquestia-shell-sidebar-width', false);

    expect($appearance->fresh()->getRawOriginal())->toBe($before)
        ->and(ConfiguratorRuleWritingDraft::count())->toBe(0);
});

test('Writing profile identities are trusted and the full authoring and data tools render without creating drafts', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $owner = Configurator::factory()->create();
    $page = Livewire::withQueryParams(['user' => 'untrusted-user', 'owner' => '999999'])->test(WriteConfiguratorRules::class, ['record' => $owner->id])
        ->assertOk()->assertSee('Display options')->assertSee('Writing helpers')->assertSee('Choices')->assertSee('What next?')->assertSee('Logic')->assertSee('Learning')->assertSee('Copy complete');
    $view = (new ReflectionMethod(WriteConfiguratorRules::class, 'getViewData'))->invoke($page->instance());
    expect($view['writingConfig']['user'])->toBe((string) $actor->id)->and($view['writingConfig']['owner'])->toBe((string) $owner->id);
    expect(ConfiguratorRuleWritingDraft::count())->toBe(0);
});

test('Writing toolbar renders executable Alpine expressions instead of literal Blade directives', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $owner = Configurator::factory()->create();

    $response = $this->get("/admin/configurators/{$owner->id}/writing")
        ->assertOk()->assertDontSee('@js(', false);

    $html = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    foreach (['commands', 'attributes', 'values', 'products'] as $key) {
        expect($html)->toContain('x-on:click="openChoices(\''.$key.'\')"')
            ->toContain('x-bind:disabled="!!composing || current.pendingText !== null || !context.toolbar[\''.$key.'\']"');
    }
    foreach (['condition', 'group', 'compare', 'join', 'mapping', 'effects'] as $group) {
        expect($html)->toContain('x-bind:disabled="!helperEnabled(\''.$group.'\')"');
    }
    expect(ConfiguratorRuleWritingDraft::count())->toBe(0);
});
