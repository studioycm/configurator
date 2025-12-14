<?php

use App\DTO\ConfigOptionDTO;
use App\DTO\ConfigStageDTO;
use App\Models\ConfigProfile;
use App\Models\OptionRule;
use App\Services\ConfiguratorEngine;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(Tests\TestCase::class, DatabaseMigrations::class);

function makeStages(): array
{
    $stage1 = new ConfigStageDTO(
        id: 1,
        slug: 'body',
        label: 'Body',
        sortOrder: 1,
        segmentIndex: 1,
        isRequired: true,
        options: [
            new ConfigOptionDTO(1, 'Ductile Iron', 'DI', 1, true, true),
            new ConfigOptionDTO(2, 'Carbon Steel', 'CS', 2, false, true),
        ],
    );

    $stage2 = new ConfigStageDTO(
        id: 2,
        slug: 'seal',
        label: 'Seal',
        sortOrder: 2,
        segmentIndex: 2,
        isRequired: true,
        options: [
            new ConfigOptionDTO(3, 'EPDM', 'EP', 1, true, true),
            new ConfigOptionDTO(4, 'Viton', 'VT', 2, false, true),
        ],
    );

    return [$stage1, $stage2];
}

test('default selection picks defaults then first active', function () {
    $engine = new ConfiguratorEngine();
    $stages = makeStages();

    $selection = $engine->defaultSelection($stages);

    expect($selection)->toEqual([1 => 1, 2 => 3]);
});

test('base allowed returns all active option ids', function () {
    $engine = new ConfiguratorEngine();
    $stages = makeStages();

    $allowed = $engine->baseAllowed($stages);

    expect($allowed[1])->toEqual([1, 2])
        ->and($allowed[2])->toEqual([3, 4]);
});

test('recalculateAllowed intersects rules only', function () {
    $profile = ConfigProfile::factory()->create();

    $bodyAttr = $profile->attributes()->create([
        'name' => 'Body',
        'label' => 'Body',
        'slug' => 'body',
        'input_type' => 'select',
        'sort_order' => 1,
        'is_required' => true,
        'segment_index' => 1,
    ]);

    $sealAttr = $profile->attributes()->create([
        'name' => 'Seal',
        'label' => 'Seal',
        'slug' => 'seal',
        'input_type' => 'select',
        'sort_order' => 2,
        'is_required' => true,
        'segment_index' => 2,
    ]);

    $bodyOpt1 = $bodyAttr->options()->create([
        'label' => 'Ductile Iron',
        'code' => 'DI',
        'sort_order' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    $bodyOpt2 = $bodyAttr->options()->create([
        'label' => 'Carbon Steel',
        'code' => 'CS',
        'sort_order' => 2,
        'is_default' => false,
        'is_active' => true,
    ]);

    $sealOpt1 = $sealAttr->options()->create([
        'label' => 'EPDM',
        'code' => 'EP',
        'sort_order' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);

    $sealOpt2 = $sealAttr->options()->create([
        'label' => 'Viton',
        'code' => 'VT',
        'sort_order' => 2,
        'is_default' => false,
        'is_active' => true,
    ]);

    // Rule: if body=DI then seal allowed [EPDM]
    OptionRule::factory()->create([
        'config_profile_id' => $profile->id,
        'config_option_id' => $bodyOpt1->id,
        'target_attribute_id' => $sealAttr->id,
        'allowed_option_ids' => [$sealOpt1->id],
    ]);

    $engine = new ConfiguratorEngine();

    $stages = [
        new ConfigStageDTO(
            id: $bodyAttr->id,
            slug: $bodyAttr->slug,
            label: $bodyAttr->label,
            sortOrder: $bodyAttr->sort_order,
            segmentIndex: $bodyAttr->segment_index,
            isRequired: (bool) $bodyAttr->is_required,
            options: [
                new ConfigOptionDTO($bodyOpt1->id, $bodyOpt1->label, $bodyOpt1->code, $bodyOpt1->sort_order, (bool) $bodyOpt1->is_default, (bool) $bodyOpt1->is_active),
                new ConfigOptionDTO($bodyOpt2->id, $bodyOpt2->label, $bodyOpt2->code, $bodyOpt2->sort_order, (bool) $bodyOpt2->is_default, (bool) $bodyOpt2->is_active),
            ],
        ),
        new ConfigStageDTO(
            id: $sealAttr->id,
            slug: $sealAttr->slug,
            label: $sealAttr->label,
            sortOrder: $sealAttr->sort_order,
            segmentIndex: $sealAttr->segment_index,
            isRequired: (bool) $sealAttr->is_required,
            options: [
                new ConfigOptionDTO($sealOpt1->id, $sealOpt1->label, $sealOpt1->code, $sealOpt1->sort_order, (bool) $sealOpt1->is_default, (bool) $sealOpt1->is_active),
                new ConfigOptionDTO($sealOpt2->id, $sealOpt2->label, $sealOpt2->code, $sealOpt2->sort_order, (bool) $sealOpt2->is_default, (bool) $sealOpt2->is_active),
            ],
        ),
    ];

    $selection = [$bodyAttr->id => $bodyOpt1->id];

    $allowed = $engine->recalculateAllowed($profile, $stages, $selection);

    expect($allowed[$sealAttr->id])->toEqual([$sealOpt1->id])
        ->and($allowed[$bodyAttr->id])->toEqual([$bodyOpt1->id, $bodyOpt2->id]);
});

test('pruneInvalidSelections removes only invalid choices', function () {
    $engine = new ConfiguratorEngine();
    $stages = makeStages();
    $allowed = [1 => [1], 2 => [3]];
    $selection = [1 => 2, 2 => 3, 99 => 5];

    $pruned = $engine->pruneInvalidSelections($stages, $selection, $allowed);

    expect($pruned)->toEqual([2 => 3]);
});

test('fillMissingSelections auto picks default or first allowed when current is invalid', function () {
    $engine = new ConfiguratorEngine();
    $stages = makeStages();

    $allowed = [
        1 => [1],      // body must be option 1
        2 => [4],      // seal only option 4 allowed now
    ];

    $selection = [
        1 => 2, // invalid under allowed
        2 => 3, // invalid under allowed, default (3) not allowed, so should pick first allowed (4)
    ];

    $filled = $engine->fillMissingSelections($stages, $allowed, $selection);

    expect($filled)->toEqual([
        1 => 1,
        2 => 4,
    ]);
});

test('isComplete validates required stages filled', function () {
    $engine = new ConfiguratorEngine();
    $stages = makeStages();

    expect($engine->isComplete($stages, []))->toBeFalse();
    expect($engine->isComplete($stages, [1 => 1]))->toBeFalse();
    expect($engine->isComplete($stages, [1 => 1, 2 => 3]))->toBeTrue();
});

test('buildConfigurationCode concatenates codes by segment index', function () {
    $engine = new ConfiguratorEngine();
    $stages = makeStages();
    $selection = [1 => 2, 2 => 4];

    $code = $engine->buildConfigurationCode($stages, $selection);

    expect($code)->toBe('CS-VT');
});
