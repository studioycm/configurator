<?php

namespace App\DTO;

use InvalidArgumentException;

final readonly class CatalogAssetChange
{
    public function __construct(public string $operation, public ?int $assetId = null)
    {
        if (! in_array($operation, ['keep', 'set', 'remove'], true)) {
            throw new InvalidArgumentException('Unsupported main-image operation.');
        }
        if (($operation === 'set' && ($assetId === null || $assetId < 1))
            || ($operation !== 'set' && $assetId !== null)) {
            throw new InvalidArgumentException('Only set accepts a positive prepared asset identity.');
        }
    }
}
