<?php

namespace App\Models;

use Database\Factories\ConfiguratorOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguratorOption extends Model
{
    /** @use HasFactory<ConfiguratorOptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['configurator_attribute_id', 'option_id', 'display_order', 'label_override', 'display_value_override', 'hint', 'hidden_by_default', 'disabled_by_default'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['display_order' => 'integer', 'hidden_by_default' => 'boolean', 'disabled_by_default' => 'boolean'];
    }

    public function configuratorAttribute(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorAttribute::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
