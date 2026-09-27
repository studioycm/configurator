<?php

namespace App\DTO;

final readonly class CatalogSnapshot
{
    public const int SCHEMA = 1;

    /** @param array<string, mixed> $data Plain cache/wire data; identifiers and revision are strings. */
    public function __construct(public array $data) {}

    public function revision(): string
    {
        return $this->data['revision'];
    }
}
