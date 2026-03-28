<?php

namespace App\Models;

use App\Casts\NormalizedIntArrayCast;
use App\OptionRuleDependencyType;
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
        'dependency_type',
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
            'dependency_type' => OptionRuleDependencyType::class,
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

    /**
     * @param  int[]  $optionIds
     * @return array<int, string>
     */
    public function targetOptionLabelsFor(array $optionIds): array
    {
        if ($optionIds === []) {
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
            ->whereIn('id', $optionIds)
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
        return $this->dependency_type?->value;
    }

    /**
     * @return int[]
     */
    public function hiddenOptionIds(): array
    {
        return $this->normalizeOptionIds(data_get($this->rule_payload ?? [], 'hide_option_ids', []));
    }

    /**
     * @return array<int, string>
     */
    public function hiddenOptionLabels(): array
    {
        return $this->targetOptionLabelsFor($this->hiddenOptionIds());
    }

    /**
     * @return int[]
     */
    public function disabledOptionIds(): array
    {
        return $this->normalizeOptionIds(data_get($this->rule_payload ?? [], 'disable_option_ids', []));
    }

    /**
     * @return array<int, string>
     */
    public function disabledOptionLabels(): array
    {
        return $this->targetOptionLabelsFor($this->disabledOptionIds());
    }

    /**
     * @return array<string, string>
     */
    public function labelOverrides(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'label_overrides', []))
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(fn (array $override): bool => is_numeric($override['option_id'] ?? null) && is_string($override['label'] ?? null) && $override['label'] !== '')
            ->mapWithKeys(fn (array $override): array => [(string) ((int) $override['option_id']) => $override['label']])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function labelOverrideSummaries(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'label_overrides', []))
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(fn (array $override): bool => is_numeric($override['option_id'] ?? null) && is_string($override['label'] ?? null) && $override['label'] !== '')
            ->map(function (array $override): string {
                $label = $this->targetOptionLabelsFor([(int) $override['option_id']])[0] ?? ('#'.$override['option_id']);

                return $label.' → '.$override['label'];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function valueOverrides(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'value_overrides', []))
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(fn (array $override): bool => is_numeric($override['option_id'] ?? null) && is_string($override['value'] ?? null) && $override['value'] !== '')
            ->mapWithKeys(fn (array $override): array => [(string) ((int) $override['option_id']) => $override['value']])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function valueOverrideSummaries(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'value_overrides', []))
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(fn (array $override): bool => is_numeric($override['option_id'] ?? null) && is_string($override['value'] ?? null) && $override['value'] !== '')
            ->map(function (array $override): string {
                $label = $this->targetOptionLabelsFor([(int) $override['option_id']])[0] ?? ('#'.$override['option_id']);

                return $label.' → '.$override['value'];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function hintOverrides(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'hints', []))
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(fn (array $override): bool => is_numeric($override['option_id'] ?? null) && is_string($override['hint'] ?? null) && $override['hint'] !== '')
            ->mapWithKeys(fn (array $override): array => [(string) ((int) $override['option_id']) => $override['hint']])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function hintOverrideSummaries(): array
    {
        return collect(data_get($this->rule_payload ?? [], 'hints', []))
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(fn (array $override): bool => is_numeric($override['option_id'] ?? null) && is_string($override['hint'] ?? null) && $override['hint'] !== '')
            ->map(function (array $override): string {
                $label = $this->targetOptionLabelsFor([(int) $override['option_id']])[0] ?? ('#'.$override['option_id']);

                return $label.' → '.$override['hint'];
            })
            ->values()
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
     * @return array<int, string>
     */
    public function activationConditionSummaries(): array
    {
        return collect($this->activationConditions())
            ->map(fn (array $condition): string => sprintf(
                '%s %s %s',
                (string) ($condition['source'] ?? '—'),
                (string) ($condition['operator'] ?? '='),
                is_array($condition['value'] ?? null)
                    ? implode(', ', array_map(fn (mixed $value): string => (string) $value, $condition['value']))
                    : (string) ($condition['value'] ?? '—'),
            ))
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
