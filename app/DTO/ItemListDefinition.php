<?php

namespace App\DTO;

use Illuminate\Database\Eloquent\Builder;

final readonly class ItemListDefinition
{
    /** @param array<string, string> $columns */
    public function __construct(public string $title, public Builder|array $query, public array $columns, public ?string $fullListUrl = null) {}
}
