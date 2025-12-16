<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ConfigOption extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'config_attribute_id',
        'label',
        'code',
        'sort_order',
        'is_default',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'config_attribute_id' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // relation has a configProfile through ConfigAttribute's config_profile_id
    public function configProfile(): HasOneThrough
    {
        return $this->hasOneThrough(ConfigProfile::class, ConfigAttribute::class, 'id', 'id', 'config_attribute_id', 'config_profile_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ConfigAttribute::class, 'config_attribute_id', 'id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(OptionRule::class, 'config_option_id', 'id');
    }
}
