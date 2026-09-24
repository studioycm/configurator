<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['legacy_id', 'legacy_group_id', 'group_id', 'product_code', 'product_name', 'description', 'properties', 'parts', 'extra_data'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['group_id' => 'integer', 'properties' => 'array', 'parts' => 'array', 'extra_data' => 'array'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    protected function name(): EloquentAttribute
    {
        return EloquentAttribute::make(get: fn (): string => $this->product_code);
    }
}
