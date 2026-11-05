<?php

namespace App\Models;

use Database\Factories\TourDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourDefinition extends Model
{
    /** @use HasFactory<TourDefinitionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['key', 'title', 'locale', 'enabled', 'steps'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['steps' => 'array', 'enabled' => 'boolean', 'revision' => 'integer'];
    }
}
