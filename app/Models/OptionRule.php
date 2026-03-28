<?php

namespace App\Models;

use App\Casts\NormalizedIntArrayCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
        'rule_payload',
        'is_active',
        'priority',
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
            'rule_payload' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function configProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class, 'config_profile_id', 'id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ConfigOption::class, 'config_option_id', 'id');
    }

    public function optionAttribute(): HasOneThrough
    {
        return $this->hasOneThrough(ConfigAttribute::class, ConfigOption::class, 'id', 'id', 'config_option_id', 'config_attribute_id');
    }

    public function targetAttribute(): BelongsTo
    {
        return $this->belongsTo(ConfigAttribute::class, 'target_attribute_id', 'id');
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

    public function effectType(): string
    {
        return (string) data_get($this->rule_payload ?? [], 'effect', 'restrict_allowed_options');
    }

    public function uiMode(): ?string
    {
        $uiMode = data_get($this->rule_payload ?? [], 'ui_mode');

        return is_string($uiMode) && $uiMode !== '' ? $uiMode : null;
    }

    /**
     * @return int[]
     */
    public function hiddenOptionIds(): array
    {
        return $this->normalizeOptionIds(data_get($this->rule_payload ?? [], 'hide_option_ids', []));
    }

    /**
     * @return int[]
     */
    public function disabledOptionIds(): array
    {
        return $this->normalizeOptionIds(data_get($this->rule_payload ?? [], 'disable_option_ids', []));
    }

    /**
     * @return array<string, string>
     */
    public function labelOverrides(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'label_overrides', []))
            ->filter(fn (mixed $label, mixed $optionId): bool => is_scalar($optionId) && is_string($label) && $label !== '')
            ->mapWithKeys(fn (string $label, mixed $optionId): array => [(string) $optionId => $label])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function hintOverrides(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'hints', []))
            ->filter(fn (mixed $hint, mixed $optionId): bool => is_scalar($optionId) && is_string($hint) && $hint !== '')
            ->mapWithKeys(fn (string $hint, mixed $optionId): array => [(string) $optionId => $hint])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activationConditions(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'activate_if', []))
            ->filter(fn (mixed $condition): bool => is_array($condition))
            ->values()
            ->all();
    }

    /**
     * @return int[]
     */
    protected function normalizeOptionIds(mixed $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
