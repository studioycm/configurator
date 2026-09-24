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
    protected $fillable = ['label', 'description'];

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }
}
