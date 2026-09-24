<?php

namespace App\Models;

use Database\Factories\ConfiguratorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Configurator extends Model
{
    /** @use HasFactory<ConfiguratorFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'description', 'context_schema', 'hidden_context_options', 'policy_overrides'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['context_schema' => 'array', 'hidden_context_options' => 'array', 'policy_overrides' => 'array'];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ConfiguratorAttribute::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ConfiguratorRule::class);
    }
}
