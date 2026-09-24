<?php

namespace App\Models;

use Database\Factories\RuleEffectOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleEffectOption extends Model
{
    /** @use HasFactory<RuleEffectOptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['effect_id', 'configurator_option_id'];

    public function effect(): BelongsTo
    {
        return $this->belongsTo(RuleEffect::class, 'effect_id');
    }

    public function configuratorOption(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorOption::class);
    }
}
