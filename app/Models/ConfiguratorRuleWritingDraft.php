<?php

namespace App\Models;

use App\RuleKind;
use Database\Factories\ConfiguratorRuleWritingDraftFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguratorRuleWritingDraft extends Model
{
    /** @use HasFactory<ConfiguratorRuleWritingDraftFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['source', 'label', 'description'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => RuleKind::class, 'revision' => 'integer', 'format_version' => 'integer', 'base_rule' => 'array', 'bindings' => 'array', 'last_saved_authoring' => 'array'];
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
