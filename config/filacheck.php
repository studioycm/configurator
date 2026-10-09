<?php

return [
    /**
     * This rule rewrites every matching string, including schema state paths.
     * Filament 5 still binds table filters to the tableFilters property;
     * only the URL alias is filters. Rewriting state paths disconnects filters.
     */
    'deprecated-url-parameters' => [
        'enabled' => false,
    ],
];
