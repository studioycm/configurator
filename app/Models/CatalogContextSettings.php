<?php

namespace App\Models;

use Database\Factories\CatalogContextSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogContextSettings extends Model
{
    /** @use HasFactory<CatalogContextSettingsFactory> */
    use HasFactory;

    protected $table = 'catalog_context_settings';

    /** @var list<string> */
    protected $fillable = ['choices'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['choices' => 'array'];
    }

    public static function current(bool $lock = false): self
    {
        return static::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail(1);
    }
}
