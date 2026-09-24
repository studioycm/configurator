<?php

namespace App\Models;

use Database\Factories\ValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Value extends Model
{
    /** @use HasFactory<ValueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['label', 'description', 'tags'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tags' => 'array'];
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
