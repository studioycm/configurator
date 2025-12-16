<?php

use App\Services\ConfiguratorEngine;

test('baseAllowedFromManifest returns only active option ids per attribute', function () {
    $engine = new ConfiguratorEngine;

    $manifest = [
        'stages' => [
            [
                'id' => 100,
                'options' => [
                    ['id' => 1, 'is_active' => true],
                    ['id' => 2, 'is_active' => false],
                ],
            ],
            [
                'id' => 200,
                'options' => [
                    ['id' => 10, 'is_active' => true],
                ],
            ],
        ],
        'rules' => [],
    ];

    expect($engine->baseAllowedFromManifest($manifest))->toBe([
        100 => [1],
        200 => [10],
    ]);
});

test('recalculateAllowedFromManifest applies restrict_allowed_options rules', function () {
    $engine = new ConfiguratorEngine;

    $manifest = [
        'stages' => [
            [
                'id' => 100,
                'options' => [
                    ['id' => 1, 'is_active' => true],
                    ['id' => 2, 'is_active' => true],
                ],
            ],
            [
                'id' => 200,
                'options' => [
                    ['id' => 10, 'is_active' => true],
                    ['id' => 11, 'is_active' => true],
                    ['id' => 12, 'is_active' => false],
                ],
            ],
        ],
        'rules' => [
            [
                'id' => 1,
                'type' => 'restrict_allowed_options',
                'trigger_option_id' => 2,
                'target_attribute_id' => 200,
                'allowed_option_ids' => [11, 12],
            ],
        ],
    ];

    $allowed = $engine->recalculateAllowedFromManifest($manifest, [
        100 => 2,
    ]);

    expect($allowed[200])->toBe([11]);
});
