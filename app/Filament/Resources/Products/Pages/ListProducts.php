<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SplitListRecords;

class ListProducts extends SplitListRecords
{
    protected static string $resource = ProductResource::class;
}
