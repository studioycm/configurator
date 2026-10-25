<?php

namespace App;

use RuntimeException;
use Throwable;

/** The transaction owner reports rollback evidence; this type never retries an operation. */
class CatalogWriteFailure extends RuntimeException
{
    public function __construct(public readonly bool $rolledBack, Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
