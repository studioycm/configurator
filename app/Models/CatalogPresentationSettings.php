<?php

namespace App\Models;

use Database\Factories\CatalogPresentationSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogPresentationSettings extends Model
{
    /** @use HasFactory<CatalogPresentationSettingsFactory> */
    use HasFactory;

    protected $table = 'catalog_presentation_settings';

    protected $fillable = ['settings', 'version'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'version' => 'string'];
    }
}
