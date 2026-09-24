<?php

namespace App\Models;

use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['key', 'label'];

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    public function inclusions(): HasMany
    {
        return $this->hasMany(ConfiguratorAttribute::class);
    }
}
