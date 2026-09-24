<?php

namespace App\Models;

use Database\Factories\ConfiguratorAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguratorAttribute extends Model
{
    /** @use HasFactory<ConfiguratorAttributeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['configurator_id', 'attribute_id', 'display_order', 'code_order', 'label_override', 'input_type', 'help_text', 'default_configurator_option_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['display_order' => 'integer', 'code_order' => 'integer'];
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ConfiguratorOption::class);
    }

    public function defaultOption(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorOption::class, 'default_configurator_option_id');
    }
}
