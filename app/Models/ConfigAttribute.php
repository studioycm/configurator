<?php

namespace App\Models;

use App\Models\ConfigOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigAttribute extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'config_profile_id',
        'name',
        'label',
        'slug',
        'input_type',
        'sort_order',
        'is_required',
        'segment_index',
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
            'is_required' => 'boolean',
        ];
    }

    public function configProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ConfigOption::class);
    }
}
