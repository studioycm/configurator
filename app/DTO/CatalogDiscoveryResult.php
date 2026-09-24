<?php

namespace App\DTO;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class CatalogDiscoveryResult
{
    /**
     * @param  list<array{key: string, label: string, values: list<array{value: string, label: string, count: int, selected: bool}>}>  $fields
     * @param  list<array{id: int, label: string}>  $subGroups
     * @param  list<string>  $notices
     * @param  array{default_page_size: int, allow_page_size_change: bool, page_size_options: list<int>}  $settings
     */
    public function __construct(
        public CatalogDiscoveryState $state,
        public array $fields,
        public array $subGroups,
        public LengthAwarePaginator $products,
        public array $notices,
        public array $settings,
        public bool $repaired = false,
    ) {}
}
