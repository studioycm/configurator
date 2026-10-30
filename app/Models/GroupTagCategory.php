<?php

namespace App\Models;

use Database\Factories\GroupTagCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupTagCategory extends Model
{
    /** @use HasFactory<GroupTagCategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['label', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(GroupTagValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
