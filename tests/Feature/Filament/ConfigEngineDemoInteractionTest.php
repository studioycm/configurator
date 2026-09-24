<?php

use App\Models\User;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Tests\Fixtures\Legacy\Filament\Pages\ConfigEngineDemo;
use Tests\Fixtures\Legacy\LoadsLegacyFixtures;
use Tests\Fixtures\Legacy\Models\ConfigAttribute;
use Tests\Fixtures\Legacy\Models\ConfigOption;
use Tests\Fixtures\Legacy\Models\ConfigProfile;
use Tests\Fixtures\Legacy\Models\OptionRule;

uses(LoadsLegacyFixtures::class);

it('recalculates dependent options across Livewire updates and context actions', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));

    $profile = ConfigProfile::factory()->create([
        'slug' => 'd60s-p16-03-configurator',
        'is_active' => true,
    ]);

    [$body, $seal] = ConfigAttribute::factory()
        ->for($profile, 'configProfile')
        ->count(2)
        ->sequence(
            ['label' => 'Body', 'slug' => 'body', 'input_type' => 'toggle', 'sort_order' => 1, 'segment_index' => 1],
            ['label' => 'Seal', 'slug' => 'seal', 'input_type' => 'select', 'sort_order' => 2, 'segment_index' => 2],
        )
        ->create(['is_required' => true]);

    [$iron, $steel] = ConfigOption::factory()
        ->for($body, 'attribute')
        ->count(2)
        ->sequence(
            ['label' => 'Ductile Iron', 'code' => 'DI', 'is_default' => true, 'sort_order' => 1],
            ['label' => 'Carbon Steel', 'code' => 'CS', 'is_default' => false, 'sort_order' => 2],
        )
        ->create(['is_active' => true]);

    [$epdm, $viton, $nbr] = ConfigOption::factory()
        ->for($seal, 'attribute')
        ->count(3)
        ->sequence(
            ['label' => 'EPDM', 'code' => 'EP', 'is_default' => true, 'sort_order' => 1],
            ['label' => 'Viton', 'code' => 'VT', 'is_default' => false, 'sort_order' => 2],
            ['label' => 'NBR', 'code' => 'NB', 'is_default' => false, 'sort_order' => 3],
        )
        ->create(['is_active' => true]);

    OptionRule::factory()->create([
        'config_profile_id' => $profile->id,
        'config_option_id' => $steel->id,
        'target_attribute_id' => $seal->id,
        'allowed_option_ids' => [$epdm->id, $viton->id],
        'dependency_type' => 'hidden',
        'is_active' => true,
        'rule_payload' => ['disable_option_ids' => [$viton->id]],
    ]);

    OptionRule::factory()->create([
        'config_profile_id' => $profile->id,
        'config_option_id' => $iron->id,
        'target_attribute_id' => $seal->id,
        'allowed_option_ids' => [$epdm->id],
        'dependency_type' => 'hidden',
        'is_active' => true,
        'rule_payload' => [
            'activate_if' => [
                ['source' => 'context.territory', 'operator' => '=', 'value' => 'Europe'],
            ],
        ],
    ]);

    $page = Livewire::test(ConfigEngineDemo::class)
        ->assertSet('currentCode', 'DI-EP')
        ->set("selection.{$seal->id}", $viton->id)
        ->assertSet('currentCode', 'DI-VT')
        ->set("selection.{$body->id}", $steel->id)
        ->assertSet("selection.{$seal->id}", $epdm->id)
        ->assertSet('currentCode', 'CS-EP')
        ->assertSet("hiddenOptionsByAttribute.{$seal->id}", [$nbr->id])
        ->assertSet("disabledOptionsByAttribute.{$seal->id}", [$viton->id])
        ->assertFormFieldExists("selection.{$seal->id}", 'form', function (Select $field) use ($epdm, $viton): bool {
            return $field->getOptions() === [$epdm->id => 'EPDM', $viton->id => 'Viton']
                && $field->isOptionDisabled((string) $viton->id, 'Viton');
        });

    $page->set("selection.{$body->id}", $iron->id)
        ->set("selection.{$seal->id}", $nbr->id)
        ->assertSet('currentCode', 'DI-NB')
        ->callAction('editContext', ['territory' => 'Europe', 'application' => 'Industry'])
        ->assertHasNoFormErrors()
        ->assertSet('territory', 'Europe')
        ->assertSet('application', 'Industry')
        ->assertSet("selection.{$seal->id}", $epdm->id)
        ->assertSet('currentCode', 'DI-EP')
        ->assertFormFieldExists("selection.{$seal->id}", 'form', fn (Select $field): bool => $field->getOptions() === [$epdm->id => 'EPDM']);

    expect(session('config_engine_demo.context.territory'))->toBe('Europe');
});
