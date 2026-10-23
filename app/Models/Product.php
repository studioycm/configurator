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
    protected $fillable = ['legacy_id', 'legacy_group_id', 'group_id', 'product_code', 'product_name', 'description', 'profile_fields', 'parts', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'group_id' => 'integer', 'profile_fields' => 'array', 'parts' => 'array'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    protected function profileFields(): EloquentAttribute
    {
        return EloquentAttribute::make(set: fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function parts(): EloquentAttribute
    {
        return EloquentAttribute::make(set: fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function name(): EloquentAttribute
    {
        return EloquentAttribute::make(get: fn (): string => $this->product_code);
    }
}
