<?php

namespace App\DTO;

final readonly class CatalogDiscoveryState
{
    /** @param array<string, string> $filters @param list<string> $precedence */
    public function __construct(
        public array $filters = [],
        public array $precedence = [],
        public ?int $subGroupId = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {}

    /** @return array{version: int, filters: array<string, string>, precedence: list<string>, subGroupId: ?int, page: int, perPage: int} */
    public function toArray(): array
    {
        return ['version' => 1, 'filters' => $this->filters, 'precedence' => $this->precedence, 'subGroupId' => $this->subGroupId, 'page' => $this->page, 'perPage' => $this->perPage];
    }
}
