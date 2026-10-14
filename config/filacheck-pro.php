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

    /**
     * Reviewed batch actions clear selection explicitly after a successful Apply.
     * Native automatic deselection also runs on Preview and failed validation,
     * discarding the selection needed for review/repair. BatchCatalogActionsTest
     * covers retaining it on those paths and clearing it after Apply.
     */
    'bulk-action-missing-deselect' => [
        'enabled' => false,
    ],

    /**
     * The compact replacement form has seven visible controls for one task.
     * The scanner also counts its Hidden operation and preview View as fields.
     */
    'flat-form-overload' => [
        'max_fields' => 9,
    ],
];
