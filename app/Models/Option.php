<?php

namespace App\Models;

use Database\Factories\OptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Option extends Model
{
    /** @use HasFactory<OptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['attribute_id', 'value_id', 'code', 'is_active', 'is_hidden'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true, 'is_hidden' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_hidden' => 'boolean'];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(Value::class);
    }

    public function inclusions(): HasMany
    {
        return $this->hasMany(ConfiguratorOption::class);
    }
}
