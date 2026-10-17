<?php

namespace App\Models;

use Database\Factories\ValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Value extends Model
{
    /** @use HasFactory<ValueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['label', 'semantic_id', 'description', 'tags'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public static function suggestSemanticId(string $label): string
    {
        $base = rtrim(substr(Str::slug($label, '_'), 0, 100), '_') ?: 'value';
        $semanticId = $base;
        $suffix = 2;

        while (static::query()->where('semantic_id', $semanticId)->exists()) {
            $ending = '_'.$suffix++;
            $semanticId = rtrim(substr($base, 0, 100 - strlen($ending)), '_').$ending;
        }

        return $semanticId;
    }

    public function labelWithSemanticId(?string $label = null): string
    {
        return ($label ?? $this->label).' - '.$this->semantic_id;
    }

    /** @return array<string, string> */
    public static function tagOptions(): array
    {
        return static::query()->whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->mapWithKeys(fn (string $tag): array => [$tag => $tag])->all();
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }
}
