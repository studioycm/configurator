<?php

namespace App\Models;

use App\Casts\JsonRuleCast;
use App\ConfigProfileScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ConfigProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_profile_id',
        'name',
        'slug',
        'description',
        'scope',
        'is_active',
        'extra_rules_json',
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
            'product_profile_id' => 'integer',
            'scope' => ConfigProfileScope::class,
            'is_active' => 'boolean',
            'extra_rules_json' => JsonRuleCast::class,
        ];
    }

    public function productProfile(): BelongsTo
    {
        return $this->belongsTo(ProductProfile::class, 'product_profile_id', 'id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ConfigAttribute::class, 'config_profile_id', 'id');
    }

    public function options(): HasManyThrough
    {
        return $this->hasManyThrough(ConfigOption::class, ConfigAttribute::class, 'config_profile_id', 'config_attribute_id', 'id', 'id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(OptionRule::class, 'config_profile_id', 'id');
    }
}
