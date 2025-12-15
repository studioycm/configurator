<?php

namespace App\Models;

use App\Casts\NormalizedIntArrayCast;
use App\Models\ConfigOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class OptionRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'config_profile_id',
        'config_option_id',
        'target_attribute_id',
        'allowed_option_ids',
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
            'config_profile_id' => 'integer',
            'config_option_id' => 'integer',
            'target_attribute_id' => 'integer',
            'allowed_option_ids' => NormalizedIntArrayCast::class,
        ];
    }

    public function configProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ConfigOption::class);
    }

    public function targetAttribute(): BelongsTo
    {
        return $this->belongsTo(ConfigAttribute::class);
    }

    public function targetAttributeOptions(): HasMany
    {
        return $this->hasMany(ConfigOption::class, 'config_attribute_id', 'target_attribute_id');
    }



    public function allowedOptionLabels(): array
    {
        $allowedIds = $this->allowed_option_ids ?? [];

        if ($allowedIds === []) {
            return [];
        }

        $options = null;

        if ($this->relationLoaded('targetAttribute') && $this->targetAttribute?->relationLoaded('options')) {
            $options = $this->targetAttribute->options;
        }

        if ($options === null) {
            $options = $this->targetAttribute()
                ->with('options')
                ->first()?->options;
        }

        if ($options === null) {
            return [];
        }

        return $options
            ->whereIn('id', $allowedIds)
            ->pluck('label')
            ->values()
            ->all();
    }
}
