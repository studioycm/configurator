<?php

namespace App\Models;

use Database\Factories\MappingSetTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappingSetTarget extends Model
{
    /** @use HasFactory<MappingSetTargetFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['mapping_set_id', 'configurator_option_id'];

    public function mappingSet(): BelongsTo
    {
        return $this->belongsTo(MappingSet::class);
    }

    public function configuratorOption(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorOption::class);
    }
}
