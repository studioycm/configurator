<?php

namespace App\Models;

use App\ConfigInputType;
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
        'ui_schema',
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
            'input_type' => ConfigInputType::class,
            'is_required' => 'boolean',
            'ui_schema' => 'array',
        ];
    }

    public function configProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class, 'config_profile_id', 'id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ConfigOption::class, 'config_attribute_id', 'id');
    }

    public function presentationMode(): string
    {
        return $this->input_type?->value ?? 'toggle';
    }

    public function helpText(): ?string
    {
        $helpText = data_get($this->ui_schema ?? [], 'help_text');

        return is_string($helpText) && $helpText !== '' ? $helpText : null;
    }

    public function groupKey(): ?string
    {
        $groupKey = data_get($this->ui_schema ?? [], 'group');

        return is_string($groupKey) && $groupKey !== '' ? $groupKey : null;
    }

    public function autoSelectFirstAllowed(): bool
    {
        return (bool) data_get($this->ui_schema ?? [], 'auto_select_first_allowed', true);
    }
}
