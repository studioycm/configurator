<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'allowed_option_ids' => 'array',
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
}
