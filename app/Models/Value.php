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

    /** @param list<string> $reserved */
    public static function suggestSemanticId(string $label, array $reserved = []): string
    {
        $base = rtrim(substr(Str::slug($label, '_'), 0, 100), '_') ?: 'value';
        $semanticId = $base;
        $suffix = 2;

        while (in_array($semanticId, $reserved, true) || static::query()->where('semantic_id', $semanticId)->exists()) {
            $ending = '_'.$suffix++;
            $semanticId = rtrim(substr($base, 0, 100 - strlen($ending)), '_').$ending;
        }

        return $semanticId;
    }

    public function labelWithSemanticId(?string $label = null): string
    {
        return ($label ?? $this->label).' - '.$this->semantic_id;
    }

    /** @return list<string> */
    public static function tagVocabulary(): array
    {
        return static::query()->whereNotNull('tags')->pluck('tags')
            ->flatMap(fn (mixed $tags): array => is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [])
            ->uniqueStrict()->sort()->values()->all();
    }

    /** @return array<int|string, string> */
    public static function tagOptions(): array
    {
        $tags = static::tagVocabulary();

        return array_combine($tags, $tags);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }
}
