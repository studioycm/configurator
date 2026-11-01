<?php

return [
    'enabled' => env('CATALOG_ASSETS_ENABLED', false),
    'image' => [
        'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_bytes' => 3145728,
        'max_pixels' => 25000000,
        'conversion_sizes' => [160, 480, 960],
    ],
    'limits' => [
        'source_files' => 2000,
        'source_bytes' => 524288000,
        'selected_records' => 5000,
        'assignments_per_apply' => 50,
        'default_page_size' => 10,
        'maximum_page_size' => 500,
        'page_sizes' => [5, 10, 15, 25, 50, 100, 200],
        'review_hours' => 24,
    ],
    'requests' => [
        'scan_new_nodes' => 100,
        'scan_hashes' => 10,
        'scan_bytes' => 16777216,
        'scan_soft_budget_ms' => 500,
        'replay_nodes' => 4000,
        'source_nodes' => 4000,
        'source_depth' => 16,
        'review_records' => 100,
        'claim_seconds' => 60,
    ],
    'sources' => [],
];
