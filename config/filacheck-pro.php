<?php

return [
    /**
     * Every resource badge uses AdminNavigationCounts::total(), which caches
     * authorized totals and invalidates them through model observers.
     * This AST rule only recognizes cache calls inside the resource method,
     * so it incorrectly flags the shared service. AdminBadgesTest covers it.
     */
    'navigation-badge-not-cached' => [
        'enabled' => false,
    ],
];
