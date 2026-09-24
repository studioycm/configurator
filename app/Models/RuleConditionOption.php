<?php

namespace App\Models;

use Database\Factories\RuleConditionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleConditionOption extends Model
{
    /** @use HasFactory<RuleConditionOptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['condition_id', 'configurator_option_id'];

    public function condition(): BelongsTo
    {
        return $this->belongsTo(RuleCondition::class, 'condition_id');
    }

    public function configuratorOption(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorOption::class);
    }
}
