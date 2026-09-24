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
    protected $fillable = ['attribute_id', 'value_id', 'code'];

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
