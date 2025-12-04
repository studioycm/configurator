<?php

namespace App\Models;

use App\Models\ConfigAttribute;
use App\Models\OptionRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'is_active' => 'boolean',
            'extra_rules_json' => 'array',
        ];
    }

    public function productProfile(): BelongsTo
    {
        return $this->belongsTo(ProductProfile::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ConfigAttribute::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(OptionRule::class);
    }
}
